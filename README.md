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
config/bootstrap.php     Env loading, session start, autoloader
src/                      Application classes (App\ namespace)
public/                   Web root — point your vhost/docroot here
cron/run_scheduler.php    Publishes due scheduled posts (run every minute)
database/schema.sql       MySQL schema
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
   Create the `storage/` directory if you want file logging.

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

## Known limitations / next steps

- Token-expiry warning is shown in-app only — no email/push reminder when a connection is about to expire.
- No queue/worker beyond a once-a-minute cron poll (fine at small scale; move to a real queue if volume grows).
- Uploaded images are stored on local disk under `public/uploads/` — fine for a single server, but move to object storage (S3-compatible) before scaling to multiple app servers.
- No automated tests yet.
