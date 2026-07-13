-- Contact type × compta type matrix + compta type archiving.
--
-- contact_type_compta_type: which compta_type rows may be picked when
-- creating a new entry for a contact of a given contact_type. Permissive by
-- default — a contact_type with ZERO rows here is unrestricted (every
-- non-archived compta_type stays selectable); as soon as one row exists for
-- a contact_type_id, only the listed compta_type_ids are offered for that
-- contact_type (see includes/lib/contact_type_compta_type.php).
--
-- compta_type.is_archived: reversible "no longer used" flag. Archived types
-- stay fully visible/manageable in Réglages → Types compta (existing
-- entries keep their label/history) but are excluded from the type picker
-- when creating a NEW entry.
CREATE TABLE IF NOT EXISTS `contact_type_compta_type` (
  `contact_type_id` int(11) NOT NULL,
  `compta_type_id`  int(11) NOT NULL,
  PRIMARY KEY (`contact_type_id`, `compta_type_id`),
  KEY `idx_ctct_compta_type` (`compta_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_type_compta_type'
                     AND CONSTRAINT_NAME = 'fk_ctct_contact_type');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `contact_type_compta_type` ADD CONSTRAINT `fk_ctct_contact_type` FOREIGN KEY (`contact_type_id`) REFERENCES `contact_type` (`id`) ON DELETE CASCADE',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_type_compta_type'
                     AND CONSTRAINT_NAME = 'fk_ctct_compta_type');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `contact_type_compta_type` ADD CONSTRAINT `fk_ctct_compta_type` FOREIGN KEY (`compta_type_id`) REFERENCES `compta_type` (`id`) ON DELETE CASCADE',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta_type'
                      AND COLUMN_NAME = 'is_archived');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `compta_type` ADD COLUMN `is_archived` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_company`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
