-- Replace EAV team membership pattern with a proper join table.
-- user_properties rows WHERE parameter LIKE 'team_%' are migrated here.
CREATE TABLE IF NOT EXISTS user_team (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    PRIMARY KEY (user_id, team_id),
    KEY idx_user_team_team_id (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO user_team (user_id, team_id)
SELECT user_id, CAST(SUBSTRING(parameter, 6) AS UNSIGNED)
FROM user_properties
WHERE parameter LIKE 'team_%' AND value = 'true';

DELETE FROM user_properties WHERE parameter LIKE 'team_%';
