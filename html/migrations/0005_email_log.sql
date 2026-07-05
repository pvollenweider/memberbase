-- Email send log: one row per send attempt (success or failure).
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
  `to_email`   varchar(255) NOT NULL DEFAULT '',
  `subject`    varchar(500) NOT NULL DEFAULT '',
  `status`     enum('sent','error') NOT NULL DEFAULT 'sent',
  `error_msg`  text         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
