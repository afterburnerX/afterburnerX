<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Generates advertising / growth suggestions via the Claude API.
 */
class AIAdvisor
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public static function suggest(string $businessDescription, ?string $goal): string
    {
        $apiKey = env('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $prompt = "You are a social media marketing advisor for small businesses.\n"
            . "Business: {$businessDescription}\n"
            . ($goal ? "Goal: {$goal}\n" : '')
            . "Give 5 concrete, actionable suggestions for advertising and growing this business on "
            . "Facebook and Instagram. Include post ideas, targeting tips, and posting cadence. "
            . "Use short bullet points.";

        $payload = json_encode([
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
            'max_tokens' => 800,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $ch = curl_init(self::API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('AI request failed: ' . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);

        if (isset($decoded['error'])) {
            throw new RuntimeException('AI API error: ' . ($decoded['error']['message'] ?? 'unknown'));
        }

        $text = '';
        foreach ($decoded['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        return trim($text) ?: 'No suggestion returned.';
    }
}
