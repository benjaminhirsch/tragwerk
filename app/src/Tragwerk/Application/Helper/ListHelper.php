<?php

declare(strict_types=1);

namespace Tragwerk\Application\Helper;

use Generator;

use function is_array;
use function iterator_to_array;
use function strtoupper;
use function usort;

final readonly class ListHelper
{
    /**
     * @param Generator<T> $generator
     *
     * @return Generator<T>
     *
     * @template T of array|object
     */
    public static function sortGenerator(Generator $generator, string $sortKey, string $sortOrder = 'ASC'): Generator
    {
        $items = iterator_to_array($generator, preserve_keys: false);

        usort($items, static function ($a, $b) use ($sortKey, $sortOrder) {
            // @phpstan-ignore property.dynamicName
            $valA = is_array($a) ? $a[$sortKey] : $a->$sortKey;
            // @phpstan-ignore property.dynamicName
            $valB = is_array($b) ? $b[$sortKey] : $b->$sortKey;

            $cmp = $valA <=> $valB;

            return strtoupper($sortOrder) === 'DESC' ? -$cmp : $cmp;
        });

        yield from $items;
    }

    /**
     * @param Generator<T> $generator
     *
     * @return T|null
     *
     * @template T of array|object
     */
    public static function first(Generator $generator): mixed
    {
        if (! $generator->valid()) {
            return null;
        }

        return $generator->current();
    }
}
