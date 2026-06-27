-- Rename active column to status on users table
ALTER TABLE users CHANGE active status TINYINT(1) NOT NULL DEFAULT 1;
