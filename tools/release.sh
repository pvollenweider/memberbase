#!/usr/bin/env bash
# Core release: version bump + commit + tag + GitHub release + site changelog.
#
# Usage: tools/release.sh 3.8.0
# Prerequisite: CHANGELOG.md already contains the "## [3.8.0] — YYYY-MM-DD" entry.
set -euo pipefail

VERSION="${1:-}"
[ -n "$VERSION" ] || { echo "Usage: tools/release.sh x.y.z"; exit 1; }

cd "$(dirname "$0")/.."
BOOTSTRAP=html/includes/lib/bootstrap.php

grep -q "^## \[$VERSION\]" CHANGELOG.md \
  || { echo "CHANGELOG.md has no entry '## [$VERSION]' — write it first."; exit 1; }
git rev-parse "v$VERSION" >/dev/null 2>&1 \
  && { echo "Tag v$VERSION already exists."; exit 1; }
[ -z "$(git status --porcelain "$BOOTSTRAP" CHANGELOG.md)" ] \
  || { echo "Uncommitted changes in $BOOTSTRAP / CHANGELOG.md — commit or stash first."; exit 1; }

sed -i '' "s/const APP_VERSION = '[0-9.]*';/const APP_VERSION = '$VERSION';/" "$BOOTSTRAP"
grep -q "APP_VERSION = '$VERSION'" "$BOOTSTRAP" || { echo "Version bump failed."; exit 1; }

git add "$BOOTSTRAP" CHANGELOG.md
git commit --author="pvollenweider <pvollenweider@jahia.com>" -m "Release $VERSION"
git tag "v$VERSION"
git push origin main "v$VERSION"

# Release notes = the CHANGELOG entry body (without its heading).
NOTES=$(awk "/^## \[$VERSION\]/{f=1; next} /^## \[/{f=0} f" CHANGELOG.md)
gh release create "v$VERSION" --title "MemberBase v$VERSION" --notes "$NOTES"

bash tools/publish-site.sh
echo "Release v$VERSION done."
