<?php

declare(strict_types=1);

namespace NorthernExposure\WooCommerce\Shipping;

defined('ABSPATH') || exit;
define('NE_FLAT_RATE_SHIPPING_URL', plugin_dir_url(__FILE__));

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

        add_action(
            'wp_ajax_ne_flat_rate_save_product_field',
            [$this, 'save_product_field']
        );

        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueue_assets']
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

                printf(
                    '<input type="number" min="0" step="any" class="ne-shipping-weight-input" data-ne-shipping-field="weight" data-product-id="%1$d" value="%2$s" aria-label="%3$s">',
                    $post_id,
                    esc_attr($weight),
                    esc_attr__('Product weight', 'ne-flat-rate-shipping')
                );

                break;

            case 'ne_shipping_bulky':
            case 'ne_shipping_fragile':
                $shipping_class = $product->get_shipping_class();

                $current_class = 'ne_shipping_bulky' === $column
                    ? 'ne-bulky'
                    : 'ne-fragile';

                printf(
                    '<select data-ne-shipping-field="shipping_class" data-product-id="%1$d" aria-label="%2$s">',
                    $post_id,
                    esc_attr(
                        'ne_shipping_bulky' === $column
                            ? __('Shipping class: Bulky', 'ne-flat-rate-shipping')
                            : __('Shipping class: Fragile', 'ne-flat-rate-shipping')
                    )
                );

                printf(
                    '<option value="" %1$s>%2$s</option>',
                    selected(
                        !in_array($shipping_class, ['ne-bulky', 'ne-fragile'], true),
                        true,
                        false
                    ),
                    esc_html__('No', 'ne-flat-rate-shipping')
                );

                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr($current_class),
                    selected($shipping_class, $current_class, false),
                    esc_html__('Yes', 'ne-flat-rate-shipping')
                );

                echo '</select>';

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

    public function save_product_field(): void
    {
        check_ajax_referer('ne_flat_rate_product_edit', 'nonce');

        $product_id = isset($_POST['product_id'])
            ? absint($_POST['product_id'])
            : 0;

        if (
            !$product_id
            || !current_user_can('edit_post', $product_id)
        ) {
            wp_send_json_error(
                ['message' => 'You are not allowed to edit this product.'],
                403
            );
        }

        $product = wc_get_product($product_id);

        if (!$product instanceof \WC_Product) {
            wp_send_json_error(
                ['message' => 'Product not found.'],
                404
            );
        }

        $field = isset($_POST['field']) && is_string($_POST['field'])
            ? sanitize_key(wp_unslash($_POST['field']))
            : '';

        $value = isset($_POST['value']) && is_string($_POST['value'])
            ? trim(wp_unslash($_POST['value']))
            : null;

        if (null === $value) {
            wp_send_json_error(
                ['message' => 'Missing field value.'],
                400
            );
        }

        switch ($field) {
            case 'weight':
                if (
                    '' !== $value
                    && (!is_numeric($value) || (float) $value < 0)
                ) {
                    wp_send_json_error(
                        ['message' => 'Weight must be a non-negative number.'],
                        400
                    );
                }

                $product->set_weight(
                    '' === $value ? '' : wc_format_decimal($value)
                );

                break;

            case 'shipping_class':
                if ('' === $value) {
                    $product->set_shipping_class_id(0);

                    break;
                }

                $allowed_classes = [
                    'ne-bulky',
                    'ne-fragile',
                ];

                if (!in_array($value, $allowed_classes, true)) {
                    wp_send_json_error(
                        ['message' => 'Invalid shipping class.'],
                        400
                    );
                }

                $term = get_term_by(
                    'slug',
                    $value,
                    'product_shipping_class'
                );

                if (!$term instanceof \WP_Term) {
                    wp_send_json_error(
                        ['message' => 'Shipping class not found.'],
                        404
                    );
                }

                $product->set_shipping_class_id((int) $term->term_id);

                break;

            default:
                wp_send_json_error(
                    ['message' => 'Unsupported field.'],
                    400
                );
        }

        $product->save();

        wp_send_json_success([
            'product_id' => $product_id,
            'field' => $field,
            'value' => $value,
        ]);
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if ('edit.php' !== $hook_suffix) {
            return;
        }

        $screen = get_current_screen();

        if (!$screen || 'edit-product' !== $screen->id) {
            return;
        }

        $handle = 'ne-flat-rate-admin-product-columns';

        wp_enqueue_script(
            $handle,
            plugin_dir_url(dirname(__DIR__)) . basename(dirname(__DIR__)) . '/assets/js/admin-product-columns.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            $handle,
            'NEFlatRateShipping',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ne_flat_rate_product_edit'),
            ]
        );

        wp_enqueue_style(
            'ne-flat-rate-admin-product-columns',
            plugin_dir_url(dirname(__DIR__)) . basename(dirname(__DIR__)) . '/assets/js/admin-product-columns.css',
            [],
            '1.0.0'
        );
    }
}