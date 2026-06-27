-- Add active column to users table (1=active, 0=hidden/inactive)
ALTER TABLE users ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1;
CREATE INDEX IF NOT EXISTS idx_users_active ON users (active);
