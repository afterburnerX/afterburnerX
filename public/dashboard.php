<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\Crypto;
use App\Csrf;
use App\SocialAccountRepository;

Auth::requireLogin();

$user = Auth::user();
$pages = SocialAccountRepository::pagesForUser(Auth::id());
$connected = isset($_GET['connected']);
$disconnected = isset($_GET['disconnected']);
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
  <?php if ($disconnected): ?>
    <p class="alert alert-success">
      Facebook disconnected. Your stored tokens, Pages, Instagram accounts, and
      any posts scheduled to them have been deleted.
    </p>
  <?php endif; ?>
  <?php if ($oauthError): ?>
    <p class="alert"><?= htmlspecialchars($oauthError) ?></p>
  <?php endif; ?>
  <?php if ($pages && !Crypto::isConfigured()): ?>
    <p class="alert">
      <strong>Access tokens are being stored unencrypted.</strong>
      Anyone with a copy of the database could post to your connected accounts.
      Set <code>APP_ENCRYPTION_KEY</code> in <code>.env</code>
      (<code>php tools/generate-key.php</code>), then run
      <code>php tools/encrypt-existing-tokens.php</code>.
    </p>
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

  <section class="card">
    <h2>Your Data</h2>

    <?php if ($pages): ?>
      <p class="muted small">
        Disconnecting removes your stored access tokens, Pages, Instagram
        accounts, and any posts scheduled to them. Your account and AI
        suggestion history are kept.
      </p>
      <form method="post" action="/account-actions.php"
            onsubmit="return confirm('Disconnect Facebook? Scheduled posts to these Pages will be deleted.');">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="disconnect_facebook">
        <button class="btn btn-outline" type="submit">Disconnect Facebook</button>
      </form>
      <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">
    <?php endif; ?>

    <p class="muted small">
      Deleting your account permanently removes everything: your login, all
      posts, connected accounts, and suggestion history. This cannot be undone.
    </p>
    <form method="post" action="/account-actions.php"
          onsubmit="return confirm('Permanently delete your account and all data? This cannot be undone.');">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="delete_account">
      <label>Type <strong><?= htmlspecialchars($user['email']) ?></strong> to confirm
        <input type="email" name="confirm_email" placeholder="<?= htmlspecialchars($user['email']) ?>" required>
      </label>
      <button class="btn btn-outline" type="submit">Delete my account</button>
    </form>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
