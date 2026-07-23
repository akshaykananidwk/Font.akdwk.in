-- ગુજરાતી ફોન્ટ કન્વર્ટર — સંપૂર્ણ Database Schema
-- બધા ટેબલ utf8mb4_unicode_ci; {{prefix}} installer replace કરે છે.

SET NAMES utf8mb4;

-- 3.1 settings
CREATE TABLE IF NOT EXISTS `{{prefix}}settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`),
  KEY `idx_setting_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.2 languages
CREATE TABLE IF NOT EXISTS `{{prefix}}languages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `native_name` VARCHAR(100) NOT NULL,
  `unicode_font` VARCHAR(100) NOT NULL DEFAULT 'Shruti',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lang_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.3 fonts
CREATE TABLE IF NOT EXISTS `{{prefix}}fonts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `language_id` INT UNSIGNED NOT NULL,
  `font_name` VARCHAR(100) NOT NULL,
  `font_slug` VARCHAR(120) NOT NULL,
  `mapping_file` VARCHAR(255) NOT NULL,
  `font_family` VARCHAR(100) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `conversion_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `page_content` LONGTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_font_slug` (`font_slug`),
  KEY `idx_font_lang` (`language_id`),
  KEY `idx_font_active` (`is_active`, `sort_order`),
  CONSTRAINT `fk_fonts_language` FOREIGN KEY (`language_id`) REFERENCES `{{prefix}}languages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4 conversions_log (ટેક્સ્ટ ક્યારેય store થતો નથી — માત્ર char_count)
CREATE TABLE IF NOT EXISTS `{{prefix}}conversions_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `font_id` INT UNSIGNED NULL,
  `direction` ENUM('legacy_to_unicode','unicode_to_legacy') NOT NULL,
  `char_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `user_id` INT UNSIGNED NULL,
  `api_key_id` INT UNSIGNED NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conv_ip` (`ip_address`),
  KEY `idx_conv_font` (`font_id`),
  KEY `idx_conv_created` (`created_at`),
  CONSTRAINT `fk_conv_font` FOREIGN KEY (`font_id`) REFERENCES `{{prefix}}fonts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.5 demo_usage
CREATE TABLE IF NOT EXISTS `{{prefix}}demo_usage` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempts_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `first_attempt_at` TIMESTAMP NULL,
  `last_attempt_at` TIMESTAMP NULL,
  `reset_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_demo_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.7 plans (users કરતાં પહેલા — FK માટે)
CREATE TABLE IF NOT EXISTS `{{prefix}}plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `char_limit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
  `daily_limit` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
  `api_access` TINYINT(1) NOT NULL DEFAULT 0,
  `api_daily_limit` INT UNSIGNED NOT NULL DEFAULT 0,
  `features` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.6 users
CREATE TABLE IF NOT EXISTS `{{prefix}}users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `plan_id` INT UNSIGNED NULL,
  `subscription_start` DATETIME NULL,
  `subscription_end` DATETIME NULL,
  `status` ENUM('active','expired','suspended') NOT NULL DEFAULT 'active',
  `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verify_token` VARCHAR(64) NULL,
  `reset_token` VARCHAR(64) NULL,
  `reset_expires` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`),
  KEY `idx_user_plan` (`plan_id`),
  KEY `idx_user_status` (`status`),
  CONSTRAINT `fk_users_plan` FOREIGN KEY (`plan_id`) REFERENCES `{{prefix}}plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.8 subscriptions
CREATE TABLE IF NOT EXISTS `{{prefix}}subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(50) NULL,
  `transaction_id` VARCHAR(190) NULL,
  `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `starts_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_user` (`user_id`),
  KEY `idx_sub_status` (`status`),
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_plan` FOREIGN KEY (`plan_id`) REFERENCES `{{prefix}}plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.9 api_keys
CREATE TABLE IF NOT EXISTS `{{prefix}}api_keys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `api_key` VARCHAR(64) NOT NULL,
  `api_secret` VARCHAR(128) NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT 'Default',
  `daily_limit` INT UNSIGNED NOT NULL DEFAULT 1000,
  `calls_today` INT UNSIGNED NOT NULL DEFAULT 0,
  `calls_date` DATE NULL,
  `total_calls` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at` DATETIME NULL,
  `allowed_ips` JSON NULL,
  `status` ENUM('active','revoked','suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_key` (`api_key`),
  KEY `idx_apikey_user` (`user_id`),
  CONSTRAINT `fk_apikey_user` FOREIGN KEY (`user_id`) REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.10 api_logs
CREATE TABLE IF NOT EXISTS `{{prefix}}api_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `api_key_id` INT UNSIGNED NULL,
  `endpoint` VARCHAR(100) NOT NULL,
  `method` VARCHAR(10) NOT NULL DEFAULT 'POST',
  `ip_address` VARCHAR(45) NOT NULL,
  `request_chars` INT UNSIGNED NOT NULL DEFAULT 0,
  `response_code` SMALLINT UNSIGNED NOT NULL DEFAULT 200,
  `response_time_ms` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_apilog_key` (`api_key_id`),
  KEY `idx_apilog_created` (`created_at`),
  CONSTRAINT `fk_apilog_key` FOREIGN KEY (`api_key_id`) REFERENCES `{{prefix}}api_keys` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.11 pages (CMS)
CREATE TABLE IF NOT EXISTS `{{prefix}}pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(320) NULL,
  `meta_keywords` VARCHAR(500) NULL,
  `og_image` VARCHAR(255) NULL,
  `canonical_url` VARCHAR(255) NULL,
  `is_indexed` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('published','draft') NOT NULL DEFAULT 'published',
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_slug` (`slug`),
  KEY `idx_page_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.13 blog_categories (posts પહેલા — FK)
CREATE TABLE IF NOT EXISTS `{{prefix}}blog_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(320) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.12 blog_posts
CREATE TABLE IF NOT EXISTS `{{prefix}}blog_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `excerpt` TEXT NULL,
  `content` LONGTEXT NULL,
  `featured_image` VARCHAR(255) NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(320) NULL,
  `focus_keyword` VARCHAR(100) NULL,
  `category_id` INT UNSIGNED NULL,
  `tags` VARCHAR(500) NULL,
  `views` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('published','draft') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `author_id` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_slug` (`slug`),
  KEY `idx_post_status` (`status`, `published_at`),
  KEY `idx_post_cat` (`category_id`),
  CONSTRAINT `fk_post_cat` FOREIGN KEY (`category_id`) REFERENCES `{{prefix}}blog_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.14 contacts
CREATE TABLE IF NOT EXISTS `{{prefix}}contacts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `subject` VARCHAR(255) NULL,
  `message` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `status` ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.15 admins
CREATE TABLE IF NOT EXISTS `{{prefix}}admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NULL,
  `role` ENUM('super_admin','editor') NOT NULL DEFAULT 'editor',
  `last_login` DATETIME NULL,
  `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.16 admin_activity_log
CREATE TABLE IF NOT EXISTS `{{prefix}}admin_activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `details` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actlog_admin` (`admin_id`),
  KEY `idx_actlog_created` (`created_at`),
  CONSTRAINT `fk_actlog_admin` FOREIGN KEY (`admin_id`) REFERENCES `{{prefix}}admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.17 updates_log
CREATE TABLE IF NOT EXISTS `{{prefix}}updates_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_version` VARCHAR(20) NOT NULL,
  `to_version` VARCHAR(20) NOT NULL,
  `commit_hash` VARCHAR(64) NULL,
  `commit_message` TEXT NULL,
  `status` ENUM('running','success','failed','rolled_back') NOT NULL DEFAULT 'running',
  `backup_path` VARCHAR(255) NULL,
  `log_output` TEXT NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_updlog_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.18 migrations
CREATE TABLE IF NOT EXISTS `{{prefix}}migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration_file` VARCHAR(190) NOT NULL,
  `batch` INT UNSIGNED NOT NULL DEFAULT 1,
  `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migration_file` (`migration_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.19 seo_redirects
CREATE TABLE IF NOT EXISTS `{{prefix}}seo_redirects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_url` VARCHAR(190) NOT NULL,
  `to_url` VARCHAR(500) NOT NULL,
  `redirect_type` ENUM('301','302') NOT NULL DEFAULT '301',
  `hits` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_redirect_from` (`from_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.20 rate_limits
CREATE TABLE IF NOT EXISTS `{{prefix}}rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(100) NOT NULL,
  `endpoint` VARCHAR(100) NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rl_identifier` (`identifier`, `endpoint`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
