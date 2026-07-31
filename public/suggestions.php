<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\AIAdvisor;
use App\AISuggestionRepository;

Auth::requireLogin();

$error = null;
$latest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session, please try again.';
    } else {
        $description = trim($_POST['business_description'] ?? '');
        $goal = trim($_POST['goal'] ?? '') ?: null;

        if ($description === '') {
            $error = 'Describe your business first.';
        } else {
            try {
                $suggestion = AIAdvisor::suggest($description, $goal);
                AISuggestionRepository::save(Auth::id(), $description, $goal, $suggestion);
                $latest = $suggestion;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$history = AISuggestionRepository::forUser(Auth::id());
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Suggestions — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container narrow">
  <h1>AI Advertising Suggestions</h1>
  <?php if ($error): ?><p class="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <form method="post" class="card">
    <?= Csrf::field() ?>
    <label>Describe your business
      <textarea name="business_description" rows="3" required></textarea>
    </label>
    <label>Goal (optional)
      <input type="text" name="goal" placeholder="e.g. more foot traffic, more bookings">
    </label>
    <button class="btn" type="submit">Get Suggestions</button>
  </form>

  <?php if ($latest): ?>
    <section class="card">
      <h2>Latest Suggestion</h2>
      <pre class="suggestion"><?= htmlspecialchars($latest) ?></pre>
    </section>
  <?php endif; ?>

  <?php if ($history): ?>
    <section class="card">
      <h2>History</h2>
      <?php foreach ($history as $item): ?>
        <details>
          <summary><?= htmlspecialchars($item['created_at']) ?> — <?= htmlspecialchars(mb_strimwidth($item['business_description'], 0, 60, '…')) ?></summary>
          <pre class="suggestion"><?= htmlspecialchars($item['suggestion']) ?></pre>
        </details>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
