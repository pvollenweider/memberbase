-- 0001 — Ajoute la colonne email_alt (adresse alternative / historique).
-- Introduit en v3.5.4. Idempotent sous MariaDB (IF NOT EXISTS).
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email_alt` VARCHAR(255) NOT NULL DEFAULT '' AFTER `email`;
