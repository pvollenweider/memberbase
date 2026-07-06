-- Tracks which membership year a cotisation entry covers, independently of
-- the payment date. NULL = derive year from the entry date (legacy behaviour).
ALTER TABLE `compta` ADD COLUMN IF NOT EXISTS `cotisation_year` smallint(4) DEFAULT NULL;
ALTER TABLE `compta` ADD INDEX IF NOT EXISTS `idx_cotisation_year` (`cotisation_year`);
