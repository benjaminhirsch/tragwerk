<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Exception\Validation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function sprintf;
use function strlen;

final readonly class UserRegistration implements DtoInterface
{
    private const int PASSWORD_MINIMUM_LENGTH = 8;

    public function __construct(
        #[FromBody]
        public string $firstname,
        #[FromBody]
        public string $lastname,
        #[FromBody]
        public string $email,
        #[FromBody]
        public string $password1,
        #[FromBody]
        public string $password2,
    ) {
        if (! $this->passwordsAreIdentical()) {
            throw Validation::make('password1', _('Passwords are not identical'));
        }

        if (! $this->passwordHasMiniumLength()) {
            throw Validation::make(
                'password1',
                sprintf(
                    _('Password does not met the required minimum length of %d characters'),
                    self::PASSWORD_MINIMUM_LENGTH,
                ),
            );
        }
    }

    private function passwordsAreIdentical(): bool
    {
        return $this->password1 === $this->password2;
    }

    private function passwordHasMiniumLength(): bool
    {
        return strlen($this->password1) >= 8;
    }

    public function createUser(): User
    {
        $now = TimestampImmutable::now();

        return new User(
            UserIdentifier::create(),
            $this->email,
            $this->firstname,
            $this->lastname,
            null,
            PasswordHash::create($this->password1),
            $now,
            $now,
        );
    }
}
