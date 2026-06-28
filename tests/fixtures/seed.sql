-- Deterministic seed for Playwright E2E tests
-- DB: members_test
-- Admin password: TestPassword1! (bcrypt)

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- App user (admin)
INSERT INTO `app_users` (`id`, `username`, `display_name`, `email`, `password_hash`, `role`, `force_password_change`, `is_active`)
VALUES (1, 'testadmin', 'Test Admin', 'testadmin@example.com', '$2y$10$EYbiM6FFG8rJrlQfspD8wevoLGlK6aeE8CvvHt34lvxflse7Rq1MG', 'admin', 0, 1);

-- Regular members
INSERT INTO `users` (`id`, `lastname`, `firstname`, `email`, `status`, `creationDate`, `modificationDate`, `comment`)
VALUES
  (1, 'Dupont', 'Alice', 'alice@example.com', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), ''),
  (2, 'Martin', 'Bob', 'bob@example.com', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), '');

-- Teams / groups
INSERT INTO `team` (`id`, `name`, `hidden`) VALUES (1, 'Membre 2025', 0);
INSERT INTO `team` (`id`, `name`, `hidden`) VALUES (2, 'Membre 2026', 0);

-- App settings — default_team=0 means "all active members" (no group filter)
INSERT INTO `app_settings` (`key`, `value`) VALUES
  ('default_team', '0'),
  ('membre_team', '2'),
  ('membre_team_prefix', 'Membre'),
  ('org_name', 'MemberBase Test');

-- Compta type
INSERT INTO `compta_type` (`id`, `label`, `color`, `sort_order`, `is_cotisation`, `is_excluded_from_donation`, `is_institutional`)
VALUES (1, 'Cotisation', 'bg-primary', 0, 1, 0, 0);

-- Compta entry for user 1
INSERT INTO `compta` (`id`, `user_id`, `date`, `libele`, `sum`, `quittance`, `type_id`, `wants_attestation`)
VALUES (1, 1, UNIX_TIMESTAMP(), 'Cotisation annuelle', '50', 'Q-001', 1, 0);

-- user_properties: link both users to team 1 (Membre 2025) and team 2 (Membre 2026)
INSERT INTO `user_properties` (`id`, `user_id`, `parameter`, `date`, `value`) VALUES
  (1, 1, 'team_1', UNIX_TIMESTAMP(), 'true'),
  (2, 1, 'team_2', UNIX_TIMESTAMP(), 'true'),
  (3, 2, 'team_1', UNIX_TIMESTAMP(), 'true'),
  (4, 2, 'team_2', UNIX_TIMESTAMP(), 'true');

-- maxval rows
INSERT INTO `maxval` (`parameter`, `value`) VALUES
  ('userpropertiesid', 4),
  ('metagroup_id', 0);

SET foreign_key_checks = 1;
