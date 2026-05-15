<?php

declare(strict_types=1);

namespace Tragwerk\Application\Helper;

use Throwable;

final readonly class ThrowableHelper
{
    /**
     * @return mixed[]
     *
     * @psalm-pure
     */
    public static function toArray(Throwable $e): array
    {
        $previous = $e->getPrevious();

        return [
            'class'    => $e::class,
            'message'  => $e->getMessage(),
            'code'     => $e->getCode(),
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'trace'    => $e->getTraceAsString(),
            'previous' => $previous !== null ? self::toArray($previous) : null,
        ];
    }
}
