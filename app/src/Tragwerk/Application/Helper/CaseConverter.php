<?php

declare(strict_types=1);

namespace Tragwerk\Application\Helper;

use function assert;
use function is_array;
use function is_string;
use function preg_replace;
use function strtolower;

final readonly class CaseConverter
{
    /**
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    public static function camelToSnakeCase(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            assert(is_string($key));
            $newKey = self::toSnakeCase($key);

            if (is_array($value)) {
                $result[$newKey] = self::camelToSnakeCase($value);
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    private static function toSnakeCase(string $string): string
    {
        $replacedString = preg_replace('/(?<!^)[A-Z]/', '_$0', $string);
        assert(is_string($replacedString));

        return strtolower($replacedString);
    }
}
