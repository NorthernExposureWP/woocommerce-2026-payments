<?php

namespace NE\PaymentLab;

defined( 'ABSPATH' ) || exit;

/**
 * Receives payment status callbacks from the payment provider:
 *
 *     POST /wp-json/ne-payment-lab/v1/callback
 *
 * Every request must be signed by the provider (see authenticate()).
 */
class CallbackController
{
    private const REST_NAMESPACE   = 'ne-payment-lab/v1';
    private const ROUTE            = '/callback';
    private const TIMESTAMP_HEADER = 'X-Payment-Timestamp';
    private const SIGNATURE_HEADER = 'X-Payment-Signature';
    private const MAX_CLOCK_SKEW   = 300; // seconds

    private const META_DECLINED_PAYMENT_ID = '_ne_payment_lab_declined_payment_id';

    private Gateway $gateway;

    public function __construct(Gateway $gateway)
    {
        $this->gateway = $gateway;
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::REST_NAMESPACE,
            self::ROUTE,
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle' ],
                'permission_callback' => [ $this, 'authenticate' ],
            ]
        );
    }

    /**
     * Checks that the request was sent by the payment provider.
     *
     * The provider signs "{timestamp}.{raw body}" with HMAC-SHA256 using a secret shared with
     * this site. The timestamp limits how long a captured request can be replayed.
     *
     * @return true|\WP_Error
     */
    public function authenticate( \WP_REST_Request $request )
    {
        $secret = trim( (string) $this->gateway->get_option( 'callback_secret', '' ) );

        if ( '' === $secret ) {
            $this->log( 'error', 'Callback rejected: the callback secret is not configured.' );

            return new \WP_Error(
                'ne_payment_lab_not_configured',
                'Callback endpoint is not configured.',
                [ 'status' => 500 ]
            );
        }

        $timestamp = (string) $request->get_header( self::TIMESTAMP_HEADER );
        $signature = (string) $request->get_header( self::SIGNATURE_HEADER );

        if ( 1 !== preg_match( '/^\d{1,12}$/', $timestamp ) || '' === $signature ) {
            return $this->reject( 401, 'invalid_signature', 'Invalid signature.', 'Missing or malformed signature headers.' );
        }

        if ( abs( time() - (int) $timestamp ) > self::MAX_CLOCK_SKEW ) {
            return $this->reject( 401, 'invalid_signature', 'Invalid signature.', 'Timestamp is outside the allowed time window.' );
        }

        $expected = hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            return $this->reject( 401, 'invalid_signature', 'Invalid signature.', 'Signature does not match.' );
        }

        return true;
    }

    /**
     * Validates the callback, checks it against the order and applies the result.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle( \WP_REST_Request $request )
    {
        try {
            return $this->process( $request );
        } catch ( \Throwable $e ) {
            $this->log( 'error', sprintf( 'Callback failed: %s: %s', get_class( $e ), $e->getMessage() ) );

            return new \WP_Error(
                'ne_payment_lab_internal_error',
                'Internal error.',
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    private function process( \WP_REST_Request $request )
    {
        $data = json_decode( $request->get_body() );

        if ( ! $data instanceof \stdClass ) {
            return $this->reject( 400, 'invalid_json', 'Request body must be a valid JSON object.' );
        }

        $data = get_object_vars( $data );

        $paymentId = $data['payment_id'] ?? null;
        $orderId   = $data['order_id'] ?? null;
        $amount    = $data['amount'] ?? null;
        $currency  = $data['currency'] ?? null;
        $status    = $data['status'] ?? null;

        $invalid = [];

        if ( ! is_string( $paymentId ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $paymentId ) ) {
            $invalid[] = 'payment_id';
        }
        if ( ! is_int( $orderId ) || $orderId <= 0 ) {
            $invalid[] = 'order_id';
        }
        if ( ! is_string( $amount ) || 1 !== preg_match( '/^\d+(?:\.\d+)?$/', $amount ) ) {
            $invalid[] = 'amount';
        }
        if ( ! is_string( $currency ) || 1 !== preg_match( '/^[A-Za-z]{3}$/', $currency ) ) {
            $invalid[] = 'currency';
        }
        if ( ! in_array( $status, [ 'succeeded', 'declined' ], true ) ) {
            $invalid[] = 'status';
        }

        if ( [] !== $invalid ) {
            return $this->reject(
                422,
                'invalid_fields',
                'Missing or invalid fields: ' . implode( ', ', $invalid ) . '.'
            );
        }

        $context = sprintf( 'order #%d, payment %s', $orderId, $paymentId );

        // 1. The order exists.
        $order = wc_get_order( $orderId );

        if ( ! $order instanceof \WC_Order ) {
            return $this->reject( 404, 'order_not_found', 'Order not found.', "Order not found ({$context})." );
        }

        // 2. The order was placed with this payment method.
        if ( $order->get_payment_method() !== $this->gateway->id ) {
            return $this->reject(
                422,
                'payment_method_mismatch',
                'The order was not placed with this payment method.',
                "Payment method mismatch ({$context})."
            );
        }

        // 3. The payment ID is the one created earlier for this order.
        if ( (string) $order->get_meta( Gateway::META_PAYMENT_ID ) !== $paymentId ) {
            return $this->reject(
                422,
                'payment_id_mismatch',
                'The payment ID does not match the order.',
                "Payment ID mismatch ({$context})."
            );
        }

        // 4. The amount matches (compared as decimal strings, without float rounding).
        if (
            wc_format_decimal( $amount, false, true )
            !== wc_format_decimal( $order->get_total(), wc_get_price_decimals(), true )
        ) {
            return $this->reject(
                422,
                'amount_mismatch',
                'The amount does not match the order.',
                "Amount mismatch ({$context}): got {$amount}, order total is {$order->get_total()}."
            );
        }

        // 5. The currency matches.
        if ( strtoupper( $currency ) !== strtoupper( $order->get_currency() ) ) {
            return $this->reject(
                422,
                'currency_mismatch',
                'The currency does not match the order.',
                "Currency mismatch ({$context}): got {$currency}, order currency is {$order->get_currency()}."
            );
        }

        // A repeated delivery of a callback that has already been applied is acknowledged
        // with HTTP 200 and is not processed again.
        if ( $this->is_duplicate( $order, $status, $paymentId ) ) {
            $this->log( 'info', "Duplicate {$status} callback acknowledged ({$context})." );

            return new \WP_REST_Response( [ 'result' => 'ok', 'duplicate' => true ], 200 );
        }

        // 6. The order has not been paid yet.
        if ( $order->is_paid() ) {
            return $this->reject(
                409,
                'order_already_paid',
                'The order has already been paid.',
                "Order already paid ({$context})."
            );
        }

        if ( 'succeeded' === $status ) {
            // Marks the order as paid and stores the payment ID as the transaction ID.
            if ( ! $order->payment_complete( $paymentId ) ) {
                return $this->reject(
                    409,
                    'order_not_payable',
                    'The current order status does not allow completing the payment.',
                    "payment_complete() refused ({$context}), order status: {$order->get_status()}."
                );
            }

            $order->add_order_note( sprintf( 'Payment Lab: payment succeeded (payment ID: %s).', $paymentId ) );
        } else {
            $order->add_order_note(
                sprintf( 'Payment Lab: payment declined by the provider (payment ID: %s). The order has not been paid.', $paymentId )
            );

            $order->update_meta_data( self::META_DECLINED_PAYMENT_ID, $paymentId );
            $order->save();
        }

        return new \WP_REST_Response( [ 'result' => 'ok' ], 200 );
    }

    /**
     * Whether this exact callback has already been applied to the order.
     * Call it only after the order and the payment ID have been matched.
     */
    private function is_duplicate( \WC_Order $order, string $status, string $paymentId ): bool
    {
        if ( 'succeeded' === $status ) {
            // Already paid, and completed with this very payment ID as the transaction ID.
            return $order->is_paid() && $order->get_transaction_id() === $paymentId;
        }

        // Declined: this payment's decline has already been recorded and the order is still unpaid.
        return ! $order->is_paid()
            && (string) $order->get_meta( self::META_DECLINED_PAYMENT_ID ) === $paymentId;
    }

    /**
     * Logs why a callback was refused and returns the error for the provider.
     * $logMessage defaults to $message and may contain more detail than the provider should see.
     */
    private function reject( int $status, string $code, string $message, string $logMessage = '' ): \WP_Error
    {
        $this->log(
            'warning',
            sprintf( 'Callback rejected (HTTP %d, %s): %s', $status, $code, '' !== $logMessage ? $logMessage : $message )
        );

        return new \WP_Error( 'ne_payment_lab_' . $code, $message, [ 'status' => $status ] );
    }

    private function log( string $level, string $message ): void
    {
        wc_get_logger()->log( $level, $message, [ 'source' => $this->gateway->id ] );
    }
}