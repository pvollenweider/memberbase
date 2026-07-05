-- Backfill cotisation_year for all compta rows that have a date but no year set.
-- Applies to every row (not filtered by type) so the migration works even when
-- compta_type does not yet exist (legacy upgrade path). The field is only
-- displayed/used in the UI for cotisation-type entries, so setting it on other
-- rows is harmless.
UPDATE `compta`
SET `cotisation_year` = YEAR(FROM_UNIXTIME(`date`))
WHERE `cotisation_year` IS NULL
  AND `date` > 0;
