#!/usr/bin/env bash
# Asserts that the legacy database (tests/fixtures/legacy.sql) has converged to
# the current schema after `migrate.php` ran. Used by .github/workflows/upgrade.yml.
#
# Expects DB connection via env: DB_HOST, DB_NAME, DB_USER, DB_PASS.
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="${DB_NAME:-members}"
DB_USER="${DB_USER:-members}"
DB_PASS="${DB_PASS:-members}"

# Run a scalar query, trimmed. -N: no column names, -B: batch (tab-separated).
q() { mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -B -e "$1"; }

fail() { echo "FAIL: $1"; exit 1; }

# 1. Migration 0001 added users.email_alt
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='email_alt'")" = "1" ] \
  || fail "users.email_alt column missing (0001 not applied)"

# 2. Migration 0002 converted compta.sum to DECIMAL
DT="$(q "SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='compta' AND column_name='sum'")"
[ "$DT" = "decimal" ] || fail "compta.sum data type is '$DT', expected 'decimal' (0002 not applied)"

# 3. Dirty values cleaned during the conversion
[ "$(q "SELECT sum FROM compta WHERE id=2")" = "12.50" ] || fail "comma decimal not converted to 12.50"
[ "$(q "SELECT sum FROM compta WHERE id=3")" = "0.00"  ] || fail "empty value not zeroed"
[ "$(q "SELECT sum FROM compta WHERE id=4")" = "0.00"  ] || fail "non-numeric value not zeroed"

# 4. Totals compute correctly (50 + 12.50 + 0 + 0 + 100 = 162.50)
TOT="$(q "SELECT COALESCE(SUM(sum),0) FROM compta")"
[ "$TOT" = "162.50" ] || fail "SUM(compta.sum) is '$TOT', expected 162.50"

# 5. Migration 0003 added app_users.locale
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_users' AND column_name='locale'")" = "1" ] \
  || fail "app_users.locale column missing (0003 not applied)"

# 6. Migration 0004 widened app_settings.value to TEXT
DT2="$(q "SELECT DATA_TYPE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='app_settings' AND column_name='value'")"
[ "$DT2" = "text" ] || fail "app_settings.value data type is '$DT2', expected 'text' (0004 not applied)"

# 7. Migration 0005 created the email_log table
TBL="$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='email_log'")"
[ "$TBL" = "1" ] || fail "email_log table missing (0005 not applied)"

# 8. Migration 0006 created the email_templates table
TBL2="$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='email_templates'")"
[ "$TBL2" = "1" ] || fail "email_templates table missing (0006 not applied)"

# 9. All 6 migrations recorded in schema_migrations, with checksums
N="$(q "SELECT COUNT(*) FROM schema_migrations")"
[ "$N" = "6" ] || fail "schema_migrations has $N rows, expected 6"
BAD="$(q "SELECT COUNT(*) FROM schema_migrations WHERE checksum='' OR checksum IS NULL")"
[ "$BAD" = "0" ] || fail "$BAD applied migration(s) missing a checksum"

echo "Upgrade convergence OK (schema + data + tracking)"
