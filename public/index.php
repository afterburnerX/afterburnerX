<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;

if (Auth::check()) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AfterburnerX — Social Media Scheduler</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="container hero">
  <h1>AfterburnerX</h1>
  <p>Connect Facebook &amp; Instagram, schedule or post instantly, and get AI-powered advertising suggestions.</p>
  <a class="btn" href="/register.php">Get Started</a>
  <a class="btn btn-outline" href="/login.php">Log In</a>
</div>
</body>
</html>
