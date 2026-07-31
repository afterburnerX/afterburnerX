<?php

declare(strict_types=1);

namespace App;

class PostRepository
{
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
             ORDER BY sp.scheduled_at ASC LIMIT 50'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function markPosted(int $id, string $remotePostId): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE scheduled_posts SET status = "posted", remote_post_id = ?, error_message = NULL WHERE id = ?');
        $stmt->execute([$remotePostId, $id]);
    }

    public static function markFailed(int $id, string $error): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE scheduled_posts SET status = "failed", error_message = ? WHERE id = ?');
        $stmt->execute([$error, $id]);
    }
}
