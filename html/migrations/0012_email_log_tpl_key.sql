-- Add tpl_key to email_log to allow querying by template type without relying on subject text.
ALTER TABLE `email_log`
  ADD COLUMN IF NOT EXISTS `tpl_key` varchar(100) NOT NULL DEFAULT '' AFTER `user_id`;
ALTER TABLE `email_log`
  ADD INDEX IF NOT EXISTS `idx_tpl_key` (`tpl_key`);
