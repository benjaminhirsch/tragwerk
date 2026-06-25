<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Service\TwoFactor;

use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Exception\TwoFactor\TwoFactorSecretDecryptionFailed;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Domain\Entity\RecoveryCode;
use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function base64_encode;
use function preg_match;
use function str_repeat;
use function strtolower;
use function substr;

final class TwoFactorServiceTest extends TestCase
{
    private TwoFactorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TwoFactorService(base64_encode(str_repeat('k', 32)), 'Tragwerk', 30);
    }

    #[Test]
    public function verifyAcceptsTheCurrentCodeAndRejectsAWrongOne(): void
    {
        $secret      = $this->service->generateSecret();
        $currentCode = TOTP::createFromSecret($secret)->now();

        self::assertTrue($this->service->verify($secret, $currentCode));
        self::assertFalse($this->service->verify($secret, '000000'));
        self::assertFalse($this->service->verify($secret, ''));
    }

    #[Test]
    public function codesFromAForeignSecretAreRejected(): void
    {
        $secret      = $this->service->generateSecret();
        $otherSecret = $this->service->generateSecret();
        $foreignCode = TOTP::createFromSecret($otherSecret)->now();

        self::assertFalse($this->service->verify($secret, $foreignCode));
    }

    #[Test]
    public function secretEncryptionRoundTrips(): void
    {
        $secret    = $this->service->generateSecret();
        $encrypted = $this->service->encryptSecret($secret);

        self::assertNotSame($secret, $encrypted);
        self::assertSame($secret, $this->service->decryptSecret($encrypted));
    }

    #[Test]
    public function encryptionUsesAFreshNoncePerCall(): void
    {
        $secret = $this->service->generateSecret();

        self::assertNotSame($this->service->encryptSecret($secret), $this->service->encryptSecret($secret));
    }

    #[Test]
    public function decryptingTamperedCiphertextFails(): void
    {
        $this->expectException(TwoFactorSecretDecryptionFailed::class);

        $this->service->decryptSecret('not-valid-ciphertext');
    }

    #[Test]
    public function generatesTheRequestedNumberOfFormattedRecoveryCodes(): void
    {
        $codes = $this->service->generateRecoveryCodes(8);

        self::assertCount(8, $codes);
        foreach ($codes as $code) {
            self::assertSame(1, preg_match('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $code));
        }
    }

    #[Test]
    public function recoveryCodeVerificationIgnoresDashAndCase(): void
    {
        $plain = $this->service->generateRecoveryCodes(1)[0];
        $code  = $this->recoveryCode($this->service->hashRecoveryCode($plain));

        // Same code, reformatted (lowercase, no dash) still matches.
        $match = $this->service->verifyRecoveryCode(strtolower(substr($plain, 0, 5) . substr($plain, 6)), [$code]);

        self::assertNotNull($match);
        self::assertTrue($match->id->isEqualTo($code->id));
        self::assertNull($this->service->verifyRecoveryCode('ZZZZZ-ZZZZZ', [$code]));
    }

    private function recoveryCode(string $hash): RecoveryCode
    {
        return new RecoveryCode(
            id: RecoveryCodeIdentifier::create(),
            userId: UserIdentifier::create(),
            codeHash: $hash,
            createdAt: TimestampImmutable::now(),
        );
    }
}
