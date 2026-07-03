#!/usr/bin/env bash
# Dumps the MemberBase database to a SQL file.
#
# Usage:  bash html/tools/backup.sh [output-file.sql]
# Config: env DB_HOST/DB_NAME/DB_USER/DB_PASS, else read from conf/db.php.
#
# The dump uses --single-transaction (consistent, non-locking on InnoDB) and
# --add-drop-table (default) so a restore replaces existing tables.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CONF="$REPO_ROOT/conf/db.php"

# Resolve DB config: env wins; otherwise extract from conf/db.php via PHP.
if [ -z "${DB_NAME:-}" ] && [ -f "$CONF" ]; then
  DB_HOST="$(php -r 'require $argv[1]; echo defined("DB_HOST")?DB_HOST:"localhost";' "$CONF")"
  DB_NAME="$(php -r 'require $argv[1]; echo DB_NAME;' "$CONF")"
  DB_USER="$(php -r 'require $argv[1]; echo DB_USER;' "$CONF")"
  DB_PASS="$(php -r 'require $argv[1]; echo DB_PASS;' "$CONF")"
fi
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-members}"
DB_PASS="${DB_PASS:-members}"
DB_NAME="${DB_NAME:-members}"

OUT="${1:-backup_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql}"

export MYSQL_PWD="$DB_PASS"

# A MySQL 8 client dumping a MariaDB server queries information_schema.
# COLUMN_STATISTICS (which MariaDB lacks) and fails — disable it when supported.
# MariaDB's own mariadb-dump has no such option, so only add it when present.
DUMP_OPTS="--single-transaction --routines --triggers --add-drop-table"
if mysqldump --help 2>/dev/null | grep -q -- '--column-statistics'; then
  DUMP_OPTS="$DUMP_OPTS --column-statistics=0"
fi

mysqldump -h"$DB_HOST" -u"$DB_USER" $DUMP_OPTS "$DB_NAME" > "$OUT"

echo "Backup written to $OUT ($(wc -c < "$OUT") bytes)"
