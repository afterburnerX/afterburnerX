<?php
require __DIR__ . '/../config/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\PostRepository;

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::verify($_POST['csrf_token'] ?? null)) {
    $postId = (int) ($_POST['post_id'] ?? 0);
    if ($postId > 0) {
        PostRepository::cancel($postId, Auth::id());
    }
}

header('Location: /posts.php');
exit;
