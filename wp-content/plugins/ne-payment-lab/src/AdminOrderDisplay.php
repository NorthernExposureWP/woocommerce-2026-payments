<?php

namespace NE\PaymentLab;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the provider's payment IDs, read-only, on the order edit page in the admin panel.
 */
class AdminOrderDisplay
{
    public function register(): void
    {
        // Billing column of the order data box, next to the other payment details.
        // Fires for both the classic and the HPOS order edit screens.
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ $this, 'render' ] );
    }

    /**
     * @param mixed $order The order being edited (\WC_Order).
     */
    public function render( $order ): void
    {
        if ( ! $order instanceof \WC_Order ) {
            return;
        }

        $fields = [
            'Payment ID'          => $order->get_meta( Gateway::META_PAYMENT_ID ),
            'Declined Payment ID' => $order->get_meta( CallbackController::META_DECLINED_PAYMENT_ID ),
        ];

        foreach ( $fields as $label => $value ) {
            $value = (string) $value;

            // Only non-empty values are shown. Plain text, not a form field, so nothing can be edited.
            if ( '' === $value ) {
                continue;
            }

            printf(
                '<p><strong>%s:</strong> %s</p>',
                esc_html( $label ),
                esc_html( $value )
            );
        }
    }
}