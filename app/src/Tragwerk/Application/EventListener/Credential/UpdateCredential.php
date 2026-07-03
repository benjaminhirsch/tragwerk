<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Credential;

use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;

final readonly class UpdateCredential
{
    public function __construct(
        private CredentialRepository $credentialRepository,
        private CredentialEncryptor $credentialEncryptor,
    ) {
    }

    public function __invoke(Event\CredentialUpdated $event): void
    {
        $credential = $this->credentialRepository->getById($event->credentialId);
        assert($credential instanceof Credential);

        $plainKey = $event->credential->privateKey === '' ? null : $event->credential->privateKey;

        $credential->name       = $event->credential->name;
        $credential->username   = $event->credential->username;
        $credential->privateKey = $plainKey === null ? null : $this->credentialEncryptor->encrypt($plainKey);
        $credential->updatedAt  = TimestampImmutable::now();
        $credential->updatedBy  = $event->updatedBy;

        $this->credentialRepository->update($credential);
    }
}
