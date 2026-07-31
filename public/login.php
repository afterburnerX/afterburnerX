<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\Csrf;

if (Auth::check()) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session, please try again.';
    } elseif (!Auth::attempt(trim($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        $error = 'Invalid email or password.';
    } else {
        header('Location: /dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log In — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container narrow">
  <h1>Log In</h1>
  <?php if ($error): ?><p class="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post" class="card">
    <?= Csrf::field() ?>
    <label>Email<input type="email" name="email" required></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn" type="submit">Log In</button>
  </form>
  <p>No account? <a href="/register.php">Register</a></p>
</main>
</body>
</html>
