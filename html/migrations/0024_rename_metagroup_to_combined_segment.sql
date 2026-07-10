-- Renames the "metagroup" concept to "combined_segment" — the UI has called
-- this feature "Segment combiné" since v5.0.0, but the underlying table,
-- class, and identifiers still said "metagroup"/"Group". This migration only
-- renames tables/columns/indexes/constraints; column data is unaffected.
-- Idempotent: safe to re-run after a partial failure (DDL is auto-committed).

RENAME TABLE `metagroup` TO `combined_segment`;
RENAME TABLE `metagroup_member` TO `combined_segment_member`;

ALTER TABLE `combined_segment_member`
    CHANGE COLUMN `metagroup_id` `combined_segment_id` int(11) NOT NULL;

ALTER TABLE `combined_segment_member` DROP FOREIGN KEY IF EXISTS `fk_metagroup_member_metagroup`;
ALTER TABLE `combined_segment_member` DROP FOREIGN KEY IF EXISTS `fk_metagroup_member_segment`;
ALTER TABLE `combined_segment_member` DROP INDEX IF EXISTS `idx_metagroup_member_segment_id`;

ALTER TABLE `combined_segment_member` ADD KEY IF NOT EXISTS `idx_combined_segment_member_segment_id` (`segment_id`);

ALTER TABLE `combined_segment_member` ADD CONSTRAINT `fk_combined_segment_member_combined_segment`
    FOREIGN KEY (`combined_segment_id`) REFERENCES `combined_segment` (`id`) ON DELETE CASCADE;
ALTER TABLE `combined_segment_member` ADD CONSTRAINT `fk_combined_segment_member_segment`
    FOREIGN KEY (`segment_id`) REFERENCES `segment` (`id`) ON DELETE CASCADE;
