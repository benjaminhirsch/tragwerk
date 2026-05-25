<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Credential;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\CredentialRepository;

final readonly class DeleteCredential
{
    public function __construct(
        private CredentialRepository $credentialRepository,
    ) {
    }

    public function __invoke(Event\CredentialDeleted $event): void
    {
        $this->credentialRepository->delete($event->credentialId);
    }
}
