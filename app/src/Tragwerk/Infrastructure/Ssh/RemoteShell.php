<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Ssh;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;

use function is_string;

/**
 * Runs a single shell command on a remote server over SSH and returns its output.
 *
 * Encapsulates the connect/login dance (key loading → SFTP login → exec) that is otherwise
 * repeated across the SSH handlers and commands.
 */
final class RemoteShell
{
    private const int TIMEOUT = 30;

    /** @throws RuntimeException */
    public function run(Server $server, Credential $credential, string $command): string
    {
        if ($credential->privateKey === null) {
            throw new RuntimeException('Credential has no SSH key.');
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
        } catch (NoKeyLoadedException $e) {
            throw new RuntimeException('Failed to load SSH key: ' . $e->getMessage(), previous: $e);
        }

        $sftp = new SFTP($server->host, $server->port, self::TIMEOUT);

        if (! $sftp->login($credential->username, $key)) {
            throw new RuntimeException('SSH login failed.');
        }

        $sftp->setTimeout(self::TIMEOUT);
        $raw = $sftp->exec($command);

        return is_string($raw) ? $raw : '';
    }
}
