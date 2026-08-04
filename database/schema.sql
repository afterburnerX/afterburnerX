-- AfterburnerX schema
-- Import with: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS afterburnerx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE afterburnerx;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS social_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL DEFAULT 'facebook',
  fb_user_id VARCHAR(64) NOT NULL,
  access_token TEXT NOT NULL,
  token_expires_at DATETIME NULL,
  expiry_notified_for DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_provider (user_id, provider),
  CONSTRAINT fk_social_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  social_account_id INT UNSIGNED NOT NULL,
  page_id VARCHAR(64) NOT NULL,
  page_name VARCHAR(190) NOT NULL,
  page_access_token TEXT NOT NULL,
  ig_user_id VARCHAR(64) NULL,
  ig_username VARCHAR(190) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_social_page (social_account_id, page_id),
  CONSTRAINT fk_pages_social_account FOREIGN KEY (social_account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scheduled_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  page_id INT UNSIGNED NOT NULL,
  target ENUM('facebook','instagram') NOT NULL,
  message TEXT NULL,
  media_url VARCHAR(500) NULL,
  link VARCHAR(500) NULL,
  scheduled_at DATETIME NOT NULL,
  status ENUM('pending','posted','failed','canceled') NOT NULL DEFAULT 'pending',
  remote_post_id VARCHAR(120) NULL,
  error_message TEXT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_scheduled_posts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_scheduled_posts_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
  INDEX idx_due (status, scheduled_at, next_attempt_at)
) ENGINE=InnoDB;

-- Meta requires a data deletion callback plus a status URL the user can
-- visit to confirm the deletion happened; the confirmation code is what
-- ties those together.
CREATE TABLE IF NOT EXISTS deletion_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  confirmation_code VARCHAR(64) NOT NULL UNIQUE,
  fb_user_id VARCHAR(64) NOT NULL,
  status ENUM('completed','nothing_to_delete') NOT NULL DEFAULT 'completed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fb_user (fb_user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_suggestions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  business_description TEXT NOT NULL,
  goal VARCHAR(190) NULL,
  suggestion TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_suggestions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
