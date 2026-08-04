<?php
/**
 * Status page Meta links the user to after a data deletion request.
 * Public by design: it's reached from Facebook, not from a logged-in
 * session, and shows nothing beyond whether that code's deletion ran.
 */

require __DIR__ . '/../config/bootstrap.php';

use App\DataDeletionRepository;

$code = (string) ($_GET['code'] ?? '');
$request = $code !== '' ? DataDeletionRepository::findRequest($code) : null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Deletion Status — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container narrow">
  <h1>Data Deletion Status</h1>

  <?php if (!$request): ?>
    <div class="card">
      <p class="alert">We couldn't find a deletion request with that confirmation code.</p>
      <p class="muted">Check the code from your Facebook settings, or
      <a href="/privacy.php">contact us</a> if you believe this is an error.</p>
    </div>
  <?php else: ?>
    <div class="card">
      <p class="alert alert-success">
        <?php if ($request['status'] === 'completed'): ?>
          Your Facebook data has been deleted from AfterburnerX.
        <?php else: ?>
          There was no Facebook data stored for your account, so there was nothing to delete.
        <?php endif; ?>
      </p>
      <p><strong>Confirmation code:</strong> <?= htmlspecialchars($request['confirmation_code']) ?></p>
      <p><strong>Requested:</strong> <?= htmlspecialchars($request['created_at']) ?></p>
      <p class="muted small">
        This removed the Facebook connection, the Pages and Instagram accounts
        we retrieved, the stored access tokens, and any posts scheduled to
        those Pages. See our <a href="/privacy.php">privacy policy</a> for details.
      </p>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
