<?php
/**
 * Meta Deauthorize Callback.
 *
 * Configure this URL in the Facebook app under
 * Settings > Basic > Deauthorize Callback URL.
 *
 * Fires when a user removes the app from their Facebook account. Their
 * tokens are useless at that point, so drop the connection rather than
 * leaving dead credentials (and a scheduler that keeps trying to use
 * them) sitting in the database.
 */

require __DIR__ . '/../config/bootstrap.php';

use App\DataDeletionRepository;
use App\SignedRequest;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$signedRequest = $_POST['signed_request'] ?? '';

if ($signedRequest === '') {
    http_response_code(400);
    exit;
}

try {
    $data = SignedRequest::parse($signedRequest, (string) env('FB_APP_SECRET'));
} catch (\Throwable $e) {
    error_log('Deauthorize callback rejected: ' . $e->getMessage());
    http_response_code(400);
    exit;
}

$fbUserId = (string) ($data['user_id'] ?? '');

if ($fbUserId === '') {
    http_response_code(400);
    exit;
}

try {
    DataDeletionRepository::deleteFacebookDataForFbUser($fbUserId);
} catch (\Throwable $e) {
    error_log('Deauthorize cleanup failed: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

http_response_code(200);
