<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\SocialAccountRepository;

Auth::requireLogin();

$user = Auth::user();
$pages = SocialAccountRepository::pagesForUser(Auth::id());
$connected = isset($_GET['connected']);
$oauthError = $_GET['error'] ?? null;

$tokenExpiresAt = SocialAccountRepository::facebookTokenExpiresAt(Auth::id());
$tokenWarning = null;
if ($tokenExpiresAt) {
    $daysLeft = (int) floor((strtotime($tokenExpiresAt) - time()) / 86400);
    if ($daysLeft < 0) {
        $tokenWarning = 'Your Facebook connection has expired. Reconnect to keep posting.';
    } elseif ($daysLeft <= 7) {
        $tokenWarning = "Your Facebook connection expires in {$daysLeft} day(s). Reconnect soon to avoid interrupted posting.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container">
  <h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>

  <?php if ($connected): ?>
    <p class="alert alert-success">Facebook account connected.</p>
  <?php endif; ?>
  <?php if ($oauthError): ?>
    <p class="alert"><?= htmlspecialchars($oauthError) ?></p>
  <?php endif; ?>
  <?php if ($tokenWarning): ?>
    <p class="alert">
      <?= htmlspecialchars($tokenWarning) ?>
      <a href="/facebook-connect.php">Reconnect now</a>.
    </p>
  <?php endif; ?>

  <section class="card">
    <h2>Connected Facebook Pages &amp; Instagram Accounts</h2>
    <?php if (!$pages): ?>
      <p>No pages connected yet.</p>
      <a class="btn" href="/facebook-connect.php">Connect Facebook</a>
    <?php else: ?>
      <ul class="list">
        <?php foreach ($pages as $page): ?>
          <li>
            <strong><?= htmlspecialchars($page['page_name']) ?></strong>
            <?php if ($page['ig_username']): ?>
              — Instagram: @<?= htmlspecialchars($page['ig_username']) ?>
            <?php else: ?>
              — <span class="muted">No Instagram account linked</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <a class="btn btn-outline" href="/facebook-connect.php">Reconnect / Refresh</a>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Quick Actions</h2>
    <a class="btn" href="/compose.php">Create a Post</a>
    <a class="btn" href="/suggestions.php">Get AI Ad Suggestions</a>
  </section>
</main>
</body>
</html>
