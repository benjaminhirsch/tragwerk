<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Random\Engine\Secure;
use Random\Randomizer;
use Stringable;
use Tragwerk\Domain\Exception\ValueObject\InvalidToken;

use function bin2hex;
use function ctype_xdigit;
use function hash_equals;
use function strlen;
use function strtolower;

final readonly class Token implements ValueObject, Stringable
{
    private function __construct(
        private string $token,
    ) {
    }

    /** @psalm-mutation-free */
    public static function generate(int $entropyBytes = 64): self
    {
        $randomizer = new Randomizer(new Secure());
        $tokenBytes = $randomizer->getBytes($entropyBytes);
        $token      = bin2hex($tokenBytes);

        return new self($token);
    }

    /** @psalm-pure */
    public static function fromString(string $token): self
    {
        if (! self::isValid($token)) {
            throw new InvalidToken('Given value is no valid Token');
        }

        return new self($token);
    }

    /** @psalm-pure */
    public static function isValid(string $token): bool
    {
        return $token !== '' && ctype_xdigit($token) && strlen($token) % 2 === 0;
    }

    /** @psalm-pure */
    public function verify(string $token): bool
    {
        return hash_equals(strtolower($this->token), strtolower($token));
    }

    /** @psalm-pure */
    public function isEqualTo(ValueObject $valueObject): bool
    {
        return $valueObject instanceof self && $this->verify($valueObject->token);
    }

    /** @psalm-pure */
    public function toString(): string
    {
        return (string) $this;
    }

    /** @psalm-pure */
    public function __toString(): string
    {
        return $this->token;
    }

    public function jsonSerialize(): string
    {
        return $this->token;
    }
}
