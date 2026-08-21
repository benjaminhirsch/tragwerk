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
use function strtoupper;
use function trim;

final readonly class VariableCreation implements DtoInterface
{
    public const string SCOPE_PROJECT     = 'project';
    public const string SCOPE_ENVIRONMENT = 'environment';

    /** Normalized to uppercase: the input renders uppercase, so the value must match. */
    public string $key;

    public bool $isSecret;

    public bool $isInherited;

    /** Project scope means the var applies to every environment — see Entity\EnvVar::$branch. */
    public bool $isProjectScoped;

    public function __construct(
        #[FromBody]
        string $key,
        #[FromBody]
        public string $value,
        #[FromBody]
        string|null $isSecret = null,
        #[FromBody]
        string|null $isInherited = null,
        #[FromBody]
        string|null $scope = null,
    ) {
        $this->isSecret        = $isSecret !== null;
        $this->isProjectScoped = $scope === self::SCOPE_PROJECT;

        // "Inherited" means "pass down to child branches" and is meaningless for a var that already
        // applies everywhere — the database enforces the same rule with a CHECK constraint.
        $this->isInherited = $isInherited !== null && ! $this->isProjectScoped;

        $this->key = strtoupper(trim($key));

        $errors = [];

        if ($this->key === '') {
            $errors[] = ValidationError::make('key', _('Field can\'t be empty'));
        } elseif (preg_match('/^[A-Z][A-Z0-9_]*$/', $this->key) !== 1) {
            $errors[] = ValidationError::make(
                'key',
                _('Key must start with a letter and contain only letters, digits and underscores'),
            );
        }

        if (trim($this->value) === '') {
            $errors[] = ValidationError::make('value', _('Field can\'t be empty'));
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function createEnvVar(EnvVarIdentifier $id, ProjectIdentifier $projectId, string|null $branch): EnvVar
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
