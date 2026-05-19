<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Credential;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\Credential as CredentialEntity;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function base64_decode;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function preg_split;
use function sprintf;
use function strlen;
use function substr;
use function trim;
use function unpack;

final readonly class Credential implements DtoInterface
{
    private const array ALLOWED_SSH_ALGORITHMS = [
        'ssh-rsa',
        'ssh-ed25519',
        'ecdsa-sha2-nistp256',
        'ecdsa-sha2-nistp384',
        'ecdsa-sha2-nistp521',
        'ssh-dss',
    ];

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
            $errors[] = ValidationError::make('privateKey', _('An SSH public key is required'));
        } elseif (! self::isValidSshPublicKey($key)) {
            $errors[] = ValidationError::make('privateKey', sprintf(
                _('Invalid SSH public key. Valid formats: %s'),
                implode(', ', self::ALLOWED_SSH_ALGORITHMS),
            ));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    private static function isValidSshPublicKey(string $key): bool
    {
        $parts = preg_split('/\s+/', $key, 3);
        if ($parts === false || count($parts) < 2) {
            return false;
        }

        $algorithm = $parts[0];
        $encoded   = $parts[1];

        if (! in_array($algorithm, self::ALLOWED_SSH_ALGORITHMS, true)) {
            return false;
        }

        $blob = base64_decode($encoded, strict: true);
        if ($blob === false || strlen($blob) < 4) {
            return false;
        }

        $unpacked = unpack('N', substr($blob, 0, 4));
        if (! is_array($unpacked) || ! isset($unpacked[1]) || ! is_int($unpacked[1])) {
            return false;
        }

        $typeLen = $unpacked[1];
        if ($typeLen > strlen($blob) - 4) {
            return false;
        }

        return substr($blob, 4, $typeLen) === $algorithm;
    }

    public function createCredential(UserIdentifier $createdBy, ProjectIdentifier $projectId): CredentialEntity
    {
        $now = TimestampImmutable::now();

        return new CredentialEntity(
            CredentialIdentifier::create(),
            $this->name,
            $this->username,
            $this->privateKey === '' ? null : $this->privateKey,
            $projectId,
            $now,
            $createdBy,
            $now,
            $createdBy,
        );
    }
}
