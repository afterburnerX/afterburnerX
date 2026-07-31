<?php

declare(strict_types=1);

use App\PostRepository;

test('backoffSecondsForAttempt() follows the documented escalation schedule', function () {
    assertSame(120, PostRepository::backoffSecondsForAttempt(1));
    assertSame(300, PostRepository::backoffSecondsForAttempt(2));
    assertSame(900, PostRepository::backoffSecondsForAttempt(3));
    assertSame(1800, PostRepository::backoffSecondsForAttempt(4));
    assertSame(3600, PostRepository::backoffSecondsForAttempt(5));
});

test('backoffSecondsForAttempt() defaults to an hour beyond the mapped attempts', function () {
    assertSame(3600, PostRepository::backoffSecondsForAttempt(6));
    assertSame(3600, PostRepository::backoffSecondsForAttempt(99));
});

test('MAX_ATTEMPTS is higher than the last mapped backoff attempt', function () {
    // Otherwise markTransientFailure() would give up before ever using
    // the longest backoff step.
    assertTrue(PostRepository::MAX_ATTEMPTS > 5);
});
