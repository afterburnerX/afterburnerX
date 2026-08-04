<?php

declare(strict_types=1);

use App\Crypto;

/**
 * env() caches .env into a static, but getenv() is consulted for keys the
 * file doesn't define - and the test runner runs without a .env - so
 * putenv() is enough to control APP_ENCRYPTION_KEY here.
 */
function with_encryption_key(?string $key, callable $fn)
{
    $previous = getenv('APP_ENCRYPTION_KEY');

    if ($key === null) {
        putenv('APP_ENCRYPTION_KEY');
    } else {
        putenv('APP_ENCRYPTION_KEY=' . $key);
    }

    try {
        return $fn();
    } finally {
        if ($previous === false) {
            putenv('APP_ENCRYPTION_KEY');
        } else {
            putenv('APP_ENCRYPTION_KEY=' . $previous);
        }
    }
}

test('generateKey() produces a 32-byte base64 key', function () {
    $key = Crypto::generateKey();
    $raw = base64_decode($key, true);

    assertTrue($raw !== false, 'key should be valid base64');
    assertSame(32, strlen($raw));
});

test('generateKey() does not repeat itself', function () {
    assertFalse(Crypto::generateKey() === Crypto::generateKey());
});

test('encrypt() then decrypt() round-trips a token', function () {
    $token = 'EAAG1234567890abcdefTOKEN';
    $cipher = Crypto::encrypt($token);

    assertFalse($cipher === $token, 'ciphertext should differ from plaintext');
    assertTrue(str_starts_with($cipher, 'enc:v1:'), 'should carry the version prefix');
    assertSame($token, Crypto::decrypt($cipher));
});

test('the plaintext token does not appear in the ciphertext', function () {
    $token = 'EAAsupersecrettokenvalue';

    assertFalse(
        str_contains(Crypto::encrypt($token), $token),
        'the raw token must not be recoverable by reading the stored value'
    );
});

test('encrypting the same value twice gives different ciphertexts', function () {
    // Random nonce per encryption: identical tokens must not be
    // correlatable in the database.
    assertFalse(Crypto::encrypt('same-token') === Crypto::encrypt('same-token'));
});

test('decrypt() passes through legacy plaintext written before encryption', function () {
    assertSame('legacy-plaintext-token', Crypto::decrypt('legacy-plaintext-token'));
});

test('isEncrypted() distinguishes encrypted values from legacy plaintext', function () {
    assertTrue(Crypto::isEncrypted(Crypto::encrypt('x')));
    assertFalse(Crypto::isEncrypted('plain-token'));
});

test('decrypt() rejects a tampered ciphertext instead of returning garbage', function () {
    $cipher = Crypto::encrypt('sensitive-token');

    // Flip a byte in the base64 body.
    $body = substr($cipher, strlen('enc:v1:'));
    $raw = base64_decode($body, true);
    $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0xFF);
    $tampered = 'enc:v1:' . base64_encode($raw);

    $threw = false;
    try {
        Crypto::decrypt($tampered);
    } catch (RuntimeException $e) {
        $threw = true;
    }

    assertTrue($threw, 'authenticated encryption must reject a modified ciphertext');
});

test('decrypt() rejects a malformed encrypted value', function () {
    $threw = false;
    try {
        Crypto::decrypt('enc:v1:not-valid-base64!!!');
    } catch (RuntimeException $e) {
        $threw = true;
    }

    assertTrue($threw);
});

test('with no key configured, encrypt() leaves the value alone so upgrades do not break', function () {
    with_encryption_key(null, function () {
        assertFalse(Crypto::isConfigured());
        assertSame('plain-token', Crypto::encrypt('plain-token'));
        assertSame('plain-token', Crypto::decrypt('plain-token'));
    });
});

test('losing the key surfaces a clear error instead of silently failing', function () {
    $cipher = Crypto::encrypt('token-that-needs-the-key');

    $message = '';
    try {
        with_encryption_key(null, fn () => Crypto::decrypt($cipher));
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
    }

    assertTrue(
        str_contains($message, 'APP_ENCRYPTION_KEY'),
        'the error should name the missing key; got: ' . $message
    );
});

test('an invalid key is rejected rather than treated as unconfigured', function () {
    with_encryption_key('not-valid-base64-and-wrong-length', function () {
        assertFalse(Crypto::isConfigured(), 'a malformed key must not count as configured');
    });
});

test('decrypt() fails with a different key rather than returning wrong data', function () {
    // Encrypted under the suite's key; a different key must not decrypt it.
    $cipher = Crypto::encrypt('token-under-key-a');

    $threw = false;
    try {
        with_encryption_key(Crypto::generateKey(), function () use ($cipher) {
            Crypto::decrypt($cipher);
        });
    } catch (RuntimeException $e) {
        $threw = true;
    }

    assertTrue($threw, 'a wrong key must fail loudly');
});
