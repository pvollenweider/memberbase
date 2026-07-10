-- Default entry label per accounting type: prefills the "libellé" field of the
-- compta add form. For cotisation types the selected year is appended
-- client-side (e.g. "Cotisation 2026").
ALTER TABLE compta_type ADD COLUMN IF NOT EXISTS `default_libele` varchar(255) NOT NULL DEFAULT '' AFTER `color`;
