-- Run this against a database that was already created from an earlier
-- version of schema.sql (before rate-limit retry backoff was added).
-- A fresh `mysql -u root -p < database/schema.sql` import already includes it.

USE afterburnerx;

ALTER TABLE scheduled_posts
  ADD COLUMN attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER error_message,
  ADD COLUMN next_attempt_at DATETIME NULL AFTER attempts;

ALTER TABLE scheduled_posts
  DROP INDEX idx_due,
  ADD INDEX idx_due (status, scheduled_at, next_attempt_at);
