# AfterburnerX

A small PHP + MySQL platform where users create an account, connect their
Facebook Page (and its linked Instagram Business account), then post to
both immediately or on a schedule, and get AI-generated advertising
suggestions for their business.

Plain PHP (no framework, no Composer dependencies) so it runs on any
standard LAMP/LEMP host. Uses PDO/MySQLi-compatible PDO for the database
and raw cURL for the Meta Graph API and Claude API calls.

## Stack

- PHP 8.1+ (PDO MySQL, cURL, OpenSSL extensions)
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite`/`mod_authz_core` (for the included `.htaccess`), or equivalent Nginx config
- A cron runner for the scheduler worker

## Project layout

```
config/bootstrap.php               Env loading, session start, autoloader
src/                                Application classes (App\ namespace)
public/                             Web root — point your vhost/docroot here
cron/run_scheduler.php              Publishes due scheduled posts (run every minute)
cron/send_expiry_reminders.php      Emails users with an expiring Facebook connection (run daily)
database/schema.sql                 MySQL schema
```

## Setup

1. **Database**
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   If you already ran `schema.sql` from an earlier checkout, apply new
   changes from `database/migrations/` in filename order instead of
   re-running the whole schema.

2. **Config**
   ```bash
   cp .env.example .env
   ```
   Fill in `DB_*`, `FB_APP_ID`, `FB_APP_SECRET`, `FB_REDIRECT_URI`, and
   `ANTHROPIC_API_KEY`.

3. **Web server document root** → point it at `public/`. If your host
   can't change the docroot, the root `.htaccess` denies direct access to
   `src/`, `config/`, and `.env` as a fallback (Apache only — for Nginx
   add an equivalent `location` block denying everything outside `/public`).

4. **Scheduler cron** (every minute):
   ```
   * * * * * php /full/path/to/afterburnerX/cron/run_scheduler.php >> /full/path/to/afterburnerX/storage/scheduler.log 2>&1
   ```

5. **Expiry-reminder cron** (once a day):
   ```
   0 9 * * * php /full/path/to/afterburnerX/cron/send_expiry_reminders.php >> /full/path/to/afterburnerX/storage/mail.log 2>&1
   ```
   Create the `storage/` directory if you want file logging for either cron job.

## Setting up the Facebook app (required before anything can post)

1. Create an app at [developers.facebook.com](https://developers.facebook.com/) → type **Business**.
2. Add the **Facebook Login** product. Under its settings, add your exact
   callback URL to **Valid OAuth Redirect URIs**:
   `https://yourdomain.com/facebook-callback.php`
3. Add the **Instagram** product if you want IG publishing (Instagram
   posting works through a Facebook Page's linked Instagram Business
   Account — there's no separate IG-only login in this app).
4. In **App Review → Permissions and Features**, request:
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`
   - `instagram_basic`
   - `instagram_content_publish`
   - `business_management`

   Until these are approved, the app only works with users added as
   **Testers/Developers/Admins** on the app (Roles tab) — fine for
   building and testing, not for the public.
5. **Business verification**: Meta requires this before granting the
   permissions above for public use. You'll need a live privacy policy
   URL, terms of service, and often a screencast showing the exact
   OAuth → connect → post flow. Budget real calendar time for this step —
   it's typically the slowest part of shipping, not the code.
6. Facebook user tokens exchanged via `longLivedToken()` last ~60 days;
   there's no refresh token, so users will periodically need to
   reconnect (click "Reconnect" on the dashboard) — this app doesn't
   silently re-auth for them.

## How posting works

- **Immediate**: `compose.php` calls `PostPublisher::publish()` synchronously and shows success/failure right away.
- **Scheduled**: the same row is written with `status = 'pending'` and a future `scheduled_at`; `cron/run_scheduler.php` polls every minute for due rows and calls the same `PostPublisher::publish()`.
- **Instagram**: always a two-step Graph API call — create a media container from an image URL, then publish it. The Graph API needs a URL it can fetch, so `compose.php` accepts either an uploaded image (saved under `public/uploads/`, served back as a public URL) or a pasted image URL. Set `APP_URL` in `.env` if the app runs behind a proxy/load balancer so uploaded-file URLs are built correctly.
- **Token health**: the dashboard warns when the connected Facebook account's long-lived token is within 7 days of expiring (or already expired) and links to reconnect. There's still no automatic refresh — Meta doesn't issue one — so this is a manual "click to reconnect" flow, not silent renewal.
- **Cancel**: pending scheduled posts can be canceled from `posts.php` any time before they publish.
- **Rate limits**: `FacebookClient` treats Graph API rate-limit errors (codes 4/17/32/613, or `is_transient`) and network blips as retryable — 2 quick in-process retries first, then the post is left `pending` with an exponential backoff (2m → 5m → 15m → 30m → 60m) so the cron worker retries it automatically, up to `PostRepository::MAX_ATTEMPTS` (6) before it's marked permanently failed. An immediate "post now" that hits a rate limit falls back to this same queued retry instead of just failing.

## AI suggestions

`suggestions.php` sends the business description + goal to the Claude API
(`ANTHROPIC_API_KEY`) and stores each response so users can browse past
suggestions.

## Email reminders

`cron/send_expiry_reminders.php` (run daily) emails any user whose
Facebook connection is within 7 days of expiring, or already has. It
tracks `social_accounts.expiry_notified_for` so each user gets exactly
one email per expiry date — reconnecting gets a new `token_expires_at`,
which naturally re-arms the reminder for the new expiry.

No PHPMailer/Symfony Mailer dependency: `App\Mailer` is a small
hand-rolled SMTP client (EHLO/STARTTLS/AUTH LOGIN/MAIL FROM/RCPT
TO/DATA over a raw socket) that's used if `SMTP_HOST` is set in `.env`,
and falls back to PHP's `mail()` otherwise. Point `SMTP_HOST` at any
standard SMTP provider (SES, Mailgun, Postmark, your own Postfix, etc.)
— nothing provider-specific is assumed.

TLS certificates are verified by default. `SMTP_VERIFY_PEER=false`
disables that for relays behind a private/internal CA — it's a real
escape hatch for that case, not something to set routinely, since it
makes the connection trivially MITM-able.

This client has been exercised against a local test SMTP server covering
the plaintext, AUTH LOGIN, and STARTTLS paths (including a real TLS
handshake, and confirming a bad password and an untrusted certificate
are both rejected rather than silently "sent"). What that doesn't cover
is provider-specific behavior in the wild — most notably servers that
only offer `AUTH PLAIN`, which this client does not implement. If your
provider requires it, that's a small addition to `sendViaSmtp()`.

## Upload storage

`STORAGE_DRIVER` in `.env` controls where uploaded post images go:

- `local` (default) — saved under `public/uploads/`, served from this
  server. Simplest option, fine until you run more than one app server.
- `s3` — uploaded straight to any S3-compatible object store (AWS S3,
  Cloudflare R2, DigitalOcean Spaces, MinIO, ...) via `App\S3Uploader`,
  a small hand-rolled AWS Signature Version 4 signer over cURL — no AWS
  SDK dependency. Configure `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`,
  `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, and optionally
  `S3_PATH_STYLE` / `S3_PUBLIC_URL_BASE` (e.g. to point at a CDN domain
  in front of the bucket). Objects are uploaded `public-read` since the
  Graph API needs to be able to fetch the image URL directly.

  The upload has been exercised end-to-end against a local test endpoint
  that independently recomputes the SigV4 signature and rejects a
  mismatch — covering both path-style and virtual-hosted-style URLs, and
  confirmed (via a deliberately wrong secret) that the check really does
  reject bad signatures rather than rubber-stamping them.

  Still worth knowing before production: that test endpoint is not a real
  S3 implementation, so provider-specific behavior (bucket policies that
  refuse `public-read` ACLs — increasingly the default on AWS — regional
  redirects, or R2's ACL handling, which differs from S3's) is unverified.
  Do one real upload against your actual provider and confirm the
  returned URL is publicly fetchable, since the Graph API has to be able
  to fetch it.

## Running tests

```bash
php tests/run.php
```

No PHPUnit/Composer required, on purpose — this project has no runtime
dependencies so it can drop onto plain shared hosting, and pulling in a
dev-only dependency tree just for tests didn't seem worth breaking that.
`tests/run.php` is a ~70-line runner; test cases live in `tests/cases/*.php`
as `test('name', function () { ... });` blocks using `assertSame()` /
`assertTrue()` / `assertFalse()` / `assertNull()`. Coverage today is the
pure logic that's cheap to isolate (CSRF tokens, rate-limit
classification, retry backoff schedule, upload MIME validation) — nothing
that needs a real database or live Facebook/Claude credentials.

## Known limitations / next steps

- No queue/worker beyond a once-a-minute cron poll (fine at small scale; move to a real queue if volume grows).
- Test coverage is limited to pure logic — no integration tests against a real (or in-memory) database yet.
- `App\Mailer` implements `AUTH LOGIN` only, not `AUTH PLAIN`; and neither it nor `App\S3Uploader` has been run against a real provider — see the notes under "Email reminders" and "Upload storage".
- `S3Uploader` doesn't URL-encode object keys. Keys generated by `MediaUploader` are hex + extension so this is safe today, but it would need encoding before accepting user-supplied filenames.
