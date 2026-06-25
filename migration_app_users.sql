-- Migration: app_users table for PHP-based authentication
-- Run once on the server: mysql -u members -p members < migration_app_users.sql

CREATE TABLE IF NOT EXISTS app_users (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    username             VARCHAR(100) NOT NULL UNIQUE,
    display_name         VARCHAR(200) DEFAULT NULL,
    email                VARCHAR(200) DEFAULT NULL,
    password_hash        VARCHAR(255) NOT NULL,
    role                 ENUM('admin','user') NOT NULL DEFAULT 'user',
    force_password_change TINYINT(1)  NOT NULL DEFAULT 1,
    is_active            TINYINT(1)  NOT NULL DEFAULT 1,
    created_at           TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login           TIMESTAMP   NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial admin user: login=admin / password=ChangeMe123!
-- Password MUST be changed on first login (force_password_change=1).
-- Generate the hash on the server:
--   php -r "echo password_hash('ChangeMe123!', PASSWORD_DEFAULT);"
-- Then insert with the real hash:
INSERT IGNORE INTO app_users (username, display_name, role, password_hash, force_password_change)
VALUES ('admin', 'Administrateur', 'admin',
        /* REPLACE with: php -r "echo password_hash('ChangeMe123!', PASSWORD_DEFAULT);" */
        '$2y$12$PLACEHOLDER_RUN_PHP_TO_GENERATE_REAL_HASH_XXXXXXXXX',
        1);
