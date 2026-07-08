-- Add user_id and rendered body columns to email_log for suivi integration.
ALTER TABLE `email_log`
  ADD COLUMN IF NOT EXISTS `user_id`   INT          NULL     AFTER `id`,
  ADD COLUMN IF NOT EXISTS `body_text` TEXT         NOT NULL DEFAULT '' AFTER `error_msg`,
  ADD COLUMN IF NOT EXISTS `body_html` LONGTEXT     NOT NULL DEFAULT '' AFTER `body_text`;

ALTER TABLE `email_log`
  ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`);
