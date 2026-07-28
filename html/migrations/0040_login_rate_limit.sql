-- Login brute-force rate limiting (fixed-window counter per username+IP).
-- Same shape as api_rate_limit, kept separate: a distinct pre-auth surface,
-- reset independently of the authenticated-API limiter.
CREATE TABLE IF NOT EXISTS `login_rate_limit` (
  `bucket`       varchar(190) NOT NULL,
  `hits`         int(11)      NOT NULL DEFAULT 0,
  `window_start` int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
