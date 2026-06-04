<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function _;
use function sprintf;
use function strlen;

final readonly class PasswordResetApply implements DtoInterface
{
    private const int PASSWORD_MINIMUM_LENGTH = 8;

    public function __construct(
        #[FromBody]
        public string $password1,
        #[FromBody]
        public string $password2,
    ) {
        $errors = [];
        if ($this->password1 !== $this->password2) {
            $errors[] = ValidationError::make('password2', _('Passwords are not identical'));
        }

        if (strlen($this->password1) < self::PASSWORD_MINIMUM_LENGTH) {
            $errors[] = ValidationError::make(
                'password1',
                sprintf(
                    _('Password does not met the required minimum length of %d characters'),
                    self::PASSWORD_MINIMUM_LENGTH,
                ),
            );
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }
}
