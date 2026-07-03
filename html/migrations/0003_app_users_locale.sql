-- Per-user UI locale for the multilingual interface.
-- 'fr' is the application default; 'en', 'de', 'es' are available overrides.
ALTER TABLE `app_users`
  ADD COLUMN IF NOT EXISTS `locale` varchar(5) NOT NULL DEFAULT 'fr' AFTER `role`;
