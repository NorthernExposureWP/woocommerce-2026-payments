<?php

namespace NE\PaymentLab;

defined( 'ABSPATH' ) || exit;

class Gateway extends \WC_Payment_Gateway {
    private string $testMode = 'success';

    public function __construct() {
        $this->id                 = 'ne_payment_lab';
        $this->method_title       = 'NE Payment Lab';
        $this->method_description = 'Educational gateway for testing WooCommerce payment flows.';
        $this->has_fields         = false;
        $this->testMode = $this->get_option('test_mode', 'success');

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled     = $this->get_option( 'enabled', 'no' );

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [ $this, 'process_admin_options' ]
        );
    }

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        if ( ! $order instanceof \WC_Order ) {
            wc_add_notice(
                'Unable to process payment: order not found.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        if ( $order->get_payment_method() !== $this->id ) {
            wc_add_notice(
                'Unable to process payment: incorrect payment method.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        // Simulate a successful test payment.
        if ( ! $order->payment_complete() ) {
            wc_add_notice(
                'Unable to complete the test payment. Please try again.',
                'error'
            );

            return [
                'result' => 'failure',
            ];
        }

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        ];
    }

    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Payment Lab',
                'default' => 'no',
            ],
            'test_mode' => [
                'title'       => 'Test Mode',
                'type'        => 'select',
                'description' => 'Choose the simulated payment result.',
                'default'     => 'success',
                'options'     => [
                    'success' => 'Success',
                    'failure' => 'Failure',
                ],
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