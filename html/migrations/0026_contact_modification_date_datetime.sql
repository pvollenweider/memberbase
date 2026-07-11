-- 0026 — contact.modificationDate: Unix timestamp int(16) → DATETIME (issue #143, step 1/5).
-- ALTER ... MODIFY cannot convert int → DATETIME directly (MariaDB would misinterpret the raw
-- integer as a date literal, not a Unix timestamp), so this goes through a temp column.
-- Guarded with information_schema checks so a retry after a partial failure (DDL is
-- auto-committed, no rollback) is safe regardless of which step it died on.

ALTER TABLE `contact` ADD COLUMN IF NOT EXISTS `modificationDate_dt` datetime DEFAULT NULL;

SET @old_is_int = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                      AND COLUMN_NAME = 'modificationDate' AND DATA_TYPE = 'int');

SET @sql = IF(@old_is_int > 0,
    'UPDATE `contact` SET `modificationDate_dt` = FROM_UNIXTIME(`modificationDate`) WHERE `modificationDate` > 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@old_is_int > 0, 'ALTER TABLE `contact` DROP COLUMN `modificationDate`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dt_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                     AND COLUMN_NAME = 'modificationDate_dt');

SET @sql = IF(@dt_exists > 0,
    'ALTER TABLE `contact` CHANGE COLUMN `modificationDate_dt` `modificationDate` datetime DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
