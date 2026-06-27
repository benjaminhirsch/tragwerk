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
use Tragwerk\Domain\Event\CronRunsCollected;
use Tragwerk\Domain\Model\CronRun;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\CronRunRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Infrastructure\Cron\CronRunCollector;
use Tragwerk\Infrastructure\Mercure\MercurePublisher;

use function assert;
use function count;
use function function_exists;
use function is_numeric;
use function max;
use function pcntl_async_signals;
use function pcntl_signal;
use function sleep;
use function sprintf;

use const SIGINT;
use const SIGTERM;

/**
 * Ingests cron run history from every host's `{app}-cron` sidecar logs (ticker), persists it via the
 * {@see CronRunsCollected} event, and notifies browsers over Mercure so open cron views refresh.
 * Run alongside metrics:sample and the queue worker.
 */
#[AsCommand(name: 'cron:sample', description: 'Ingest cron run history from app cron sidecars (ticker)')]
final class SampleCronRuns extends Command
{
    public function __construct(
        private readonly ServerRepository $servers,
        private readonly CredentialRepository $credentials,
        private readonly CronRunCollector $collector,
        private readonly CronRunRepository $repository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MercurePublisher $mercure,
        private readonly LoggerInterface $logger,
        private readonly int $retentionDays = 30,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('interval', 'i', InputOption::VALUE_REQUIRED, 'Seconds between sampling cycles', 60);
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Sample a single cycle and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $once        = (bool) $input->getOption('once');
        $intervalRaw = $input->getOption('interval');
        $interval    = max(1, is_numeric($intervalRaw) ? (int) $intervalRaw : 60);

        $stopping = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, static function () use (&$stopping): void {
                $stopping = true;
            });
            pcntl_signal(SIGINT, static function () use (&$stopping): void {
                $stopping = true;
            });
        }

        do {
            $this->prune($output);
            // Read a window slightly larger than the interval so no runs slip between cycles; the
            // repository upsert keys make overlapping reads idempotent.
            $this->sampleAll($interval + 30, $output);

            if ($once) {
                break;
            }

            for ($slept = 0; $slept < $interval && ! $stopping; $slept++) {
                sleep(1);
            }
        } while (! $stopping);

        return Command::SUCCESS;
    }

    private function sampleAll(int $sinceSeconds, OutputInterface $output): void
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

            $this->sampleServer($server, $credential, $sinceSeconds, $output);
        }
    }

    private function sampleServer(
        Server $server,
        Credential $credential,
        int $sinceSeconds,
        OutputInterface $output,
    ): void {
        try {
            $runs = $this->collector->collectServer($server, $credential, $sinceSeconds);
        } catch (Throwable $e) {
            $this->logger->error('Cron run sampling failed for server ' . $server->name, [
                'server_id' => $server->id->toString(),
                'exception' => $e->getMessage(),
            ]);
            $output->writeln(sprintf('<error>[cron] %s: %s</error>', $server->name, $e->getMessage()));

            return;
        }

        if ($runs === []) {
            return;
        }

        $this->dispatcher->dispatch(new CronRunsCollected($runs));
        $this->notify($runs);

        $output->writeln(sprintf('<info>[cron] %s: %d run(s) ingested</info>', $server->name, count($runs)));
    }

    /** @param list<CronRun> $runs */
    private function notify(array $runs): void
    {
        $seen = [];
        foreach ($runs as $run) {
            $env = $run->projectId . '|' . $run->branch;
            if (isset($seen[$env])) {
                continue;
            }

            $seen[$env] = true;

            try {
                $this->mercure->publish(
                    $this->mercure->topic('/project/' . $run->projectId . '/cron/' . $run->branch),
                    ['type' => 'update'],
                );
            } catch (Throwable $e) {
                $this->logger->error('Cron Mercure publish failed', ['exception' => $e->getMessage()]);
            }
        }
    }

    private function prune(OutputInterface $output): void
    {
        try {
            $deleted = $this->repository->pruneOlderThan(
                new DateTimeImmutable(sprintf('-%d days', $this->retentionDays)),
            );

            if ($deleted > 0) {
                $output->writeln(sprintf('<comment>[cron] pruned %d run(s)</comment>', $deleted));
            }
        } catch (Throwable $e) {
            $this->logger->error('Cron run pruning failed', ['exception' => $e->getMessage()]);
        }
    }
}
