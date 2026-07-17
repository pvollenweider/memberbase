#!/usr/bin/env node
// Concat + minify the vendor/app CSS and JS listed in html/index.php's
// <head> into a handful of committed bundles (html/css/dist/, html/js/dist/)
// — cuts the page from ~25 separate vendor requests down to ~6. Run this
// (`npm run dist`) whenever a listed source file changes and commit the
// result, same convention as html/vendor/ for PHP deps (no build step in
// prod). `npm run dist:watch` re-runs on save for local dev.
//
// Deliberately left OUT of the bundles (kept as individual <script> tags in
// index.php): alpine.min.js (defer — concatenating it with the blocking
// vendor scripts would force the whole bundle to defer, changing load
// timing for jQuery/Bootstrap/DataTables too), member-general-form.js (must
// run before Alpine boots, ordering relative to the deferred alpine.min.js
// matters), and tiptap-editor.js (type="module", not concatenable with
// legacy global scripts).
import esbuild from 'esbuild';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');
const watch = process.argv.includes('--watch');

// Exact load order from html/index.php's <head> — cascade (CSS) and
// execution order (JS, all plain blocking scripts) both depend on it.
const CSS_FILES = [
    'html/css/vendor/inter.css',
    'html/css/vendor/metropolis.css',
    'html/css/vendor/bootstrap.min.css',
    'html/css/vendor/dataTables.bootstrap5.min.css',
    'html/css/vendor/buttons.bootstrap5.min.css',
    'html/css/bootstrap-datetimepicker.min.css',
    'html/css/vendor/font-awesome.min.css',
    'html/css/custom.css',
];

const JS_VENDOR_FILES = [
    'html/js/vendor/jquery-3.7.1.min.js',
    'html/js/vendor/bootstrap.bundle.min.js',
    'html/js/vendor/moment.min.js',
    'html/js/vendor/fr.js',
    'html/js/vendor/bootstrap-datetimepicker.min.js',
    'html/js/vendor/jquery.highlight.js',
    'html/js/vendor/datahref2.jquery.js',
    'html/js/vendor/jquery.dataTables.min.js',
    'html/js/vendor/dataTables.bootstrap5.min.js',
    'html/js/vendor/dataTables.buttons.min.js',
    'html/js/vendor/buttons.bootstrap5.min.js',
    'html/js/vendor/jszip.min.js',
    'html/js/vendor/pdfmake.min.js',
    'html/js/vendor/vfs_fonts.js',
    'html/js/vendor/buttons.html5.min.js',
    'html/js/vendor/buttons.print.min.js',
    'html/js/vendor/buttons.colVis.min.js',
    'html/js/vendor/datetime-moment.js',
    'html/js/dt_defaults.js',
    'html/js/vendor/Chart.bundle.min.js',
    'html/js/vendor/htmx.min.js',
];

// Body-end app scripts (also plain blocking, fixed order).
const JS_APP_FILES = [
    'html/js/app.js',
    'html/js/sidebar-nav.js',
];

function concat(files, sep = '\n') {
    return files.map((f) => readFileSync(join(ROOT, f), 'utf8')).join(sep);
}

// @charset is only valid as the very first bytes of a stylesheet — harmless
// once concatenated mid-file (browsers just ignore it), but stripped here to
// keep the output spec-clean. The page's own <meta charset="UTF-8"> already
// covers this for a same-origin CSS file.
function stripCharset(css) {
    return css.replace(/^﻿?@charset\s+["'][^"']*["'];?\s*/, '');
}

async function buildCss() {
    // Plain concatenation, no minify transform: esbuild's CSS minifier is a
    // stricter parser than browsers and risks mangling legacy vendor CSS
    // (old jQuery UI datetimepicker styles, IE-era hacks in some plugins) in
    // ways that are hard to catch without visually re-checking every page.
    // Cutting 8 requests down to 1 is the actual win here; shaving a few kB
    // off an already-cached file isn't worth that risk. Plain '\n' join (not
    // ';\n' like JS) — a bare ';' between stylesheets is invalid top-level
    // CSS, and bootstrap.min.css's leading @charset only being valid at
    // position 0 is another reason to keep this simple and exact.
    const code = CSS_FILES.map((f) => stripCharset(readFileSync(join(ROOT, f), 'utf8'))).join('\n');
    mkdirSync(join(ROOT, 'html/css/dist'), { recursive: true });
    writeFileSync(join(ROOT, 'html/css/dist/app.min.css'), code);
    console.log(`html/css/dist/app.min.css  (${CSS_FILES.length} files -> ${(code.length / 1024).toFixed(1)} kB)`);
}

async function buildJs(files, outPath, label) {
    const src = concat(files);
    const { code } = await esbuild.transform(src, { loader: 'js', minify: true });
    mkdirSync(dirname(join(ROOT, outPath)), { recursive: true });
    writeFileSync(join(ROOT, outPath), code);
    console.log(`${outPath}  (${files.length} files -> ${(code.length / 1024).toFixed(1)} kB)`);
}

async function build() {
    await buildCss();
    await buildJs(JS_VENDOR_FILES, 'html/js/dist/vendor.min.js', 'vendor');
    await buildJs(JS_APP_FILES, 'html/js/dist/app.min.js', 'app');
}

await build();

if (watch) {
    const { watch: fsWatch } = await import('fs');
    const watched = [...CSS_FILES, ...JS_VENDOR_FILES, ...JS_APP_FILES].map((f) => join(ROOT, f));
    console.log(`\nWatching ${watched.length} source files for changes...`);
    let pending = null;
    for (const f of watched) {
        fsWatch(f, () => {
            clearTimeout(pending);
            pending = setTimeout(() => build().catch((e) => console.error(e)), 100);
        });
    }
}
