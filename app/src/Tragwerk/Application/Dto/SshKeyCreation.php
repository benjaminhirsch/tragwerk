<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Exception\NoKeyLoadedException;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function trim;

final readonly class SshKeyCreation implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        #[FromBody]
        public string $publicKey,
    ) {
        if (trim($this->name) === '') {
            throw ValidationError::make('name', _('Name is required'));
        }

        if (! $this->isValidPublicKey($this->publicKey)) {
            throw ValidationError::make('publicKey', _('Invalid SSH public key'));
        }
    }

    public function createKey(SshKeyIdentifier $id, UserIdentifier $userId): SshKey
    {
        return new SshKey(
            $id,
            $userId,
            trim($this->name),
            trim($this->publicKey),
            TimestampImmutable::now(),
        );
    }

    private function isValidPublicKey(string $key): bool
    {
        try {
            PublicKeyLoader::loadPublicKey(trim($key));

            return true;
        } catch (NoKeyLoadedException) {
            return false;
        }
    }
}
