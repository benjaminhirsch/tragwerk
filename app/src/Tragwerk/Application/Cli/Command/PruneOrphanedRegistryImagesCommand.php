<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Repository\RegistryPrefixRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Service\RegistryPruner;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;

use function count;
use function implode;
use function is_string;
use function sprintf;

#[AsCommand(name: 'registry:prune-orphaned', description: 'Delete registry images whose branch no longer exists')]
final class PruneOrphanedRegistryImagesCommand extends Command
{
    public function __construct(
        private readonly RegistryRepository $registryRepository,
        private readonly RegistryPrefixRepository $registryPrefixRepository,
        private readonly RegistryPruner $pruner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('registry-id', null, InputOption::VALUE_OPTIONAL, 'Limit to one registry UUID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $registryId = $input->getOption('registry-id');
        $dryRun     = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $output->writeln('<comment>Dry-run mode — nothing will be deleted.</comment>');
        }

        $registries = $this->getRegistries($registryId);

        foreach ($registries as $registry) {
            $this->pruneRegistry($registry, $dryRun, $output);
        }

        return Command::SUCCESS;
    }

    /** @return list<Registry> */
    private function getRegistries(mixed $registryId): array
    {
        if (is_string($registryId)) {
            try {
                $registry = $this->registryRepository->getById(
                    RegistryIdentifier::fromString($registryId),
                );

                return [$registry];
            } catch (Throwable) {
                return [];
            }
        }

        $result = [];
        foreach ($this->registryRepository->getAll() as $registry) {
            $result[] = $registry;
        }

        return $result;
    }

    private function pruneRegistry(Registry $registry, bool $dryRun, OutputInterface $output): void
    {
        $rows           = $this->registryPrefixRepository->findByRegistry($registry->id);
        $activePrefixes = [];
        foreach ($rows as $row) {
            $activePrefixes[] = $row['app_slug'] . '-' . $row['branch_slug'] . '-';
        }

        if ($dryRun) {
            $output->writeln(sprintf(
                'Registry <info>%s</info>: %d active prefix(es): %s',
                $registry->name,
                count($activePrefixes),
                count($activePrefixes) > 0 ? implode(', ', $activePrefixes) : '(none)',
            ));
            $output->writeln('  Skipping deletion (dry-run).');

            return;
        }

        try {
            $deleted = $this->pruner->pruneOrphaned($registry, $activePrefixes);

            if (count($deleted) > 0) {
                $output->writeln(sprintf(
                    'Registry <info>%s</info>: deleted %d orphaned tag(s): %s',
                    $registry->name,
                    count($deleted),
                    implode(', ', $deleted),
                ));
                $this->logger->info('Pruned orphaned registry images', [
                    'registry' => $registry->name,
                    'deleted'  => count($deleted),
                    'tags'     => implode(', ', $deleted),
                ]);
            } else {
                $output->writeln(sprintf('Registry <info>%s</info>: nothing to prune.', $registry->name));
            }
        } catch (Throwable $e) {
            $output->writeln(sprintf(
                '<error>Registry %s: pruning failed — %s</error>',
                $registry->name,
                $e->getMessage(),
            ));
            $this->logger->warning('Orphaned registry pruning failed', [
                'registry' => $registry->name,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
