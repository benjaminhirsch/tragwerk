<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\SshKey;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\SshKeyRepository;

final readonly class PersistSshKey
{
    public function __construct(
        private SshKeyRepository $sshKeyRepository,
    ) {
    }

    public function __invoke(Event\SshKeyCreated $event): void
    {
        $this->sshKeyRepository->create($event->key);
    }
}
