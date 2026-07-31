<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\PostRepository;

Auth::requireLogin();

$posts = PostRepository::forUser(Auth::id());
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scheduled Posts — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container">
  <h1>Your Posts</h1>
  <?php if (!$posts): ?>
    <p>No posts yet. <a href="/compose.php">Create one</a>.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Page</th><th>Target</th><th>Message</th><th>Scheduled</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($posts as $post): ?>
        <tr>
          <td><?= htmlspecialchars($post['page_name']) ?></td>
          <td><?= htmlspecialchars($post['target']) ?></td>
          <td><?= htmlspecialchars(mb_strimwidth((string) $post['message'], 0, 80, '…')) ?></td>
          <td><?= htmlspecialchars($post['scheduled_at']) ?></td>
          <td>
            <span class="badge badge-<?= htmlspecialchars($post['status']) ?>"><?= htmlspecialchars($post['status']) ?></span>
            <?php if ($post['status'] === 'failed' && $post['error_message']): ?>
              <div class="muted small"><?= htmlspecialchars($post['error_message']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($post['status'] === 'pending'): ?>
              <form method="post" action="/cancel-post.php" onsubmit="return confirm('Cancel this scheduled post?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                <button class="btn btn-outline" type="submit">Cancel</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
