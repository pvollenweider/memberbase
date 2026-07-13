-- Font Awesome icon per contact_type, so the type can be shown as a small
-- icon badge instead of plain text (more visible on the member fiche).
-- Stores the icon suffix only (e.g. "user", not "fa-user" / "fas fa-user") —
-- the view prefixes it with "fas fa-" when rendering.
ALTER TABLE `contact_type` ADD COLUMN IF NOT EXISTS `icon` varchar(50) NOT NULL DEFAULT '' AFTER `label`;

UPDATE `contact_type` SET `icon` = 'user'            WHERE `code` = 'private'     AND `icon` = '';
UPDATE `contact_type` SET `icon` = 'landmark'         WHERE `code` = 'institution' AND `icon` = '';
UPDATE `contact_type` SET `icon` = 'building-columns' WHERE `code` = 'financial'   AND `icon` = '';
UPDATE `contact_type` SET `icon` = 'building'         WHERE `code` = 'company'     AND `icon` = '';
