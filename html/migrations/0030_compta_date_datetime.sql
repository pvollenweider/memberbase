-- 0030 — compta.date: Unix timestamp int(16) → DATETIME (issue #143, step 5/5).
-- `date = 0` was the "not set"/invalid sentinel (flagged by Réglages → Intégrité);
-- it becomes NULL. Entries are dated via formatedDateToTimeStamp()/mktime() under
-- PHP's hardcoded "Europe/Zurich" timezone (see includes/lib/bootstrap.php) — same
-- timezone trap as 0028 (contact.birthday) and 0029 (contact_properties.date), so
-- the backfill re-targets Europe/Zurich via CONVERT_TZ() rather than trusting
-- MySQL's session timezone. If the named-timezone tables aren't loaded
-- (`mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql`), CONVERT_TZ()
-- silently returns NULL per row — those rows are left with date=NULL (same as
-- "unset") rather than a wrong date, flagged by Réglages → Intégrité afterward.
-- (An earlier version of this migration used SIGNAL to hard-abort in that case,
-- but SIGNAL via dynamic PREPARE/EXECUTE isn't portable — MariaDB/MySQL error
-- 1295 "not supported in the prepared statement protocol" on some servers.)
--
-- `date` is part of two indexes (idx_date, user_id_2). Dropping the column drops/
-- shrinks them unpredictably depending on MariaDB version, so they're dropped
-- explicitly up front and re-added at the end against the new column.
--
-- Guarded with information_schema checks so a retry after a partial failure (DDL is
-- auto-committed, no rollback) is safe regardless of which step it died on.

ALTER TABLE `compta` DROP INDEX IF EXISTS `idx_date`;
ALTER TABLE `compta` DROP INDEX IF EXISTS `user_id_2`;

ALTER TABLE `compta` ADD COLUMN IF NOT EXISTS `date_dt` datetime DEFAULT NULL;

SET @old_is_int = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta'
                      AND COLUMN_NAME = 'date' AND DATA_TYPE = 'int');

SET @sql = IF(@old_is_int > 0,
    'UPDATE `compta` SET `date_dt` = CONVERT_TZ(FROM_UNIXTIME(`date`), @@session.time_zone, ''Europe/Zurich'') WHERE `date` > 0',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@old_is_int > 0, 'ALTER TABLE `compta` DROP COLUMN `date`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dt_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta'
                     AND COLUMN_NAME = 'date_dt');

SET @sql = IF(@dt_exists > 0,
    'ALTER TABLE `compta` CHANGE COLUMN `date_dt` `date` datetime DEFAULT NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `compta` ADD INDEX IF NOT EXISTS `idx_date` (`date`);
ALTER TABLE `compta` ADD INDEX IF NOT EXISTS `user_id_2` (`user_id`, `date`);
