-- Casa Members — full database schema
-- Run via the web installer at /install.php
-- All statements are idempotent (CREATE TABLE IF NOT EXISTS).
--
-- @license AGPL-3.0-or-later

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- Contacts (formerly "users")
CREATE TABLE IF NOT EXISTS `contact` (
  `id`               int(8)       NOT NULL AUTO_INCREMENT,
  `lastname`         varchar(255) NOT NULL DEFAULT '',
  `firstname`        varchar(255) NOT NULL DEFAULT '',
  `society`          varchar(255) NOT NULL DEFAULT '',
  `address`          varchar(255) NOT NULL DEFAULT '',
  `npa`              varchar(255) NOT NULL DEFAULT '',
  `tel`              varchar(255) NOT NULL DEFAULT '',
  `telprof`          varchar(255) NOT NULL DEFAULT '',
  `portable`         varchar(255) NOT NULL DEFAULT '',
  `fax`              varchar(255) NOT NULL DEFAULT '',
  `email`            varchar(255) NOT NULL DEFAULT '',
  `email_alt`        varchar(255) NOT NULL DEFAULT '',
  `web`              varchar(255) NOT NULL DEFAULT '',
  `sexe`             varchar(8)   NOT NULL DEFAULT 'na',
  `title`            varchar(255) NOT NULL DEFAULT '',
  `comment`          mediumtext   NOT NULL,
  `birthday`         date         DEFAULT NULL,
  `creationDate`     datetime     DEFAULT NULL,
  `modificationDate` datetime     DEFAULT NULL,
  `status`           tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `lastname`  (`lastname`(250)),
  KEY `firstname` (`firstname`(250)),
  KEY `idx_contact_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Segments (formerly "teams" / groups)
CREATE TABLE IF NOT EXISTS `segment` (
  `id`     int(11)      NOT NULL AUTO_INCREMENT,
  `name`   varchar(64)  NOT NULL DEFAULT '',
  `hidden` tinyint(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `id`         (`id`, `name`),
  KEY `idx_hidden` (`hidden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Segment → contact membership join table
CREATE TABLE IF NOT EXISTS `contact_segment` (
  `user_id`    INT NOT NULL,
  `segment_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `segment_id`),
  KEY `idx_contact_segment_segment_id` (`segment_id`),
  CONSTRAINT `fk_contact_segment_user` FOREIGN KEY (`user_id`) REFERENCES `contact` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contact_segment_segment` FOREIGN KEY (`segment_id`) REFERENCES `segment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact extra properties (EAV data)
CREATE TABLE IF NOT EXISTS `contact_properties` (
  `id`        int(8)       NOT NULL AUTO_INCREMENT,
  `user_id`   int(8)       NOT NULL DEFAULT 0,
  `parameter` varchar(64)  NOT NULL DEFAULT '',
  `date`      int(16)      NOT NULL DEFAULT 0,
  `value`     varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `parameter`    (`parameter`),
  KEY `idx_contact_param` (`user_id`, `parameter`),
  CONSTRAINT `fk_contact_properties_user` FOREIGN KEY (`user_id`) REFERENCES `contact` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Combined segments (formerly "metagroups") — header row only, one per filter/category
CREATE TABLE IF NOT EXISTS `combined_segment` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `name`       varchar(255) DEFAULT NULL,
  `is_filter`  tinyint(1)   NOT NULL DEFAULT 1,
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Combined segment → segment membership join table
CREATE TABLE IF NOT EXISTS `combined_segment_member` (
  `combined_segment_id` int(11) NOT NULL,
  `segment_id`          int(11) NOT NULL,
  PRIMARY KEY (`combined_segment_id`, `segment_id`),
  KEY `idx_combined_segment_member_segment_id` (`segment_id`),
  CONSTRAINT `fk_combined_segment_member_combined_segment` FOREIGN KEY (`combined_segment_id`) REFERENCES `combined_segment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_combined_segment_member_segment` FOREIGN KEY (`segment_id`) REFERENCES `segment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounting types
CREATE TABLE IF NOT EXISTS `compta_type` (
  `id`                        int(11)      NOT NULL AUTO_INCREMENT,
  `label`                     varchar(255) NOT NULL,
  `color`                     varchar(64)  NOT NULL DEFAULT 'bg-light',
  `default_libele`            varchar(255) NOT NULL DEFAULT '',
  `sort_order`                int(11)      NOT NULL DEFAULT 0,
  `is_cotisation`             tinyint(1)   NOT NULL DEFAULT 0,
  `is_excluded_from_donation` tinyint(1)   NOT NULL DEFAULT 0,
  `is_institutional`          tinyint(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounting entries
CREATE TABLE IF NOT EXISTS `compta` (
  `id`                int(8)       NOT NULL AUTO_INCREMENT,
  `user_id`           int(8)       NOT NULL DEFAULT 0,
  `date`              int(16)      NOT NULL DEFAULT 0,
  `libele`            varchar(255) NOT NULL DEFAULT '',
  `sum`               decimal(10,2) NOT NULL DEFAULT 0.00,
  `quittance`         varchar(64)  NOT NULL DEFAULT '',
  `type_id`           int(11)      DEFAULT NULL,
  `wants_attestation` tinyint(1)   NOT NULL DEFAULT 0,
  `notified_at`       datetime     DEFAULT NULL,
  `cotisation_year`   smallint(4)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id`   (`user_id`),
  KEY `user_id_2` (`user_id`, `date`),
  KEY `idx_type_id`        (`type_id`),
  KEY `idx_date`           (`date`),
  KEY `idx_notified_at`    (`notified_at`),
  KEY `idx_cotisation_year` (`cotisation_year`),
  CONSTRAINT `fk_compta_user` FOREIGN KEY (`user_id`) REFERENCES `contact` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_compta_type` FOREIGN KEY (`type_id`) REFERENCES `compta_type` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto-increment helper
CREATE TABLE IF NOT EXISTS `maxval` (
  `parameter` varchar(64) NOT NULL DEFAULT '',
  `value`     int(8)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`parameter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App-level configuration
CREATE TABLE IF NOT EXISTS `app_settings` (
  `key`   varchar(64)  NOT NULL,
  `value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App users (authentication)
CREATE TABLE IF NOT EXISTS `app_users` (
  `id`                    int(11)      NOT NULL AUTO_INCREMENT,
  `username`              varchar(100) NOT NULL,
  `display_name`          varchar(200) DEFAULT NULL,
  `email`                 varchar(200) DEFAULT NULL,
  `password_hash`         varchar(255) NOT NULL,
  `role`                  enum('admin','manager','user','readonly') NOT NULL DEFAULT 'user',
  `locale`                varchar(5)   NOT NULL DEFAULT 'fr',
  `force_password_change` tinyint(1)   NOT NULL DEFAULT 1,
  `is_active`             tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`            timestamp    NOT NULL DEFAULT current_timestamp(),
  `last_login`            timestamp    NULL DEFAULT NULL,
  `reset_token`           varchar(64)  DEFAULT NULL,
  `token_expires_at`      datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `created_at`      datetime     NOT NULL DEFAULT current_timestamp(),
  `app_user_id`     int(11)      DEFAULT NULL,
  `username`        varchar(100) DEFAULT NULL,
  `action`          varchar(100) NOT NULL,
  `detail`          text         DEFAULT NULL,
  `subject_user_id` int(11)      DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at`   (`created_at`),
  KEY `idx_subject_user` (`subject_user_id`),
  CONSTRAINT `fk_audit_log_subject` FOREIGN KEY (`subject_user_id`) REFERENCES `contact` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Configurable email templates
CREATE TABLE IF NOT EXISTS `email_templates` (
  `key`        varchar(64)  NOT NULL,
  `subject`    varchar(500) NOT NULL DEFAULT '',
  `body_text`  text         NOT NULL,
  `body_html`  longtext     NOT NULL DEFAULT '',
  `updated_at` datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email send log
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      NULL,
  `tpl_key`    varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
  `to_email`   varchar(255) NOT NULL DEFAULT '',
  `subject`    varchar(500) NOT NULL DEFAULT '',
  `status`     enum('sent','error') NOT NULL DEFAULT 'sent',
  `error_msg`  text         DEFAULT NULL,
  `body_text`  text         NOT NULL DEFAULT '',
  `body_html`  longtext     NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_user_id`   (`user_id`),
  KEY `idx_tpl_key`   (`tpl_key`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status`     (`status`),
  CONSTRAINT `fk_email_log_user` FOREIGN KEY (`user_id`) REFERENCES `contact` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API rate limiting (fixed-window counter per user+IP)
CREATE TABLE IF NOT EXISTS `api_rate_limit` (
  `bucket`       varchar(190) NOT NULL,
  `hits`         int(11)      NOT NULL DEFAULT 0,
  `window_start` int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version`    varchar(255) NOT NULL,
  `applied_at` int(11)      NOT NULL DEFAULT 0,
  `checksum`   char(64)     NOT NULL DEFAULT '',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
