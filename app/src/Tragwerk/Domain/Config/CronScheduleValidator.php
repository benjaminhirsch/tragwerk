<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Config;

use function array_filter;
use function array_key_exists;
use function array_shift;
use function array_values;
use function count;
use function ctype_digit;
use function explode;
use function in_array;
use function preg_match;
use function str_contains;
use function strtoupper;
use function trim;

/**
 * Validates crontab schedule expressions for the cron sidecar.
 *
 * Mirrors what supercronic (gorhill/cronexpr) accepts: standard 5-field
 * expressions, an optional leading seconds field (6 fields), `@`-descriptors
 * and `@every <duration>`. Field values are range-checked so semantically
 * broken schedules (e.g. "61 * * * *" or "*&#47;0 * * * *") fail at build time
 * instead of crash-looping the cron container at runtime.
 *
 * Intentionally lenient on the advanced day-of-month / day-of-week syntax
 * (`L`, `W`, `#`, `?`) supercronic supports, to avoid rejecting valid
 * schedules — the goal is to catch obvious garbage early, not to reimplement
 * the full parser.
 */
final readonly class CronScheduleValidator
{
    private const array DESCRIPTORS = [
        '@yearly',
        '@annually',
        '@monthly',
        '@weekly',
        '@daily',
        '@midnight',
        '@hourly',
    ];

    /** Per-field inclusive numeric bounds, in 5-field order. */
    private const array RANGES = [
        ['min' => 0, 'max' => 59], // minute
        ['min' => 0, 'max' => 23], // hour
        ['min' => 1, 'max' => 31], // day of month
        ['min' => 1, 'max' => 12], // month
        ['min' => 0, 'max' => 7],  // day of week (0 and 7 = Sunday)
    ];

    private const array MONTHS = [
        'JAN' => 1,
        'FEB' => 2,
        'MAR' => 3,
        'APR' => 4,
        'MAY' => 5,
        'JUN' => 6,
        'JUL' => 7,
        'AUG' => 8,
        'SEP' => 9,
        'OCT' => 10,
        'NOV' => 11,
        'DEC' => 12,
    ];

    private const array WEEKDAYS = [
        'SUN' => 0,
        'MON' => 1,
        'TUE' => 2,
        'WED' => 3,
        'THU' => 4,
        'FRI' => 5,
        'SAT' => 6,
    ];

    public function isValid(string $schedule): bool
    {
        $schedule = trim($schedule);

        if ($schedule === '') {
            return false;
        }

        if (str_contains($schedule, '@')) {
            return $this->isValidDescriptor($schedule);
        }

        $fields = explode(' ', $schedule);
        $fields = array_values(array_filter($fields, static fn (string $f): bool => $f !== ''));

        // Allow an optional leading seconds field (6 fields total).
        if (count($fields) === 6) {
            if (! $this->isValidField($fields[0], 0, 59, [])) {
                return false;
            }

            array_shift($fields);
        }

        if (count($fields) !== 5) {
            return false;
        }

        foreach ($fields as $index => $field) {
            $names = match ($index) {
                3       => self::MONTHS,
                4       => self::WEEKDAYS,
                default => [],
            };

            if (! $this->isValidField($field, self::RANGES[$index]['min'], self::RANGES[$index]['max'], $names)) {
                return false;
            }
        }

        return true;
    }

    private function isValidDescriptor(string $schedule): bool
    {
        if (in_array($schedule, self::DESCRIPTORS, true)) {
            return true;
        }

        // "@every 1h30m" — golang duration with at least one unit.
        return preg_match('/^@every (\d+(ns|us|µs|ms|s|m|h))+$/', $schedule) === 1;
    }

    /** @param array<string, int> $names Named aliases allowed in this field (month/weekday). */
    private function isValidField(string $field, int $min, int $max, array $names): bool
    {
        // Advanced day-of-month / day-of-week syntax we accept without deep checks.
        if ($field === '?' || str_contains($field, 'L') || str_contains($field, 'W') || str_contains($field, '#')) {
            return true;
        }

        foreach (explode(',', $field) as $item) {
            if ($item === '' || ! $this->isValidItem($item, $min, $max, $names)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, int> $names */
    private function isValidItem(string $item, int $min, int $max, array $names): bool
    {
        // Optional step: "<range>/<n>" with n > 0.
        if (str_contains($item, '/')) {
            [$range, $step] = explode('/', $item, 2);

            if (! ctype_digit($step) || (int) $step === 0) {
                return false;
            }

            $item = $range;
        }

        if ($item === '*') {
            return true;
        }

        // Range "a-b": both bounds valid and a <= b.
        if (str_contains($item, '-')) {
            [$from, $to] = explode('-', $item, 2);
            $fromValue   = $this->resolveValue($from, $min, $max, $names);
            $toValue     = $this->resolveValue($to, $min, $max, $names);

            return $fromValue !== null && $toValue !== null && $fromValue <= $toValue;
        }

        return $this->resolveValue($item, $min, $max, $names) !== null;
    }

    /** @param array<string, int> $names */
    private function resolveValue(string $value, int $min, int $max, array $names): int|null
    {
        if (ctype_digit($value)) {
            $number = (int) $value;

            return $number >= $min && $number <= $max ? $number : null;
        }

        $upper = strtoupper($value);

        return array_key_exists($upper, $names) ? $names[$upper] : null;
    }
}
