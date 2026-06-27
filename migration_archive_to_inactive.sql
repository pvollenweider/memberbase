-- Migrate archive group (team_19) members to inactive (status=0)
--
-- ARCHIVE_ID = 19 (from app_settings / declarations.inc)
--
-- Step 1: Set status=0 for all users currently in the archive group
UPDATE users
SET status = 0
WHERE status = 1
  AND id IN (
      SELECT user_id FROM user_properties WHERE parameter = 'team_19'
  );

-- Step 2 (optional): Remove all team memberships from newly inactive users
-- This cleans up their group assignments; skip if you want to keep the historical data.
--
-- DELETE FROM user_properties
-- WHERE user_id IN (
--     SELECT id FROM users WHERE status = 0
-- )
-- AND parameter LIKE 'team_%';

-- Verify result:
-- SELECT COUNT(*) AS inactive_count FROM users WHERE status = 0;
-- SELECT u.id, u.firstName, u.lastName FROM users u
-- JOIN user_properties up ON up.user_id = u.id AND up.parameter = 'team_19'
-- WHERE u.status = 0;
