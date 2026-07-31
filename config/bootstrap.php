<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function env(string $key, ?string $default = null): ?string
{
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $path = BASE_PATH . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                $vars[trim($k)] = trim($v);
            }
        }
    }

    $value = $vars[$key] ?? getenv($key);

    return $value !== false && $value !== null ? $value : $default;
}

if (PHP_SAPI !== 'cli') {
    session_name('afterburnerx_session');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}
