<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Credential;

use CuyZ\Valinor\Mapper\Http\FromBody;
use phpseclib3\Crypt\PublicKeyLoader;
use Throwable;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\Credential as CredentialEntity;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function trim;

final readonly class Credential implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        #[FromBody]
        public string $username,
        #[FromBody]
        public string|null $privateKey = null,
    ) {
        $errors            = [];
        $emptyFieldMessage = _('Field can\'t be empty');

        if (trim($this->name) === '') {
            $errors[] = ValidationError::make('name', $emptyFieldMessage);
        }

        if (trim($this->username) === '') {
            $errors[] = ValidationError::make('username', $emptyFieldMessage);
        }

        $key = trim($this->privateKey ?? '');

        if ($key === '') {
            $errors[] = ValidationError::make('privateKey', _('An SSH private key is required'));
        } elseif (! self::isValidSshPrivateKey($key)) {
            $errors[] = ValidationError::make('privateKey', _('Invalid SSH private key. Must be a PEM-encoded private key.')); //phpcs:ignore
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    private static function isValidSshPrivateKey(string $key): bool
    {
        try {
            PublicKeyLoader::loadPrivateKey($key);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function createCredential(
        UserIdentifier $createdBy,
        TeamIdentifier $teamId,
        CredentialIdentifier $id,
    ): CredentialEntity {
        $now = TimestampImmutable::now();

        return new CredentialEntity(
            $id,
            $this->name,
            $this->username,
            $this->privateKey === '' ? null : $this->privateKey,
            $teamId,
            $now,
            $createdBy,
            $now,
            $createdBy,
        );
    }
}
