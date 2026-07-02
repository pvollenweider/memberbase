-- 0002 — Store compta.sum as DECIMAL(10,2) instead of VARCHAR.
-- Financial amounts were stored as text (varchar(64)); this ends that data
-- debt. Existing values are cleaned first: commas normalized to dots, then any
-- remaining non-numeric / empty value set to 0 so the ALTER cannot fail.
UPDATE compta SET sum = REPLACE(sum, ',', '.') WHERE sum LIKE '%,%';
UPDATE compta SET sum = '0' WHERE sum NOT REGEXP '^-?[0-9]+([.][0-9]+)?$';
ALTER TABLE compta MODIFY COLUMN sum DECIMAL(10,2) NOT NULL DEFAULT 0.00;
