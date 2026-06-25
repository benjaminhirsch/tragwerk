<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service\TwoFactor;

use Mezzio\Session\SessionInterface;

use function is_array;
use function is_int;
use function time;

/**
 * Manages the "pending second factor" session state. After a correct password
 * the user is intentionally NOT granted a full session (the UserInterface entry
 * is removed); instead this pending payload is held until the second factor is
 * confirmed at the challenge.
 */
final readonly class TwoFactorSession
{
    public const string KEY_PENDING       = 'two_factor.pending';
    public const string KEY_PENDING_SINCE = 'two_factor.pending_since';
    public const string KEY_ATTEMPTS      = 'two_factor.attempts';

    /** Pending state is abandoned after this many seconds. */
    public const int TTL_SECONDS = 600;

    /** Maximum failed challenge attempts before the pending state is dropped. */
    public const int MAX_ATTEMPTS = 5;

    /** @param array<array-key, mixed> $userPayload The captured UserInterface session entry. */
    public static function begin(SessionInterface $session, array $userPayload): void
    {
        $session->set(self::KEY_PENDING, $userPayload);
        $session->set(self::KEY_PENDING_SINCE, time());
        $session->set(self::KEY_ATTEMPTS, 0);
    }

    public static function isPending(SessionInterface $session): bool
    {
        return is_array($session->get(self::KEY_PENDING));
    }

    /** @return array<array-key, mixed>|null */
    public static function payload(SessionInterface $session): array|null
    {
        $payload = $session->get(self::KEY_PENDING);

        return is_array($payload) ? $payload : null;
    }

    public static function isExpired(SessionInterface $session): bool
    {
        $since = $session->get(self::KEY_PENDING_SINCE);

        return ! is_int($since) || (time() - $since) > self::TTL_SECONDS;
    }

    public static function attempts(SessionInterface $session): int
    {
        $attempts = $session->get(self::KEY_ATTEMPTS);

        return is_int($attempts) ? $attempts : 0;
    }

    public static function recordFailedAttempt(SessionInterface $session): int
    {
        $attempts = self::attempts($session) + 1;
        $session->set(self::KEY_ATTEMPTS, $attempts);

        return $attempts;
    }

    public static function clear(SessionInterface $session): void
    {
        $session->unset(self::KEY_PENDING);
        $session->unset(self::KEY_PENDING_SINCE);
        $session->unset(self::KEY_ATTEMPTS);
    }
}
