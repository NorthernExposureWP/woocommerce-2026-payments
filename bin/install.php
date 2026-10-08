<?php

declare(strict_types=1);

$source = __DIR__ . '/../docker/apache/.htaccess';
$target = __DIR__ . '/../public/.htaccess';

if (!copy($source, $target)) {
    throw new RuntimeException('Failed to install public/.htaccess.');
}

if (!chown($target, fileowner(__DIR__ . '/../composer.json'))
    || !chgrp($target, filegroup(__DIR__ . '/../composer.json'))) {
    throw new RuntimeException('Failed to set ownership for public/.htaccess.');
}

echo "Installed public/.htaccess\n";
