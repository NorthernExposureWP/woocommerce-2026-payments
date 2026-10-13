<?php
/**
 * Plugin Name: NE Flat Rate Shipping
 * Description: Custom WooCommerce shipping method with weight-based pricing.
 * Version: 1.0.0
 * Requires PHP: 8.4
 * Requires Plugins: woocommerce
 * Text Domain: ne-flat-rate-shipping
 *
 * @package NorthernExposure\WooCommerce\Shipping
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action(
    'woocommerce_shipping_init',
    static function (): void {
        require_once __DIR__ . '/src/ShippingMethod.php';
    }
);

add_filter(
    'woocommerce_shipping_methods',
    static function (array $methods): array {
        $methods['ne_flat_rate_shipping'] =
            \NorthernExposure\WooCommerce\Shipping\ShippingMethod::class;

        return $methods;
    }
);

add_action(
    'admin_init',
    static function (): void {
        if (!class_exists('WooCommerce')) {
            return;
        }
        require_once __DIR__ . '/src/AdminProductColumns.php';
        new \NorthernExposure\WooCommerce\Shipping\AdminProductColumns();
    }
);

add_action(
    'init',
    static function (): void {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $shipping_classes = [
            'ne-bulky' => [
                'name' => 'Bulky Items',
                'description' => 'Bulky products',
            ],
            'ne-fragile' => [
                'name' => 'Fragile Items',
                'description' => 'Fragile products',
            ],
        ];

        foreach ($shipping_classes as $slug => $class) {
            if (term_exists($slug, 'product_shipping_class')) {
                continue;
            }

            wp_insert_term(
                $class['name'],
                'product_shipping_class',
                [
                    'slug' => $slug,
                    'description' => $class['description'],
                ]
            );
        }
    },
    20
);