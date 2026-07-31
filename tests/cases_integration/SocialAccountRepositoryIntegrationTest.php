<?php

declare(strict_types=1);

use App\SocialAccountRepository;

integration_test('saveFacebookAccount() stores a token and computes its expiry', function () {
    $userId = make_user();

    $id = SocialAccountRepository::saveFacebookAccount($userId, 'fb_123', 'token_abc', 5184000); // ~60 days

    assertTrue($id > 0);
    $expiresAt = SocialAccountRepository::facebookTokenExpiresAt($userId);
    assertTrue($expiresAt !== null, 'expected an expiry to be recorded');
    assertTrue(strtotime($expiresAt) > time(), 'expiry should be in the future');
});

integration_test('saveFacebookAccount() upserts rather than duplicating on reconnect', function () {
    $userId = make_user();

    $first = SocialAccountRepository::saveFacebookAccount($userId, 'fb_123', 'token_one', 3600);
    $second = SocialAccountRepository::saveFacebookAccount($userId, 'fb_123', 'token_two', 7200);

    assertSame($first, $second, 'reconnecting should update the same row');

    $stmt = App\Database::connection()->prepare('SELECT COUNT(*) FROM social_accounts WHERE user_id = ?');
    $stmt->execute([$userId]);
    assertSame(1, (int) $stmt->fetchColumn());
});

integration_test('savePages() stores pages with their linked Instagram account', function () {
    $userId = make_user();
    $socialId = SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'tok', 3600);

    SocialAccountRepository::savePages($socialId, [
        [
            'id' => 'page_1',
            'name' => 'Coffee Shop',
            'access_token' => 'page_token_1',
            'instagram_business_account' => ['id' => 'ig_1', 'username' => 'coffeeshop'],
        ],
        [
            'id' => 'page_2',
            'name' => 'No Instagram',
            'access_token' => 'page_token_2',
        ],
    ]);

    $pages = SocialAccountRepository::pagesForUser($userId);
    assertSame(2, count($pages));

    $byName = [];
    foreach ($pages as $page) {
        $byName[$page['page_name']] = $page;
    }

    assertSame('ig_1', $byName['Coffee Shop']['ig_user_id']);
    assertSame('coffeeshop', $byName['Coffee Shop']['ig_username']);
    assertNull($byName['No Instagram']['ig_user_id']);
});

integration_test('savePages() refreshes an existing page instead of duplicating it', function () {
    $userId = make_user();
    $socialId = SocialAccountRepository::saveFacebookAccount($userId, 'fb_1', 'tok', 3600);

    SocialAccountRepository::savePages($socialId, [
        ['id' => 'page_1', 'name' => 'Old Name', 'access_token' => 'old_token'],
    ]);
    SocialAccountRepository::savePages($socialId, [
        ['id' => 'page_1', 'name' => 'New Name', 'access_token' => 'new_token'],
    ]);

    $pages = SocialAccountRepository::pagesForUser($userId);
    assertSame(1, count($pages), 'reconnecting must not duplicate the page');
    assertSame('New Name', $pages[0]['page_name']);
    assertSame('new_token', $pages[0]['page_access_token']);
});

integration_test('findPage() will not return another user\'s page', function () {
    $owner = make_user('owner@example.com');
    $attacker = make_user('attacker@example.com');
    $pageId = make_page(make_social_account($owner));

    assertTrue(SocialAccountRepository::findPage($owner, $pageId) !== null, 'owner should see their page');
    assertNull(
        SocialAccountRepository::findPage($attacker, $pageId),
        'another user must not be able to load this page'
    );
});

integration_test('pagesForUser() is scoped to the user', function () {
    $a = make_user('a@example.com');
    $b = make_user('b@example.com');
    make_page(make_social_account($a));
    make_page(make_social_account($b));

    assertSame(1, count(SocialAccountRepository::pagesForUser($a)));
});

integration_test('expiry reminders pick up connections inside the window', function () {
    $userId = make_user('soon@example.com');
    make_social_account($userId, date('Y-m-d H:i:s', strtotime('+3 days')));

    $due = SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7);
    assertSame(1, count($due));
    assertSame('soon@example.com', $due[0]['email']);
});

integration_test('expiry reminders ignore connections outside the window', function () {
    $userId = make_user('later@example.com');
    make_social_account($userId, date('Y-m-d H:i:s', strtotime('+30 days')));

    assertSame(0, count(SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7)));
});

integration_test('expiry reminders include already-expired connections', function () {
    $userId = make_user('expired@example.com');
    make_social_account($userId, date('Y-m-d H:i:s', strtotime('-2 days')));

    assertSame(1, count(SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7)));
});

integration_test('a user is not emailed twice for the same expiry date', function () {
    $userId = make_user('once@example.com');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days'));
    $socialId = make_social_account($userId, $expiresAt);

    $due = SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7);
    assertSame(1, count($due));

    SocialAccountRepository::markExpiryNotified($socialId, $due[0]['token_expires_at']);

    assertSame(
        0,
        count(SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7)),
        'the same expiry must not trigger a second email'
    );
});

integration_test('reconnecting re-arms the reminder for the new expiry date', function () {
    $userId = make_user('rearm@example.com');
    $oldExpiry = date('Y-m-d H:i:s', strtotime('+2 days'));
    $socialId = make_social_account($userId, $oldExpiry);

    // Notified for the current expiry.
    SocialAccountRepository::markExpiryNotified($socialId, $oldExpiry);
    assertSame(0, count(SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7)));

    // User reconnects; the new token expires at a different time, still
    // inside the window - they should be eligible again.
    SocialAccountRepository::saveFacebookAccount($userId, 'fb' . $userId, 'newtoken', 4 * 86400);

    assertSame(
        1,
        count(SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7)),
        'a new expiry date should make the user eligible for a fresh reminder'
    );
});

integration_test('connections with no known expiry are never reminded about', function () {
    $userId = make_user('noexpiry@example.com');
    make_social_account($userId, null);

    assertSame(0, count(SocialAccountRepository::facebookAccountsNeedingExpiryReminder(7)));
});
