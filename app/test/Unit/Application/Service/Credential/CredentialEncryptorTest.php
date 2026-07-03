<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Service\Credential;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Exception\Credential\CredentialKeyEncryptionFailed;
use Tragwerk\Application\Service\Credential\CredentialEncryptor;

use function base64_encode;
use function str_repeat;

final class CredentialEncryptorTest extends TestCase
{
    private const string SSH_KEY = <<<'KEY'
        -----BEGIN OPENSSH PRIVATE KEY-----
        b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gt
        -----END OPENSSH PRIVATE KEY-----
        KEY;

    private CredentialEncryptor $encryptor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encryptor = new CredentialEncryptor(base64_encode(str_repeat('k', 32)));
    }

    #[Test]
    public function encryptionRoundTrips(): void
    {
        $encrypted = $this->encryptor->encrypt(self::SSH_KEY);

        self::assertNotSame(self::SSH_KEY, $encrypted);
        self::assertStringStartsNotWith('-----BEGIN', $encrypted);
        self::assertSame(self::SSH_KEY, $this->encryptor->decrypt($encrypted));
    }

    #[Test]
    public function encryptionUsesAFreshNoncePerCall(): void
    {
        self::assertNotSame(
            $this->encryptor->encrypt(self::SSH_KEY),
            $this->encryptor->encrypt(self::SSH_KEY),
        );
    }

    #[Test]
    public function decryptingTamperedCiphertextFails(): void
    {
        $this->expectException(CredentialKeyEncryptionFailed::class);

        $this->encryptor->decrypt('not-valid-ciphertext');
    }

    #[Test]
    public function decryptingWithAWrongKeyFails(): void
    {
        $encrypted = $this->encryptor->encrypt(self::SSH_KEY);

        $this->expectException(CredentialKeyEncryptionFailed::class);

        (new CredentialEncryptor(base64_encode(str_repeat('x', 32))))->decrypt($encrypted);
    }

    #[Test]
    public function aMalformedEncryptionKeyIsRejected(): void
    {
        $this->expectException(CredentialKeyEncryptionFailed::class);

        (new CredentialEncryptor(base64_encode('too-short')))->encrypt(self::SSH_KEY);
    }
}
