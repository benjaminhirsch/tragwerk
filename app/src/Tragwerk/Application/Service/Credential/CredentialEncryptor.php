<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service\Credential;

use SensitiveParameter;
use Tragwerk\Application\Exception\Credential\CredentialKeyEncryptionFailed;

use function base64_decode;
use function base64_encode;
use function random_bytes;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function strlen;
use function substr;

use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

/**
 * Encrypts SSH private keys at rest with libsodium secretbox (XSalsa20-Poly1305).
 *
 * The 32-byte key is supplied via the CREDENTIAL_ENCRYPTION_KEY environment variable
 * and lives outside the database — a leaked `credentials.private_key` column is
 * unusable without it. Decryption happens only in a local scope right before the
 * SSH connect, never on the persisted entity.
 */
final readonly class CredentialEncryptor
{
    public function __construct(
        #[SensitiveParameter]
        private string $encryptionKey,
    ) {
    }

    public function encrypt(#[SensitiveParameter]
    string $plain,): string
    {
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key());

        return base64_encode($nonce . $cipher);
    }

    /** @return non-empty-string */
    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw CredentialKeyEncryptionFailed::create();
        }

        $nonce  = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());

        if ($plain === false || $plain === '') {
            throw CredentialKeyEncryptionFailed::create();
        }

        return $plain;
    }

    private function key(): string
    {
        $key = base64_decode($this->encryptionKey, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw CredentialKeyEncryptionFailed::invalidKey();
        }

        return $key;
    }
}
