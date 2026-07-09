-- Rename team-related tables and columns to use the "segment" terminology.
RENAME TABLE `team` TO `segment`;
RENAME TABLE `user_team` TO `user_segment`;
ALTER TABLE `metagroup` CHANGE `teamid` `segmentid` INT(11) DEFAULT NULL;
-- Rename the foreign-key column in user_segment to match the new table name.
ALTER TABLE `user_segment` CHANGE `team_id` `segment_id` INT(11) NOT NULL;
-- Update the index on user_segment if it exists.
ALTER TABLE `user_segment` DROP INDEX IF EXISTS `team_id`;
ALTER TABLE `user_segment` ADD INDEX `segment_id` (`segment_id`);
