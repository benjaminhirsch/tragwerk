<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

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
use function trim;

final readonly class VolumeSizesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $params = $request->getQueryParams();
        $branch = is_string($params['branch'] ?? null) ? $params['branch'] : null;

        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        $volumes = [];
        $error   = null;

        try {
            $volumes = $this->fetchVolumes($project, $branch);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->renderer->render($request, 'page::project/_volume_sizes', [
            'project' => $project,
            'branch'  => $branch,
            'volumes' => $volumes,
            'error'   => $error,
        ]);
    }

    /** @return array<string, string> volume name → human-readable size */
    private function fetchVolumes(Project $project, string $branch): array
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

        $branchSlug = $this->slugify(basename($branch));

        return $this->parseVolumes($output, $branchSlug);
    }

    /** @return array<string, string> */
    private function parseVolumes(string $output, string $branchSlug): array
    {
        $volumes       = [];
        $inSection     = false;
        $headerSkipped = false;
        $prefix        = $branchSlug . '_';

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

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }

    private function resolveProject(ServerRequestInterface $request): Project|null
    {
        $routeId = $request->getAttribute('id');

        if (! is_string($routeId) || ! ProjectIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');

        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($routeId));
            assert($project instanceof Project);

            if ($project->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $project;
        } catch (Throwable) {
            return null;
        }
    }
}
