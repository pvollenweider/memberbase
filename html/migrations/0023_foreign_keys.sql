-- Declares real FOREIGN KEY constraints instead of relying purely on
-- application code + schema.sql's `SET foreign_key_checks = 0`. Cleans up
-- pre-existing orphan rows first (required — MySQL/MariaDB refuses to add a
-- constraint that existing data violates).
--
-- ON DELETE choice per relationship:
-- - CASCADE where the app already deletes the child rows when the parent is
--   removed (contact_segment, metagroup_member, contact_properties, compta
--   rows tied to a deleted contact) — makes that manual cleanup redundant
--   but harmless, and protects any future code path that forgets it.
-- - RESTRICT for compta.type_id — the app already blocks deleting a
--   compta_type that still has entries (see deleteComptaType).
-- - SET NULL for audit_log.subject_user_id / email_log.user_id — these are
--   history logs that must survive the referenced contact being deleted.
--
-- Idempotent: DROP FOREIGN KEY IF EXISTS before each ADD CONSTRAINT (MariaDB
-- has no "ADD CONSTRAINT IF NOT EXISTS" for foreign keys), safe to re-run
-- after a partial failure (DDL is auto-committed).

-- --- Orphan cleanup -----------------------------------------------------

-- contact_segment: mostly garbage rows with segment_id=0 (never a valid
-- segment) plus a handful pointing at genuinely deleted segments/contacts.
DELETE cs FROM contact_segment cs LEFT JOIN contact c ON c.id = cs.user_id WHERE c.id IS NULL;
DELETE cs FROM contact_segment cs LEFT JOIN segment s ON s.id = cs.segment_id WHERE s.id IS NULL;

DELETE mm FROM metagroup_member mm LEFT JOIN metagroup m ON m.id = mm.metagroup_id WHERE m.id IS NULL;
DELETE mm FROM metagroup_member mm LEFT JOIN segment s ON s.id = mm.segment_id WHERE s.id IS NULL;

DELETE cp FROM contact_properties cp LEFT JOIN contact c ON c.id = cp.user_id WHERE c.id IS NULL;
DELETE c FROM compta c LEFT JOIN contact u ON u.id = c.user_id WHERE u.id IS NULL;
UPDATE compta c LEFT JOIN compta_type t ON t.id = c.type_id
    SET c.type_id = NULL WHERE c.type_id IS NOT NULL AND t.id IS NULL;

-- History logs: null out dangling references, keep the row (audit/email
-- trail must survive a deleted/merged/anonymized contact).
UPDATE audit_log a LEFT JOIN contact c ON c.id = a.subject_user_id
    SET a.subject_user_id = NULL WHERE a.subject_user_id IS NOT NULL AND c.id IS NULL;
UPDATE email_log e LEFT JOIN contact c ON c.id = e.user_id
    SET e.user_id = NULL WHERE e.user_id IS NOT NULL AND c.id IS NULL;

-- --- Column type fix (FK requires matching signedness) -------------------
-- audit_log.subject_user_id was `int(10) unsigned`; contact.id is signed.
ALTER TABLE audit_log MODIFY `subject_user_id` int(11) DEFAULT NULL;

-- --- Foreign keys ---------------------------------------------------------

ALTER TABLE contact_segment DROP FOREIGN KEY IF EXISTS fk_contact_segment_user;
ALTER TABLE contact_segment ADD CONSTRAINT fk_contact_segment_user
    FOREIGN KEY (user_id) REFERENCES contact(id) ON DELETE CASCADE;

ALTER TABLE contact_segment DROP FOREIGN KEY IF EXISTS fk_contact_segment_segment;
ALTER TABLE contact_segment ADD CONSTRAINT fk_contact_segment_segment
    FOREIGN KEY (segment_id) REFERENCES segment(id) ON DELETE CASCADE;

ALTER TABLE metagroup_member DROP FOREIGN KEY IF EXISTS fk_metagroup_member_metagroup;
ALTER TABLE metagroup_member ADD CONSTRAINT fk_metagroup_member_metagroup
    FOREIGN KEY (metagroup_id) REFERENCES metagroup(id) ON DELETE CASCADE;

ALTER TABLE metagroup_member DROP FOREIGN KEY IF EXISTS fk_metagroup_member_segment;
ALTER TABLE metagroup_member ADD CONSTRAINT fk_metagroup_member_segment
    FOREIGN KEY (segment_id) REFERENCES segment(id) ON DELETE CASCADE;

ALTER TABLE contact_properties DROP FOREIGN KEY IF EXISTS fk_contact_properties_user;
ALTER TABLE contact_properties ADD CONSTRAINT fk_contact_properties_user
    FOREIGN KEY (user_id) REFERENCES contact(id) ON DELETE CASCADE;

ALTER TABLE compta DROP FOREIGN KEY IF EXISTS fk_compta_user;
ALTER TABLE compta ADD CONSTRAINT fk_compta_user
    FOREIGN KEY (user_id) REFERENCES contact(id) ON DELETE CASCADE;

ALTER TABLE compta DROP FOREIGN KEY IF EXISTS fk_compta_type;
ALTER TABLE compta ADD CONSTRAINT fk_compta_type
    FOREIGN KEY (type_id) REFERENCES compta_type(id) ON DELETE RESTRICT;

ALTER TABLE audit_log DROP FOREIGN KEY IF EXISTS fk_audit_log_subject;
ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_subject
    FOREIGN KEY (subject_user_id) REFERENCES contact(id) ON DELETE SET NULL;

ALTER TABLE email_log DROP FOREIGN KEY IF EXISTS fk_email_log_user;
ALTER TABLE email_log ADD CONSTRAINT fk_email_log_user
    FOREIGN KEY (user_id) REFERENCES contact(id) ON DELETE SET NULL;
