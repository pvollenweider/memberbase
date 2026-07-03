#!/usr/bin/env bash
# Publish the latest CHANGELOG.md entry to the gh-pages site (lightweight path).
#
# Patches only the changelog section of index.html via tools/site-release.mjs —
# no full site regeneration. Use /impeccable only when doc content changed
# substantially (new pages, reworked doc/*.md).
set -euo pipefail

cd "$(dirname "$0")/.."
WORKTREE=$(mktemp -d /tmp/gh-pages.XXXXXX)
trap 'git worktree remove --force "$WORKTREE" 2>/dev/null || true; rm -rf "$WORKTREE"' EXIT

git fetch origin gh-pages
git worktree add "$WORKTREE" origin/gh-pages --detach

node tools/site-release.mjs "$WORKTREE/index.html" CHANGELOG.md

if git -C "$WORKTREE" diff --quiet; then
  echo "Site already up to date."
  exit 0
fi

VERSION=$(sed -nE 's/^## \[([0-9.]+)\].*/\1/p' CHANGELOG.md | head -1)
git -C "$WORKTREE" add index.html
git -C "$WORKTREE" commit --author="pvollenweider <pvollenweider@jahia.com>" \
  -m "Site : changelog v${VERSION}"
git -C "$WORKTREE" push origin HEAD:gh-pages
echo "gh-pages updated to v${VERSION}."
