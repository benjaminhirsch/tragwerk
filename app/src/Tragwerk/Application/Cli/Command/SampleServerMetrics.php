<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Event\AppMetricsSampled;
use Tragwerk\Domain\Event\ServerMetricsSampled;
use Tragwerk\Domain\Repository\AppMetricRepository;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerMetricRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Infrastructure\Metrics\EnvironmentMetricsCollector;
use Tragwerk\Infrastructure\Metrics\MetricsCollector;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

use function assert;
use function count;
use function is_numeric;
use function max;
use function pcntl_async_signals;
use function pcntl_signal;
use function sleep;
use function sprintf;
use function str_contains;
use function trim;

use const SIGINT;
use const SIGTERM;

#[AsCommand(name: 'metrics:sample', description: 'Sample host + per-environment app metrics (ticker)')]
final class SampleServerMetrics extends Command
{
    private const int VERSION_REFRESH_CYCLES = 60;

    public function __construct(
        private readonly ServerRepository $servers,
        private readonly CredentialRepository $credentials,
        private readonly MetricsCollector $collector,
        private readonly EnvironmentMetricsCollector $appCollector,
        private readonly ServerMetricRepository $metrics,
        private readonly AppMetricRepository $appMetrics,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly RemoteShell $shell,
        private readonly int $retentionDays = 7,
        private readonly int $appRetentionDays = 30,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'interval',
            'i',
            InputOption::VALUE_REQUIRED,
            'Seconds between sampling cycles',
            60,
        );
        $this->addOption(
            'once',
            null,
            InputOption::VALUE_NONE,
            'Sample a single cycle and exit (for testing / cron)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $once        = (bool) $input->getOption('once');
        $intervalRaw = $input->getOption('interval');
        $interval    = max(1, is_numeric($intervalRaw) ? (int) $intervalRaw : 60);

        $stopping = false;
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static function () use (&$stopping): void {
            $stopping = true;
        });
        pcntl_signal(SIGINT, static function () use (&$stopping): void {
            $stopping = true;
        });

        $cycle = 0;

        do {
            $this->prune($output);
            $this->sampleAll($output);

            if ($cycle % self::VERSION_REFRESH_CYCLES === 0) {
                $this->refreshVersionsAll($output);
            }

            $cycle++;

            if ($once) {
                break;
            }

            for ($slept = 0; $slept < $interval && ! $stopping; $slept++) {
                sleep(1);
            }
        } while (! $stopping);

        return Command::SUCCESS;
    }

    private function sampleAll(OutputInterface $output): void
    {
        foreach ($this->servers->getAll() as $server) {
            assert($server instanceof Server);

            if ($server->credentialId === null) {
                continue;
            }

            try {
                $credential = $this->credentials->getById($server->credentialId);
                assert($credential instanceof Credential);
            } catch (Throwable $e) {
                $this->logger->error('Could not load credential for server ' . $server->name, [
                    'server_id' => $server->id->toString(),
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            $this->sampleHost($server, $credential, $output);
            $this->sampleApps($server, $credential, $output);
        }
    }

    private function sampleHost(Server $server, Credential $credential, OutputInterface $output): void
    {
        try {
            $sample = $this->collector->collect($server, $credential);
            $this->dispatcher->dispatch(new ServerMetricsSampled($sample));

            $output->writeln(sprintf(
                '<info>[metrics] %s: cpu=%.1f%% mem=%d/%d disk=%d/%d load=%.2f</info>',
                $server->name,
                $sample->cpuPercent,
                $sample->memUsedBytes,
                $sample->memTotalBytes,
                $sample->diskUsedBytes,
                $sample->diskTotalBytes,
                $sample->load1,
            ));
        } catch (Throwable $e) {
            $this->logger->error('Host metrics sampling failed for server ' . $server->name, [
                'server_id' => $server->id->toString(),
                'exception' => $e->getMessage(),
            ]);
            $output->writeln(sprintf('<error>[metrics] %s (host): %s</error>', $server->name, $e->getMessage()));
        }
    }

    private function sampleApps(Server $server, Credential $credential, OutputInterface $output): void
    {
        try {
            $envs = $this->appCollector->collectServer($server, $credential);
        } catch (Throwable $e) {
            $this->logger->error('App metrics sampling failed for server ' . $server->name, [
                'server_id' => $server->id->toString(),
                'exception' => $e->getMessage(),
            ]);
            $output->writeln(sprintf('<error>[metrics] %s (apps): %s</error>', $server->name, $e->getMessage()));

            return;
        }

        foreach ($envs as $env) {
            try {
                $this->dispatcher->dispatch(new AppMetricsSampled($env['projectId'], $env['branch'], $env['metrics']));
            } catch (Throwable $e) {
                $this->logger->error('Storing app metrics failed', [
                    'project_id' => $env['projectId'],
                    'branch'     => $env['branch'],
                    'exception'  => $e->getMessage(),
                ]);
            }
        }

        if ($envs === []) {
            return;
        }

        $output->writeln(sprintf(
            '<info>[metrics] %s: %d environment(s) sampled</info>',
            $server->name,
            count($envs),
        ));
    }

    private function refreshVersionsAll(OutputInterface $output): void
    {
        foreach ($this->servers->getAll() as $server) {
            assert($server instanceof Server);

            if ($server->credentialId === null) {
                continue;
            }

            try {
                $credential = $this->credentials->getById($server->credentialId);
                assert($credential instanceof Credential);
            } catch (Throwable $e) {
                $this->logger->error('Could not load credential for server ' . $server->name, [
                    'server_id' => $server->id->toString(),
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }

            $this->refreshVersions($server, $credential, $output);
        }
    }

    private function refreshVersions(Server $server, Credential $credential, OutputInterface $output): void
    {
        try {
            $dockerRaw  = $this->shell->run($server, $credential, 'docker --version 2>&1');
            $composeRaw = $this->shell->run($server, $credential, 'docker compose version 2>&1');

            $dockerVersion  = str_contains($dockerRaw, 'Docker version') ? trim($dockerRaw) : null;
            $composeVersion = str_contains($composeRaw, 'Docker Compose') ? trim($composeRaw) : null;

            $this->servers->updateVersions($server->id, $dockerVersion, $composeVersion);

            $output->writeln(sprintf(
                '<info>[versions] %s: docker=%s compose=%s</info>',
                $server->name,
                $dockerVersion ?? 'n/a',
                $composeVersion ?? 'n/a',
            ));
        } catch (Throwable $e) {
            $this->logger->error('Version refresh failed for server ' . $server->name, [
                'server_id' => $server->id->toString(),
                'exception' => $e->getMessage(),
            ]);
            $output->writeln(sprintf('<error>[versions] %s: %s</error>', $server->name, $e->getMessage()));
        }
    }

    private function prune(OutputInterface $output): void
    {
        try {
            $hostDeleted = $this->metrics->pruneOlderThan(
                new DateTimeImmutable(sprintf('-%d days', $this->retentionDays)),
            );
            $appDeleted  = $this->appMetrics->pruneOlderThan(
                new DateTimeImmutable(sprintf('-%d days', $this->appRetentionDays)),
            );

            if ($hostDeleted + $appDeleted > 0) {
                $output->writeln(sprintf(
                    '<comment>[metrics] pruned %d host + %d app sample(s)</comment>',
                    $hostDeleted,
                    $appDeleted,
                ));
            }
        } catch (Throwable $e) {
            $this->logger->error('Metrics pruning failed', ['exception' => $e->getMessage()]);
        }
    }
}
