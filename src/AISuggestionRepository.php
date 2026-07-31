<?php

declare(strict_types=1);

namespace App;

class AISuggestionRepository
{
    public static function save(int $userId, string $businessDescription, ?string $goal, string $suggestion): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO ai_suggestions (user_id, business_description, goal, suggestion) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $businessDescription, $goal, $suggestion]);

        return (int) $db->lastInsertId();
    }

    public static function forUser(int $userId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM ai_suggestions WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }
}
