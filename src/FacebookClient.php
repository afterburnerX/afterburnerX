<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Thin wrapper around the Meta Graph API for the scopes this app needs:
 * Facebook Page posting and Instagram content publishing via a linked
 * Facebook Page (Instagram Login is not used - IG posting always goes
 * through a Page's connected Instagram Business Account).
 */
class FacebookClient
{
    private const GRAPH_VERSION = 'v21.0';
    private const GRAPH_URL = 'https://graph.facebook.com/' . self::GRAPH_VERSION;

    private string $appId;
    private string $appSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->appId = (string) env('FB_APP_ID');
        $this->appSecret = (string) env('FB_APP_SECRET');
        $this->redirectUri = (string) env('FB_REDIRECT_URI');
    }

    public function loginUrl(string $state): string
    {
        $scopes = [
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'instagram_basic',
            'instagram_content_publish',
            'business_management',
        ];

        $params = [
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
        ];

        return 'https://www.facebook.com/' . self::GRAPH_VERSION . '/dialog/oauth?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): array
    {
        return $this->request('GET', '/oauth/access_token', [
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);
    }

    public function longLivedToken(string $shortLivedToken): array
    {
        return $this->request('GET', '/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId,
            'client_secret' => $this->appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);
    }

    public function me(string $accessToken): array
    {
        return $this->request('GET', '/me', [
            'access_token' => $accessToken,
            'fields' => 'id,name,email',
        ]);
    }

    /**
     * Pages the user manages, each with its own Page access token and
     * (if linked) the connected Instagram Business Account.
     */
    public function pages(string $userAccessToken): array
    {
        $result = $this->request('GET', '/me/accounts', [
            'access_token' => $userAccessToken,
            'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            'limit' => 100,
        ]);

        return $result['data'] ?? [];
    }

    public function postToPage(string $pageId, string $pageAccessToken, string $message, ?string $link = null): array
    {
        $params = ['message' => $message, 'access_token' => $pageAccessToken];
        if ($link) {
            $params['link'] = $link;
        }

        return $this->request('POST', "/{$pageId}/feed", $params);
    }

    public function postPhotoToPage(string $pageId, string $pageAccessToken, string $imageUrl, string $caption): array
    {
        return $this->request('POST', "/{$pageId}/photos", [
            'url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $pageAccessToken,
        ]);
    }

    /**
     * Instagram publishing is a two-step process: create a media
     * container, then publish it.
     */
    public function postToInstagram(string $igUserId, string $pageAccessToken, string $imageUrl, string $caption): array
    {
        $container = $this->request('POST', "/{$igUserId}/media", [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $pageAccessToken,
        ]);

        if (empty($container['id'])) {
            throw new RuntimeException('Failed to create Instagram media container.');
        }

        return $this->request('POST', "/{$igUserId}/media_publish", [
            'creation_id' => $container['id'],
            'access_token' => $pageAccessToken,
        ]);
    }

    /** Graph API error codes that mean "rate limited", not "your request is wrong". */
    private const RATE_LIMIT_CODES = [4, 17, 32, 613];

    /** Quick in-process retries for blips, before handing off to the caller's own backoff. */
    private const MAX_QUICK_RETRIES = 2;
    private const QUICK_RETRY_DELAY_SECONDS = [1, 3];

    private function request(string $method, string $path, array $params = []): array
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->attemptRequest($method, $path, $params);
            } catch (TransientApiException $e) {
                if ($attempt >= self::MAX_QUICK_RETRIES) {
                    throw $e;
                }
                sleep(self::QUICK_RETRY_DELAY_SECONDS[$attempt] ?? 3);
                $attempt++;
            }
        }
    }

    /**
     * @throws TransientApiException for rate limits / network blips (retryable)
     * @throws RuntimeException for everything else (not worth retrying)
     */
    private function attemptRequest(string $method, string $path, array $params): array
    {
        $url = self::GRAPH_URL . $path;

        $ch = curl_init();

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new TransientApiException('Facebook API request failed: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if (is_array($decoded) && isset($decoded['error'])) {
            $err = $decoded['error'];
            $message = $err['message'] ?? 'Unknown Facebook API error';
            $code = $err['code'] ?? null;

            if (!empty($err['is_transient']) || in_array($code, self::RATE_LIMIT_CODES, true)) {
                throw new TransientApiException('Facebook API rate limit: ' . $message);
            }

            throw new RuntimeException('Facebook API error: ' . $message);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
