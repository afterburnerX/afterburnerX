<?php

declare(strict_types=1);

use App\Crypto;
use App\PostRepository;
use App\SocialAccountRepository;

function raw_column(string $table, string $column): string
{
    return (string) App\Database::connection()
        ->query("SELECT {$column} FROM {$table} LIMIT 1")
        ->fetchColumn();
}

integration_test('the user access token is stored encrypted, not in plaintext', function () {
    $userId = make_user();
    SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'EAAsecretusertoken', 3600);

    $stored = raw_column('social_accounts', 'access_token');

    assertFalse(
        str_contains($stored, 'EAAsecretusertoken'),
        'a database dump must not reveal the token'
    );
    assertTrue(Crypto::isEncrypted($stored));
});

integration_test('page access tokens are stored encrypted', function () {
    $userId = make_user();
    $socialId = SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'usertok', 3600);

    SocialAccountRepository::savePages($socialId, [
        ['id' => 'p1', 'name' => 'My Page', 'access_token' => 'EAApagesecrettoken'],
    ]);

    $stored = raw_column('pages', 'page_access_token');

    assertFalse(str_contains($stored, 'EAApagesecrettoken'), 'page token must not be readable');
    assertTrue(Crypto::isEncrypted($stored));
});

integration_test('pagesForUser() returns a usable decrypted token', function () {
    $userId = make_user();
    $socialId = SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'usertok', 3600);
    SocialAccountRepository::savePages($socialId, [
        ['id' => 'p1', 'name' => 'My Page', 'access_token' => 'EAApagetoken123'],
    ]);

    $pages = SocialAccountRepository::pagesForUser($userId);

    assertSame('EAApagetoken123', $pages[0]['page_access_token']);
});

integration_test('findPage() returns a usable decrypted token', function () {
    $userId = make_user();
    $socialId = SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'usertok', 3600);
    SocialAccountRepository::savePages($socialId, [
        ['id' => 'p1', 'name' => 'My Page', 'access_token' => 'EAAfindpagetoken'],
    ]);
    $pageId = (int) SocialAccountRepository::pagesForUser($userId)[0]['id'];

    $page = SocialAccountRepository::findPage($userId, $pageId);

    assertSame('EAAfindpagetoken', $page['page_access_token']);
});

integration_test('the scheduler gets a decrypted token it can actually publish with', function () {
    $userId = make_user();
    $socialId = SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'usertok', 3600);
    SocialAccountRepository::savePages($socialId, [
        ['id' => 'p1', 'name' => 'My Page', 'access_token' => 'EAAschedulertoken'],
    ]);
    $pageId = (int) SocialAccountRepository::pagesForUser($userId)[0]['id'];
    PostRepository::schedule($userId, $pageId, 'facebook', 'x', null, null, '2020-01-01 00:00:00');

    $due = PostRepository::due()[0];

    // PostPublisher passes this straight to the Graph API.
    assertSame('EAAschedulertoken', $due['page_access_token']);
});

integration_test('rows written before encryption are still readable afterwards', function () {
    $userId = make_user();
    $socialId = make_social_account($userId);

    // Simulate a legacy row: plaintext token written directly.
    App\Database::connection()
        ->prepare('INSERT INTO pages (social_account_id, page_id, page_name, page_access_token) VALUES (?, ?, ?, ?)')
        ->execute([$socialId, 'legacy_page', 'Legacy Page', 'PLAINTEXT_LEGACY_TOKEN']);

    $pages = SocialAccountRepository::pagesForUser($userId);

    assertSame(
        'PLAINTEXT_LEGACY_TOKEN',
        $pages[0]['page_access_token'],
        'an install that upgrades must keep working before tokens are migrated'
    );
});

integration_test('reconnecting re-encrypts with a fresh ciphertext', function () {
    $userId = make_user();
    SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'sametoken', 3600);
    $first = raw_column('social_accounts', 'access_token');

    SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'sametoken', 3600);
    $second = raw_column('social_accounts', 'access_token');

    assertFalse($first === $second, 'random nonce should produce a different ciphertext each time');
});
