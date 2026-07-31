<?php use App\Auth; ?>
<header class="nav">
  <div class="container nav-inner">
    <a class="brand" href="/">AfterburnerX</a>
    <nav>
      <?php if (Auth::check()): ?>
        <a href="/dashboard.php">Dashboard</a>
        <a href="/compose.php">Compose</a>
        <a href="/posts.php">Scheduled Posts</a>
        <a href="/suggestions.php">AI Suggestions</a>
        <a href="/logout.php">Logout</a>
      <?php else: ?>
        <a href="/login.php">Login</a>
        <a href="/register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
