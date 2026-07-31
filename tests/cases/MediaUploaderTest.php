<?php

declare(strict_types=1);

use App\MediaUploader;

test('allowedExtensionForMime() maps every supported image type', function () {
    assertSame('jpg', MediaUploader::allowedExtensionForMime('image/jpeg'));
    assertSame('png', MediaUploader::allowedExtensionForMime('image/png'));
    assertSame('webp', MediaUploader::allowedExtensionForMime('image/webp'));
});

test('allowedExtensionForMime() rejects unsupported types', function () {
    assertNull(MediaUploader::allowedExtensionForMime('application/pdf'));
    assertNull(MediaUploader::allowedExtensionForMime('image/gif'));
    assertNull(MediaUploader::allowedExtensionForMime('text/html'));
});
