-- Contact type classification (issue #165): distinguishes private donors from
-- institutions, financial institutions and companies. Real lookup table
-- (like compta_type, segment...) rather than a hardcoded enum column, so
-- labels stay editable without a code change — but the 4 rows' `code` is a
-- stable key the classification logic depends on (includes/lib/contact_type.php),
-- never the (renameable) label.
--
-- compta_type gains two flags (same pattern as the existing is_institutional)
-- so the admin can mark which of their real accounting types signal each
-- contact category.
CREATE TABLE IF NOT EXISTS `contact_type` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `code`       varchar(20)  NOT NULL,
  `label`      varchar(255) NOT NULL,
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_contact_type_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `contact_type` (`id`, `code`, `label`, `sort_order`) VALUES
  (1, 'private',     'Donateur privé',           0),
  (2, 'institution',  'Institution',              1),
  (3, 'financial',    'Établissement financier',  2),
  (4, 'company',      'Entreprise',               3);

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                      AND COLUMN_NAME = 'contact_type_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `contact` ADD COLUMN `contact_type_id` int(11) NOT NULL DEFAULT 1 AFTER `sexe`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact'
                     AND CONSTRAINT_NAME = 'fk_contact_contact_type');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `contact` ADD CONSTRAINT `fk_contact_contact_type` FOREIGN KEY (`contact_type_id`) REFERENCES `contact_type` (`id`)',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta_type'
                      AND COLUMN_NAME = 'is_financial_institution');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `compta_type` ADD COLUMN `is_financial_institution` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_institutional`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'compta_type'
                      AND COLUMN_NAME = 'is_company');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `compta_type` ADD COLUMN `is_company` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_financial_institution`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
