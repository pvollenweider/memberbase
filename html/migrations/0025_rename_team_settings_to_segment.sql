-- Rename remaining "team" vocabulary to "segment" in app_settings keys
-- and the contact_properties import marker prefix.
UPDATE `app_settings` SET `key` = 'default_segment' WHERE `key` = 'default_team';
UPDATE `app_settings` SET `key` = 'membre_segment' WHERE `key` = 'membre_team';
UPDATE `app_settings` SET `key` = 'member_no_coti_segment' WHERE `key` = 'member_no_coti_team';
UPDATE `app_settings` SET `key` = 'membre_segment_prefix' WHERE `key` = 'membre_team_prefix';

UPDATE `contact_properties`
   SET `parameter` = CONCAT('segment_', SUBSTRING(`parameter`, 6))
 WHERE `parameter` LIKE 'team\_%';
