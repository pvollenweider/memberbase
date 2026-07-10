-- metagroup used to store BOTH a "header" row (one per filter/category, id
-- allocated from maxval.metagroup_id, segmentid IS NULL) AND "member" rows
-- (one per segment included in that filter/category, sharing the SAME id as
-- the header, segmentid set to the real segment id). Because id was shared
-- across header+members, the table had no PRIMARY KEY and could not use
-- AUTO_INCREMENT — a separate maxval row manually tracked the next id.
--
-- This splits members into a real join table (metagroup_member), leaving
-- metagroup as header-only rows with a proper AUTO_INCREMENT PRIMARY KEY.
-- Idempotent: safe to re-run after a partial failure (DDL is auto-committed).

CREATE TABLE IF NOT EXISTS metagroup_member (
    metagroup_id INT NOT NULL,
    segment_id   INT NOT NULL,
    PRIMARY KEY (metagroup_id, segment_id),
    KEY idx_metagroup_member_segment_id (segment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: every row with segmentid set is a "member" row under the old scheme.
INSERT IGNORE INTO metagroup_member (metagroup_id, segment_id)
SELECT id, segmentid FROM metagroup WHERE segmentid IS NOT NULL;

-- Remove migrated member rows, leaving only header rows.
DELETE FROM metagroup WHERE segmentid IS NOT NULL;

ALTER TABLE metagroup DROP COLUMN IF EXISTS segmentid;
ALTER TABLE metagroup DROP INDEX IF EXISTS idx_segmentid;
ALTER TABLE metagroup DROP INDEX IF EXISTS idx_id_name;
ALTER TABLE metagroup MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY IF NOT EXISTS (`id`);
ALTER TABLE metagroup ADD KEY IF NOT EXISTS idx_name (`name`(64));

-- The metagroup_id sequence row is no longer used — new headers get their id
-- from AUTO_INCREMENT now.
DELETE FROM maxval WHERE parameter = 'metagroup_id';
