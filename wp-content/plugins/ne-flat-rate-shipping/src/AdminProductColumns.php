<?php

declare(strict_types=1);

namespace NorthernExposure\WooCommerce\Shipping;

defined('ABSPATH') || exit;

final class AdminProductColumns
{
    public function __construct()
    {
        add_filter(
            'manage_edit-product_columns',
            [$this, 'add_columns']
        );

        add_action(
            'manage_product_posts_custom_column',
            [$this, 'render_column'],
            10,
            2
        );

        add_filter(
            'manage_edit-product_sortable_columns',
            [$this, 'register_sortable_columns']
        );

        add_action(
            'pre_get_posts',
            [$this, 'prepare_product_sorting']
        );

        add_filter(
            'posts_clauses',
            [$this, 'apply_product_sorting'],
            10,
            2
        );
    }

    public function add_columns(array $columns): array
    {
        $columns['ne_shipping_weight'] = __('Weight', 'ne-flat-rate-shipping');
        $columns['ne_shipping_bulky'] = __('Bulky', 'ne-flat-rate-shipping');
        $columns['ne_shipping_fragile'] = __('Fragile', 'ne-flat-rate-shipping');

        return $columns;
    }

    public function render_column(string $column, int $post_id): void
    {
        if (!in_array(
            $column,
            [
                'ne_shipping_weight',
                'ne_shipping_bulky',
                'ne_shipping_fragile',
            ],
            true
        )) {
            return;
        }

        $product = wc_get_product($post_id);

        if (!$product instanceof \WC_Product) {
            echo '&mdash;';

            return;
        }

        switch ($column) {
            case 'ne_shipping_weight':
                $weight = $product->get_weight();

                echo '' !== $weight
                    ? esc_html(wc_format_weight($weight))
                    : '&mdash;';

                break;

            case 'ne_shipping_bulky':
                echo 'ne-bulky' === $product->get_shipping_class()
                    ? esc_html__('Yes', 'ne-flat-rate-shipping')
                    : '&mdash;';

                break;

            case 'ne_shipping_fragile':
                echo 'ne-fragile' === $product->get_shipping_class()
                    ? esc_html__('Yes', 'ne-flat-rate-shipping')
                    : '&mdash;';

                break;
        }
    }

    public function register_sortable_columns(array $columns): array
    {
        $columns['ne_shipping_weight'] = 'ne_shipping_weight';
        $columns['ne_shipping_bulky'] = 'ne_shipping_bulky';
        $columns['ne_shipping_fragile'] = 'ne_shipping_fragile';

        return $columns;
    }

    public function prepare_product_sorting(\WP_Query $query): void
    {
        if (
            !is_admin()
            || !$query->is_main_query()
            || 'product' !== $query->get('post_type')
        ) {
            return;
        }

        $orderby = $query->get('orderby');

        if (!in_array(
            $orderby,
            [
                'ne_shipping_weight',
                'ne_shipping_bulky',
                'ne_shipping_fragile',
            ],
            true
        )) {
            return;
        }

        // Tell the posts_clauses filter which field to sort by.
        $query->set('ne_shipping_sort', $orderby);
    }

    public function apply_product_sorting(array $clauses, \WP_Query $query): array
    {
        global $wpdb;

        $orderby = $query->get('ne_shipping_sort');

        if (!$orderby || 'product' !== $query->get('post_type')) {
            return $clauses;
        }

        $direction = strtoupper((string) $query->get('order')) === 'DESC'
            ? 'DESC'
            : 'ASC';

        if ('ne_shipping_weight' === $orderby) {
            $clauses['orderby'] = sprintf(
                '(SELECT CAST(pm.meta_value AS DECIMAL(20,6))
                FROM %1$s AS pm
                WHERE pm.post_id = %2$s.ID
                    AND pm.meta_key = \'_weight\'
                LIMIT 1) %3$s, %2$s.ID ASC',
                $wpdb->postmeta,
                $wpdb->posts,
                $direction
            );

            return $clauses;
        }

        $shipping_class = 'ne_shipping_bulky' === $orderby
            ? 'ne-bulky'
            : 'ne-fragile';

        $clauses['orderby'] = $wpdb->prepare(
            "EXISTS (
            SELECT 1
            FROM {$wpdb->term_relationships} AS tr
            INNER JOIN {$wpdb->term_taxonomy} AS tt
                ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} AS t
                ON t.term_id = tt.term_id
            WHERE tr.object_id = {$wpdb->posts}.ID
                AND tt.taxonomy = 'product_shipping_class'
                AND t.slug = %s
        ) {$direction}, {$wpdb->posts}.ID ASC",
            $shipping_class
        );

        return $clauses;
    }
}