<?php
/**
 * Zero-dependency test runner (no Composer/PHPUnit) - keeps the project
 * installable on plain shared hosting without pulling in dev tooling.
 *
 * Unit tests:        php tests/run.php
 * With integration:  TEST_DB_NAME=afterburnerx_test php tests/run.php
 *
 * Integration tests are skipped (not failed) when TEST_DB_NAME is unset.
 */

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/integration_bootstrap.php';

// Run the suite the way a correctly-configured install runs: with token
// encryption on. Individual tests override this to cover the
// unconfigured and wrong-key paths.
if (getenv('APP_ENCRYPTION_KEY') === false || getenv('APP_ENCRYPTION_KEY') === '') {
    putenv('APP_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
}

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn, 'unit'];
}

function assertSame($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            '%sExpected %s, got %s',
            $message !== '' ? $message . ': ' : '',
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertTrue($condition, string $message = 'Expected true'): void
{
    if ($condition !== true) {
        throw new RuntimeException($message);
    }
}

function assertFalse($condition, string $message = 'Expected false'): void
{
    if ($condition !== false) {
        throw new RuntimeException($message);
    }
}

function assertNull($value, string $message = 'Expected null'): void
{
    if ($value !== null) {
        throw new RuntimeException($message);
    }
}

foreach (glob(__DIR__ . '/cases/*.php') as $file) {
    require $file;
}

$hasDb = integration_db_available();

if ($hasDb) {
    integration_db_setup();
}

// Always registered so they can be reported as skipped rather than
// silently vanishing when no test database is configured.
foreach (glob(__DIR__ . '/cases_integration/*.php') as $file) {
    require $file;
}

$failures = 0;
$skipped = 0;

foreach ($GLOBALS['__tests'] as [$name, $fn, $kind]) {
    if ($kind === 'integration') {
        if (!$hasDb) {
            $skipped++;
            continue;
        }
        integration_db_reset();
    }

    try {
        $fn();
        echo "  PASS  {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "  FAIL  {$name}\n";
        echo '        ' . $e->getMessage() . "\n";
    }
}

$total = count($GLOBALS['__tests']) - $skipped;
$passed = $total - $failures;
echo "\n{$passed}/{$total} passed.\n";

if ($skipped > 0) {
    echo "{$skipped} integration test(s) skipped — set TEST_DB_NAME=<something>_test to run them.\n";
}

exit($failures > 0 ? 1 : 0);
