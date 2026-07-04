<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Credential;

use Tragwerk\Application\Service\Credential\CredentialEncryptor;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Enum\CredentialPrivilege;
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

        $credential->name      = $event->credential->name;
        $credential->username  = $event->credential->username;
        $credential->privilege = CredentialPrivilege::from($event->credential->privilege);

        // A blank key field means "keep the existing (encrypted) key"; only re-encrypt
        // when the user submitted a new one. Store it verbatim, like the create path.
        if ($event->credential->hasNewPrivateKey()) {
            assert($event->credential->privateKey !== null);
            $credential->privateKey = $this->credentialEncryptor->encrypt($event->credential->privateKey);
        }

        $credential->updatedAt = TimestampImmutable::now();
        $credential->updatedBy = $event->updatedBy;

        $this->credentialRepository->update($credential);
    }
}
