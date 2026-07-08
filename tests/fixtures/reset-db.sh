#!/usr/bin/env bash
# Drops and recreates members_test, then seeds it.
# Requires the mariadb container to be running.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

echo "Resetting members_test database..."

# Drop and recreate
docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T mariadb \
  mariadb -uroot -proot -e "
    DROP DATABASE IF EXISTS members_test;
    CREATE DATABASE members_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON members_test.* TO 'members'@'%';
    FLUSH PRIVILEGES;
  "

# Apply schema
docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T mariadb \
  mariadb -umembers -pmembers members_test < "$REPO_ROOT/schema.sql"

# Apply seed
docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T mariadb \
  mariadb -umembers -pmembers members_test < "$SCRIPT_DIR/seed.sql"

# Write conf/db.php pointing at members_test so the app uses it during tests
mkdir -p "$REPO_ROOT/conf"
cat > "$REPO_ROOT/conf/db.php" <<'PHP'
<?php
define('DB_HOST', 'mariadb');
define('DB_NAME', 'members_test');
define('DB_USER', 'members');
define('DB_PASS', 'members');
PHP

# Baseline all migrations so the "pending migrations" banner never shows during tests.
# The schema was applied directly from schema.sql; --baseline records them as applied.
docker compose -f "$REPO_ROOT/docker-compose.yml" exec -T php \
  php /var/www/html/tools/migrate.php --baseline 2>/dev/null || true

# Purge Mailpit inbox so tests start clean
curl -s -X DELETE http://localhost:8025/api/v1/messages || true

echo "members_test reset complete."
