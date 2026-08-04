<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Authenticated encryption for secrets at rest - specifically the
 * Facebook user and Page access tokens, which are effectively posting
 * credentials for every connected business.
 *
 * XChaCha20-Poly1305 via libsodium (bundled with PHP since 7.2, so no
 * new dependency). Authenticated, so a tampered ciphertext fails loudly
 * rather than decrypting to garbage.
 *
 * Values are stored as "enc:v1:<base64 nonce||ciphertext>". The prefix
 * does two jobs: it lets decrypt() pass through rows written before
 * encryption was enabled, so turning this on doesn't break an existing
 * install, and it leaves room to rotate the scheme later without
 * guessing at what a stored value is.
 */
class Crypto
{
    private const PREFIX = 'enc:v1:';
    private const KEY_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;
    private const NONCE_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    public static function isConfigured(): bool
    {
        try {
            self::key();
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Encrypts a value. With no key configured the value is returned
     * unchanged, so an existing deployment keeps working until the
     * operator sets APP_ENCRYPTION_KEY (the dashboard warns while that
     * is the case).
     */
    public static function encrypt(string $plaintext): string
    {
        if (!self::isConfigured()) {
            return $plaintext;
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            '',
            $nonce,
            self::key()
        );

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypts a value written by encrypt(). Values without the prefix
     * are assumed to predate encryption and are returned as-is.
     *
     * @throws RuntimeException if an encrypted value can't be authenticated
     */
    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            return $value;
        }

        if (!self::isConfigured()) {
            throw new RuntimeException(
                'Found an encrypted value but APP_ENCRYPTION_KEY is not set. '
                . 'Restore the key that was used to encrypt these rows.'
            );
        }

        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= self::NONCE_BYTES) {
            throw new RuntimeException('Malformed encrypted value.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $ciphertext = substr($raw, self::NONCE_BYTES);

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $ciphertext,
            '',
            $nonce,
            self::key()
        );

        if ($plaintext === false) {
            throw new RuntimeException(
                'Could not decrypt value - wrong APP_ENCRYPTION_KEY, or the data was tampered with.'
            );
        }

        return $plaintext;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Generates a new key in the format APP_ENCRYPTION_KEY expects.
     */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(self::KEY_BYTES));
    }

    private static function key(): string
    {
        // Deliberately not cached: decoding 44 characters of base64 is
        // negligible, and a cache would make the key impossible to change
        // within a process (which key-rotation tooling and the tests need).
        $configured = env('APP_ENCRYPTION_KEY');

        if ($configured === null || trim($configured) === '') {
            throw new RuntimeException('APP_ENCRYPTION_KEY is not set.');
        }

        $key = base64_decode(trim($configured), true);

        if ($key === false || strlen($key) !== self::KEY_BYTES) {
            throw new RuntimeException(
                'APP_ENCRYPTION_KEY must be ' . self::KEY_BYTES . ' bytes, base64-encoded. '
                . 'Generate one with: php tools/generate-key.php'
            );
        }

        return $key;
    }
}
