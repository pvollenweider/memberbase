-- Replace EAV team membership pattern with a proper join table.
-- user_properties rows WHERE parameter LIKE 'team_%' are migrated here.
CREATE TABLE IF NOT EXISTS user_team (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    PRIMARY KEY (user_id, team_id),
    KEY idx_user_team_team_id (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure user_properties exists before backfilling (fresh/test installs may not have it).
-- IF NOT EXISTS is a no-op on real legacy installs that already have the table.
CREATE TABLE IF NOT EXISTS user_properties (
    user_id   INT          NOT NULL,
    parameter VARCHAR(100) NOT NULL,
    value     VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (user_id, parameter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: extract team_N → team_id from EAV rows.
INSERT IGNORE INTO user_team (user_id, team_id)
SELECT user_id, CAST(SUBSTRING(parameter, 6) AS UNSIGNED)
FROM user_properties
WHERE parameter LIKE 'team_%' AND value = 'true';

-- Remove migrated EAV rows.
DELETE FROM user_properties WHERE parameter LIKE 'team_%';
