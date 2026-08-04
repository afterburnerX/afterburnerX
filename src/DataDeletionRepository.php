<?php

declare(strict_types=1);

namespace App;

/**
 * Handles Meta's data deletion requirement.
 *
 * Scope: this deletes everything obtained *from Facebook* - the
 * connection row (fb_user_id, access token), the Pages and linked
 * Instagram accounts fetched from the Graph API, and the posts targeting
 * those Pages, which go via the pages foreign key. It deliberately keeps
 * the AfterburnerX account itself (email/password the user created here,
 * not data from Meta) and their AI suggestion history (business
 * descriptions they typed in). A user who wants that gone too can delete
 * their whole account from the dashboard.
 */
class DataDeletionRepository
{
    /**
     * Deletes Facebook-derived data for a Facebook user id.
     *
     * @return bool true if there was something to delete
     */
    public static function deleteFacebookDataForFbUser(string $fbUserId): bool
    {
        $db = Database::connection();

        $stmt = $db->prepare('SELECT id FROM social_accounts WHERE provider = "facebook" AND fb_user_id = ?');
        $stmt->execute([$fbUserId]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (!$ids) {
            return false;
        }

        // pages (and their scheduled_posts) cascade from social_accounts.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("DELETE FROM social_accounts WHERE id IN ({$placeholders})")->execute($ids);

        return true;
    }

    /**
     * Deletes a whole AfterburnerX account. Everything else cascades from
     * the users row.
     */
    public static function deleteAccount(int $userId): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }

    public static function recordRequest(string $fbUserId, bool $hadData): string
    {
        $code = bin2hex(random_bytes(16));

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO deletion_requests (confirmation_code, fb_user_id, status) VALUES (?, ?, ?)'
        );
        $stmt->execute([$code, $fbUserId, $hadData ? 'completed' : 'nothing_to_delete']);

        return $code;
    }

    public static function findRequest(string $confirmationCode): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM deletion_requests WHERE confirmation_code = ?');
        $stmt->execute([$confirmationCode]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
