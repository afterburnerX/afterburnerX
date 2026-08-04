-- Run this against a database that was already created from an earlier
-- version of schema.sql (before the Meta data deletion callback existed).
-- A fresh `mysql -u root -p < database/schema.sql` import already includes it.

USE afterburnerx;

CREATE TABLE IF NOT EXISTS deletion_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  confirmation_code VARCHAR(64) NOT NULL UNIQUE,
  fb_user_id VARCHAR(64) NOT NULL,
  status ENUM('completed','nothing_to_delete') NOT NULL DEFAULT 'completed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fb_user (fb_user_id)
) ENGINE=InnoDB;
