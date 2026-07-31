<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\SocialAccountRepository;
use App\PostRepository;
use App\PostPublisher;

Auth::requireLogin();

$userId = Auth::id();
$pages = SocialAccountRepository::pagesForUser($userId);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session, please try again.';
    } else {
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $target = ($_POST['target'] ?? 'facebook') === 'instagram' ? 'instagram' : 'facebook';
        $message = trim($_POST['message'] ?? '');
        $mediaUrl = trim($_POST['media_url'] ?? '') ?: null;
        $link = trim($_POST['link'] ?? '') ?: null;
        $when = $_POST['when'] ?? 'now';
        $scheduledAtInput = $_POST['scheduled_at'] ?? '';

        $page = SocialAccountRepository::findPage($userId, $pageId);

        if (!$page) {
            $error = 'Please choose a valid connected page.';
        } elseif ($message === '' && !$mediaUrl) {
            $error = 'Add a message or an image URL.';
        } elseif ($target === 'instagram' && !$page['ig_user_id']) {
            $error = 'That page has no linked Instagram business account.';
        } elseif ($target === 'instagram' && !$mediaUrl) {
            $error = 'Instagram posts require an image URL.';
        } elseif ($when === 'schedule' && (!$scheduledAtInput || strtotime($scheduledAtInput) <= time())) {
            $error = 'Choose a future date/time to schedule the post.';
        } else {
            $scheduledAtValue = $when === 'schedule'
                ? date('Y-m-d H:i:s', strtotime($scheduledAtInput))
                : date('Y-m-d H:i:s');

            $postId = PostRepository::schedule($userId, $pageId, $target, $message ?: null, $mediaUrl, $link, $scheduledAtValue);

            if ($when === 'now') {
                try {
                    $remoteId = PostPublisher::publish([
                        'target' => $target,
                        'message' => $message,
                        'media_url' => $mediaUrl,
                        'link' => $link,
                        'fb_page_id' => $page['page_id'],
                        'page_access_token' => $page['page_access_token'],
                        'ig_user_id' => $page['ig_user_id'],
                    ]);
                    PostRepository::markPosted($postId, $remoteId);
                    $success = 'Posted immediately!';
                } catch (\Throwable $e) {
                    PostRepository::markFailed($postId, $e->getMessage());
                    $error = 'Post failed: ' . $e->getMessage();
                }
            } else {
                $success = 'Post scheduled for ' . $scheduledAtValue . '.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Compose — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container narrow">
  <h1>Compose a Post</h1>
  <?php if ($error): ?><p class="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="alert alert-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>

  <?php if (!$pages): ?>
    <p>Connect a Facebook Page first.</p>
    <a class="btn" href="/facebook-connect.php">Connect Facebook</a>
  <?php else: ?>
    <form method="post" class="card">
      <?= Csrf::field() ?>
      <label>Page
        <select name="page_id" required>
          <?php foreach ($pages as $page): ?>
            <option value="<?= (int) $page['id'] ?>"><?= htmlspecialchars($page['page_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Post To
        <select name="target">
          <option value="facebook">Facebook Page</option>
          <option value="instagram">Instagram</option>
        </select>
      </label>
      <label>Message
        <textarea name="message" rows="4"></textarea>
      </label>
      <label>Image URL <span class="muted">(required for Instagram)</span>
        <input type="url" name="media_url" placeholder="https://example.com/image.jpg">
      </label>
      <label>Link <span class="muted">(Facebook only, optional)</span>
        <input type="url" name="link" placeholder="https://example.com">
      </label>
      <label class="radio-inline"><input type="radio" name="when" value="now" checked> Post Immediately</label>
      <label class="radio-inline"><input type="radio" name="when" value="schedule"> Schedule for later</label>
      <label>Scheduled Time
        <input type="datetime-local" name="scheduled_at">
      </label>
      <button class="btn" type="submit">Submit</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
