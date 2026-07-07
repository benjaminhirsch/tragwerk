<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Log;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Tragwerk\Application\Exception\Credential\CredentialKeyEncryptionFailed;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function assert;
use function basename;
use function date;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function md5;
use function preg_match;
use function preg_replace;
use function strtolower;
use function strtotime;
use function substr;
use function trim;

final readonly class TailHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
        private CacheItemPoolInterface $cache,
        private CredentialEncryptor $credentialEncryptor,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $request->getAttribute('active_environment');
        assert(is_string($branch));

        // Restrict to our slug shape (app / app-worker-x / app-cron); anything else falls back to
        // the default app container and never reaches the shell.
        $serviceParam = $request->getQueryParams()['service'] ?? '';
        $service      = is_string($serviceParam) && preg_match('/^[a-z0-9-]+$/', $serviceParam) === 1
            ? $serviceParam
            : '';

        // Cache briefly. The pool holds a blocking lock per key, so concurrent
        // pollers (10s auto-refresh across tabs) wait for one SSH call.
        $item = $this->cache->getItem(
            'frankenlog_' . $project->id->toString() . '_' . md5($branch . '|' . $service),
        );

        if ($item->isHit()) {
            /** @var array{entries: list<array{time: string, level: string, logger: string, msg: string, raw: string}>, error: string|null} $result */
            $result = $item->get();
        } else {
            try {
                $result = ['entries' => $this->fetchLogs($project, $branch, $service), 'error' => null];
                $item->set($result)->expiresAfter(8);
            } catch (Throwable $e) {
                $result = ['entries' => [], 'error' => $e->getMessage()];
                $item->set($result)->expiresAfter(5);
            }

            $this->cache->save($item);
        }

        return $this->renderer->render($request, 'page::log/tail', [
            'branch'  => $branch,
            'service' => $service,
            'entries' => $result['entries'],
            'error'   => $result['error'],
        ]);
    }

    /** @return list<array{time: string, level: string, logger: string, msg: string, raw: string}> */
    private function fetchLogs(Project $project, string $branch, string $service = ''): array
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
            $key = PublicKeyLoader::loadPrivateKey($this->credentialEncryptor->decrypt($credential->privateKey));
        } catch (NoKeyLoadedException | CredentialKeyEncryptionFailed $e) {
            throw new RuntimeException('Failed to load SSH key: ' . $e->getMessage(), previous: $e);
        }

        $sftp = new SFTP($server->host, $server->port, 30);

        if (! $sftp->login($credential->username, $key)) {
            throw new RuntimeException('SSH login failed.');
        }

        $sftp->setTimeout(30);

        $branchSlug     = $this->slugify(basename($branch));
        $projectId      = $project->id->toString();
        $shortId        = substr($projectId, 0, 8);
        $composeProject = 'tw-' . $shortId . '-' . $branchSlug;
        $slotDir        = '~/tragwerk/' . $projectId . '/' . $branch;

        // A specific compose service (worker/cron/non-primary app) is resolved by name; the primary
        // app falls through to the blue/green slot discovery below.
        $container = $service !== ''
            ? $this->resolveServiceContainer($sftp, $composeProject, $service)
            : '';

        if ($container === '') {
            // Find the running app container: prefer blue/green slot, fall back to compose name.
            $container = trim((string) $sftp->exec(
                'slot=$(cat ' . $slotDir . '/.slot-* 2>/dev/null | head -1); '
                . 'if [ -n "$slot" ]; then '
                . '  docker ps --filter "name=' . $composeProject . '" --format "{{.Names}}" '
                . '    | grep -E -- "-${slot}$" | head -1; '
                . 'else '
                . '  docker ps --filter "name=' . $composeProject . '" --format "{{.Names}}" '
                . '    | grep -v -- "-db-" | head -1; '
                . 'fi 2>/dev/null',
            ));
        }

        if ($container === '') {
            throw new RuntimeException('No running container found for this service.');
        }

        $raw = (string) $sftp->exec('docker logs --tail 500 ' . $container . ' 2>&1');

        return $this->parseEntries($raw);
    }

    /**
     * Resolves a compose service name (e.g. "app-cron") to its running container name via
     * `docker compose ps --format json`. Returns '' if the service is not running.
     */
    private function resolveServiceContainer(SFTP $sftp, string $composeProject, string $service): string
    {
        $raw = (string) $sftp->exec(
            'docker compose --project-name ' . $composeProject
            . ' ps --status running --format json 2>/dev/null',
        );

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);
            if (! is_array($obj)) {
                continue;
            }

            $svc  = is_string($obj['Service'] ?? null) ? $obj['Service'] : '';
            $name = is_string($obj['Name'] ?? null) ? $obj['Name'] : '';

            if ($svc === $service && $name !== '') {
                return $name;
            }
        }

        return '';
    }

    /** @return list<array{time: string, level: string, logger: string, msg: string, raw: string}> */
    private function parseEntries(string $output): array
    {
        $entries = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);

            if (! is_array($obj)) {
                // Non-JSON output (e.g. plain stdout) is shown verbatim rather than dropped.
                $entries[] = ['time' => '', 'level' => 'info', 'logger' => '', 'msg' => $line, 'raw' => $line];

                continue;
            }

            // FrankenPHP logs use a numeric "ts"; supercronic (-json) uses an RFC3339 "time" string
            // and carries the job command instead of a logger name.
            $ts   = $obj['ts'] ?? null;
            $time = '';
            if (is_numeric($ts)) {
                $time = date('H:i:s', (int) $ts);
            } else {
                $rawTime = $obj['time'] ?? null;
                $parsed  = is_string($rawTime) ? strtotime($rawTime) : false;
                if ($parsed !== false) {
                    $time = date('H:i:s', $parsed);
                }
            }

            $logger = is_string($obj['logger'] ?? null) ? $obj['logger']
                : (is_string($obj['job.command'] ?? null) ? $obj['job.command'] : '');
            $msg    = is_string($obj['msg'] ?? null) ? $obj['msg'] : $line;

            $entries[] = [
                'time'   => $time,
                'level'  => strtolower(is_string($obj['level'] ?? null) ? $obj['level'] : 'info'),
                'logger' => $logger,
                'msg'    => $msg,
                'raw'    => $line,
            ];
        }

        return $entries;
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
