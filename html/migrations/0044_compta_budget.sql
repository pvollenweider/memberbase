-- Per-compta_type yearly budget figures, editable on the new "Budgets" page
-- (Finances submenu). One row per (compta_type_id, year); missing rows mean
-- "no budget set" (treated as 0), not deleted on purpose.
CREATE TABLE IF NOT EXISTS `compta_budget` (
  `compta_type_id` int(11)       NOT NULL,
  `year`           smallint(4)   NOT NULL,
  `amount`         decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`compta_type_id`, `year`),
  CONSTRAINT `fk_compta_budget_type` FOREIGN KEY (`compta_type_id`) REFERENCES `compta_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
