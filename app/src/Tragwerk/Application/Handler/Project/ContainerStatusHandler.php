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
use function escapeshellarg;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function trim;

final readonly class ContainerStatusHandler implements RequestHandlerInterface
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

        $containers = [];
        $error      = null;

        try {
            $containers = $this->fetchContainers($project, $branch);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->renderer->render($request, 'page::project/_container_status', [
            'project'    => $project,
            'branch'     => $branch,
            'containers' => $containers,
            'error'      => $error,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function fetchContainers(Project $project, string $branch): array
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

        $remoteDir   = 'tragwerk/' . $project->id->toString() . '/' . $branch;
        $labelFilter = escapeshellarg('label=tragwerk.working_dir=/root/' . $remoteDir);
        $raw         = $sftp->exec(
            'cd ~/' . $remoteDir . ' && docker compose ps --format json 2>&1; '
            . 'docker ps --filter ' . $labelFilter . ' --format json 2>/dev/null',
        );
        $output      = trim(is_string($raw) ? $raw : '');

        return $this->parseContainers($output);
    }

    /** @return list<array<string, mixed>> */
    private function parseContainers(string $output): array
    {
        if ($output === '') {
            return [];
        }

        $containers = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);

            if (! is_array($obj)) {
                continue;
            }

            // docker compose ps uses "Name"; docker ps uses "Names"
            if (! isset($obj['Name']) && isset($obj['Names'])) {
                $obj['Name'] = $obj['Names'];
            }

            if (! isset($obj['Name'])) {
                continue;
            }

            $containers[] = $obj;
        }

        return $containers;
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
