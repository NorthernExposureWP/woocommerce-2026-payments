<?php

declare(strict_types=1);

if (!defined('WC_VERSION')) {
    throw new RuntimeException('WooCommerce is not active.');
}

echo "WooCommerce template provisioning\n";
echo "=================================\n";

echo "WordPress: " . get_bloginfo('version') . PHP_EOL;
echo "WooCommerce: " . WC_VERSION . PHP_EOL;

$permalinkStructure = '/%postname%/';

if (get_option('permalink_structure') !== $permalinkStructure) {
    update_option('permalink_structure', $permalinkStructure);
    flush_rewrite_rules();

    echo "Permalink structure: {$permalinkStructure}\n";
} else {
    echo "Permalink structure: already configured\n";
}

if (get_option('woocommerce_coming_soon') !== 'no') {
    update_option('woocommerce_coming_soon', 'no');

    echo "WooCommerce Coming Soon: disabled\n";
} else {
    echo "WooCommerce Coming Soon: already disabled\n";
}

$homePageId = provision_page('home', 'Home');
$shopPageId = provision_page('shop', 'Shop');
$cartPageId = provision_page('cart', 'Cart');
$checkoutPageId = provision_page('checkout', 'Checkout');
$myAccountPageId = provision_page('my-account', 'My account');

echo "Home page ID: {$homePageId}\n";
echo "Shop page ID: {$shopPageId}\n";
echo "Cart page ID: {$cartPageId}\n";
echo "Checkout page ID: {$checkoutPageId}\n";
echo "My account page ID: {$myAccountPageId}\n";

update_option('woocommerce_shop_page_id', $shopPageId);
update_option('woocommerce_cart_page_id', $cartPageId);
update_option('woocommerce_checkout_page_id', $checkoutPageId);
update_option('woocommerce_myaccount_page_id', $myAccountPageId);

echo "WooCommerce page settings: configured\n";

update_option('show_on_front', 'page');
update_option('page_on_front', $homePageId);

echo "Front page: configured\n";

$samplePage = get_page_by_path('sample-page', OBJECT, 'page');

if ($samplePage instanceof WP_Post) {
    wp_delete_post($samplePage->ID, true);

    echo "Removed default Sample Page\n";
}


provision_navigation(
    'navigation',
    'Navigation',
    [
        $shopPageId      => 'Shop',
        $cartPageId      => 'Cart',
        $checkoutPageId  => 'Checkout',
        $myAccountPageId => 'My account',
    ],
);

function provision_page(string $slug, string $title): int
{
    $page = get_page_by_path($slug, OBJECT, 'page');

    if ($page instanceof WP_Post) {
        if ($page->post_status !== 'publish') {
            $result = wp_update_post([
                'ID'          => $page->ID,
                'post_status' => 'publish',
            ], true);

            if (is_wp_error($result)) {
                throw new RuntimeException(
                    sprintf(
                        'Failed to update page "%s": %s',
                        $slug,
                        $result->get_error_message()
                    )
                );
            }
        }

        return $page->ID;
    }

    $pageId = wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_name'   => $slug,
    ], true);

    if (is_wp_error($pageId)) {
        throw new RuntimeException(
            sprintf('Failed to create page "%s": %s', $slug, $pageId->get_error_message())
        );
    }

    echo "Created page: {$title} (ID {$pageId})\n";

    return $pageId;
}

function provision_navigation(
    string $slug,
    string $title,
    array $pages
): void {
    $navigation = get_page_by_path($slug, OBJECT, 'wp_navigation');

    if (!$navigation instanceof WP_Post) {
        $navigationId = wp_insert_post([
            'post_type'    => 'wp_navigation',
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => '',
        ], true);

        if (is_wp_error($navigationId)) {
            throw new RuntimeException(
                sprintf(
                    'Failed to create navigation "%s": %s',
                    $slug,
                    $navigationId->get_error_message()
                )
            );
        }

        $navigation = get_post($navigationId);
    }

    $blocks = [
        '<!-- wp:navigation-link ' . wp_json_encode([
            'label' => 'Home',
            'url'   => home_url('/'),
        ]) . ' /-->',
    ];

    foreach ($pages as $pageId => $label) {
        $blocks[] = '<!-- wp:navigation-link ' . wp_json_encode([
                'label' => $label,
                'type'  => 'page',
                'id'    => $pageId,
                'url'   => get_permalink($pageId),
            ]) . ' /-->';
    }

    $result = wp_update_post([
        'ID'           => $navigation->ID,
        'post_content' => implode("\n", $blocks),
    ], true);

    if (is_wp_error($result)) {
        throw new RuntimeException(
            sprintf(
                'Failed to update navigation "%s": %s',
                $slug,
                $result->get_error_message()
            )
        );
    }

    echo "Navigation: configured\n";
}
