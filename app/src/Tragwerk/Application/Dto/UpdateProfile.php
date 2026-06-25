<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;

use function _;
use function filter_var;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class UpdateProfile implements DtoInterface
{
    public string $firstname;
    public string $lastname;
    public string $email;

    public function __construct(
        #[FromBody]
        string $firstname,
        #[FromBody]
        string $lastname,
        #[FromBody]
        string $email,
    ) {
        $this->firstname = trim($firstname);
        $this->lastname  = trim($lastname);
        $this->email     = trim($email);

        $errors = [];
        if ($this->firstname === '') {
            $errors[] = ValidationError::make('firstname', _('First name is required'));
        }

        if ($this->lastname === '') {
            $errors[] = ValidationError::make('lastname', _('Last name is required'));
        }

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = ValidationError::make('email', _('Please enter a valid email address'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }
}
