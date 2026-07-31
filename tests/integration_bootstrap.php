<?php
/**
 * Sets up a throwaway MySQL database for the integration tests and
 * points the repositories at it.
 *
 * Integration tests only run when TEST_DB_NAME is set, so `php
 * tests/run.php` still works on a machine with no database. Safety: the
 * database is dropped and recreated on every run, so the name is
 * required to end in "_test" - that makes it impossible to point this
 * at a real database by mistake (e.g. by copying a .env).
 */

declare(strict_types=1);

use App\Database;

function integration_db_available(): bool
{
    return getenv('TEST_DB_NAME') !== false && getenv('TEST_DB_NAME') !== '';
}

function integration_db_setup(): void
{
    $name = (string) getenv('TEST_DB_NAME');

    if (!str_ends_with($name, '_test')) {
        fwrite(STDERR, "REFUSING to run integration tests: TEST_DB_NAME ('{$name}') must end in '_test'.\n");
        fwrite(STDERR, "This database is dropped and recreated on every run.\n");
        exit(1);
    }

    $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
    $port = getenv('TEST_DB_PORT') ?: '3306';
    $user = getenv('TEST_DB_USER') ?: 'root';
    $pass = getenv('TEST_DB_PASS') ?: '';

    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $server->exec("DROP DATABASE IF EXISTS `{$name}`");
    $server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Load the real schema, minus its CREATE DATABASE / USE lines, so the
    // tests exercise exactly the DDL shipped to users.
    $schema = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
    $schema = preg_replace('/^\s*(CREATE DATABASE|USE)\b[^;]*;/mi', '', $schema);
    $pdo->exec((string) $schema);

    Database::useConnection($pdo);
}

/**
 * Wipes all rows so each test starts from a known state.
 */
function integration_db_reset(): void
{
    $pdo = Database::connection();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['ai_suggestions', 'scheduled_posts', 'pages', 'social_accounts', 'users'] as $table) {
        $pdo->exec("TRUNCATE TABLE {$table}");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/**
 * Registers a test that needs the database. Skipped (not failed) when no
 * test database is configured.
 */
function integration_test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn, 'integration'];
}

// --- helpers for building fixture rows ---

function make_user(string $email = 'user@example.com', string $name = 'Test User'): int
{
    $pdo = Database::connection();
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash('password123', PASSWORD_DEFAULT)]);

    return (int) $pdo->lastInsertId();
}

function make_social_account(int $userId, ?string $expiresAt = null, ?string $notifiedFor = null): int
{
    $pdo = Database::connection();
    $stmt = $pdo->prepare(
        'INSERT INTO social_accounts (user_id, provider, fb_user_id, access_token, token_expires_at, expiry_notified_for)
         VALUES (?, "facebook", ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, 'fb' . $userId, 'token' . $userId, $expiresAt, $notifiedFor]);

    return (int) $pdo->lastInsertId();
}

function make_page(int $socialAccountId, ?string $igUserId = null): int
{
    $pdo = Database::connection();
    $stmt = $pdo->prepare(
        'INSERT INTO pages (social_account_id, page_id, page_name, page_access_token, ig_user_id, ig_username)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $socialAccountId,
        'fbpage' . $socialAccountId,
        'Page ' . $socialAccountId,
        'pagetoken' . $socialAccountId,
        $igUserId,
        $igUserId ? 'ig_user' : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function post_row(int $id): ?array
{
    $stmt = Database::connection()->prepare('SELECT * FROM scheduled_posts WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}
