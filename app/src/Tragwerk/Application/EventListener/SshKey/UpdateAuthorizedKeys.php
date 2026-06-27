<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\SshKey;

use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Repository\SshKeyRepository;

use function array_map;
use function dirname;
use function file_put_contents;
use function implode;
use function is_dir;
use function iterator_to_array;
use function mkdir;

final readonly class UpdateAuthorizedKeys
{
    public function __construct(
        private SshKeyRepository $sshKeyRepository,
        private string $authorizedKeysPath,
    ) {
    }

    public function __invoke(): void
    {
        $keys = iterator_to_array($this->sshKeyRepository->getAll(), false);

        $lines = array_map(
            static fn (SshKey $key) => 'command="/usr/local/bin/git-auth-wrapper ' . $key->id->toString() . '",'
                . 'no-port-forwarding,no-X11-forwarding,no-agent-forwarding,no-pty '
                . $key->publicKey,
            $keys,
        );

        $dir = dirname($this->authorizedKeysPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->authorizedKeysPath, implode("\n", $lines) . "\n");
    }
}
