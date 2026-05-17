<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Project;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function sprintf;
use function strlen;

final readonly class InviteRegistration implements DtoInterface
{
    private const int PASSWORD_MINIMUM_LENGTH = 8;

    public function __construct(
        #[FromBody]
        public string $firstname,
        #[FromBody]
        public string $lastname,
        #[FromBody]
        public string $password1,
        #[FromBody]
        public string $password2,
    ) {
        if ($this->password1 !== $this->password2) {
            throw ValidationError::make('password1', _('Passwords are not identical'));
        }

        if (strlen($this->password1) < self::PASSWORD_MINIMUM_LENGTH) {
            throw ValidationError::make(
                'password1',
                sprintf(_('Password must be at least %d characters'), self::PASSWORD_MINIMUM_LENGTH),
            );
        }
    }

    public function createUser(string $email): User
    {
        $now = TimestampImmutable::now();

        return new User(
            UserIdentifier::create(),
            $email,
            $this->firstname,
            $this->lastname,
            PasswordHash::create($this->password1),
            $now,
            $now,
        );
    }
}
