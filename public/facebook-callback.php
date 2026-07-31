<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\FacebookClient;
use App\SocialAccountRepository;

Auth::requireLogin();

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$expectedState = $_SESSION['fb_oauth_state'] ?? '';
unset($_SESSION['fb_oauth_state']);

if (!$code || !$expectedState || !hash_equals($expectedState, $state)) {
    http_response_code(400);
    die('Invalid OAuth state or missing authorization code.');
}

try {
    $client = new FacebookClient();

    $tokenResponse = $client->exchangeCodeForToken($code);
    $shortToken = $tokenResponse['access_token'] ?? null;
    if (!$shortToken) {
        throw new RuntimeException('Facebook did not return an access token.');
    }

    $longLived = $client->longLivedToken($shortToken);
    $accessToken = $longLived['access_token'] ?? $shortToken;
    $expiresIn = isset($longLived['expires_in']) ? (int) $longLived['expires_in'] : null;

    $me = $client->me($accessToken);

    $socialAccountId = SocialAccountRepository::saveFacebookAccount(
        Auth::id(),
        (string) $me['id'],
        $accessToken,
        $expiresIn
    );

    $pages = $client->pages($accessToken);
    SocialAccountRepository::savePages($socialAccountId, $pages);

    header('Location: /dashboard.php?connected=1');
    exit;
} catch (\Throwable $e) {
    error_log('Facebook connect failed: ' . $e->getMessage());
    header('Location: /dashboard.php?error=' . urlencode('Could not connect Facebook: ' . $e->getMessage()));
    exit;
}
