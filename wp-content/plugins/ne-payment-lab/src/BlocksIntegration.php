<?php

namespace NE\PaymentLab;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class BlocksIntegration extends AbstractPaymentMethodType {

    protected $name = 'ne_payment_lab';

    public function initialize(): void {
        $this->settings = get_option( 'woocommerce_ne_payment_lab_settings', [] );
    }

    public function is_active(): bool {
        return 'yes' === $this->get_setting( 'enabled', 'no' );
    }

    public function get_payment_method_script_handles(): array {
        $script_path = __DIR__ . '/../assets/js/payment-method.js';

        wp_register_script(
            'ne-payment-lab',
            plugins_url( '../assets/js/payment-method.js', __FILE__ ),
            [
                'wc-blocks-registry',
                'wp-element',
                'wc-settings',
            ],
            (string) filemtime( $script_path ),
            true
        );

        return [ 'ne-payment-lab' ];
    }

    public function get_payment_method_data(): array {
        return [
            'title'       => $this->get_setting( 'title', 'Payment Lab — Test Payment' ),
            'description' => $this->get_setting(
                'description',
                'Educational test payment. No real money will be charged.'
            ),
            'supports'    => $this->get_supported_features(),
        ];
    }
}