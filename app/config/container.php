<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;

// Load configuration
$config = require __DIR__ . '/config.php';

$dependencies                       = $config['dependencies'];
$dependencies['services']['config'] = $config;

// Build container
// @phpstan-ignore argument.type
return new ServiceManager($dependencies);
