<?php

declare(strict_types=1);

namespace Tragwerk\Application\Dto\Variable;

use CuyZ\Valinor\Mapper\Http\FromBody;
use Tragwerk\Application\Dto\DtoInterface;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Model\ReservedEnvVars;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function _;
use function preg_match;
use function sprintf;
use function strtoupper;
use function trim;

final readonly class VariableUpdate implements DtoInterface
{
    /** Normalized to uppercase: the input renders uppercase, so the value must match. */
    public string $key;

    public bool $isSecret;

    public bool $isInherited;

    public function __construct(
        #[FromBody]
        string $key,
        #[FromBody]
        public string $value = '',
        #[FromBody]
        string|null $isSecret = null,
        #[FromBody]
        string|null $isInherited = null,
    ) {
        $this->isSecret    = $isSecret !== null;
        $this->isInherited = $isInherited !== null;

        $this->key = strtoupper(trim($key));

        $errors = [];

        if ($this->key === '') {
            $errors[] = ValidationError::make('key', _('Field can\'t be empty'));
        } elseif (preg_match('/^[A-Z][A-Z0-9_]*$/', $this->key) !== 1) {
            $errors[] = ValidationError::make(
                'key',
                _('Key must start with a letter and contain only letters, digits and underscores'),
            );
        } elseif (ReservedEnvVars::isReserved($this->key)) {
            $errors[] = ValidationError::make(
                'key',
                sprintf(_('%s is reserved by Tragwerk and cannot be used as a variable'), $this->key),
            );
        }

        if ($errors !== []) {
            throw ValidationCollection::fromValidations(...$errors);
        }
    }

    public function applyTo(EnvVar $existing): EnvVar
    {
        // A secret with a blank submitted value keeps the existing value.
        $value = $existing->isSecret && trim($this->value) === ''
            ? $existing->value
            : $this->value;

        return new EnvVar(
            $existing->id,
            $existing->projectId,
            $existing->branch,
            $this->key,
            $value,
            $this->isSecret,
            $this->isInherited,
            $existing->createdAt,
            TimestampImmutable::now(),
        );
    }
}
