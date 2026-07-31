<?php
/**
 * Emails users whose Facebook connection is within 7 days of expiring
 * (or already expired). Run once a day, e.g. via crontab:
 *   0 9 * * * php /path/to/afterburnerX/cron/send_expiry_reminders.php >> /path/to/afterburnerX/storage/mail.log 2>&1
 */

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

use App\Mailer;
use App\SocialAccountRepository;

const REMINDER_WINDOW_DAYS = 7;

$accounts = SocialAccountRepository::facebookAccountsNeedingExpiryReminder(REMINDER_WINDOW_DAYS);

if (!$accounts) {
    echo date('c') . " No expiry reminders due.\n";
}

$appUrl = rtrim((string) env('APP_URL', ''), '/');
$reconnectUrl = $appUrl !== '' ? $appUrl . '/facebook-connect.php' : '/facebook-connect.php';

foreach ($accounts as $account) {
    $daysLeft = (int) floor((strtotime($account['token_expires_at']) - time()) / 86400);

    $subject = $daysLeft < 0
        ? 'Your AfterburnerX Facebook connection has expired'
        : "Your AfterburnerX Facebook connection expires in {$daysLeft} day(s)";

    $situation = $daysLeft < 0
        ? "Your Facebook connection on AfterburnerX has expired, so scheduled and immediate posts will fail until you reconnect."
        : "Your Facebook connection on AfterburnerX expires in {$daysLeft} day(s). Reconnect before then to avoid interrupted posting.";

    $body = "Hi {$account['name']},\n\n{$situation}\n\nReconnect here: {$reconnectUrl}\n\n- AfterburnerX";

    $sent = Mailer::send($account['email'], $subject, $body);

    if ($sent) {
        SocialAccountRepository::markExpiryNotified((int) $account['id'], $account['token_expires_at']);
    }

    echo date('c') . ' ' . ($sent ? '[OK]' : '[FAIL]')
        . " reminder for social_accounts.id={$account['id']} ({$account['email']})\n";
}
