<?php

declare(strict_types=1);

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('template provision', static function (): void {
        require __DIR__ . '/provision.php';
    });
}
