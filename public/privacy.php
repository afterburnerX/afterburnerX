<?php
/**
 * Privacy policy. Meta requires a reachable privacy policy URL for App
 * Review.
 *
 * The data-handling sections below accurately describe what this
 * application actually does. The legal framing is a starting template
 * only - have a lawyer review it, and fill in COMPANY_* in .env.
 */

require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/partials/legal_placeholder.php';

$updated = 'August 4, 2026';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy — AfterburnerX</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<main class="container narrow">
  <h1>Privacy Policy</h1>
  <p class="muted small">Last updated: <?= htmlspecialchars($updated) ?></p>

  <?php if (!legal_is_configured()): ?>
    <p class="alert">
      This policy still contains unfilled placeholders. Set
      <code>COMPANY_NAME</code>, <code>COMPANY_CONTACT_EMAIL</code>, and
      <code>COMPANY_ADDRESS</code> in <code>.env</code>, and have the text
      reviewed by a lawyer, before submitting for Meta App Review.
    </p>
  <?php endif; ?>

  <div class="card">
    <p>
      This policy explains what <?= legal_value('COMPANY_NAME', 'Company name') ?>
      ("we", "us") collects when you use AfterburnerX, why we collect it, and
      how you can have it deleted.
    </p>

    <h2>What we collect</h2>
    <ul>
      <li><strong>Account details.</strong> Your name and email address. Your password is stored only as a one-way hash — we never store or see the password itself.</li>
      <li><strong>Facebook and Instagram connection data.</strong> When you connect your account we store your Facebook user ID, an access token, and the Facebook Pages and linked Instagram Business accounts you authorise, including their names, IDs, and per-Page access tokens. We request only the permissions needed to list your Pages and publish posts to them.</li>
      <li><strong>Content you create.</strong> The text, links, scheduled times, and images of posts you compose, along with the resulting status and any error messages.</li>
      <li><strong>Uploaded images.</strong> Images you attach to posts, which must be publicly reachable by URL because Meta's publishing API fetches them from us.</li>
      <li><strong>AI suggestion inputs.</strong> The business descriptions and goals you submit for advertising suggestions, and the suggestions returned.</li>
    </ul>

    <h2>How we use it</h2>
    <p>
      Solely to operate the service: to authenticate you, to publish the posts
      you schedule to the accounts you connected, to show you their status, to
      generate advertising suggestions you request, and to email you when a
      connection is about to expire.
    </p>
    <p><strong>We do not sell your data or use it for advertising.</strong></p>

    <h2>Who we share it with</h2>
    <ul>
      <li><strong>Meta (Facebook/Instagram)</strong> — post content and images are sent to Meta's Graph API in order to publish them, at your instruction.</li>
      <li><strong>Anthropic</strong> — the business description and goal you enter on the AI suggestions page are sent to the Claude API to generate a response.</li>
      <li><strong>Our hosting, object storage, and email providers</strong> — to the extent needed to store data and deliver notification emails.</li>
    </ul>
    <p>We do not otherwise share your data, except where legally required.</p>

    <h2>Retention</h2>
    <p>
      We keep your data for as long as your account exists. Facebook access
      tokens are long-lived but expire on Meta's schedule (roughly 60 days),
      after which they are useless until you reconnect.
    </p>

    <h2>Deleting your data</h2>
    <p>There are three routes, and all of them work:</p>
    <ul>
      <li><strong>Disconnect Facebook</strong> from your AfterburnerX dashboard. This removes the stored tokens, your Pages and Instagram accounts, and any posts scheduled to them.</li>
      <li><strong>Remove the app from Facebook</strong> (Facebook Settings → Apps and Websites). Facebook notifies us and we delete the same data automatically.</li>
      <li><strong>Request deletion through Facebook</strong>, which sends us a deletion request and gives you a confirmation code you can check on our <a href="/deletion-status.php">deletion status page</a>.</li>
    </ul>
    <p>
      To delete your entire AfterburnerX account — including your email address
      and AI suggestion history, which are not Facebook data — use "Delete my
      account" on your dashboard, or contact us at
      <?= legal_value('COMPANY_CONTACT_EMAIL', 'Contact email') ?>.
    </p>

    <h2>Security</h2>
    <p>
      Passwords are hashed. Traffic should be served over HTTPS. Access
      tokens are stored so we can post on your behalf at the times you
      schedule; treat access to your account as access to posting on your
      connected Pages, and use a strong, unique password.
    </p>

    <h2>Contact</h2>
    <p>
      <?= legal_value('COMPANY_NAME', 'Company name') ?><br>
      <?= legal_value('COMPANY_ADDRESS', 'Company address') ?><br>
      <?= legal_value('COMPANY_CONTACT_EMAIL', 'Contact email') ?>
    </p>
  </div>
</main>
</body>
</html>
