-- 0028 — contact.birthday: Unix timestamp int(16) → DATE (issue #143, step 3/5).
-- `birthday = 0` was the "not set" sentinel; it becomes NULL (application code
-- already treats getBirthDay()==0 and the new NULL read as equivalent "unset").
--
-- Timezone note: historical birthday values were produced by
-- Contact::setBirthDay()'s mktime(), which runs under PHP's hardcoded
-- "Europe/Zurich" timezone (see includes/lib/bootstrap.php). Naively converting
-- via FROM_UNIXTIME() alone would use MySQL's *session* timezone instead, which
-- can silently shift the calendar day when the two don't match. The backfill
-- below explicitly re-targets Europe/Zurich via CONVERT_TZ(), which requires the
-- named-timezone tables to be loaded (`mysql_tzinfo_to_sql /usr/share/zoneinfo |
-- mysql -u root mysql`). If they aren't loaded, CONVERT_TZ() silently returns
-- NULL per row — those rows are left with birthday=NULL (same as "unset") rather
-- than a wrong date, and show up in Réglages → Intégrité afterward for review.
-- (An earlier version of this migration used SIGNAL to hard-abort in that case,
-- but SIGNAL via dynamic PREPARE/EXECUTE isn't portable — MariaDB/MySQL error
-- 1295 "not supported in the prepared statement protocol" on some servers.)
--
-- Guarded with information_schema checks so a retry after a partial failure (DDL
-- is auto-committed, no rollback) is safe regardless of which step it died on.

ALTER TABLE `contact` ADD COLUMN IF NOT EXISTS `birthday_dt` date DEFAULT NULL;

SET @old_is_int = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                      AND COLUMN_NAME = 'birthday' AND DATA_TYPE = 'int');

SET @sql = IF(@old_is_int > 0,
    'UPDATE `contact` SET `birthday_dt` = DATE(CONVERT_TZ(FROM_UNIXTIME(`birthday`), @@session.time_zone, ''Europe/Zurich'')) WHERE `birthday` > 0',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@old_is_int > 0, 'ALTER TABLE `contact` DROP COLUMN `birthday`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dt_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                     AND COLUMN_NAME = 'birthday_dt');

SET @sql = IF(@dt_exists > 0,
    'ALTER TABLE `contact` CHANGE COLUMN `birthday_dt` `birthday` date DEFAULT NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
