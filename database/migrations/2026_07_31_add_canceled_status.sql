-- Run this against a database that was already created from an earlier
-- version of schema.sql (before "canceled" was added as a post status).
-- A fresh `mysql -u root -p < database/schema.sql` import already includes it.

USE afterburnerx;

ALTER TABLE scheduled_posts
  MODIFY COLUMN status ENUM('pending','posted','failed','canceled') NOT NULL DEFAULT 'pending';
