<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Project;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function trim;

final readonly class ProjectCreation implements DtoInterface
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

    public function createProject(
        ProjectIdentifier $id,
        TeamIdentifier $teamId,
        UserIdentifier $createdBy,
    ): Project {
        $now = TimestampImmutable::now();

        return new Project(
            $id,
            $this->name,
            ServerIdentifier::fromString($this->serverId),
            $teamId,
            $now,
            $createdBy,
            $now,
            $createdBy,
        );
    }
}
