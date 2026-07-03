<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Credential;

use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\CredentialRepository;

final readonly class CreateCredential
{
    public function __construct(
        private CredentialRepository $credentialRepository,
        private CredentialEncryptor $credentialEncryptor,
    ) {
    }

    public function __invoke(Event\CredentialCreated $event): void
    {
        $credential = $event->credential->createCredential(
            $event->createdBy,
            $event->teamId,
            $event->credentialId,
        );

        if ($credential->privateKey !== null) {
            $credential->privateKey = $this->credentialEncryptor->encrypt($credential->privateKey);
        }

        $this->credentialRepository->create($credential);
    }
}
