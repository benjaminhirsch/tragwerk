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
use function date;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function preg_replace;
use function strtolower;
use function trim;

final readonly class EnvironmentLogsHandler implements RequestHandlerInterface
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
        $level  = is_string($params['level'] ?? null) ? strtolower($params['level']) : 'all';

        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        $entries = [];
        $error   = null;

        try {
            $entries = $this->fetchLogs($project, $branch, $level);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->renderer->render($request, 'page::project/_environment_logs', [
            'project' => $project,
            'branch'  => $branch,
            'level'   => $level,
            'entries' => $entries,
            'error'   => $error,
        ]);
    }

    /** @return list<array{time: string, level: string, logger: string, msg: string, raw: string}> */
    private function fetchLogs(Project $project, string $branch, string $level): array
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

        $branchSlug = $this->slugify(basename($branch));
        $projectId  = $project->id->toString();
        $slotDir    = '~/tragwerk/' . $projectId . '/' . $branch;

        // Find the running app container: prefer blue/green slot, fall back to compose name.
        $container = trim((string) $sftp->exec(
            'slot=$(cat ' . $slotDir . '/.slot-* 2>/dev/null | head -1); '
            . 'if [ -n "$slot" ]; then '
            . '  docker ps --filter "name=' . $branchSlug . '" --format "{{.Names}}" '
            . '    | grep -E -- "-${slot}$" | head -1; '
            . 'else '
            . '  docker ps --filter "name=' . $branchSlug . '" --format "{{.Names}}" '
            . '    | grep -v -- "-db-" | head -1; '
            . 'fi 2>/dev/null',
        ));

        if ($container === '') {
            throw new RuntimeException('No running application container found.');
        }

        $raw = (string) $sftp->exec('docker logs --tail 500 ' . $container . ' 2>&1');

        return $this->parseEntries($raw, $level);
    }

    /** @return list<array{time: string, level: string, logger: string, msg: string, raw: string}> */
    private function parseEntries(string $output, string $level): array
    {
        $entries = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);

            if (is_array($obj)) {
                $entryLevel = strtolower(is_string($obj['level'] ?? null) ? $obj['level'] : 'info');

                if ($level !== 'all' && $entryLevel !== $level) {
                    continue;
                }

                $ts     = $obj['ts'] ?? null;
                $time   = is_numeric($ts) ? date('H:i:s', (int) $ts) : '';
                $logger = is_string($obj['logger'] ?? null) ? $obj['logger'] : '';
                $msg    = is_string($obj['msg'] ?? null) ? $obj['msg'] : $line;

                $entries[] = [
                    'time'   => $time,
                    'level'  => $entryLevel,
                    'logger' => $logger,
                    'msg'    => $msg,
                    'raw'    => $line,
                ];
            } else {
                if ($level !== 'all') {
                    continue;
                }

                $entries[] = [
                    'time'   => '',
                    'level'  => 'plain',
                    'logger' => '',
                    'msg'    => $line,
                    'raw'    => $line,
                ];
            }
        }

        return $entries;
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
