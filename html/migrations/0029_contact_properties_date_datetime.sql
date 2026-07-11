-- 0029 — contact_properties.date: Unix timestamp int(16) → DATETIME (issue #143, step 4/5).
-- `date = 0` was the "not set" sentinel (segment-membership marker rows never set it);
-- it becomes NULL. 'suivi' entries use formatedDateToTimeStamp()/mktime() under PHP's
-- hardcoded "Europe/Zurich" timezone (see includes/lib/bootstrap.php) — same timezone
-- trap as 0028 (contact.birthday), so the backfill re-targets Europe/Zurich via
-- CONVERT_TZ() rather than trusting MySQL's session timezone. The safety check aborts
-- loudly instead of silently corrupting every suivi date if the named-timezone tables
-- (`mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql`) aren't loaded.
--
-- Guarded with information_schema checks so a retry after a partial failure (DDL is
-- auto-committed, no rollback) is safe regardless of which step it died on.

SET @tz_test = CONVERT_TZ('2026-01-01 00:00:00', @@session.time_zone, 'Europe/Zurich');
SET @sql = IF(@tz_test IS NULL,
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''CONVERT_TZ returned NULL: MariaDB named timezone tables are not loaded. Run `mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql` on the DB server, then retry this migration.''',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `contact_properties` ADD COLUMN IF NOT EXISTS `date_dt` datetime DEFAULT NULL;

SET @old_is_int = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_properties'
                      AND COLUMN_NAME = 'date' AND DATA_TYPE = 'int');

SET @sql = IF(@old_is_int > 0,
    'UPDATE `contact_properties` SET `date_dt` = CONVERT_TZ(FROM_UNIXTIME(`date`), @@session.time_zone, ''Europe/Zurich'') WHERE `date` > 0',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@old_is_int > 0, 'ALTER TABLE `contact_properties` DROP COLUMN `date`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dt_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_properties'
                     AND COLUMN_NAME = 'date_dt');

SET @sql = IF(@dt_exists > 0,
    'ALTER TABLE `contact_properties` CHANGE COLUMN `date_dt` `date` datetime DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
