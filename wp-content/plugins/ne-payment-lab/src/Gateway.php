<?php

namespace NE\PaymentLab;

defined( 'ABSPATH') || exit;

class Gateway extends \WC_Payment_Gateway
{
    private const META_PAYMENT_ID = '_ne_payment_lab_payment_id';


    public function __construct()
    {
        $this->id                 = 'ne_payment_lab';
        $this->method_title       = 'NE Payment Lab';
        $this->method_description = 'Educational gateway for testing WooCommerce payment flows.';
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled', 'no');

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [ $this, 'process_admin_options' ]
        );
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order( $order_id );

        if (!$order instanceof \WC_Order) {
            wc_add_notice(
                'Unable to process payment: order not found.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        if ($order->get_payment_method() !== $this->id) {
            wc_add_notice(
                'Unable to process payment: incorrect payment method.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        if ($order->is_paid()) {
            wc_add_notice(
                'This order has already been paid.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        if ($order->get_transaction_id()) {
            wc_add_notice(
                'This order already has a transaction ID.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        $payment = $this->create_provider_payment($order);

        if (is_wp_error($payment)) {
            $this->log(
                sprintf(
                    'Order #%d: %s (%s)',
                    $order->get_id(),
                    $payment->get_error_message(),
                    $payment->get_error_code()
                )
            );

            wc_add_notice(
                'Unable to start the payment. Please try again later.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        $order->update_meta_data(self::META_PAYMENT_ID, $payment['payment_id']);
        $order->save();

        return [
            'result'   => 'success',
            'redirect' => $payment['payment_url'],
        ];
    }

    /**
     * Creates a payment at the payment provider (POST {provider_url}/api/payments).
     *
     * @return array{payment_id: string, payment_url: string}|\WP_Error
     */
    private function create_provider_payment(\WC_Order $order)
    {
        $providerUrl = trim($this->get_option('provider_url', '' ));
        $callbackUrl = trim($this->get_option( 'callback_url', ''));

        if (!$this->is_http_url($callbackUrl)) {
            return new \WP_Error(
                'callback_url_invalid',
                'Callback URL is not configured or is not a valid http(s) URL.'
            );
        }

        if (!$this->is_http_url($providerUrl)) {
            return new \WP_Error(
                'provider_url_invalid',
                'Payment provider URL is not configured or is not a valid http(s) URL.'
            );
        }

        $body = wp_json_encode( [
            'order_id'     => $order->get_id(),
            'amount'       => wc_format_decimal($order->get_total(), wc_get_price_decimals()),
            'currency'     => $order->get_currency(),
            'return_url'   => $this->get_return_url($order),
            // The route is not registered yet: it will receive the provider's messages in a later step.
            'callback_url' => $callbackUrl,
        ] );

        if ( false === $body ) {
            return new \WP_Error('request_encode_failed', 'Unable to encode the request as JSON.');
        }

        $response = wp_remote_post(
            untrailingslashit($providerUrl) . '/api/payments',
            [
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'    => $body,
            ]
        );

        if (is_wp_error( $response)) {
            return new \WP_Error('http_request_failed', $response->get_error_message());
        }

        $status  = (int) wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);

        if (201 !== $status) {
            return new \WP_Error(
                'unexpected_status',
                sprintf('Provider returned HTTP %d: %s', $status, mb_substr($rawBody, 0, 500))
            );
        }

        $data = json_decode($rawBody);

        if (!$data instanceof \stdClass) {
            return new \WP_Error(
                'invalid_response',
                'Provider response is not a valid JSON object: ' . mb_substr($rawBody, 0, 500)
            );
        }

        $paymentId  = $data->payment_id ?? null;
        $paymentUrl = $data->payment_url ?? null;

        if ( ! is_string( $paymentId ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{1,100}$/', $paymentId ) ) {
            return new \WP_Error('invalid_response', 'Provider response contains a missing or invalid payment_id.');
        }

        if (!is_string( $paymentUrl) || ! $this->is_http_url($paymentUrl)) {
            return new \WP_Error('invalid_response', 'Provider response contains a missing or invalid payment_url.');
        }

        return [
            'payment_id'  => $paymentId,
            'payment_url' => $paymentUrl,
        ];
    }

    private function is_http_url(string $url): bool
    {
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        $host   = (string) wp_parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true ) && '' !== $host;
    }

    private function log( string $message ): void
    {
        wc_get_logger()->error($message, ['source' => $this->id]);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Payment Lab',
                'default' => 'no',
            ],
            'provider_url' => [
                'title'       => 'Payment Provider URL',
                'type'        => 'text',
                'description' => 'Base URL of the payment provider, e.g. http://host.docker.internal:8081 (without /api/payments).',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'callback_url' => [
                'title'       => 'Callback URL',
                'type'        => 'text',
                'description' => 'Full WooCommerce callback endpoint URL.',
                'default'     => '',
                'desc_tip'    => true,
            ],
            'title' => [
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'Payment method title shown to customers at checkout.',
                'default'     => 'Payment Lab — Test Payment',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Explain that this is a simulated test payment.',
                'default'     => 'Educational test payment. No real money will be charged.',
                'desc_tip'    => true,
            ],
        ];
    }
}