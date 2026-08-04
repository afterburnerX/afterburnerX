<?php
/**
 * One-off migration for installs that stored access tokens before
 * encryption existed. Encrypts any still-plaintext token in place.
 *
 *   php tools/encrypt-existing-tokens.php --dry-run   # report only
 *   php tools/encrypt-existing-tokens.php             # apply
 *
 * Safe to re-run: rows already encrypted are skipped, so an interrupted
 * run can just be run again.
 */

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use App\Crypto;
use App\Database;

$dryRun = in_array('--dry-run', $argv, true);

if (!Crypto::isConfigured()) {
    fwrite(STDERR, "APP_ENCRYPTION_KEY is not set (or is invalid).\n");
    fwrite(STDERR, "Generate one with: php tools/generate-key.php\n");
    exit(1);
}

$db = Database::connection();

$targets = [
    ['table' => 'social_accounts', 'column' => 'access_token'],
    ['table' => 'pages', 'column' => 'page_access_token'],
];

$totalEncrypted = 0;
$totalSkipped = 0;

foreach ($targets as $target) {
    $table = $target['table'];
    $column = $target['column'];

    $rows = $db->query("SELECT id, {$column} AS token FROM {$table}")->fetchAll();
    $update = $db->prepare("UPDATE {$table} SET {$column} = ? WHERE id = ?");

    $encrypted = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        $token = (string) $row['token'];

        if ($token === '' || Crypto::isEncrypted($token)) {
            $skipped++;
            continue;
        }

        if (!$dryRun) {
            $update->execute([Crypto::encrypt($token), $row['id']]);
        }

        $encrypted++;
    }

    $verb = $dryRun ? 'would encrypt' : 'encrypted';
    echo "{$table}.{$column}: {$verb} {$encrypted}, already encrypted {$skipped}\n";

    $totalEncrypted += $encrypted;
    $totalSkipped += $skipped;
}

echo "\n";

if ($dryRun) {
    echo "Dry run - nothing was written. {$totalEncrypted} row(s) would be encrypted.\n";
} else {
    echo "Done. Encrypted {$totalEncrypted} row(s), skipped {$totalSkipped} already-encrypted row(s).\n";
    echo "Keep APP_ENCRYPTION_KEY backed up - without it these tokens are unrecoverable.\n";
}
