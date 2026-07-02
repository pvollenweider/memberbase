/**
 * Schema drift guard — schema.sql vs the schema embedded in install.php
 *
 * The installer embeds its own copy of the schema (heredoc) so a fresh
 * install has no external file dependency. That copy MUST stay in sync
 * with schema.sql: in v3.5.4 the users.email_alt column was added to
 * schema.sql only, and every fresh install produced a database where
 * User::save() crashed on INSERT (Unknown column 'email_alt').
 *
 * Pure Node test — no browser, no database. Parses both files and
 * compares table lists and per-table column definitions.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const ROOT = path.resolve(__dirname, '..');

type TableColumns = Map<string, Map<string, string>>; // table -> column -> normalized definition

function parseSchema(sql: string): TableColumns {
  const tables: TableColumns = new Map();
  const tableRe = /CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?\s*\(([\s\S]*?)\)\s*ENGINE/g;
  let tm: RegExpExecArray | null;
  while ((tm = tableRe.exec(sql)) !== null) {
    const [, table, body] = tm;
    const cols = new Map<string, string>();
    for (const line of body.split('\n')) {
      const cm = line.match(/^\s*`(\w+)`\s+(.*?),?\s*$/);
      if (cm) {
        // Normalize: lowercase, collapse whitespace, ignore trailing comma
        cols.set(cm[1], cm[2].toLowerCase().replace(/\s+/g, ' ').trim());
      }
    }
    tables.set(table, cols);
  }
  return tables;
}

function loadEmbeddedSchema(): string {
  const installer = fs.readFileSync(path.join(ROOT, 'html/install.php'), 'utf-8');
  const m = installer.match(/<<<'SQL'\n([\s\S]*?)\nSQL;/);
  if (!m) throw new Error("Embedded schema heredoc (<<<'SQL' ... SQL;) not found in html/install.php");
  return m[1];
}

test('install.php embedded schema matches schema.sql (tables and columns)', () => {
  const fromFile = parseSchema(fs.readFileSync(path.join(ROOT, 'schema.sql'), 'utf-8'));
  const fromInstaller = parseSchema(loadEmbeddedSchema());

  expect(fromFile.size, 'schema.sql should define tables').toBeGreaterThan(0);
  expect(fromInstaller.size, 'install.php should embed tables').toBeGreaterThan(0);

  const drift: string[] = [];

  const allTables = new Set([...fromFile.keys(), ...fromInstaller.keys()]);
  for (const table of [...allTables].sort()) {
    const a = fromFile.get(table);
    const b = fromInstaller.get(table);
    if (!a) { drift.push(`table '${table}' only in install.php`); continue; }
    if (!b) { drift.push(`table '${table}' missing from install.php`); continue; }

    const allCols = new Set([...a.keys(), ...b.keys()]);
    for (const col of [...allCols].sort()) {
      if (!a.has(col)) drift.push(`${table}.${col} only in install.php`);
      else if (!b.has(col)) drift.push(`${table}.${col} missing from install.php`);
      else if (a.get(col) !== b.get(col)) {
        drift.push(`${table}.${col} definition differs:\n    schema.sql : ${a.get(col)}\n    install.php: ${b.get(col)}`);
      }
    }
  }

  expect(drift, `Schema drift detected between schema.sql and install.php:\n- ${drift.join('\n- ')}\n\nUpdate BOTH files (and document the migration in MIGRATION_PROD.md).`).toEqual([]);
});
