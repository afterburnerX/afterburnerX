<?php
/**
 * Meta Data Deletion Callback.
 *
 * Configure this URL in the Facebook app under
 * Settings > Basic > Data Deletion Request URL.
 *
 * Facebook POSTs a signed_request; we verify it, delete the Facebook-derived
 * data for that user, and respond with the JSON Meta expects:
 *   {"url": "<status page>", "confirmation_code": "<code>"}
 */

require __DIR__ . '/../config/bootstrap.php';

use App\DataDeletionRepository;
use App\SignedRequest;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$signedRequest = $_POST['signed_request'] ?? '';

if ($signedRequest === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing signed_request']);
    exit;
}

try {
    $data = SignedRequest::parse($signedRequest, (string) env('FB_APP_SECRET'));
} catch (\Throwable $e) {
    error_log('Data deletion callback rejected: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signed_request']);
    exit;
}

$fbUserId = (string) ($data['user_id'] ?? '');

if ($fbUserId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'signed_request contained no user_id']);
    exit;
}

try {
    $hadData = DataDeletionRepository::deleteFacebookDataForFbUser($fbUserId);
    $code = DataDeletionRepository::recordRequest($fbUserId, $hadData);
} catch (\Throwable $e) {
    error_log('Data deletion failed for fb user: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Deletion failed']);
    exit;
}

$base = rtrim((string) env('APP_URL', ''), '/');

echo json_encode([
    'url' => $base . '/deletion-status.php?code=' . urlencode($code),
    'confirmation_code' => $code,
]);
