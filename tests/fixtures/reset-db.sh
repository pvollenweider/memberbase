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

echo "members_test reset complete."
