-- "Paused" state for tasks — distinct from open/done, lets the secretary
-- park a task without pretending it's finished or leaving it cluttering the
-- active list. Mutually exclusive with done_at in practice (closing a task
-- clears paused_at), but not enforced at the DB level, application handles it.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suivi_task'
                      AND COLUMN_NAME = 'paused_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `suivi_task` ADD COLUMN `paused_at` datetime DEFAULT NULL AFTER `due_date`',
    'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
