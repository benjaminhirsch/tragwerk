<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Credential;

use CuyZ\Valinor\Mapper\Http\FromBody;
use phpseclib3\Crypt\PublicKeyLoader;
use Throwable;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function _;
use function trim;

/**
 * Edit variant of the credential form.
 *
 * Unlike {@see Credential} (create), the private key is optional: leaving it blank
 * keeps the existing stored key. If a key is provided it must be a valid PEM key.
 */
final readonly class EditCredential implements DtoInterface
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

        if ($key !== '' && ! self::isValidSshPrivateKey($key)) {
            $errors[] = ValidationError::make('privateKey', _('Invalid SSH private key. Must be a PEM-encoded private key.')); //phpcs:ignore
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function hasNewPrivateKey(): bool
    {
        return trim($this->privateKey ?? '') !== '';
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
}
