<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\ValueObject;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Exception\ValueObject\InvalidToken;
use Tragwerk\Domain\ValueObject\Token;
use Tragwerk\Domain\ValueObject\Uuid;

use function json_encode;
use function str_repeat;
use function strlen;

final class TokenTest extends TestCase
{
    private const string VALID_TOKEN = 'deadbeef1234abcd';

    #[Test]
    public function fromStringCreatesInstance(): void
    {
        $token = Token::fromString(self::VALID_TOKEN);

        self::assertSame(self::VALID_TOKEN, $token->toString());
    }

    #[Test]
    public function fromStringThrowsForInvalidToken(): void
    {
        $this->expectException(InvalidToken::class);

        Token::fromString('not-hex!');
    }

    #[Test]
    #[DataProvider('validTokens')]
    public function isValidReturnsTrueForValidTokens(string $token): void
    {
        self::assertTrue(Token::isValid($token));
    }

    /** @return array<string, array{string}> */
    public static function validTokens(): array
    {
        return [
            'lowercase hex' => ['deadbeef'],
            'uppercase hex' => ['DEADBEEF'],
            'mixed case'    => ['DeAdBeEf'],
            'long token'    => [str_repeat('ab', 64)],
        ];
    }

    #[Test]
    #[DataProvider('invalidTokens')]
    public function isValidReturnsFalseForInvalidTokens(string $token): void
    {
        self::assertFalse(Token::isValid($token));
    }

    /** @return array<string, array{string}> */
    public static function invalidTokens(): array
    {
        return [
            'empty'          => [''],
            'odd length'     => ['abc'],
            'non-hex chars'  => ['xyz123'],
            'with spaces'    => ['de ad be ef'],
        ];
    }

    #[Test]
    public function generateReturnsValidToken(): void
    {
        $token = Token::generate();

        self::assertTrue(Token::isValid($token->toString()));
    }

    #[Test]
    public function generateDefaultEntropyProduces128CharToken(): void
    {
        $token = Token::generate();

        // 64 bytes → 128 hex characters
        self::assertSame(128, strlen($token->toString()));
    }

    #[Test]
    public function generateWithCustomEntropyProducesExpectedLength(): void
    {
        $token = Token::generate(entropyBytes: 16);

        self::assertSame(32, strlen($token->toString()));
    }

    #[Test]
    public function eachGenerateCallReturnsDifferentToken(): void
    {
        $a = Token::generate();
        $b = Token::generate();

        self::assertNotSame($a->toString(), $b->toString());
    }

    #[Test]
    public function verifyReturnsTrueForMatchingToken(): void
    {
        $token = Token::fromString(self::VALID_TOKEN);

        self::assertTrue($token->verify(self::VALID_TOKEN));
    }

    #[Test]
    public function verifyIsCaseInsensitive(): void
    {
        $token = Token::fromString('deadbeef');

        self::assertTrue($token->verify('DEADBEEF'));
        self::assertTrue($token->verify('DeAdBeEf'));
    }

    #[Test]
    public function verifyReturnsFalseForDifferentToken(): void
    {
        $token = Token::fromString(self::VALID_TOKEN);

        self::assertFalse($token->verify('0000000000000000'));
    }

    #[Test]
    public function isEqualToReturnsTrueForSameToken(): void
    {
        $a = Token::fromString(self::VALID_TOKEN);
        $b = Token::fromString(self::VALID_TOKEN);

        self::assertTrue($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsTrueRegardlessOfCase(): void
    {
        $a = Token::fromString('deadbeef');
        $b = Token::fromString('DEADBEEF');

        self::assertTrue($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentToken(): void
    {
        $a = Token::fromString('deadbeef');
        $b = Token::fromString('cafebabe');

        self::assertFalse($a->isEqualTo($b));
    }

    #[Test]
    public function isEqualToReturnsFalseForDifferentValueObjectType(): void
    {
        $token = Token::fromString(self::VALID_TOKEN);
        $uuid  = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertFalse($token->isEqualTo($uuid));
    }

    #[Test]
    public function stringRepresentationsReturnRawToken(): void
    {
        $token = Token::fromString(self::VALID_TOKEN);

        self::assertSame(self::VALID_TOKEN, $token->toString());
        self::assertSame(self::VALID_TOKEN, (string) $token);
        self::assertSame('"' . self::VALID_TOKEN . '"', json_encode($token));
    }
}
