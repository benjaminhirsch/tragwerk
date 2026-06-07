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

final readonly class PruneAllRegistryImages
{
    public function __construct(
        private RegistryRepository $registryRepository,
        private RegistryPruner $pruner,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Message\PruneAllRegistryImages $message): void
    {
        try {
            $registry = $this->registryRepository->getById(
                RegistryIdentifier::fromString($message->registryId),
            );
        } catch (Throwable $e) {
            $this->logger->warning('Registry not found for pruneAll', ['registry_id' => $message->registryId]);

            return;
        }

        try {
            $deleted = $this->pruner->pruneAll($registry, $message->prefixes);

            if (count($deleted) > 0) {
                $this->logger->info('Pruned all registry images for removed project/branch', [
                    'registry' => $registry->name,
                    'deleted'  => count($deleted),
                    'tags'     => implode(', ', $deleted),
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Registry pruneAll failed', [
                'registry' => $registry->name,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
