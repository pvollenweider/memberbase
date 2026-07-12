-- Marks tasks created automatically by a business rule (issue #149), so a
-- re-run of the generator can dedup against tasks already created for the
-- same member/rule/year instead of creating duplicates. NULL = manual task.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suivi_task'
                      AND COLUMN_NAME = 'rule_key');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `suivi_task` ADD COLUMN `rule_key` varchar(64) DEFAULT NULL AFTER `priority`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suivi_task'
                      AND INDEX_NAME = 'idx_suivi_task_rule_key');

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `suivi_task` ADD INDEX `idx_suivi_task_rule_key` (`rule_key`, `user_id`)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
