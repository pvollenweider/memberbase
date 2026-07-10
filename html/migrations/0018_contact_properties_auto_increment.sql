-- contact_properties.id was maintained by the maxval sequence and was never a
-- reliable key: ~83k legacy rows share id=0 and duplicates are possible among
-- the rest (no PK/unique constraint). Renumber every row sequentially, then
-- switch to a proper AUTO_INCREMENT primary key. The id is only used as a
-- transient URL parameter (suivi edit/delete), never stored elsewhere.
SET @next := 0;
UPDATE contact_properties SET id = (@next := @next + 1) ORDER BY `date`, `user_id`, `parameter`;
ALTER TABLE contact_properties DROP KEY `id`;
ALTER TABLE contact_properties MODIFY `id` int(8) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`);
-- The userpropertiesid sequence row is no longer used.
DELETE FROM maxval WHERE parameter = 'userpropertiesid';
