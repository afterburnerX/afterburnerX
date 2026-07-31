<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Stores an uploaded image under public/uploads and returns a public URL
 * for it, since the Graph API needs a URL it can fetch (image_url /
 * link), not a raw file upload.
 */
class MediaUploader
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    private const ALLOWED_MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function store(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No file was uploaded.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed (error code ' . $file['error'] . ').');
        }

        if ($file['size'] > self::MAX_BYTES) {
            throw new RuntimeException('Image is too large (max 8MB).');
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid upload.');
        }

        $mime = (string) mime_content_type($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME_TO_EXT[$mime])) {
            throw new RuntimeException('Unsupported image type. Use JPEG, PNG, or WEBP.');
        }

        $extension = self::ALLOWED_MIME_TO_EXT[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        $uploadDir = BASE_PATH . '/public/uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Upload directory is not available.');
        }

        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException('Could not save uploaded file.');
        }

        return self::publicBaseUrl() . '/uploads/' . $filename;
    }

    private static function publicBaseUrl(): string
    {
        $configured = env('APP_URL');
        if ($configured) {
            return rtrim($configured, '/');
        }

        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}
