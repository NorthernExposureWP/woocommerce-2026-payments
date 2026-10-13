<?php

declare(strict_types=1);

namespace NorthernExposure\WooCommerce\Shipping;

defined('ABSPATH') || exit;

final class ShippingMethod extends \WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'ne_flat_rate_shipping';
        $this->instance_id = absint($instance_id);

        $this->method_title = __('NE Flat Rate Shipping', 'ne-flat-rate-shipping');
        $this->method_description = __(
            'Shipping cost consists of a base price and a charge based on the total order weight.',
            'ne-flat-rate-shipping'
        );

        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    public function init()
    {
        $this->init_instance_form_fields();
        $this->init_instance_settings();

        $this->enabled = $this->get_instance_option('enabled', 'no');
        $this->title = $this->get_instance_option(
            'title',
            __('NE Flat Rate Shipping', 'ne-flat-rate-shipping')
        );

        add_action(
            'woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id,
            [$this, 'process_admin_options']
        );
    }

    public function init_instance_form_fields()
    {
        $this->instance_form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'ne-flat-rate-shipping'),
                'type' => 'checkbox',
                'label' => __('Enable this shipping method', 'ne-flat-rate-shipping'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'ne-flat-rate-shipping'),
                'type' => 'text',
                'description' => __(
                    'The shipping method title displayed to customers.',
                    'ne-flat-rate-shipping'
                ),
                'default' => __('NE Flat Rate Shipping', 'ne-flat-rate-shipping'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'ne-flat-rate-shipping'),
                'type' => 'text',
                'description' => __(
                    'Optional description of the shipping method.',
                    'ne-flat-rate-shipping'
                ),
                'default' => '',
                'desc_tip' => true,
            ],
            'base_cost' => [
                'title' => __('Base Cost', 'ne-flat-rate-shipping'),
                'type' => 'price',
                'description' => __(
                    'Base shipping cost per order.',
                    'ne-flat-rate-shipping'
                ),
                'default' => '5.00',
                'desc_tip' => true,
            ],
            'cost_per_lb' => [
                'title' => __('Cost per lb', 'ne-flat-rate-shipping'),
                'type' => 'price',
                'description' => __(
                    'Additional shipping cost per pound of total order weight.',
                    'ne-flat-rate-shipping'
                ),
                'default' => '0.50',
                'desc_tip' => true,
            ],
            'bulky_surcharge' => [
                'title' => __('Bulky Items Surcharge', 'ne-flat-rate-shipping'),
                'type' => 'price',
                'description' => __(
                    'Additional shipping cost for bulky items.',
                    'ne-flat-rate-shipping'
                ),
                'default' => '5.00',
                'desc_tip' => true,
            ],
            'fragile_surcharge' => [
                'title' => __('Fragile Items Surcharge', 'ne-flat-rate-shipping'),
                'type' => 'price',
                'description' => __(
                    'Additional shipping cost for fragile items.',
                    'ne-flat-rate-shipping'
                ),
                'default' => '5.00',
                'desc_tip' => true,
            ],
        ];
    }

    public function calculate_shipping($package = [])
    {
        if ('yes' !== $this->enabled) {
            return;
        }

        $total_weight = 0.0;

        foreach ($package['contents'] ?? [] as $item) {
            $product = $item['data'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (!$product instanceof \WC_Product || $quantity <= 0) {
                continue;
            }

            $weight = $product->get_weight();

            if ('' === $weight || !is_numeric($weight)) {
                continue;
            }

            $total_weight += (float) $weight * $quantity;
        }

        // Convert the store's weight unit to pounds.
        $total_weight_in_lb = (float) wc_get_weight($total_weight, 'lbs');

        $base_cost = (float) $this->get_instance_option('base_cost', '5.00');
        $cost_per_lb = (float) $this->get_instance_option('cost_per_lb', '0.50');

        $shipping_cost = $base_cost + ($total_weight_in_lb * $cost_per_lb);

        $has_bulky_items = false;
        $has_fragile_items = false;
        foreach ($package['contents'] ?? [] as $item) {
            $product = $item['data'] ?? null;
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $shipping_class = $product->get_shipping_class();
            if ('ne-bulky' === $shipping_class) {
                $has_bulky_items = true;
            }
            if ('ne-fragile' === $shipping_class) {
                $has_fragile_items = true;
            }
            if ($has_bulky_items && $has_fragile_items) {
                break;
            }
        }

        $bulky_surcharge = $has_bulky_items
            ? (float) $this->get_instance_option('bulky_surcharge', '5.00')
            : 0.0;

        $fragile_surcharge = $has_fragile_items
            ? (float) $this->get_instance_option('fragile_surcharge', '5.00')
            : 0.0;

        $shipping_cost += $bulky_surcharge + $fragile_surcharge;

        $this->add_rate([
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $shipping_cost,
        ]);
    }
}