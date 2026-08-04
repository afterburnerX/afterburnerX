<?php
/**
 * Prints a new APP_ENCRYPTION_KEY.
 *
 *   php tools/generate-key.php
 *
 * Put the result in .env. Keep a backup somewhere safe: without this key
 * the stored access tokens cannot be decrypted, and every user would
 * have to reconnect their Facebook account.
 */

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use App\Crypto;

echo 'APP_ENCRYPTION_KEY=' . Crypto::generateKey() . "\n";
