<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class PhpConfig
{
    /** @param list<PhpSettingConfig> $settings */
    public function __construct(public array $settings = [])
    {
    }
}
