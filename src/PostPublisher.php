<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Shared publishing logic used by both the "post now" path (compose.php)
 * and the scheduler cron worker, so a post is published the same way
 * regardless of when it fires.
 *
 * Expects an array with: target, message, media_url, link, fb_page_id,
 * page_access_token, ig_user_id.
 */
class PostPublisher
{
    public static function publish(array $post): string
    {
        $client = new FacebookClient();

        if ($post['target'] === 'instagram') {
            if (empty($post['ig_user_id'])) {
                throw new RuntimeException('This page has no linked Instagram business account.');
            }
            if (empty($post['media_url'])) {
                throw new RuntimeException('Instagram posts require an image URL.');
            }

            $result = $client->postToInstagram(
                (string) $post['ig_user_id'],
                (string) $post['page_access_token'],
                (string) $post['media_url'],
                (string) ($post['message'] ?? '')
            );

            return (string) ($result['id'] ?? '');
        }

        if (!empty($post['media_url'])) {
            $result = $client->postPhotoToPage(
                (string) $post['fb_page_id'],
                (string) $post['page_access_token'],
                (string) $post['media_url'],
                (string) ($post['message'] ?? '')
            );
        } else {
            $result = $client->postToPage(
                (string) $post['fb_page_id'],
                (string) $post['page_access_token'],
                (string) ($post['message'] ?? ''),
                $post['link'] ?? null
            );
        }

        return (string) ($result['post_id'] ?? $result['id'] ?? '');
    }
}
