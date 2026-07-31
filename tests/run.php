<?php
/**
 * Zero-dependency test runner (no Composer/PHPUnit) - keeps the project
 * installable on plain shared hosting without pulling in dev tooling.
 * Run with: php tests/run.php
 */

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
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

$failures = 0;

foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    try {
        $fn();
        echo "  PASS  {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "  FAIL  {$name}\n";
        echo '        ' . $e->getMessage() . "\n";
    }
}

$total = count($GLOBALS['__tests']);
$passed = $total - $failures;
echo "\n{$passed}/{$total} passed.\n";

exit($failures > 0 ? 1 : 0);
