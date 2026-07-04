-- Extend app_settings.value to TEXT to support multi-line fields (e.g. org_purpose).
ALTER TABLE `app_settings`
  MODIFY COLUMN `value` TEXT NOT NULL DEFAULT '';
