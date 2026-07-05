-- Configurable email templates (subject + body) stored per key.
CREATE TABLE IF NOT EXISTS `email_templates` (
  `key`        varchar(64)  NOT NULL,
  `subject`    varchar(500) NOT NULL DEFAULT '',
  `body_text`  text         NOT NULL,
  `updated_at` datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
