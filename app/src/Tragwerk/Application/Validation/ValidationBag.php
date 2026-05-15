<?php

declare(strict_types=1);

namespace Tragwerk\Application\Validation;

use Override;

use function count;
use function in_array;

final readonly class ValidationBag implements ValidationBagInterface
{
    private const array SECRET_PROPERTIES = [
        'password',
        'password1',
        'password2',
    ];

    /** @param array{array-key: int|string}|array{} $passedValues */
    public function __construct(
        private array $passedValues,
        private object|null $dto,
        /** @var array<array-key, string>|array{} */
        private array $messages,
    ) {
    }

    #[Override]
    public function hasErrors(): bool
    {
        return count($this->messages) > 0;
    }

    #[Override]
    public function getErrorByName(string $name): string|null
    {
        if (! isset($this->messages[$name])) {
            return null;
        }

        return $this->messages[$name];
    }

    public function getValueByName(string $name): int|string
    {
        if (in_array($name, self::SECRET_PROPERTIES, true)) {
            return '';
        }

        return $this->passedValues[$name] ?? '';
    }

    public function getDto(): object|null
    {
        return $this->dto;
    }
}
