<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;
use function basename;
use function count;
use function explode;
use function is_string;
use function preg_replace;
use function preg_split;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final readonly class VolumeSizeReader
{
    public function __construct(
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
    ) {
    }

    /**
     * Reads docker volume sizes for the environment via SSH (`docker system df -v`).
     *
     * @return array<string, string> full docker volume name → human-readable size
     *
     * @throws RuntimeException on missing credential or SSH failure.
     */
    public function read(Project $project, string $branch): array
    {
        $server = $this->serverRepository->getById($project->serverId);
        assert($server instanceof Server);

        if ($server->credentialId === null) {
            throw new RuntimeException('No credential assigned to server.');
        }

        $credential = $this->credentialRepository->getById($server->credentialId);
        assert($credential instanceof Credential);

        if ($credential->privateKey === null) {
            throw new RuntimeException('Credential has no SSH key.');
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey($credential->privateKey);
        } catch (NoKeyLoadedException $e) {
            throw new RuntimeException('Failed to load SSH key: ' . $e->getMessage(), previous: $e);
        }

        $sftp = new SFTP($server->host, $server->port, 30);

        if (! $sftp->login($credential->username, $key)) {
            throw new RuntimeException('SSH login failed.');
        }

        $sftp->setTimeout(30);

        $raw    = $sftp->exec('docker system df -v 2>&1');
        $output = trim(is_string($raw) ? $raw : '');

        return $this->parseVolumes($output, $this->composeProjectName($project, $branch));
    }

    /**
     * The docker compose project name volumes are prefixed with (followed by `_`).
     */
    public function composeProjectName(Project $project, string $branch): string
    {
        return 'tw-' . substr($project->id->toString(), 0, 8) . '-' . $this->slugify(basename($branch));
    }

    /** @return array<string, string> */
    private function parseVolumes(string $output, string $composeProject): array
    {
        $volumes       = [];
        $inSection     = false;
        $headerSkipped = false;
        $prefix        = $composeProject . '_';

        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, 'Local Volumes space usage:')) {
                $inSection = true;
                continue;
            }

            if (! $inSection) {
                continue;
            }

            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($headerSkipped) {
                    break;
                }

                continue;
            }

            if (str_starts_with($trimmed, 'VOLUME NAME')) {
                $headerSkipped = true;
                continue;
            }

            if (! $headerSkipped) {
                continue;
            }

            $parts = preg_split('/\s+/', $trimmed, 3) ?: [];

            if (count($parts) < 3) {
                continue;
            }

            $name = $parts[0];
            $size = $parts[2];

            if (! str_starts_with($name, $prefix)) {
                continue;
            }

            $volumes[$name] = $size;
        }

        return $volumes;
    }

    public function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
