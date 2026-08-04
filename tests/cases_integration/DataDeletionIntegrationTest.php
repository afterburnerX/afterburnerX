<?php

declare(strict_types=1);

use App\DataDeletionRepository;
use App\PostRepository;
use App\SocialAccountRepository;

function count_rows(string $table): int
{
    return (int) App\Database::connection()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

integration_test('deleting Facebook data removes the connection, pages, and their posts', function () {
    $userId = make_user();
    $socialId = make_social_account($userId);
    $pageId = make_page($socialId, 'ig_1');
    PostRepository::schedule($userId, $pageId, 'facebook', 'scheduled', null, null, '2030-01-01 00:00:00');

    $deleted = DataDeletionRepository::deleteFacebookDataForFbUser('fb' . $userId);

    assertTrue($deleted, 'should report that data was deleted');
    assertSame(0, count_rows('social_accounts'));
    assertSame(0, count_rows('pages'), 'pages should cascade');
    assertSame(0, count_rows('scheduled_posts'), 'posts to those pages should cascade');
});

integration_test('deleting Facebook data keeps the account and AI suggestions', function () {
    $userId = make_user();
    make_page(make_social_account($userId));
    App\AISuggestionRepository::save($userId, 'A coffee shop', 'more foot traffic', 'Post more often.');

    DataDeletionRepository::deleteFacebookDataForFbUser('fb' . $userId);

    assertSame(1, count_rows('users'), 'the platform account is not Facebook data');
    assertSame(1, count_rows('ai_suggestions'), 'suggestion history is not Facebook data');
});

integration_test('deleting Facebook data reports false when there is nothing stored', function () {
    assertFalse(DataDeletionRepository::deleteFacebookDataForFbUser('fb_never_connected'));
});

integration_test('deleting one user\'s Facebook data leaves other users untouched', function () {
    $a = make_user('a@example.com');
    $b = make_user('b@example.com');
    make_page(make_social_account($a));
    make_page(make_social_account($b));

    DataDeletionRepository::deleteFacebookDataForFbUser('fb' . $a);

    assertSame(1, count_rows('social_accounts'), "the other user's connection must survive");
    assertSame(1, count(SocialAccountRepository::pagesForUser($b)));
});

integration_test('deleteAccount() removes the user and everything belonging to them', function () {
    $userId = make_user();
    $pageId = make_page(make_social_account($userId));
    PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2030-01-01 00:00:00');
    App\AISuggestionRepository::save($userId, 'A shop', null, 'Some advice.');

    DataDeletionRepository::deleteAccount($userId);

    assertSame(0, count_rows('users'));
    assertSame(0, count_rows('social_accounts'));
    assertSame(0, count_rows('pages'));
    assertSame(0, count_rows('scheduled_posts'));
    assertSame(0, count_rows('ai_suggestions'));
});

integration_test('a deletion request is recorded and retrievable by its confirmation code', function () {
    $code = DataDeletionRepository::recordRequest('fb_123', true);

    $request = DataDeletionRepository::findRequest($code);
    assertTrue($request !== null, 'the request should be findable');
    assertSame('fb_123', $request['fb_user_id']);
    assertSame('completed', $request['status']);
});

integration_test('a request for a user with no stored data is marked nothing_to_delete', function () {
    $code = DataDeletionRepository::recordRequest('fb_unknown', false);

    assertSame('nothing_to_delete', DataDeletionRepository::findRequest($code)['status']);
});

integration_test('an unknown confirmation code returns nothing', function () {
    assertNull(DataDeletionRepository::findRequest('not_a_real_code'));
});

integration_test('confirmation codes are unique across requests', function () {
    $a = DataDeletionRepository::recordRequest('fb_1', true);
    $b = DataDeletionRepository::recordRequest('fb_1', true);

    assertFalse($a === $b, 'each request should get its own code');
});

integration_test('facebookUserId() returns the connected id, or null when not connected', function () {
    $connected = make_user('connected@example.com');
    make_social_account($connected);
    $notConnected = make_user('solo@example.com');

    assertSame('fb' . $connected, SocialAccountRepository::facebookUserId($connected));
    assertNull(SocialAccountRepository::facebookUserId($notConnected));
});
