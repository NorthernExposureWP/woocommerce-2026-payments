<?php
/**
 * Plugin Name: NE Payment Lab
 * Description: Educational WooCommerce payment gateway experiments.
 * Version: 1.0.0
 * Requires PHP: 8.4
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', static function (): void {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    require_once __DIR__ . '/src/Gateway.php';
    require_once __DIR__ . '/src/BlocksIntegration.php';

    add_filter( 'woocommerce_payment_gateways', static function ( array $gateways ): array {
        $gateways[] = \NE\PaymentLab\Gateway::class;

        return $gateways;
    } );

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        static function ( $payment_method_registry ): void {
            $payment_method_registry->register(
                new \NE\PaymentLab\BlocksIntegration()
            );
        }
    );
} );