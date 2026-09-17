-- Whether a contact_type should appear in the compta recap page (Contacts &
-- finances > Récapitulatif) and its linked "notification" tasks.
-- Defaults to visible (1) so existing types keep their current behavior.
ALTER TABLE `contact_type` ADD COLUMN IF NOT EXISTS `visible_in_recap` TINYINT(1) NOT NULL DEFAULT 1 AFTER `visible_in_attestations`;
