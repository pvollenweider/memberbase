-- Track which compta entries have been included in a recap email.
-- NULL = never notified; set to NOW() when the entry is included in a batch send.
ALTER TABLE `compta` ADD COLUMN IF NOT EXISTS `notified_at` datetime DEFAULT NULL;
ALTER TABLE `compta` ADD INDEX IF NOT EXISTS `idx_notified_at` (`notified_at`);
