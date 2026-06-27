<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Container;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SFTP;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;

use function array_values;
use function assert;
use function basename;
use function escapeshellarg;
use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function md5;
use function preg_replace;
use function str_replace;
use function strtolower;
use function substr;
use function trim;

final readonly class StatusHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private CredentialRepository $credentialRepository,
        private ServerRepository $serverRepository,
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $request->getAttribute('active_environment');
        assert(is_string($branch));

        // Cache the SSH result briefly. The pool holds a blocking lock per key, so
        // concurrent pollers (multiple tabs/users) wait for a single SSH call instead
        // of each opening their own connection.
        $item = $this->cache->getItem('containers_' . $project->id->toString() . '_' . md5($branch));

        if ($item->isHit()) {
            /** @var array{containers: list<array<string, mixed>>, error: string|null} $result */
            $result = $item->get();
        } else {
            try {
                $result = ['containers' => $this->fetchContainers($project, $branch), 'error' => null];
                $item->set($result)->expiresAfter(15);
            } catch (Throwable $e) {
                $result = ['containers' => [], 'error' => $e->getMessage()];
                $item->set($result)->expiresAfter(5);
            }

            $this->cache->save($item);
        }

        return $this->renderer->render($request, 'page::container/status', [
            'branch'     => $branch,
            'containers' => $result['containers'],
            'error'      => $result['error'],
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

        $remoteDir = 'tragwerk/' . $project->id->toString() . '/' . $branch;
        // Must match the compose project name the deploy uses: slugify(basename($branch)). Using the
        // full branch here turns "feat/cron-observability" into "featcron-observability" (slash
        // stripped), so `docker compose -p …` targets a non-existent project and only the
        // label-filtered standalone containers show up.
        $branchSlug     = $this->slugify(basename($branch));
        $shortId        = substr($project->id->toString(), 0, 8);
        $composeProject = 'tw-' . $shortId . '-' . $branchSlug;
        $labelFilter    = escapeshellarg('label=tragwerk.working_dir=/root/' . $remoteDir);
        $dc             = 'docker compose --project-name ' . escapeshellarg($composeProject);
        // Include restarting (not just running) containers so a crash-looping service — which is
        // not "running" at the poll moment — still shows up instead of silently vanishing.
        $raw    = $sftp->exec(
            'cd ~/' . $remoteDir . ' && ' . $dc . ' ps --status running --status restarting --format json 2>&1; '
            . 'docker ps --filter ' . $labelFilter
            . ' --filter status=running --filter status=restarting --format json 2>/dev/null',
        );
        $output = trim(is_string($raw) ? $raw : '');

        $containers = $this->parseContainers($output);

        $statsRaw = $sftp->exec("docker stats --no-stream --format '{{json .}}' 2>/dev/null");
        $stats    = $this->parseStats(trim(is_string($statsRaw) ? $statsRaw : ''));

        foreach ($containers as &$container) {
            $name = is_string($container['Name'] ?? null) ? $container['Name'] : '';
            $stat = $stats[$name] ?? null;

            $container['Cpu']      = $stat['cpu'] ?? null;
            $container['Mem']      = $stat['mem'] ?? null;
            $container['MemUsage'] = $stat['memUsage'] ?? '';
        }

        unset($container);

        return $containers;
    }

    /**
     * Parses `docker stats --format '{{json .}}'` output into a map keyed by container name.
     *
     * @return array<string, array{cpu: float|null, mem: float|null, memUsage: string}>
     */
    private function parseStats(string $output): array
    {
        if ($output === '') {
            return [];
        }

        $stats = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);

            if (! is_array($obj) || ! isset($obj['Name']) || ! is_string($obj['Name'])) {
                continue;
            }

            $stats[$obj['Name']] = [
                'cpu'      => $this->toPercent($obj['CPUPerc'] ?? null),
                'mem'      => $this->toPercent($obj['MemPerc'] ?? null),
                'memUsage' => is_string($obj['MemUsage'] ?? null) ? $obj['MemUsage'] : '',
            ];
        }

        return $stats;
    }

    /** Parses a docker stats percentage like "0.50%" into a float, or null if not numeric. */
    private function toPercent(mixed $value): float|null
    {
        if (! is_string($value)) {
            return null;
        }

        $value = str_replace('%', '', trim($value));

        return is_numeric($value) ? (float) $value : null;
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

            $name = $obj['Name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            // The compose `ps` and the label-filtered `docker ps` can return the same container;
            // key by name so it appears once.
            $containers[$name] = $obj;
        }

        return array_values($containers);
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
