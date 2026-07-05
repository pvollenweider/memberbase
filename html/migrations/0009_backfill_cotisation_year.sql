-- Backfill cotisation_year for existing cotisation entries using the year
-- derived from their payment date. Only affects rows where cotisation_year
-- is still NULL and the type is flagged is_cotisation = 1.
UPDATE `compta` c
JOIN `compta_type` ct ON ct.id = c.type_id AND ct.is_cotisation = 1
SET c.cotisation_year = YEAR(FROM_UNIXTIME(c.date))
WHERE c.cotisation_year IS NULL
  AND c.date > 0;
