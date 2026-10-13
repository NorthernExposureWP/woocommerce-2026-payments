<?php

namespace NorthernExposure\WooCommerce\ShippingLab;

defined( 'ABSPATH' ) || exit;

final class CheckoutFields {

    public static function register(): void {
        woocommerce_register_additional_checkout_field(
            [
                'id'       => 'ne-shipping/delivery-instructions',
                'label'    => 'Delivery instructions',
                'location' => 'order',
                'type'     => 'text',
                'required' => false,
            ]
        );
    }
}