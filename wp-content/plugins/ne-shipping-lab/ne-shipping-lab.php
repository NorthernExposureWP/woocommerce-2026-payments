<?php
/**
 * Plugin Name: NE Shipping Lab
 * Description: WooCommerce shipping and checkout fields.
 * Version: 1.0.0
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/CheckoutFields.php';

add_action(
    'woocommerce_init',
    [ \NorthernExposure\WooCommerce\ShippingLab\CheckoutFields::class, 'register' ]
);