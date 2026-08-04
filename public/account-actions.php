<?php
/**
 * Handles "Disconnect Facebook" and "Delete my account" from the
 * dashboard. Both are destructive, so both require a POST with a valid
 * CSRF token and act only on the logged-in user's own data.
 */

require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\DataDeletionRepository;
use App\SocialAccountRepository;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /dashboard.php');
    exit;
}

$userId = Auth::id();
$action = $_POST['action'] ?? '';

if ($action === 'disconnect_facebook') {
    $fbUserId = SocialAccountRepository::facebookUserId($userId);

    if ($fbUserId !== null) {
        DataDeletionRepository::deleteFacebookDataForFbUser($fbUserId);
    }

    header('Location: /dashboard.php?disconnected=1');
    exit;
}

if ($action === 'delete_account') {
    // Require the user to type their email to confirm - this wipes
    // everything, including posts and suggestion history.
    $confirmation = trim($_POST['confirm_email'] ?? '');
    $user = Auth::user();

    if (!$user || strcasecmp($confirmation, $user['email']) !== 0) {
        header('Location: /dashboard.php?error=' . urlencode('Type your email address exactly to confirm account deletion.'));
        exit;
    }

    DataDeletionRepository::deleteAccount($userId);
    Auth::logout();

    header('Location: /?deleted=1');
    exit;
}

header('Location: /dashboard.php');
exit;
