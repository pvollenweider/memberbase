#!/usr/bin/env bash
# Deploy local html/ to production over rsync (bypasses git — no build step,
# no tag, just a straight file sync). Meant for quick fixes pushed from a
# machine that already has html/ checked out and working.
#
# Usage: tools/deploy.sh          (dry run first, then asks to confirm)
#        tools/deploy.sh --yes    (skip the confirmation prompt)
set -euo pipefail

cd "$(dirname "$0")/.."

REMOTE_HOST="pol@10.147.18.7"
REMOTE_PATH="/var/www/vhosts/membres.casa-alianza.ch/html/"
RSYNC_OPTS=(-uav --exclude=.DS_Store --exclude=.idea/ --exclude=.impeccable/)

AUTO_YES=0
[ "${1:-}" = "--yes" ] && AUTO_YES=1

echo "Dry run — files that would change on $REMOTE_HOST:"
rsync "${RSYNC_OPTS[@]}" --dry-run html/ "$REMOTE_HOST:$REMOTE_PATH"

if [ "$AUTO_YES" -ne 1 ]; then
    read -r -p "Deploy the above to production? [y/N] " REPLY
    case "$REPLY" in
        [yY]|[yY][eE][sS]) ;;
        *) echo "Aborted."; exit 1 ;;
    esac
fi

rsync "${RSYNC_OPTS[@]}" html/ "$REMOTE_HOST:$REMOTE_PATH"
echo "Deployed."
