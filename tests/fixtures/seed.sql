-- Deterministic seed for Playwright E2E tests
-- DB: members_test
-- Admin password: TestPassword1! (bcrypt)

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- App users — one per role (all share password TestPassword1!)
INSERT INTO `app_users` (`id`, `username`, `display_name`, `email`, `password_hash`, `role`, `force_password_change`, `is_active`)
VALUES
  (1, 'testadmin',    'Test Admin',    'testadmin@example.com',    '$2y$10$EYbiM6FFG8rJrlQfspD8wevoLGlK6aeE8CvvHt34lvxflse7Rq1MG', 'admin',    0, 1),
  (2, 'testmanager',  'Test Manager',  'manager@example.com',      '$2y$10$EYbiM6FFG8rJrlQfspD8wevoLGlK6aeE8CvvHt34lvxflse7Rq1MG', 'manager',  0, 1),
  (3, 'testuser',     'Test User',     'user@example.com',         '$2y$10$EYbiM6FFG8rJrlQfspD8wevoLGlK6aeE8CvvHt34lvxflse7Rq1MG', 'user',     0, 1),
  (4, 'testreadonly', 'Test Readonly', 'readonly@example.com',     '$2y$10$EYbiM6FFG8rJrlQfspD8wevoLGlK6aeE8CvvHt34lvxflse7Rq1MG', 'readonly', 0, 1);

-- Regular members
-- Users 4 and 5 are lapsed: paid 2025, not 2026. User 5 has no email (skip case).
INSERT INTO `contact` (`id`, `lastname`, `firstname`, `email`, `status`, `creationDate`, `modificationDate`, `comment`)
VALUES
  (1, 'Dupont',   'Alice',  'alice@example.com',    1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ''),
  (2, 'Martin',   'Bob',    'bob@example.com',      1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ''),
  -- Archived, no compta → can be permanently deleted (admin only)
  (3, 'Archived', 'Member', 'archived@example.com', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ''),
  -- Lapsed members (paid 2025, no 2026 entry)
  (4, 'Lapsed',   'Carol',  'carol@example.com',    1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ''),
  (5, 'Lapsed',   'Dave',   '',                     1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '');

-- Segments (formerly teams)
INSERT INTO `segment` (`id`, `name`, `hidden`) VALUES (1, 'Membre 2025', 0);
INSERT INTO `segment` (`id`, `name`, `hidden`) VALUES (2, 'Membre 2026', 0);

-- App settings — default_team=0 means "all active members" (no group filter)
-- SMTP points to Mailpit running in Docker (port 1025, no auth)
INSERT INTO `app_settings` (`key`, `value`) VALUES
  ('default_team', '0'),
  ('membre_team', '2'),
  ('membre_team_prefix', 'Membre'),
  ('org_name', 'MemberBase Test'),
  ('smtp_host', 'mailpit'),
  ('smtp_port', '1025'),
  ('smtp_encryption', 'none'),
  ('smtp_auth', '0'),
  ('smtp_from_email', 'noreply@memberbase.test'),
  ('smtp_from_name', 'MemberBase Test'),
  ('membership_url', 'https://www.memberbase.test/devenir-membre');

-- Compta types — covers all four flag combinations for filter/attestation testing
INSERT INTO `compta_type` (`id`, `label`, `color`, `sort_order`, `is_cotisation`, `is_excluded_from_donation`, `is_institutional`)
VALUES
  (1, 'Cotisation',  'bg-primary',   0, 1, 0, 0),
  (2, 'Institution',  'bg-info',      1, 0, 0, 1),
  (3, 'Don',          'bg-success',   2, 0, 0, 0),
  (4, 'Vente',        'bg-secondary', 3, 0, 1, 0);

-- Compta entries
-- User 1: paid 2026 cotisation + a Don (wants attestation) + a Vente (excluded from donations)
-- User 2: paid 2025 and 2026 cotisation (active member, loyal) + an Institution donation + a Don, both wanting attestation
-- User 4: paid 2025 cotisation only (lapsed for 2026) + a Don (no attestation requested)
-- User 5: paid 2025 cotisation only (lapsed for 2026, no email — skip case)
INSERT INTO `compta` (`id`, `user_id`, `date`, `libele`, `sum`, `quittance`, `type_id`, `wants_attestation`, `cotisation_year`)
VALUES
  (1,  1, UNIX_TIMESTAMP(), 'Cotisation annuelle',        '50',  'Q-001', 1, 0, 2026),
  (2,  2, UNIX_TIMESTAMP(), 'Cotisation annuelle 2025',   '50',  'Q-002', 1, 0, 2025),
  (3,  2, UNIX_TIMESTAMP(), 'Cotisation annuelle 2026',   '50',  'Q-003', 1, 0, 2026),
  (4,  4, UNIX_TIMESTAMP(), 'Cotisation annuelle 2025',   '50',  'Q-004', 1, 0, 2025),
  (5,  5, UNIX_TIMESTAMP(), 'Cotisation annuelle 2025',   '50',  'Q-005', 1, 0, 2025),
  (6,  1, UNIX_TIMESTAMP(), 'Don libre',                  '80',  'Q-006', 3, 1, NULL),
  (7,  1, UNIX_TIMESTAMP(), 'Vente brocante',              '30',  'Q-007', 4, 0, NULL),
  (8,  2, UNIX_TIMESTAMP(), 'Don institutionnel',          '500', 'Q-008', 2, 1, NULL),
  (9,  2, UNIX_TIMESTAMP(), 'Don libre',                  '120', 'Q-009', 3, 1, NULL),
  (10, 4, UNIX_TIMESTAMP(), 'Don libre',                  '60',  'Q-010', 3, 0, NULL);

-- Segment membership (join table)
INSERT INTO `contact_segment` (`user_id`, `segment_id`) VALUES
  (1, 1),
  (1, 2),
  (2, 1),
  (2, 2),
  (4, 1),
  (5, 1);

-- maxval rows
INSERT INTO `maxval` (`parameter`, `value`) VALUES
  ('userpropertiesid', 0),
  ('metagroup_id', 0);

SET foreign_key_checks = 1;
