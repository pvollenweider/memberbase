-- Task management (issue #117): titled tasks with priority, due date, and
-- open/closed status, parallel to the free-text suivi notes in
-- contact_properties (does not touch that table).
CREATE TABLE IF NOT EXISTS `suivi_task` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)      DEFAULT NULL,          -- member concerned (NULL = global task)
  `created_by`  int(11)      DEFAULT NULL,          -- app_users.id (no FK, same convention as audit_log.app_user_id)
  `title`       varchar(255) NOT NULL DEFAULT '',
  `body`        text         DEFAULT NULL,
  `priority`    tinyint(1)   NOT NULL DEFAULT 2,    -- 1=haute 2=normale 3=basse
  `due_date`    date         DEFAULT NULL,
  `done_at`     datetime     DEFAULT NULL,          -- NULL = tâche ouverte
  `created_at`  datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suivi_task_user_id`  (`user_id`),
  KEY `idx_suivi_task_due_date` (`due_date`),
  KEY `idx_suivi_task_done_at`  (`done_at`),
  CONSTRAINT `fk_suivi_task_user` FOREIGN KEY (`user_id`) REFERENCES `contact` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
