#!/usr/bin/env node
/**
 * Patch the gh-pages index.html changelog section from CHANGELOG.md.
 *
 * Deterministic release helper: inserts the latest CHANGELOG.md entry as a
 * <article class="release"> block (same accessible markup as existing entries:
 * h3 heading, ul/li, strong/code/a inline elements) and keeps the 3 most
 * recent releases visible. Idempotent: exits 0 without change if the version
 * is already on the page.
 *
 * Usage: node tools/site-release.mjs <path/to/gh-pages/index.html> [path/to/CHANGELOG.md]
 */
import { readFileSync, writeFileSync } from 'node:fs';

const [indexPath, changelogPath = 'CHANGELOG.md'] = process.argv.slice(2);
if (!indexPath) {
  console.error('Usage: node tools/site-release.mjs <gh-pages/index.html> [CHANGELOG.md]');
  process.exit(1);
}

const changelog = readFileSync(changelogPath, 'utf8');

// Latest entry: "## [x.y.z] — YYYY-MM-DD" up to the next "## [".
const entryMatch = changelog.match(/^## \[(\d+\.\d+\.\d+)\] — (\d{4}-\d{2}-\d{2})\n([\s\S]*?)(?=^## \[|\Z)/m);
if (!entryMatch) {
  console.error('No release entry found in ' + changelogPath);
  process.exit(1);
}
const [, version, isoDate, body] = entryMatch;

const MONTHS_FR = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const [y, m, d] = isoDate.split('-').map(Number);
const dateFr = `${d}${d === 1 ? 'er' : ''} ${MONTHS_FR[m - 1]} ${y}`;

const escapeHtml = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// Markdown inline -> HTML matching the site's existing markup conventions.
function inlineMd(s) {
  return escapeHtml(s)
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/`([^`]+)`/g, '<code class="inline">$1</code>')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
    .replace(/(^|\s)(https?:\/\/[^\s),]+)/g, '$1<a href="$2">$2</a>');
}

// Top-level bullets only (section headers like "### Nouveautés" are skipped).
const bullets = body.split('\n')
  .filter((l) => /^- /.test(l))
  .map((l) => `        <li>${inlineMd(l.slice(2).trim())}</li>`);
if (!bullets.length) {
  console.error(`Entry [${version}] has no bullet points — nothing to publish.`);
  process.exit(1);
}

let html = readFileSync(indexPath, 'utf8');
if (html.includes(`<h3 class="v">${version} `)) {
  console.log(`Version ${version} already on the site — nothing to do.`);
  process.exit(0);
}

const article = [
  '    <article class="release">',
  `      <h3 class="v">${version} <span class="d">${dateFr}</span></h3>`,
  '      <ul>',
  ...bullets,
  '      </ul>',
  '    </article>',
].join('\n');

const anchor = /(<h2 id="changelog-h">[^<]*<\/h2>\n)/;
if (!anchor.test(html)) {
  console.error('Changelog anchor <h2 id="changelog-h"> not found in ' + indexPath);
  process.exit(1);
}
html = html.replace(anchor, `$1${article}\n`);

// Keep only the 3 most recent releases in the section.
const articleRe = /[ \t]*<article class="release">[\s\S]*?<\/article>\n/g;
const articles = html.match(articleRe) || [];
for (const extra of articles.slice(3)) {
  html = html.replace(extra, '');
}

writeFileSync(indexPath, html);
console.log(`Patched ${indexPath}: added ${version} (${dateFr}), ${bullets.length} bullet(s), keeping ${Math.min(articles.length, 3)} releases.`);
