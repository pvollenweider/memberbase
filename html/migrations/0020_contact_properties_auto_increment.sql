-- contact_properties.id was maintained by the maxval sequence and was never a
-- reliable key: ~83k legacy rows share id=0 and duplicates are possible among
-- the rest (no PK/unique constraint). Renumber every row sequentially, then
-- switch to a proper AUTO_INCREMENT primary key. The id is only used as a
-- transient URL parameter (suivi edit/delete), never stored elsewhere.
-- Idempotent: safe to re-run after a partial failure (DDL is auto-committed).
SET @next := 0;
UPDATE contact_properties SET id = (@next := @next + 1) ORDER BY `date`, `user_id`, `parameter`;
ALTER TABLE contact_properties DROP INDEX IF EXISTS `id`;
ALTER TABLE contact_properties MODIFY `id` int(8) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY IF NOT EXISTS (`id`);
-- The userpropertiesid sequence row is no longer used.
DELETE FROM maxval WHERE parameter = 'userpropertiesid';
