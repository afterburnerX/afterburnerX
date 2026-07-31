<?php

declare(strict_types=1);

use App\Csrf;

test('Csrf::token() generates and persists a token in the session', function () {
    $_SESSION = [];
    $token = Csrf::token();
    assertTrue(is_string($token) && strlen($token) === 64, 'Expected a 64-char hex token');
    assertSame($token, Csrf::token(), 'Calling token() again should return the same value');
});

test('Csrf::verify() accepts the current session token', function () {
    $_SESSION = [];
    $token = Csrf::token();
    assertTrue(Csrf::verify($token));
});

test('Csrf::verify() rejects a wrong token', function () {
    $_SESSION = [];
    Csrf::token();
    assertFalse(Csrf::verify('not-the-real-token'));
});

test('Csrf::verify() rejects null/missing input', function () {
    $_SESSION = [];
    Csrf::token();
    assertFalse(Csrf::verify(null));
});

test('Csrf::verify() rejects when no token has ever been issued', function () {
    $_SESSION = [];
    assertFalse(Csrf::verify('anything'));
});

test('Csrf::field() renders a hidden input containing the escaped token', function () {
    $_SESSION = [];
    $token = Csrf::token();
    $field = Csrf::field();
    assertTrue(str_contains($field, 'name="csrf_token"'), 'Missing field name');
    assertTrue(str_contains($field, htmlspecialchars($token, ENT_QUOTES)), 'Missing token value');
});
