/**
 * E2E tests — yearly member-segment rollover and the cotisation→segment
 * membership hook (Réglages → Intégrité "cotisants absents du segment").
 *
 * The rollover lib function (mbRolloverYearlyMemberSegment) is exercised
 * directly via a PHP CLI probe rather than through cron.php's hardcoded
 * date('Y'): the seed already has "Membre <this year>" (segment id 2, a
 * foundational fixture many other spec files depend on), so faking a rollover
 * for the real current year would mean deleting/recreating that shared
 * segment — destructive to the rest of the suite. Using a synthetic future
 * year keeps this fully isolated while still exercising the exact same code
 * path cron.php calls.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';

const REPO_ROOT = __dirname + '/..';

function runPhp(code: string): string {
  return execFileSync('docker', ['compose', 'exec', '-T', 'php', 'php', '-r', code], {
    cwd: REPO_ROOT,
  }).toString().trim();
}

function sql(query: string): string {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'mariadb', 'mariadb', '-u', 'root', '-proot', 'members_test', '-N', '-e', query],
    { cwd: REPO_ROOT }
  ).toString().trim();
}

// A year far enough in the future that no seed fixture ever collides with it.
const TEST_YEAR = 2999;

test.describe('mbRolloverYearlyMemberSegment (PHP unit-style, isolated year)', () => {
  test.beforeAll(() => {
    // Clean slate: previous runs of this spec may have left the segments/settings behind.
    sql(`DELETE FROM contact_segment WHERE segment_id IN (SELECT id FROM segment WHERE name IN ('Membre ${TEST_YEAR}', 'Membre ${TEST_YEAR - 1}'))`);
    sql(`DELETE FROM segment WHERE name IN ('Membre ${TEST_YEAR}', 'Membre ${TEST_YEAR - 1}')`);
  });

  // default_segment/membre_segment are shared app_settings rows — every
  // other spec file assumes membre_segment=2 ("Membre <this year>", the
  // seed's real cotisation-tracking segment) and default_segment=0 (no
  // filter). The "creates the segment" test below deliberately points them
  // at this describe block's throwaway year-2999 segments; restore the
  // seeded values unconditionally (pass or fail) so the rest of the suite
  // isn't left running against a corrupted membre_segment.
  test.afterAll(() => {
    sql("UPDATE app_settings SET value='0' WHERE `key`='default_segment'");
    sql("UPDATE app_settings SET value='2' WHERE `key`='membre_segment'");
  });

  test('creates the segment, flips default_segment/membre_segment, and pre-fills advance payers', () => {
    // A "last year" segment to prove membre_segment gets pointed at it.
    const prevId = runPhp(`
      define('APP_ENTRY', true);
      require '/var/www/html/includes/lib/bootstrap.php';
      require '/var/www/html/classes/segment_class.php';
      $s = new Segment(); $s->name = 'Membre ${TEST_YEAR - 1}'; $s->setHidden(0); $s->save();
      echo $s->id;
    `);
    expect(Number(prevId)).toBeGreaterThan(0);

    // An advance payer for TEST_YEAR — a fresh contact with a cotisation-type
    // compta entry already dated that year, created directly in SQL so the
    // addCompta/API hook (tested separately below) never runs here — this
    // isolates the rollover's own pre-fill query.
    const contactId = sql(`INSERT INTO contact (firstName, lastName, email, status, comment) VALUES ('Advance', 'PayerE2E', 'advance.payer.e2e@example.com', 1, ''); SELECT LAST_INSERT_ID();`);
    sql(`INSERT INTO compta (user_id, date, libele, sum, type_id, cotisation_year) VALUES (${contactId}, NOW(), 'Cotisation ${TEST_YEAR}', 50, 1, ${TEST_YEAR})`);

    const out = runPhp(`
      define('APP_ENTRY', true);
      require '/var/www/html/includes/lib/bootstrap.php';
      require '/var/www/html/classes/segment_class.php';
      require '/var/www/html/classes/combined_segment_class.php';
      require '/var/www/html/includes/lib/segment_rollover.php';
      $r = mbRolloverYearlyMemberSegment(db(), $appSettings, ${TEST_YEAR});
      echo json_encode($r);
    `);
    const result = JSON.parse(out);
    expect(result.created).toBe(true);
    expect(result.name).toBe(`Membre ${TEST_YEAR}`);
    expect(result.prefilled).toBe(1);

    const defaultSegment = sql("SELECT value FROM app_settings WHERE `key`='default_segment'");
    expect(defaultSegment).toBe(String(result.segmentId));
    const membreSegment = sql("SELECT value FROM app_settings WHERE `key`='membre_segment'");
    expect(membreSegment).toBe(prevId);

    const memberIds = sql(`SELECT user_id FROM contact_segment WHERE segment_id=${result.segmentId}`);
    expect(memberIds.split('\n')).toContain(contactId);

    // The new segment must land in a "Membre" category (combined_segment, is_filter=0).
    const categoryRow = sql(`
      SELECT m.id FROM combined_segment m
      JOIN combined_segment_member mm ON mm.combined_segment_id = m.id
      WHERE mm.segment_id = ${result.segmentId} AND m.is_filter = 0 AND m.name = 'Membre'
    `);
    expect(categoryRow).not.toBe('');
  });

  test('running it again is a no-op (idempotent)', () => {
    const out = runPhp(`
      define('APP_ENTRY', true);
      require '/var/www/html/includes/lib/bootstrap.php';
      require '/var/www/html/classes/segment_class.php';
      require '/var/www/html/classes/combined_segment_class.php';
      require '/var/www/html/includes/lib/segment_rollover.php';
      $r = mbRolloverYearlyMemberSegment(db(), $appSettings, ${TEST_YEAR});
      echo json_encode($r);
    `);
    const result = JSON.parse(out);
    expect(result.created).toBe(false);

    const segCount = sql(`SELECT COUNT(*) FROM segment WHERE name='Membre ${TEST_YEAR}'`);
    expect(segCount).toBe('1');
  });
});

test.describe('cron.php wiring', () => {
  test('runs the rollover job and logs a skip when the current year segment already exists', () => {
    // Doesn't touch DB state — the seed already has "Membre <this year>",
    // so this just proves cron.php actually calls the job and it correctly
    // no-ops rather than erroring or duplicating anything.
    const out = execFileSync('docker', ['compose', 'exec', '-T', 'php', 'php', 'tools/cron.php'], {
      cwd: REPO_ROOT,
    }).toString();
    expect(out).toContain('[segment-rollover]');
  });
});

test.describe('Cotisation payment → segment membership hook', () => {
  test('adding a cotisation entry via the UI adds the member to "Membre <year>"', async ({ page }) => {
    const year = new Date().getFullYear();
    const createResp = await page.request.post('/api/contacts', {
      data: { firstName: 'UiHook', lastName: 'CotiE2E', email: 'ui.hook.coti.e2e@example.com' },
    });
    const { data: contact } = await createResp.json();

    await page.goto(`/index.php?view=compta&userid=${contact.id}`);
    const form = page.locator('form[name="addCompta"]');
    await form.locator('select[name="type_id"]').selectOption('1'); // Cotisation (seed type 1)
    await form.locator('input[name="date"]').fill(`01.06.${year}`);
    await form.locator('input[name="sum"]').fill('50');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('table', { hasText: 'Cotisation' })).toBeVisible({ timeout: 10_000 });

    const members = await (await page.request.get('/api/segments/2/members')).json(); // "Membre <year>" seed segment
    expect(members.data.map((m: any) => m.id)).toContain(contact.id);
  });

  test('adding a cotisation entry via the API adds the member to "Membre <year>"', async ({ page }) => {
    const year = new Date().getFullYear();
    const createResp = await page.request.post('/api/contacts', {
      data: { firstName: 'ApiHook', lastName: 'CotiE2E', email: 'api.hook.coti.e2e@example.com' },
    });
    const { data: contact } = await createResp.json();

    await page.request.post('/api/compta', {
      data: { memberId: contact.id, typeId: 1, date: `${year}-06-02`, amount: 50 },
    });

    const members = await (await page.request.get('/api/segments/2/members')).json();
    expect(members.data.map((m: any) => m.id)).toContain(contact.id);
  });

  test('a non-cotisation entry does NOT add the member to the segment', async ({ page }) => {
    const year = new Date().getFullYear();
    const createResp = await page.request.post('/api/contacts', {
      data: { firstName: 'NonCoti', lastName: 'HookE2E', email: 'non.coti.hook.e2e@example.com' },
    });
    const { data: contact } = await createResp.json();

    // Type 3 = "Don" in the seed (not a cotisation type).
    await page.request.post('/api/compta', {
      data: { memberId: contact.id, typeId: 3, date: `${year}-06-03`, amount: 80 },
    });

    const members = await (await page.request.get('/api/segments/2/members')).json();
    expect(members.data.map((m: any) => m.id)).not.toContain(contact.id);
  });
});

test.describe('Intégrité — cotisants absents du segment de leur année', () => {
  test('flags a member desynced from their year\'s segment, and the fix button repairs it', async ({ page }) => {
    const year = new Date().getFullYear();
    const createResp = await page.request.post('/api/contacts', {
      data: { firstName: 'Desync', lastName: 'IntegrityE2E', email: 'desync.integrity.e2e@example.com' },
    });
    const { data: contact } = await createResp.json();

    // The hook adds them to segment 2 ("Membre <year>") on payment...
    await page.request.post('/api/compta', {
      data: { memberId: contact.id, typeId: 1, date: `${year}-06-04`, amount: 50 },
    });
    // ...then simulate a desync (backfilled data, or removed by hand since).
    await page.request.delete('/api/segments/2/members', { data: { memberId: contact.id } });

    await page.goto('/index.php?view=settings&tab=integrity');
    const section = page.locator('.ca-integrity-section', { has: page.locator('text=Cotisants absents') });
    await expect(section).toBeVisible({ timeout: 10_000 });
    await section.locator('summary').click();
    await expect(section).toContainText('IntegrityE2E Desync');

    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      section.locator('form button[type="submit"]').first().click(),
    ]);

    const members = await (await page.request.get('/api/segments/2/members')).json();
    expect(members.data.map((m: any) => m.id)).toContain(contact.id);
  });
});
