-- Rename compta.quittance -> compta.comment (issue #147): the column was
-- originally meant for a payment receipt number but is used in practice as a
-- free-text comment field. No data loss -- plain column rename, same type.

SET @old_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta'
                      AND COLUMN_NAME = 'quittance');

-- CHANGE COLUMN (not RENAME COLUMN ... TO ...) — the latter needs
-- MariaDB 10.5.2+/MySQL 8.0+, and fails with a syntax error on older servers.
SET @sql = IF(@old_exists > 0,
    'ALTER TABLE `compta` CHANGE COLUMN `quittance` `comment` VARCHAR(64) NOT NULL DEFAULT ''''',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
