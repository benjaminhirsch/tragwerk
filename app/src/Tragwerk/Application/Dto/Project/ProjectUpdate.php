<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Project;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

use function _;
use function trim;

final readonly class ProjectUpdate implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        #[FromBody]
        public string $serverId,
    ) {
        $errors = [];
        if (trim($this->name) === '') {
            $errors[] = ValidationError::make('name', _('Field can\'t be empty'));
        }

        if (trim($this->serverId) === '' || ! ServerIdentifier::isValid($this->serverId)) {
            $errors[] = ValidationError::make('serverId', _('Please select a server'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }
}
