-- Rename compta.quittance -> compta.comment (issue #147): the column was
-- originally meant for a payment receipt number but is used in practice as a
-- free-text comment field. No data loss -- plain column rename, same type.

SET @old_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta'
                      AND COLUMN_NAME = 'quittance');

SET @sql = IF(@old_exists > 0,
    'ALTER TABLE `compta` RENAME COLUMN `quittance` TO `comment`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
