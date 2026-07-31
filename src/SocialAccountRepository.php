<?php

declare(strict_types=1);

namespace App;

class SocialAccountRepository
{
    public static function saveFacebookAccount(int $userId, string $fbUserId, string $accessToken, ?int $expiresIn): int
    {
        $db = Database::connection();
        $expiresAt = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

        $stmt = $db->prepare(
            'INSERT INTO social_accounts (user_id, provider, fb_user_id, access_token, token_expires_at)
             VALUES (:user_id, "facebook", :fb_user_id, :access_token, :expires_at)
             ON DUPLICATE KEY UPDATE fb_user_id = VALUES(fb_user_id), access_token = VALUES(access_token),
                token_expires_at = VALUES(token_expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'fb_user_id' => $fbUserId,
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
        ]);

        $stmt = $db->prepare('SELECT id FROM social_accounts WHERE user_id = ? AND provider = "facebook"');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public static function savePages(int $socialAccountId, array $pages): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO pages (social_account_id, page_id, page_name, page_access_token, ig_user_id, ig_username)
             VALUES (:social_account_id, :page_id, :page_name, :page_access_token, :ig_user_id, :ig_username)
             ON DUPLICATE KEY UPDATE page_name = VALUES(page_name), page_access_token = VALUES(page_access_token),
                ig_user_id = VALUES(ig_user_id), ig_username = VALUES(ig_username)'
        );

        foreach ($pages as $page) {
            $ig = $page['instagram_business_account'] ?? null;
            $stmt->execute([
                'social_account_id' => $socialAccountId,
                'page_id' => $page['id'],
                'page_name' => $page['name'],
                'page_access_token' => $page['access_token'],
                'ig_user_id' => $ig['id'] ?? null,
                'ig_username' => $ig['username'] ?? null,
            ]);
        }
    }

    public static function pagesForUser(int $userId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT p.* FROM pages p
             JOIN social_accounts sa ON sa.id = p.social_account_id
             WHERE sa.user_id = ? ORDER BY p.page_name'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public static function findPage(int $userId, int $pageId): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT p.* FROM pages p
             JOIN social_accounts sa ON sa.id = p.social_account_id
             WHERE sa.user_id = ? AND p.id = ?'
        );
        $stmt->execute([$userId, $pageId]);
        $page = $stmt->fetch();

        return $page ?: null;
    }

    public static function facebookTokenExpiresAt(int $userId): ?string
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT token_expires_at FROM social_accounts WHERE user_id = ? AND provider = "facebook"'
        );
        $stmt->execute([$userId]);
        $expiresAt = $stmt->fetchColumn();

        return $expiresAt ?: null;
    }
}
