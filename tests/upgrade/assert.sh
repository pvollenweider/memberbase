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

# 1. Migration 0001 added email_alt column (table was users, renamed to contact by 0015)
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='contact' AND column_name='email_alt'")" = "1" ] \
  || fail "contact.email_alt column missing (0001 + 0015 not applied)"

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

# 9. Migration 0007 added compta.notified_at
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='compta' AND column_name='notified_at'")" = "1" ] \
  || fail "compta.notified_at column missing (0007 not applied)"

# 10. Migration 0008 added compta.cotisation_year
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='compta' AND column_name='cotisation_year'")" = "1" ] \
  || fail "compta.cotisation_year column missing (0008 not applied)"

# 11. Migration 0009 backfilled cotisation_year — no row with a date should remain NULL
NULL_COTI="$(q "SELECT COUNT(*) FROM compta WHERE cotisation_year IS NULL AND date > 0")"
[ "$NULL_COTI" = "0" ] || fail "$NULL_COTI compta row(s) still have cotisation_year NULL after backfill (0009 not applied)"

# 12. Migration 0010 added email_templates.body_html
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_templates' AND column_name='body_html'")" = "1" ] \
  || fail "email_templates.body_html column missing (0010 not applied)"

# 13. Migration 0011 added email_log.user_id/body_text/body_html
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_log' AND column_name='user_id'")" = "1" ] \
  || fail "email_log.user_id column missing (0011 not applied)"
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_log' AND column_name='body_html'")" = "1" ] \
  || fail "email_log.body_html column missing (0011 not applied)"

# 14. Migration 0012 added email_log.tpl_key
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_log' AND column_name='tpl_key'")" = "1" ] \
  || fail "email_log.tpl_key column missing (0012 not applied)"

# 15b. Migration 0013 created user_team (0014 then renames it → user_segment; verify it no longer exists as user_team)
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='user_team'")" = "0" ] \
  || fail "user_team table still exists after rename (0014 not applied)"

# 15c. Migration 0014 renamed team→segment, user_team→user_segment, teamid→segmentid
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='segment'")" = "1" ] \
  || fail "segment table missing (0014 not applied)"
#
# NOTE: metagroup.teamid → segmentid was the 0014 rename, but metagroup itself
# no longer exists in the final schema — 0022 split segmentid-bearing "member"
# rows into metagroup_member (dropping the column), and 0024 renamed both
# tables to combined_segment/combined_segment_member. Check the final shape.
[ "$(q "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='combined_segment_member' AND column_name='segment_id'")" = "1" ] \
  || fail "combined_segment_member.segment_id column missing (0014/0022/0024 chain not applied)"

# 15d. Migration 0015 renamed users→contact, user_segment→contact_segment, user_properties→contact_properties
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='users'")" = "0" ] \
  || fail "users table still exists after rename (0015 not applied)"
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='contact'")" = "1" ] \
  || fail "contact table missing (0015 not applied)"
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='contact_segment'")" = "1" ] \
  || fail "contact_segment table missing (0015 not applied)"
[ "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='contact_properties'")" = "1" ] \
  || fail "contact_properties table missing (0015 not applied)"

# 16. Every migration file recorded in schema_migrations, with checksums
EXPECTED="$(ls "$(dirname "${BASH_SOURCE[0]}")"/../../html/migrations/*.sql | wc -l | tr -d ' ')"
N="$(q "SELECT COUNT(*) FROM schema_migrations")"
[ "$N" = "$EXPECTED" ] || fail "schema_migrations has $N rows, expected $EXPECTED"
BAD="$(q "SELECT COUNT(*) FROM schema_migrations WHERE checksum='' OR checksum IS NULL")"
[ "$BAD" = "0" ] || fail "$BAD applied migration(s) missing a checksum"

echo "Upgrade convergence OK (schema + data + tracking)"
