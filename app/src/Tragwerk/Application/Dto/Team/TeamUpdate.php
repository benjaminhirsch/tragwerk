<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Team;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Enum\TeamRole;

use function _;
use function filter_var;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class TeamUpdate implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        /** @var string[] */
        #[FromBody]
        public array $emailsToInvite = [],
        /** @var string[] */
        #[FromBody]
        public array $usersToRemove = [],
        /** @var string[] */
        #[FromBody]
        public array $rolesToInvite = [],
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

    /** Role chosen for the invite at the given index; defaults to Member and never returns Owner. */
    public function roleAt(int $index): TeamRole
    {
        return TeamRoleSelection::fromArray($this->rolesToInvite, $index);
    }
}
