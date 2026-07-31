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
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || $email === '' || strlen($password) < 8) {
            $error = 'Please fill in all fields. Password must be at least 8 characters.';
        } else {
            try {
                Auth::register($name, $email, $password);
                Auth::attempt($email, $password);
                header('Location: /dashboard.php');
                exit;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
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
<title>Register — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container narrow">
  <h1>Create Your Account</h1>
  <?php if ($error): ?><p class="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post" class="card">
    <?= Csrf::field() ?>
    <label>Name<input type="text" name="name" required></label>
    <label>Email<input type="email" name="email" required></label>
    <label>Password<input type="password" name="password" minlength="8" required></label>
    <button class="btn" type="submit">Register</button>
  </form>
  <p>Already have an account? <a href="/login.php">Log in</a></p>
</main>
</body>
</html>
