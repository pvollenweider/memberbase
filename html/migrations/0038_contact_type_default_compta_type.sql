-- Default compta_type pre-selected in the "add entry" form, per contact_type
-- (issue #165, phase 2). NULL = no default, first allowed option stays
-- implicit (previous behavior). Set via Réglages → Types de contact, next
-- to the existing contact_type × compta_type matrix.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_type'
                      AND COLUMN_NAME = 'default_compta_type_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `contact_type` ADD COLUMN `default_compta_type_id` int(11) NULL DEFAULT NULL AFTER `sort_order`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_type'
                     AND CONSTRAINT_NAME = 'fk_contact_type_default_compta_type');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `contact_type` ADD CONSTRAINT `fk_contact_type_default_compta_type` FOREIGN KEY (`default_compta_type_id`) REFERENCES `compta_type` (`id`) ON DELETE SET NULL',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
