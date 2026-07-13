-- Legacy database snapshot: the schema/data state BEFORE the versioned
-- migrations existed (see tests/upgrade). Used by the CI upgrade test to prove
-- that `migrate.php` converges an old, "dirty" database to the current schema.
--
--   * users has NO `email_alt` column   → migration 0001 must add it
--   * compta.sum is VARCHAR(64)          → migration 0002 must convert to DECIMAL
--   * a few "dirty" sums (comma, empty, non-numeric) → must be cleaned to 0/dot
--   * NO schema_migrations table          → migrate.php must create + record it
--   * users table renamed to contact     → migration 0015 must rename it
--   * user_properties.id is maxval-sequenced, not AUTO_INCREMENT (id=0 shared
--     by several rows) → migration 0020 must renumber and add a real PK
--   * creationDate/modificationDate are legacy int(16) Unix timestamps
--     → migrations 0026/0027 must convert them to DATETIME

-- Table is named `users` here (pre-migration state); migration 0015 renames it to `contact`.
-- Columns beyond the original minimal set (society..modificationDate) are real
-- pre-migration columns that migrations only ADD COLUMN ... AFTER <col> onto
-- or convert in place — never CREATE — so they must already be present here.
CREATE TABLE `users` (
  `id`               int(8)       NOT NULL AUTO_INCREMENT,
  `lastname`         varchar(255) NOT NULL DEFAULT '',
  `firstname`        varchar(255) NOT NULL DEFAULT '',
  `society`          varchar(255) NOT NULL DEFAULT '',
  `address`          varchar(255) NOT NULL DEFAULT '',
  `npa`              varchar(255) NOT NULL DEFAULT '',
  `tel`              varchar(255) NOT NULL DEFAULT '',
  `telprof`          varchar(255) NOT NULL DEFAULT '',
  `portable`         varchar(255) NOT NULL DEFAULT '',
  `fax`              varchar(255) NOT NULL DEFAULT '',
  `email`            varchar(255) NOT NULL DEFAULT '',
  `web`              varchar(255) NOT NULL DEFAULT '',
  `sexe`             varchar(8)   NOT NULL DEFAULT 'na',
  `title`            varchar(255) NOT NULL DEFAULT '',
  `comment`          mediumtext   NOT NULL,
  `birthday`         int(16)      NOT NULL DEFAULT 0,
  `creationDate`     int(16)      NOT NULL DEFAULT 0,
  `modificationDate` int(16)      NOT NULL DEFAULT 0,
  `status`           tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `lastname`, `firstname`, `email`, `comment`) VALUES
  (1, 'Doe',  'Jane', 'jane@example.org', ''),
  (2, 'Roe',  'John', 'john@example.org', '');

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

-- compta_type — always a real pre-migration table (compta.type_id already
-- references it); migrations 0021/0035/0036 only ADD COLUMN onto it, never
-- CREATE it.
CREATE TABLE `compta_type` (
  `id`                        int(11)      NOT NULL AUTO_INCREMENT,
  `label`                     varchar(255) NOT NULL,
  `color`                     varchar(64)  NOT NULL DEFAULT 'bg-light',
  `sort_order`                int(11)      NOT NULL DEFAULT 0,
  `is_cotisation`             tinyint(1)   NOT NULL DEFAULT 0,
  `is_excluded_from_donation` tinyint(1)   NOT NULL DEFAULT 0,
  `is_institutional`          tinyint(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `compta_type` (`id`, `label`, `color`, `is_cotisation`) VALUES
  (1, 'Don',        'bg-primary', 0),
  (2, 'Cotisation',  'bg-success', 1);

-- audit_log — real pre-migration table; migration 0023 only widens
-- subject_user_id from its legacy unsigned type to a signed int(11) matching
-- contact.id before adding the FK.
CREATE TABLE `audit_log` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `created_at`      datetime     NOT NULL DEFAULT current_timestamp(),
  `app_user_id`     int(11)      DEFAULT NULL,
  `username`        varchar(100) DEFAULT NULL,
  `action`          varchar(100) NOT NULL,
  `detail`          text         DEFAULT NULL,
  `subject_user_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- user_properties (legacy EAV table) → migration 0013 moves team_% rows into
-- user_team, migration 0015 renames the table to contact_properties,
-- migration 0020 normalizes id into a real AUTO_INCREMENT primary key.
-- No PK here: id was manually maintained via the `maxval` sequence below,
-- and legacy data has duplicate/zero ids.
CREATE TABLE `user_properties` (
  `id`        int(8)       NOT NULL DEFAULT 0,
  `user_id`   int(8)       NOT NULL DEFAULT 0,
  `parameter` varchar(64)  NOT NULL DEFAULT '',
  `date`      int(16)      NOT NULL DEFAULT 0,
  `value`     varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_properties` (`id`, `user_id`, `parameter`, `value`) VALUES
  (0, 1, 'team_1', 'true'),
  (0, 2, 'note',   'hello');

-- maxval: legacy manual id-sequence table, retired row by row as each table
-- it used to back gets a real AUTO_INCREMENT key (migrations 0020, 0022).
CREATE TABLE `maxval` (
  `parameter` varchar(64) NOT NULL,
  `value`     int(11)     NOT NULL DEFAULT 0,
  PRIMARY KEY (`parameter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `maxval` (`parameter`, `value`) VALUES
  ('userpropertiesid', 2),
  ('metagroup_id', 1);

-- app_settings with value as varchar(255) → migration 0004 must widen to TEXT
CREATE TABLE `app_settings` (
  `key`   varchar(64)  NOT NULL,
  `value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
