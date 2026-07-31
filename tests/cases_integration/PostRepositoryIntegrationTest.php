<?php

declare(strict_types=1);

use App\PostRepository;

integration_test('schedule() stores a pending post and returns its id', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));

    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'Hello', null, null, '2030-01-01 10:00:00');

    $row = post_row($postId);
    assertSame('pending', $row['status']);
    assertSame('Hello', $row['message']);
    assertSame('facebook', $row['target']);
    assertSame(0, (int) $row['attempts']);
});

integration_test('due() returns posts whose scheduled_at has passed', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));

    $pastId = PostRepository::schedule($userId, $pageId, 'facebook', 'past', null, null, '2020-01-01 00:00:00');
    PostRepository::schedule($userId, $pageId, 'facebook', 'future', null, null, '2030-01-01 00:00:00');

    $due = PostRepository::due();
    assertSame(1, count($due));
    assertSame($pastId, (int) $due[0]['id']);
});

integration_test('due() excludes posts that are not pending', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));

    $postedId = PostRepository::schedule($userId, $pageId, 'facebook', 'a', null, null, '2020-01-01 00:00:00');
    PostRepository::markPosted($postedId, 'remote_1');

    $failedId = PostRepository::schedule($userId, $pageId, 'facebook', 'b', null, null, '2020-01-01 00:00:00');
    PostRepository::markFailed($failedId, 'nope');

    $canceledId = PostRepository::schedule($userId, $pageId, 'facebook', 'c', null, null, '2020-01-01 00:00:00');
    PostRepository::cancel($canceledId, $userId);

    assertSame(0, count(PostRepository::due()));
});

integration_test('due() honours next_attempt_at so backed-off posts are not retried early', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));

    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'rate limited', null, null, '2020-01-01 00:00:00');
    assertSame(1, count(PostRepository::due()), 'should be due before any failure');

    PostRepository::markTransientFailure($postId, 0, 'rate limited');

    // Backoff for attempt 1 is 2 minutes, so it must not come back immediately.
    assertSame(0, count(PostRepository::due()), 'should be held back by next_attempt_at');
});

integration_test('a backed-off post becomes due again once next_attempt_at passes', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));

    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2020-01-01 00:00:00');
    PostRepository::markTransientFailure($postId, 0, 'rate limited');

    // Simulate the backoff window having elapsed.
    App\Database::connection()
        ->prepare('UPDATE scheduled_posts SET next_attempt_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = ?')
        ->execute([$postId]);

    assertSame(1, count(PostRepository::due()));
});

integration_test('markTransientFailure() increments attempts and keeps the post pending', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));
    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2020-01-01 00:00:00');

    PostRepository::markTransientFailure($postId, 0, 'rate limited');

    $row = post_row($postId);
    assertSame('pending', $row['status']);
    assertSame(1, (int) $row['attempts']);
    assertSame('rate limited', $row['error_message']);
    assertTrue($row['next_attempt_at'] !== null, 'expected a next_attempt_at to be set');
});

integration_test('markTransientFailure() gives up permanently at MAX_ATTEMPTS', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));
    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2020-01-01 00:00:00');

    // One short of the cap: still pending.
    PostRepository::markTransientFailure($postId, PostRepository::MAX_ATTEMPTS - 2, 'still trying');
    assertSame('pending', post_row($postId)['status']);

    // Reaching the cap flips it to failed.
    PostRepository::markTransientFailure($postId, PostRepository::MAX_ATTEMPTS - 1, 'last straw');
    $row = post_row($postId);
    assertSame('failed', $row['status']);
    assertTrue(str_contains($row['error_message'], 'Gave up'), 'expected a "gave up" message');
});

integration_test('markPosted() clears any previous error message', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));
    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2020-01-01 00:00:00');

    PostRepository::markTransientFailure($postId, 0, 'transient blip');
    PostRepository::markPosted($postId, 'remote_abc');

    $row = post_row($postId);
    assertSame('posted', $row['status']);
    assertSame('remote_abc', $row['remote_post_id']);
    assertNull($row['error_message']);
});

integration_test('cancel() cancels a pending post owned by the user', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));
    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2030-01-01 00:00:00');

    PostRepository::cancel($postId, $userId);

    assertSame('canceled', post_row($postId)['status']);
});

integration_test('cancel() will not touch another user\'s post', function () {
    $owner = make_user('owner@example.com');
    $attacker = make_user('attacker@example.com');
    $pageId = make_page(make_social_account($owner));
    $postId = PostRepository::schedule($owner, $pageId, 'facebook', 'x', null, null, '2030-01-01 00:00:00');

    PostRepository::cancel($postId, $attacker);

    assertSame('pending', post_row($postId)['status'], 'another user must not be able to cancel this post');
});

integration_test('cancel() will not resurrect an already-posted post', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));
    $postId = PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2020-01-01 00:00:00');
    PostRepository::markPosted($postId, 'remote_1');

    PostRepository::cancel($postId, $userId);

    assertSame('posted', post_row($postId)['status'], 'a published post must stay published');
});

integration_test('forUser() returns only that user\'s posts', function () {
    $a = make_user('a@example.com');
    $b = make_user('b@example.com');
    $pageA = make_page(make_social_account($a));
    $pageB = make_page(make_social_account($b));

    PostRepository::schedule($a, $pageA, 'facebook', 'from a', null, null, '2030-01-01 00:00:00');
    PostRepository::schedule($b, $pageB, 'facebook', 'from b', null, null, '2030-01-01 00:00:00');

    $posts = PostRepository::forUser($a);
    assertSame(1, count($posts));
    assertSame('from a', $posts[0]['message']);
});

integration_test('due() exposes the page fields PostPublisher needs', function () {
    $userId = make_user();
    $socialId = make_social_account($userId);
    $pageId = make_page($socialId, 'ig12345');
    PostRepository::schedule($userId, $pageId, 'instagram', 'x', 'http://img', null, '2020-01-01 00:00:00');

    $due = PostRepository::due()[0];

    // PostPublisher::publish() reads exactly these keys.
    assertSame('fbpage' . $socialId, $due['fb_page_id']);
    assertSame('pagetoken' . $socialId, $due['page_access_token']);
    assertSame('ig12345', $due['ig_user_id']);
});
