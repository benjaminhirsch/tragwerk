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
use Tragwerk\Domain\Event\ServerMetricsSampled;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerMetricRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Infrastructure\Metrics\MetricsCollector;

use function assert;
use function is_numeric;
use function max;
use function pcntl_async_signals;
use function pcntl_signal;
use function sleep;
use function sprintf;

use const SIGINT;
use const SIGTERM;

#[AsCommand(name: 'metrics:sample', description: 'Sample host metrics from all servers (ticker)')]
final class SampleServerMetrics extends Command
{
    public function __construct(
        private readonly ServerRepository $servers,
        private readonly CredentialRepository $credentials,
        private readonly MetricsCollector $collector,
        private readonly ServerMetricRepository $metrics,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly int $retentionDays = 7,
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

        do {
            $this->prune($output);
            $this->sampleAll($output);

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
                $this->logger->error('Metrics sampling failed for server ' . $server->name, [
                    'server_id' => $server->id->toString(),
                    'exception' => $e->getMessage(),
                ]);
                $output->writeln(sprintf('<error>[metrics] %s: %s</error>', $server->name, $e->getMessage()));
            }
        }
    }

    private function prune(OutputInterface $output): void
    {
        try {
            $threshold = new DateTimeImmutable(sprintf('-%d days', $this->retentionDays));
            $deleted   = $this->metrics->pruneOlderThan($threshold);

            if ($deleted > 0) {
                $output->writeln(sprintf('<comment>[metrics] pruned %d old sample(s)</comment>', $deleted));
            }
        } catch (Throwable $e) {
            $this->logger->error('Metrics pruning failed', ['exception' => $e->getMessage()]);
        }
    }
}
