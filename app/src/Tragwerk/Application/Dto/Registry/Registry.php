<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Registry;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\Registry as RegistryEntity;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function trim;

final readonly class Registry implements DtoInterface
{
    public function __construct(
        #[FromBody]
        public string $name,
        #[FromBody]
        public string $url,
        #[FromBody]
        public string $username,
        #[FromBody]
        public string $password,
    ) {
        $errors = [];
        $empty  = _('Field can\'t be empty');

        if (trim($this->name) === '') {
            $errors[] = ValidationError::make('name', $empty);
        }

        if (trim($this->url) === '') {
            $errors[] = ValidationError::make('url', $empty);
        }

        if (trim($this->username) === '') {
            $errors[] = ValidationError::make('username', $empty);
        }

        if (trim($this->password) === '') {
            $errors[] = ValidationError::make('password', $empty);
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function createRegistry(
        UserIdentifier $createdBy,
        TeamIdentifier $teamId,
        RegistryIdentifier $id,
    ): RegistryEntity {
        $now = TimestampImmutable::now();

        return new RegistryEntity(
            $id,
            $this->name,
            $this->url,
            $this->username,
            $this->password,
            $teamId,
            $now,
            $createdBy,
            $now,
            $createdBy,
        );
    }
}
