<?php

declare(strict_types=1);

use App\Auth;

integration_test('register() creates a user with a hashed password', function () {
    $_SESSION = [];
    $id = Auth::register('Jane', 'jane@example.com', 'password123');

    $stmt = App\Database::connection()->prepare('SELECT name, email, password_hash FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    assertSame('Jane', $row['name']);
    assertSame('jane@example.com', $row['email']);
    assertFalse($row['password_hash'] === 'password123', 'password must never be stored in plain text');
    assertTrue(password_verify('password123', $row['password_hash']), 'stored hash should verify');
});

integration_test('register() rejects a duplicate email', function () {
    $_SESSION = [];
    Auth::register('First', 'dupe@example.com', 'password123');

    $threw = false;
    try {
        Auth::register('Second', 'dupe@example.com', 'password456');
    } catch (RuntimeException $e) {
        $threw = true;
    }

    assertTrue($threw, 'registering an existing email should throw');
});

integration_test('attempt() succeeds with the right password and logs the user in', function () {
    $_SESSION = [];
    $id = Auth::register('Jane', 'login@example.com', 'password123');
    $_SESSION = [];

    assertTrue(Auth::attempt('login@example.com', 'password123'));
    assertSame($id, Auth::id());
});

integration_test('attempt() fails with the wrong password and starts no session', function () {
    $_SESSION = [];
    Auth::register('Jane', 'wrongpw@example.com', 'password123');
    $_SESSION = [];

    assertFalse(Auth::attempt('wrongpw@example.com', 'not-the-password'));
    assertNull(Auth::id(), 'a failed login must not set a session');
});

integration_test('attempt() fails for an unknown email', function () {
    $_SESSION = [];
    assertFalse(Auth::attempt('nobody@example.com', 'password123'));
    assertNull(Auth::id());
});

integration_test('user() returns the logged-in user without exposing the password hash', function () {
    $_SESSION = [];
    Auth::register('Jane', 'profile@example.com', 'password123');
    $_SESSION = [];
    Auth::attempt('profile@example.com', 'password123');

    $user = Auth::user();
    assertSame('profile@example.com', $user['email']);
    assertFalse(array_key_exists('password_hash', $user), 'password_hash must not be returned to callers');
});
