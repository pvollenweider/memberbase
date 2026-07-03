#!/usr/bin/env bash
# Restores the MemberBase database from a SQL dump produced by backup.sh.
#
# Usage:  bash html/tools/restore.sh <dump-file.sql>
# Config: env DB_HOST/DB_NAME/DB_USER/DB_PASS, else read from conf/db.php.
#
# ⚠️ Destructive: tables present in the dump are dropped and recreated.
# Always take a fresh backup before restoring in production.
set -euo pipefail

IN="${1:-}"
if [ -z "$IN" ] || [ ! -f "$IN" ]; then
  echo "Usage: bash html/tools/restore.sh <dump-file.sql>" >&2
  exit 1
fi

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
CONF="$REPO_ROOT/conf/db.php"

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

export MYSQL_PWD="$DB_PASS"
mysql -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" < "$IN"

echo "Restored $DB_NAME from $IN"
