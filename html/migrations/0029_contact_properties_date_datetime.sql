-- 0029 — contact_properties.date: Unix timestamp int(16) → DATETIME (issue #143, step 4/5).
-- `date = 0` was the "not set" sentinel (segment-membership marker rows never set it);
-- it becomes NULL. 'suivi' entries use formatedDateToTimeStamp()/mktime() under PHP's
-- hardcoded "Europe/Zurich" timezone (see includes/lib/bootstrap.php) — same timezone
-- trap as 0028 (contact.birthday), so the backfill re-targets Europe/Zurich via
-- CONVERT_TZ() rather than trusting MySQL's session timezone. If the named-timezone
-- tables aren't loaded (`mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql`),
-- CONVERT_TZ() silently returns NULL per row — those rows are left with date=NULL
-- (same as "unset") rather than a wrong date. (An earlier version of this migration
-- used SIGNAL to hard-abort in that case, but SIGNAL via dynamic PREPARE/EXECUTE
-- isn't portable — MariaDB/MySQL error 1295 "not supported in the prepared
-- statement protocol" on some servers.)
--
-- Guarded with information_schema checks so a retry after a partial failure (DDL is
-- auto-committed, no rollback) is safe regardless of which step it died on.

ALTER TABLE `contact_properties` ADD COLUMN IF NOT EXISTS `date_dt` datetime DEFAULT NULL;

SET @old_is_int = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_properties'
                      AND COLUMN_NAME = 'date' AND DATA_TYPE = 'int');

SET @sql = IF(@old_is_int > 0,
    'UPDATE `contact_properties` SET `date_dt` = CONVERT_TZ(FROM_UNIXTIME(`date`), @@session.time_zone, ''Europe/Zurich'') WHERE `date` > 0',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@old_is_int > 0, 'ALTER TABLE `contact_properties` DROP COLUMN `date`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dt_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_properties'
                     AND COLUMN_NAME = 'date_dt');

SET @sql = IF(@dt_exists > 0,
    'ALTER TABLE `contact_properties` CHANGE COLUMN `date_dt` `date` datetime DEFAULT NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
