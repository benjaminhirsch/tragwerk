<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Variable;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function _;
use function preg_match;
use function trim;

final readonly class VariableCreation implements DtoInterface
{
    public bool $isSecret;

    public bool $isInherited;

    public function __construct(
        #[FromBody]
        public string $key,
        #[FromBody]
        public string $value,
        #[FromBody]
        string|null $isSecret = null,
        #[FromBody]
        string|null $isInherited = null,
    ) {
        $this->isSecret    = $isSecret !== null;
        $this->isInherited = $isInherited !== null;

        $errors = [];

        if (trim($this->key) === '') {
            $errors[] = ValidationError::make('key', _('Field can\'t be empty'));
        } elseif (preg_match('/^[A-Z][A-Z0-9_]*$/', $this->key) !== 1) {
            $errors[] = ValidationError::make(
                'key',
                _('Key must start with an uppercase letter and contain only uppercase letters, digits and underscores'),
            );
        }

        if (trim($this->value) === '') {
            $errors[] = ValidationError::make('value', _('Field can\'t be empty'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function createEnvVar(EnvVarIdentifier $id, ProjectIdentifier $projectId, string $branch): EnvVar
    {
        $now = TimestampImmutable::now();

        return new EnvVar(
            $id,
            $projectId,
            $branch,
            $this->key,
            $this->value,
            $this->isSecret,
            $this->isInherited,
            $now,
            $now,
        );
    }
}
