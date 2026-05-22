<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Team;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function filter_var;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class TeamCreation implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        /** @var string[] */
        #[FromBody]
        public array $emailsToInvite = [],
    ) {
        $errors = [];
        if (trim($this->name) === '') {
            $errors[] = ValidationError::make('name', _('Field can\'t be empty'));
        }

        foreach ($this->emailsToInvite as $i => $email) {
            $trimmed = trim($email);
            if ($trimmed === '') {
                continue;
            }

            if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) !== false) {
                continue;
            }

            $errors[] = ValidationError::make('emailsToInvite[' . $i . ']', _('Invalid email address'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function createTeam(UserIdentifier $owner): Team
    {
        $now = TimestampImmutable::now();

        return new Team(
            TeamIdentifier::create(),
            $this->name,
            $owner,
            $now,
            $owner,
            $now,
            $owner,
        );
    }
}
