<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use Mezzio\Session\SessionInterface;
use Tragwerk\Domain\ValueObject\Token;

use function assert;
use function is_string;

class Csrf
{
    public const string SESSION_KEY = self::class . '::SESSION_KEY';

    public function generateToken(SessionInterface $session): string
    {
        $token = $this->getToken($session);
        if ($token !== null) {
            return $token;
        }

        $token = (string) Token::generate();
        $this->setToken($session, $token);

        return $token;
    }

    /** @psalm-mutation-free */
    public function isValidToken(SessionInterface $session, string $providedToken): bool
    {
        $actualToken = $this->getToken($session);
        if ($actualToken === null) {
            return false;
        }

        return $providedToken === $actualToken;
    }

    /** @psalm-mutation-free */
    public function getToken(SessionInterface $session): string|null
    {
        $token = $session->get(self::SESSION_KEY);
        assert($token === null || is_string($token));

        return $token;
    }

    public function setToken(SessionInterface $session, string $token): void
    {
        $session->set(self::SESSION_KEY, $token);
    }
}
