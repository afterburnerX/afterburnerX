<?php

declare(strict_types=1);

use App\SignedRequest;

const TEST_APP_SECRET = 'test_app_secret_value';

function assertRejects(callable $fn, string $why): void
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $why);
}

test('parse() accepts a correctly signed request and returns its payload', function () {
    $signed = SignedRequest::encode(['user_id' => '1234567890'], TEST_APP_SECRET);

    $data = SignedRequest::parse($signed, TEST_APP_SECRET);

    assertSame('1234567890', $data['user_id']);
});

test('parse() rejects a request signed with a different app secret', function () {
    // The whole point of the signature: someone who knows the callback URL
    // but not the app secret must not be able to trigger a deletion.
    $signed = SignedRequest::encode(['user_id' => '1234567890'], 'attacker_secret');

    assertRejects(
        fn () => SignedRequest::parse($signed, TEST_APP_SECRET),
        'a forged signature must not be accepted'
    );
});

test('parse() rejects a tampered payload', function () {
    $signed = SignedRequest::encode(['user_id' => 'victim_id'], TEST_APP_SECRET);
    [$sig, $payload] = explode('.', $signed, 2);

    // Swap in a different user id, keeping the original signature.
    $tampered = $sig . '.' . rtrim(strtr(base64_encode(
        (string) json_encode(['user_id' => 'someone_else', 'algorithm' => 'HMAC-SHA256'])
    ), '+/', '-_'), '=');

    assertRejects(
        fn () => SignedRequest::parse($tampered, TEST_APP_SECRET),
        'editing the payload must invalidate the signature'
    );
});

test('parse() rejects an unsigned "none" algorithm payload', function () {
    $payload = rtrim(strtr(base64_encode(
        (string) json_encode(['user_id' => '123', 'algorithm' => 'none'])
    ), '+/', '-_'), '=');

    assertRejects(
        fn () => SignedRequest::parse('sig.' . $payload, TEST_APP_SECRET),
        'an algorithm other than HMAC-SHA256 must be refused'
    );
});

test('parse() rejects structurally malformed input', function () {
    foreach (['', 'nodot', 'too.many.dots', '.', 'abc.'] as $bad) {
        assertRejects(
            fn () => SignedRequest::parse($bad, TEST_APP_SECRET),
            "malformed input '{$bad}' must be refused"
        );
    }
});

test('parse() rejects a payload that is not valid JSON', function () {
    $payload = rtrim(strtr(base64_encode('this is not json'), '+/', '-_'), '=');
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, TEST_APP_SECRET, true)), '+/', '-_'), '=');

    assertRejects(
        fn () => SignedRequest::parse($sig . '.' . $payload, TEST_APP_SECRET),
        'non-JSON payload must be refused even when correctly signed'
    );
});

test('parse() refuses to run without an app secret configured', function () {
    $signed = SignedRequest::encode(['user_id' => '123'], TEST_APP_SECRET);

    assertRejects(
        fn () => SignedRequest::parse($signed, ''),
        'an empty app secret must not be treated as valid'
    );
});
