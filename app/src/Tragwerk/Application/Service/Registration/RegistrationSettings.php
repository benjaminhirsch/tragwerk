<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service\Registration;

final readonly class RegistrationSettings
{
    public function __construct(
        public bool $enabled,
    ) {
    }
}
