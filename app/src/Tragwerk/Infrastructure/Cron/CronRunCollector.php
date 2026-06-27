<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Cron;

use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Model\CronRun;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

use function array_slice;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;

/**
 * Discovers every running `{app}-cron` sidecar on a host (one SSH connection) and reconstructs its
 * recent cron runs from the supercronic JSON logs. Job names/schedules are enriched from the
 * project's config.xml so the UI can show meaningful labels. Mirrors the metrics collector's
 * per-host discovery over a single SSH connection.
 */
final readonly class CronRunCollector
{
    public function __construct(
        private RemoteShell $shell,
        private SupercronicLogParser $parser,
        private ProjectConfigLoader $configLoader,
    ) {
    }

    /** @return list<CronRun> */
    public function collectServer(Server $server, Credential $credential, int $sinceSeconds): array
    {
        $script = sprintf(
            <<<'SH'
            SL=com.docker.compose.service
            CL=com.docker.compose.project.working_dir
            TL=tragwerk.working_dir
            for c in $(docker ps -q 2>/dev/null); do
              svc=$(docker inspect -f "{{ index .Config.Labels \"$SL\" }}" "$c" 2>/dev/null)
              case "$svc" in *-cron) ;; *) continue ;; esac
              wd=$(docker inspect -f "{{ index .Config.Labels \"$CL\" }}" "$c" 2>/dev/null)
              if [ -z "$wd" ]; then
                wd=$(docker inspect -f "{{ index .Config.Labels \"$TL\" }}" "$c" 2>/dev/null)
              fi
              case "$wd" in */tragwerk/*/*) ;; *) continue ;; esac
              echo "===CRON $wd $svc"
              docker logs --since %ds "$c" 2>&1
            done
            SH,
            $sinceSeconds,
        );

        return $this->parseServer($this->shell->run($server, $credential, $script));
    }

    /** @return list<CronRun> */
    public function parseServer(string $output): array
    {
        /** @var array<string, array{wd: string, svc: string, logs: string}> $blocks */
        $blocks = [];
        $cursor = null;

        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, '===CRON ')) {
                $rest              = trim(substr($line, 8));
                $sep               = strpos($rest, ' ');
                $wd                = $sep === false ? $rest : substr($rest, 0, $sep);
                $svc               = $sep === false ? '' : trim(substr($rest, $sep + 1));
                $cursor            = $wd . "\0" . $svc;
                $blocks[$cursor] ??= ['wd' => $wd, 'svc' => $svc, 'logs' => ''];

                continue;
            }

            if ($cursor === null) {
                continue;
            }

            $blocks[$cursor]['logs'] .= $line . "\n";
        }

        $runs = [];
        foreach ($blocks as $block) {
            $env = $this->parseWorkingDir($block['wd']);
            if ($env === null || ! str_ends_with($block['svc'], '-cron')) {
                continue;
            }

            [$projectId, $branch] = $env;
            $appSlug              = substr($block['svc'], 0, -5); // strip "-cron"
            $jobMap               = $this->jobMap($projectId, $branch);

            foreach ($this->parser->parse($block['logs']) as $parsed) {
                $key      = trim($parsed->command);
                $jobName  = $jobMap[$key]['name'] ?? $key;
                $schedule = $jobMap[$key]['schedule'] ?? $parsed->schedule;

                $runs[] = new CronRun(
                    projectId:  $projectId,
                    branch:     $branch,
                    appSlug:    $appSlug,
                    jobName:    $jobName,
                    command:    $parsed->command,
                    schedule:   $schedule,
                    startedAt:  $parsed->startedAt,
                    finishedAt: $parsed->finishedAt,
                    succeeded:  $parsed->succeeded,
                    output:     $parsed->output,
                );
            }
        }

        return $runs;
    }

    /**
     * Maps a cron command → {name, schedule} from the project's config.xml.
     *
     * @return array<string, array{name: string, schedule: string|null}>
     */
    private function jobMap(string $projectId, string $branch): array
    {
        if (! ProjectIdentifier::isValid($projectId)) {
            return [];
        }

        $config = $this->configLoader->load(ProjectIdentifier::fromString($projectId), $branch);
        if ($config === null) {
            return [];
        }

        $map = [];
        foreach ($config->applications as $app) {
            foreach ($app->crons as $cron) {
                $map[trim($cron->command)] = ['name' => $cron->name, 'schedule' => $cron->schedule];
            }
        }

        return $map;
    }

    /** @return array{string, string}|null [projectId, branch] from a .../tragwerk/{id}/{branch} path */
    private function parseWorkingDir(string $dir): array|null
    {
        $pos = strpos($dir, 'tragwerk/');
        if ($pos === false) {
            return null;
        }

        $segments = explode('/', trim(substr($dir, $pos + 9), '/'));
        if (count($segments) < 2 || $segments[0] === '' || $segments[1] === '') {
            return null;
        }

        return [$segments[0], implode('/', array_slice($segments, 1))];
    }
}
