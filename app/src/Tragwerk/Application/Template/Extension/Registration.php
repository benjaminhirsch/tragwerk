<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Override;
use Tragwerk\Application\Service\Registration\RegistrationSettings;

final readonly class Registration implements ExtensionInterface
{
    public function __construct(
        private RegistrationSettings $settings,
    ) {
    }

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('registrationEnabled', [$this, 'isRegistrationEnabled']);
    }

    /** @psalm-mutation-free */
    public function isRegistrationEnabled(): bool
    {
        return $this->settings->enabled;
    }
}
