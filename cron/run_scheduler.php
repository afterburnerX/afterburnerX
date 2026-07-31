<?php
/**
 * Publishes any posts whose scheduled_at has passed.
 * Run every minute, e.g. via crontab:
 *   * * * * * php /path/to/afterburnerX/cron/run_scheduler.php >> /path/to/afterburnerX/storage/scheduler.log 2>&1
 */

require __DIR__ . '/../config/bootstrap.php';

use App\PostRepository;
use App\PostPublisher;

$due = PostRepository::due();

if (!$due) {
    echo date('c') . " No posts due.\n";
}

foreach ($due as $post) {
    try {
        $remoteId = PostPublisher::publish($post);
        PostRepository::markPosted((int) $post['id'], $remoteId);
        echo date('c') . " [OK] Post {$post['id']} published (remote id: {$remoteId})\n";
    } catch (\Throwable $e) {
        PostRepository::markFailed((int) $post['id'], $e->getMessage());
        echo date('c') . " [FAIL] Post {$post['id']}: {$e->getMessage()}\n";
    }
}
