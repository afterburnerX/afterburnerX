<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

class Auth
{
    public static function register(string $name, string $email, string $password): int
    {
        $db = Database::connection();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new RuntimeException('An account with that email already exists.');
        }

        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

        return (int) $db->lastInsertId();
    }

    public static function attempt(string $email, string $password): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Guards against session fixation. Only meaningful when a session
        // is actually running - under CLI there is none to regenerate.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = (int) $user['id'];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
        $stmt->execute([self::id()]);
        $user = $stmt->fetch();

        return $user ?: null;
    }
}
