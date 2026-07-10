-- API rate-limit tracking table, previously created lazily on every API
-- request (a DDL statement per request). Now part of the schema.
CREATE TABLE IF NOT EXISTS `api_rate_limit` (
  `bucket`       varchar(190) NOT NULL,
  `hits`         int(11)      NOT NULL DEFAULT 0,
  `window_start` int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
