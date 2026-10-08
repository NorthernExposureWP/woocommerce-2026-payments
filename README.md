# WooCommerce 2026 Template

Minimal WooCommerce starter template for quickly creating new development stores.

The template contains the Docker environment, WordPress, WooCommerce, Composer setup, and a reproducible provisioning command.

Store-specific data such as products, categories, orders, customers, and other test data is intentionally not included.

## Requirements

* Docker Engine
* Docker Compose
* Git

## Setup

### 1. Create environment file

```bash
cp .env.example .env
```

### 2. Start Docker

```bash
docker compose up -d --build
```

### 3. Install Composer dependencies

```bash
docker compose exec -e XDEBUG_MODE=off php composer install
```

### 4. Install WordPress

Open:

```text
http://localhost:8080
```

Complete the standard WordPress installation in the browser.

### 5. Fix file ownership

If files inside `public/` were created by the Docker container and are not writable by the host user:

```bash
sudo chown -R $USER:$USER public/
```

### 6. Configure WordPress

Add the following to `public/wp-config.php`:

```php
require_once dirname(__DIR__) . '/vendor/autoload.php';

define('WP_CONTENT_DIR', dirname(__DIR__) . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8080/wp-content');
```

The project-level Composer autoloader is loaded before WordPress loads plugins.

`WP_CONTENT_DIR` points WordPress to the project-level `wp-content/` directory. The Docker/Apache configuration exposes this directory through `/wp-content/`.

### 7. Activate WooCommerce

WooCommerce 11.1.2 is included in the repository:

```text
wp-content/plugins/woocommerce/
```

Activate it with WP-CLI:

```bash
./wpcli plugin activate woocommerce
```

### 8. Provision the template

Run:

```bash
./wpcli template provision
```

The provisioning command configures the reusable store baseline:

* pretty permalinks;
* WooCommerce Coming Soon disabled;
* Home page;
* Shop page;
* Cart page;
* Checkout page;
* My Account page;
* WooCommerce page settings;
* Home as the site front page;
* primary navigation;
* removal of the default Sample Page.

The command is safe to run repeatedly.

After provisioning, the template provides the basic storefront flow:

```text
/                       Home
/shop/                  Product catalog
/product/...            Product page
/product-category/...   Product category
/cart/                  Cart
/checkout/              Checkout
/my-account/            My Account
/order-received/...     Order confirmation
```

The template does not create products, categories, customers, orders, or other store-specific data.

## WP-CLI

Use the project wrapper:

```bash
./wpcli core version
./wpcli plugin list
./wpcli theme list
./wpcli template provision
```

The wrapper uses the project's `wp-cli.yml` configuration.

## Composer autoload

Custom code uses the project-level Composer autoloader.

After adding or changing a custom namespace in `composer.json`:

```bash
docker compose exec -e XDEBUG_MODE=off php composer dump-autoload
```

Custom plugins must not contain their own `composer.json` or `vendor/` directory.

## Template architecture

The repository contains infrastructure and reusable configuration only.

Committed:

* Docker configuration;
* WordPress installation;
* WooCommerce;
* Composer configuration;
* WP-CLI configuration;
* provisioning code;
* the default theme.

Provisioned:

* WordPress/WooCommerce pages;
* WooCommerce page settings;
* permalink configuration;
* navigation;
* other reusable store settings.

Not included:

* products;
* product categories and attributes;
* customers;
* orders;
* store-specific content;
* educational/test plugins.

## Possible issues

### Image uploads

If image uploads from the WordPress admin do not work correctly, uncomment the corresponding volume mapping in `compose.yaml` and rebuild:

```bash
docker compose up -d --build
```
