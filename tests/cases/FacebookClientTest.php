<?php

declare(strict_types=1);

use App\FacebookClient;

test('isTransientError() flags known Graph API rate-limit codes', function () {
    foreach ([4, 17, 32, 613] as $code) {
        assertTrue(FacebookClient::isTransientError(['code' => $code]), "code {$code} should be transient");
    }
});

test('isTransientError() flags errors Meta explicitly marks is_transient', function () {
    assertTrue(FacebookClient::isTransientError(['code' => 999, 'is_transient' => true]));
});

test('isTransientError() treats an unrecognized error code as permanent', function () {
    assertFalse(FacebookClient::isTransientError(['code' => 100, 'message' => 'Invalid parameter']));
});

test('isTransientError() treats a missing error as permanent', function () {
    assertFalse(FacebookClient::isTransientError(null));
});

test('isTransientError() treats an error with no code and no is_transient flag as permanent', function () {
    assertFalse(FacebookClient::isTransientError(['message' => 'Something went wrong']));
});
