<?php

declare(strict_types=1);

namespace Tragwerk\Application\Validation;

use Override;

use function array_map;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;

final readonly class ValidationBag implements ValidationBagInterface
{
    private const array SECRET_PROPERTIES = [
        'password',
        'password1',
        'password2',
    ];

    /** @param array<array-key, mixed> $passedValues */
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

        if (preg_match('/^(\w+)\[(\d+)\]$/', $name, $matches) === 1) {
            $arr = $this->passedValues[$matches[1]] ?? null;
            if (is_array($arr)) {
                $val = $arr[$matches[2]] ?? '';
                if (is_int($val)) {
                    return $val;
                }

                return is_string($val) ? $val : '';
            }

            return '';
        }

        $value = $this->passedValues[$name] ?? '';

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) ? $value : '';
    }

    /** @return string[] */
    public function getArrayValueByName(string $name): array
    {
        $values = $this->passedValues[$name] ?? [];
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $v): string => is_string($v) ? $v : '', $values));
    }

    public function getDto(): object|null
    {
        return $this->dto;
    }
}
