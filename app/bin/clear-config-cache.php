<?php

declare(strict_types=1);

chdir(__DIR__ . '/../');

require 'vendor/autoload.php';

$config = include 'config/config.php';

$configCachePath = $config['config_cache_path'] ?? null;

if (! is_string($configCachePath)) {
    echo 'No configuration cache path found' . PHP_EOL;
    exit(0);
}

if (! file_exists($configCachePath)) {
    printf(
        "Configured config cache file '%s' not found%s",
        $configCachePath,
        PHP_EOL,
    );
    exit(0);
}

if (unlink($configCachePath) === false) {
    printf(
        "Error removing config cache file '%s'%s",
        $configCachePath,
        PHP_EOL,
    );
    exit(1);
}

printf(
    "Removed configured config cache file '%s'%s",
    $configCachePath,
    PHP_EOL,
);
exit(0);
