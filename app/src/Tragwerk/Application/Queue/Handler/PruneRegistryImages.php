<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use Throwable;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Service\RegistryPruner;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;

use function count;
use function implode;

final readonly class PruneRegistryImages
{
    public function __construct(
        private RegistryRepository $registryRepository,
        private RegistryPruner $pruner,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Message\PruneRegistryImages $message): void
    {
        try {
            $registry = $this->registryRepository->getById(
                RegistryIdentifier::fromString($message->registryId),
            );
        } catch (Throwable $e) {
            $this->logger->warning('Registry not found for pruning', ['registry_id' => $message->registryId]);

            return;
        }

        if (! $registry->pruningEnabled) {
            return;
        }

        try {
            $deleted = $this->pruner->prune($registry, $message->appSlug, $message->branchSlug);

            if (count($deleted) > 0) {
                $this->logger->info('Pruned registry images', [
                    'registry'  => $registry->name,
                    'deleted'   => count($deleted),
                    'tags'      => implode(', ', $deleted),
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Registry pruning failed', [
                'registry'  => $registry->name,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
