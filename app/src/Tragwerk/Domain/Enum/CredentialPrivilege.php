<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Enum;

use Override;

use function _;

/**
 * Privilege level of the SSH user a {@see \Tragwerk\Domain\Entity\Credential} logs in as.
 *
 * Determines whether remote setup commands (package installs, Docker install, daemon enable)
 * are run directly ({@see self::Root}) or wrapped in passwordless `sudo -n` ({@see self::Sudo}).
 */
enum CredentialPrivilege: string implements Translatable
{
    case Root = 'root';
    case Sudo = 'sudo';

    /** @phpstan-pure */
    #[Override]
    public function translatableName(): string
    {
        return match ($this) {
            self::Root => _('Root user'),
            self::Sudo => _('Sudo (passwordless)'),
        };
    }

    /**
     * Command prefix to elevate a single privileged command. Empty for root (already privileged),
     * `sudo -n ` for a sudoer — `-n` never prompts, so a password-requiring sudoer fails fast
     * instead of hanging (auth is SSH-key only, no password is stored).
     *
     * @phpstan-pure
     */
    public function sudoPrefix(): string
    {
        return $this === self::Sudo ? 'sudo -n ' : '';
    }
}
