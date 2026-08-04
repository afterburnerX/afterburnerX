<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Parses and verifies the `signed_request` parameter Meta POSTs to the
 * data deletion and deauthorize callbacks.
 *
 * Format is "<base64url signature>.<base64url json payload>", where the
 * signature is HMAC-SHA256 of the *raw* payload string (not the decoded
 * JSON) keyed with the app secret. Verifying it is what proves the
 * request actually came from Meta - without it, anyone who knows the
 * callback URL could delete arbitrary users' data.
 */
class SignedRequest
{
    /**
     * @return array the decoded payload (contains user_id, algorithm, ...)
     * @throws RuntimeException if the request is malformed or the signature doesn't match
     */
    public static function parse(string $signedRequest, string $appSecret): array
    {
        if ($appSecret === '') {
            throw new RuntimeException('App secret is not configured.');
        }

        if (substr_count($signedRequest, '.') !== 1) {
            throw new RuntimeException('Malformed signed_request.');
        }

        [$encodedSig, $encodedPayload] = explode('.', $signedRequest, 2);

        if ($encodedSig === '' || $encodedPayload === '') {
            throw new RuntimeException('Malformed signed_request.');
        }

        $signature = self::base64UrlDecode($encodedSig);
        $payloadJson = self::base64UrlDecode($encodedPayload);

        if ($signature === null || $payloadJson === null) {
            throw new RuntimeException('Malformed signed_request encoding.');
        }

        $data = json_decode($payloadJson, true);
        if (!is_array($data)) {
            throw new RuntimeException('signed_request payload is not valid JSON.');
        }

        $algorithm = strtoupper((string) ($data['algorithm'] ?? ''));
        if ($algorithm !== 'HMAC-SHA256') {
            throw new RuntimeException('Unsupported signed_request algorithm.');
        }

        // Signed over the encoded payload exactly as received.
        $expected = hash_hmac('sha256', $encodedPayload, $appSecret, true);

        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('signed_request signature mismatch.');
        }

        return $data;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Builds a signed_request the same way Meta does. Used by the tests;
     * also handy for manually exercising the callbacks locally.
     */
    public static function encode(array $payload, string $appSecret): string
    {
        $payload['algorithm'] = $payload['algorithm'] ?? 'HMAC-SHA256';
        $encodedPayload = self::base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac('sha256', $encodedPayload, $appSecret, true);

        return self::base64UrlEncode($signature) . '.' . $encodedPayload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
