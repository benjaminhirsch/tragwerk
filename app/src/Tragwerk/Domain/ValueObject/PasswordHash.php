<?php

declare(strict_types=1);

namespace Tragwerk\Domain\ValueObject;

use Random\Randomizer;
use Stringable;
use Tragwerk\Domain\Exception\ValueObject\InvalidPasswordHash;

use function bin2hex;
use function hash_equals;
use function password_get_info;
use function password_hash;
use function password_verify;
use function sprintf;

use const PASSWORD_ARGON2ID;

final readonly class PasswordHash implements ValueObject, Stringable
{
    private const string ALGORITHM = PASSWORD_ARGON2ID;
    private const int TIME_COST    = 8;
    private const int LENGTH       = 8;

    private function __construct(
        private string $hash,
    ) {
    }

    /** @psalm-mutation-free */
    public static function generateRandom(): self
    {
        $rawPassword = bin2hex((new Randomizer())->getBytes(self::LENGTH));

        return self::create($rawPassword);
    }

    public static function create(string $rawPassword): self
    {
        $hash = password_hash($rawPassword, self::ALGORITHM, ['time_cost' => self::TIME_COST]);

        return new self($hash);
    }

    /** @phpstan-pure */
    public static function fromHash(string $hash): self
    {
        if (! self::isValidHash($hash)) {
            throw new InvalidPasswordHash(sprintf('Given string "%s" is not a valid password hash', $hash));
        }

        return new self($hash);
    }

    /** @phpstan-pure */
    public static function isValidHash(string $value): bool
    {
        $passwordInfo = password_get_info($value);

        return $passwordInfo['algo'] === self::ALGORITHM;
    }

    public function verify(string $rawPassword): bool
    {
        return password_verify($rawPassword, $this->hash);
    }

    public function isEqualTo(ValueObject $valueObject): bool
    {
        return $valueObject instanceof self && hash_equals($this->hash, $valueObject->hash);
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    /** @phpstan-pure */
    public function toString(): string
    {
        return (string) $this;
    }

    /** @phpstan-pure */
    public function __toString(): string
    {
        return $this->hash;
    }
}
