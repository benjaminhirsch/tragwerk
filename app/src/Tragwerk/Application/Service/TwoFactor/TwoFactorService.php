<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service\TwoFactor;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use OTPHP\TOTP;
use SensitiveParameter;
use Tragwerk\Application\Exception\TwoFactor\TwoFactorSecretDecryptionFailed;
use Tragwerk\Domain\Entity\RecoveryCode;

use function assert;
use function base64_decode;
use function base64_encode;
use function implode;
use function password_hash;
use function password_verify;
use function preg_replace;
use function random_bytes;
use function random_int;
use function sodium_crypto_secretbox;
use function sodium_crypto_secretbox_open;
use function strlen;
use function strtoupper;
use function substr;

use const PASSWORD_DEFAULT;
use const SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
use const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

/**
 * Wraps the spomky-labs/otphp TOTP implementation plus secret encryption,
 * recovery-code generation/verification and QR rendering, keeping the
 * cryptographic concerns out of the HTTP handlers.
 */
final readonly class TwoFactorService
{
    /** Characters used for recovery codes — no easily-confused 0/O/1/I/L. */
    private const string RECOVERY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const int RECOVERY_GROUP_SIZE  = 5;
    private const int RECOVERY_GROUPS      = 2;

    public function __construct(
        #[SensitiveParameter]
        private string $encryptionKey,
        private string $issuer,
        private int $trustedDeviceDays,
    ) {
    }

    public function trustedDeviceDays(): int
    {
        return $this->trustedDeviceDays;
    }

    /**
     * Generates a fresh, unconfigured TOTP secret (base32).
     *
     * @return non-empty-string
     */
    public function generateSecret(): string
    {
        $secret = TOTP::generate()->getSecret();
        assert($secret !== '');

        return $secret;
    }

    public function provisioningUri(string $secret, string $accountName): string
    {
        if ($secret === '') {
            throw TwoFactorSecretDecryptionFailed::create();
        }

        $label  = $accountName !== '' ? $accountName : 'account';
        $issuer = $this->issuer !== '' ? $this->issuer : 'Tragwerk';

        return TOTP::createFromSecret($secret)
            ->withLabel($label)
            ->withIssuer($issuer)
            ->getProvisioningUri();
    }

    /** Verifies a 6-digit TOTP code against the secret, tolerating minor clock drift. */
    public function verify(string $secret, string $code, int $leeway = 1): bool
    {
        if ($secret === '' || $code === '') {
            return false;
        }

        return TOTP::createFromSecret($secret)->verify($code, null, $leeway < 0 ? 0 : $leeway);
    }

    /** Renders the provisioning URI as an inline-embeddable SVG data URI. */
    public function qrCodeDataUri(string $provisioningUri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd()));

        return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($provisioningUri));
    }

    /**
     * Generates a batch of plaintext recovery codes. The caller is responsible
     * for hashing them via {@see self::hashRecoveryCode()} before persisting.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateRecoveryCode();
        }

        return $codes;
    }

    /** Hashes the normalized form of a recovery code for storage. */
    public function hashRecoveryCode(#[SensitiveParameter]
    string $code,): string
    {
        return password_hash($this->normalizeRecoveryCode($code), PASSWORD_DEFAULT);
    }

    /**
     * Returns the matching unused recovery code for a plaintext input, or null.
     * Inputs are normalized, so users may type codes with or without the dash
     * and in any letter case.
     *
     * @param iterable<RecoveryCode> $activeCodes
     */
    public function verifyRecoveryCode(#[SensitiveParameter]
    string $input, iterable $activeCodes,): RecoveryCode|null
    {
        $normalized = $this->normalizeRecoveryCode($input);

        foreach ($activeCodes as $code) {
            if (password_verify($normalized, $code->codeHash)) {
                return $code;
            }
        }

        return null;
    }

    public function encryptSecret(#[SensitiveParameter]
    string $secret,): string
    {
        $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($secret, $nonce, $this->key());

        return base64_encode($nonce . $cipher);
    }

    /** @return non-empty-string */
    public function decryptSecret(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw TwoFactorSecretDecryptionFailed::create();
        }

        $nonce  = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());

        if ($plain === false || $plain === '') {
            throw TwoFactorSecretDecryptionFailed::create();
        }

        return $plain;
    }

    private function key(): string
    {
        $key = base64_decode($this->encryptionKey, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw TwoFactorSecretDecryptionFailed::invalidKey();
        }

        return $key;
    }

    private function generateRecoveryCode(): string
    {
        $alphabetLength = strlen(self::RECOVERY_ALPHABET);
        $groups         = [];

        for ($g = 0; $g < self::RECOVERY_GROUPS; $g++) {
            $group = '';
            for ($c = 0; $c < self::RECOVERY_GROUP_SIZE; $c++) {
                $group .= self::RECOVERY_ALPHABET[random_int(0, $alphabetLength - 1)];
            }

            $groups[] = $group;
        }

        return implode('-', $groups);
    }

    /** Strips formatting so a code matches regardless of dash or letter case. */
    private function normalizeRecoveryCode(#[SensitiveParameter]
    string $input,): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');
    }
}
