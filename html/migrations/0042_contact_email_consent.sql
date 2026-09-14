-- Whether the contact has consented to receiving emails. Defaults to 0
-- (no consent recorded) — must NOT be assumed opt-in for existing contacts.
ALTER TABLE `contact` ADD COLUMN IF NOT EXISTS `email_consent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`;
