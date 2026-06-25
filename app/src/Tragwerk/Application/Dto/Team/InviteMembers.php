<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Team;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function _;
use function filter_var;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class InviteMembers implements DtoInterface
{
    public function __construct(
        /** @var string[] */
        #[FromBody]
        public array $emailsToInvite = [],
        /** @var string[] */
        #[FromBody]
        public array $rolesToInvite = [],
    ) {
        $errors = [];

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
}
