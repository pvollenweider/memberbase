-- 0027 — contact.creationDate: Unix timestamp int(16) → DATETIME (issue #143, step 2/5).
-- Same pattern as 0026 (contact.modificationDate). Guarded with information_schema
-- checks so a retry after a partial failure (DDL is auto-committed, no rollback) is
-- safe regardless of which step it died on.

ALTER TABLE `contact` ADD COLUMN IF NOT EXISTS `creationDate_dt` datetime DEFAULT NULL;

SET @old_is_int = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                      AND COLUMN_NAME = 'creationDate' AND DATA_TYPE = 'int');

SET @sql = IF(@old_is_int > 0,
    'UPDATE `contact` SET `creationDate_dt` = FROM_UNIXTIME(`creationDate`) WHERE `creationDate` > 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@old_is_int > 0, 'ALTER TABLE `contact` DROP COLUMN `creationDate`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dt_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                     AND COLUMN_NAME = 'creationDate_dt');

SET @sql = IF(@dt_exists > 0,
    'ALTER TABLE `contact` CHANGE COLUMN `creationDate_dt` `creationDate` datetime DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
