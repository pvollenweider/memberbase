-- Legacy database snapshot: the schema/data state BEFORE the versioned
-- migrations existed (see tests/upgrade). Used by the CI upgrade test to prove
-- that `migrate.php` converges an old, "dirty" database to the current schema.
--
--   * users has NO `email_alt` column   → migration 0001 must add it
--   * compta.sum is VARCHAR(64)          → migration 0002 must convert to DECIMAL
--   * a few "dirty" sums (comma, empty, non-numeric) → must be cleaned to 0/dot
--   * NO schema_migrations table          → migrate.php must create + record it
--   * users table renamed to contact     → migration 0015 must rename it

-- Table is named `users` here (pre-migration state); migration 0015 renames it to `contact`.
CREATE TABLE `users` (
  `id`        int(8)       NOT NULL AUTO_INCREMENT,
  `lastname`  varchar(255) NOT NULL DEFAULT '',
  `firstname` varchar(255) NOT NULL DEFAULT '',
  `email`     varchar(255) NOT NULL DEFAULT '',
  `status`    tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `lastname`, `firstname`, `email`) VALUES
  (1, 'Doe',  'Jane', 'jane@example.org'),
  (2, 'Roe',  'John', 'john@example.org');

CREATE TABLE `compta` (
  `id`                int(8)       NOT NULL AUTO_INCREMENT,
  `user_id`           int(8)       NOT NULL DEFAULT 0,
  `date`              int(16)      NOT NULL DEFAULT 0,
  `libele`            varchar(255) NOT NULL DEFAULT '',
  `sum`               varchar(64)  NOT NULL DEFAULT '',
  `quittance`         varchar(64)  NOT NULL DEFAULT '',
  `type_id`           int(11)      DEFAULT NULL,
  `wants_attestation` tinyint(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `compta` (`id`, `user_id`, `sum`) VALUES
  (1, 1, '50'),        -- plain integer      → 50.00
  (2, 1, '12,50'),     -- comma decimal      → 12.50
  (3, 1, ''),          -- empty              → 0.00
  (4, 1, 'abc'),       -- non-numeric        → 0.00
  (5, 2, '100.00');    -- dotted decimal     → 100.00

-- app_users without `locale` column → migration 0003 must add it
CREATE TABLE `app_users` (
  `id`                    int(11)      NOT NULL AUTO_INCREMENT,
  `username`              varchar(100) NOT NULL,
  `display_name`          varchar(200) DEFAULT NULL,
  `email`                 varchar(200) DEFAULT NULL,
  `password_hash`         varchar(255) NOT NULL,
  `role`                  enum('admin','manager','user','readonly') NOT NULL DEFAULT 'user',
  `force_password_change` tinyint(1)   NOT NULL DEFAULT 1,
  `is_active`             tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`            timestamp    NOT NULL DEFAULT current_timestamp(),
  `last_login`            timestamp    NULL DEFAULT NULL,
  `reset_token`           varchar(64)  DEFAULT NULL,
  `token_expires_at`      datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- metagroup table → migration 0014 renames teamid column to segmentid
CREATE TABLE `metagroup` (
  `id`         int(11)      NOT NULL,
  `name`       varchar(255) DEFAULT NULL,
  `teamid`     int(11)      DEFAULT NULL,
  `is_filter`  tinyint(1)   NOT NULL DEFAULT 1,
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  KEY `idx_teamid`   (`teamid`),
  KEY `idx_id_name`  (`id`, `name`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- team table → migration 0014 renames it to segment
CREATE TABLE `team` (
  `id`     int(11)      NOT NULL AUTO_INCREMENT,
  `name`   varchar(64)  NOT NULL DEFAULT '',
  `hidden` tinyint(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- app_settings with value as varchar(255) → migration 0004 must widen to TEXT
CREATE TABLE `app_settings` (
  `key`   varchar(64)  NOT NULL,
  `value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
