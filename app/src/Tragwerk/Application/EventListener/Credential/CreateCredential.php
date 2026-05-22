<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Credential;

use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\CredentialRepository;

final readonly class CreateCredential
{
    public function __construct(private CredentialRepository $credentialRepository)
    {
    }

    public function __invoke(Event\CredentialCreated $event): void
    {
        $this->credentialRepository->create($event->credential->createCredential(
            $event->createdBy,
            $event->teamId,
        ));
    }
}
