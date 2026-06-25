<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use SensitiveParameter;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function _;
use function sprintf;
use function strlen;

final readonly class ChangePassword implements DtoInterface
{
    private const int PASSWORD_MINIMUM_LENGTH = 8;

    public function __construct(
        #[FromBody]
        #[SensitiveParameter]
        public string $currentPassword,
        #[FromBody]
        #[SensitiveParameter]
        public string $newPassword,
        #[FromBody]
        #[SensitiveParameter]
        public string $confirmPassword,
    ) {
        $errors = [];
        if ($this->currentPassword === '') {
            $errors[] = ValidationError::make('currentPassword', _('Enter your current password'));
        }

        if (strlen($this->newPassword) < self::PASSWORD_MINIMUM_LENGTH) {
            $errors[] = ValidationError::make(
                'newPassword',
                sprintf(
                    _('Password does not met the required minimum length of %d characters'),
                    self::PASSWORD_MINIMUM_LENGTH,
                ),
            );
        }

        if ($this->newPassword !== $this->confirmPassword) {
            $errors[] = ValidationError::make('confirmPassword', _('Passwords are not identical'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }
}
