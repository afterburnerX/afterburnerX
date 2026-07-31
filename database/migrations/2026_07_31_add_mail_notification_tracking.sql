-- Run this against a database that was already created from an earlier
-- version of schema.sql (before email token-expiry reminders were added).
-- A fresh `mysql -u root -p < database/schema.sql` import already includes it.

USE afterburnerx;

ALTER TABLE social_accounts
  ADD COLUMN expiry_notified_for DATETIME NULL AFTER token_expires_at;
