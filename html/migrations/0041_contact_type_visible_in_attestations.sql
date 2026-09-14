-- Whether a contact_type should appear in the attestation-donors list
-- (Contacts & finances > Dons) and in the attestation bulk/email flows.
-- Defaults to visible (1) so existing types keep their current behavior.
ALTER TABLE `contact_type` ADD COLUMN IF NOT EXISTS `visible_in_attestations` TINYINT(1) NOT NULL DEFAULT 1 AFTER `icon`;
