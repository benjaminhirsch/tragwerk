<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Config;

use Psr\Container\ContainerInterface;
use Tragwerk\Domain\Config\ConfigValidator;

final readonly class ConfigValidatorFactory
{
    public function __invoke(ContainerInterface $container): ConfigValidator
    {
        return new ConfigValidator(__DIR__ . '/../../../../Domain/Config/schema.xsd');
    }
}
