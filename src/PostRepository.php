<?php

declare(strict_types=1);

namespace App;

class PostRepository
{
    /** After this many failed attempts, a transient error is treated as permanent. */
    public const MAX_ATTEMPTS = 6;

    private const BACKOFF_SECONDS = [1 => 120, 2 => 300, 3 => 900, 4 => 1800, 5 => 3600];

    public static function backoffSecondsForAttempt(int $attempt): int
    {
        return self::BACKOFF_SECONDS[$attempt] ?? 3600;
    }

    public static function schedule(
        int $userId,
        int $pageId,
        string $target,
        ?string $message,
        ?string $mediaUrl,
        ?string $link,
        string $scheduledAt
    ): int {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO scheduled_posts (user_id, page_id, target, message, media_url, link, scheduled_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
        );
        $stmt->execute([$userId, $pageId, $target, $message, $mediaUrl, $link, $scheduledAt]);

        return (int) $db->lastInsertId();
    }

    public static function forUser(int $userId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT sp.*, p.page_name, p.ig_username FROM scheduled_posts sp
             JOIN pages p ON p.id = sp.page_id
             WHERE sp.user_id = ? ORDER BY sp.scheduled_at DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /**
     * Posts due for publishing, joined with the page fields PostPublisher needs.
     */
    public static function due(): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT sp.*, p.page_id AS fb_page_id, p.page_access_token, p.ig_user_id
             FROM scheduled_posts sp
             JOIN pages p ON p.id = sp.page_id
             WHERE sp.status = "pending" AND sp.scheduled_at <= NOW()
               AND (sp.next_attempt_at IS NULL OR sp.next_attempt_at <= NOW())
             ORDER BY sp.scheduled_at ASC LIMIT 50'
        );
        $stmt->execute();

        // The scheduler hands these straight to PostPublisher, which needs
        // a usable token.
        return array_map(
            [SocialAccountRepository::class, 'decryptPageToken'],
            $stmt->fetchAll()
        );
    }

    public static function markPosted(int $id, string $remotePostId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE scheduled_posts SET status = "posted", remote_post_id = ?, error_message = NULL WHERE id = ?'
        );
        $stmt->execute([$remotePostId, $id]);
    }

    public static function markFailed(int $id, string $error): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE scheduled_posts SET status = "failed", error_message = ? WHERE id = ?');
        $stmt->execute([$error, $id]);
    }

    /**
     * Records a transient (rate limit / network) failure and schedules a
     * later retry with backoff, keeping the post "pending" so the
     * scheduler picks it up again automatically. Once MAX_ATTEMPTS is
     * exceeded it's marked permanently failed instead.
     */
    public static function markTransientFailure(int $id, int $priorAttempts, string $error): void
    {
        $attempts = $priorAttempts + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            self::markFailed($id, "Gave up after {$attempts} attempts: {$error}");
            return;
        }

        $delay = self::backoffSecondsForAttempt($attempts);

        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE scheduled_posts
             SET attempts = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? SECOND), error_message = ?
             WHERE id = ?'
        );
        $stmt->execute([$attempts, $delay, $error, $id]);
    }

    /**
     * Cancels a still-pending scheduled post. Silently no-ops if the post
     * doesn't belong to the user or has already been published/failed, so
     * the scheduler can never race a cancel into publishing a canceled post.
     */
    public static function cancel(int $id, int $userId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE scheduled_posts SET status = "canceled" WHERE id = ? AND user_id = ? AND status = "pending"'
        );
        $stmt->execute([$id, $userId]);
    }
}
