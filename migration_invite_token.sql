-- Migration v2.2.3 — invitation token for new users
ALTER TABLE app_users
  ADD COLUMN reset_token      VARCHAR(64) DEFAULT NULL,
  ADD COLUMN token_expires_at DATETIME    DEFAULT NULL;
