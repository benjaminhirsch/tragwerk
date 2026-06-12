<?php

declare(strict_types=1);

namespace Tragwerk\Application\Helper;

use Stringable;

use function abs;
use function count;
use function crc32;
use function mb_strtoupper;
use function mb_substr;
use function sprintf;
use function str_word_count;

final readonly class AbbreviationHelper implements Stringable
{
    public function __construct(private string $value)
    {
    }

    /**
     * Generates and returns the Oklab color space for usage in CSS
     */
    public function oklch(): string
    {
        $hash = crc32($this->value);
        $hue  = abs($hash) % 360;

        return sprintf('oklch(62%% 0.15 %d)', $hue);
    }

    public function forString(): string
    {
        return $this->value
                |> (static fn ($s) => str_word_count($s, 1))
                |> (static fn ($words) => count($words) === 1
                    ? mb_strtoupper(mb_substr($words[0], 0, 1))
                    : mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1)));
    }

    public function __toString(): string
    {
        return $this->forString();
    }
}
