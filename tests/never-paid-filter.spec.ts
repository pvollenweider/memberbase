/**
 * E2E tests — "never paid, created long ago" quick filter (FILTER_NEVER_PAID_OLD)
 * and its 3 bulk actions (create segment / add to segment / archive).
 *
 * Contacts must be backfilled via raw SQL: creationDate isn't client-settable
 * through the API (set server-side on save), and this filter's whole point is
 * to distinguish contacts by age, so the fixture needs a real backdated row.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';

const REPO_ROOT = __dirname + '/..';
const FILTER_NEVER_PAID_OLD = -7777;

function sql(query: string): string {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'mariadb', 'mariadb', '-u', 'root', '-proot', 'members_test', '-N', '-e', query],
    { cwd: REPO_ROOT }
  ).toString().trim();
}

test.describe('FILTER_NEVER_PAID_OLD — quick filter', () => {
  test('flags an old contact with no compta entry, excludes a fresh one and one with a payment', async ({ page }) => {
    const oldNoCoti = sql(`
      INSERT INTO contact (firstName, lastName, email, status, comment, creationDate)
      VALUES ('OldNoCoti', 'NeverPaidE2E', 'old.nocoti.neverpaid.e2e@example.com', 1, '', DATE_SUB(NOW(), INTERVAL 5 YEAR));
      SELECT LAST_INSERT_ID();
    `);
    const oldWithCoti = sql(`
      INSERT INTO contact (firstName, lastName, email, status, comment, creationDate)
      VALUES ('OldWithCoti', 'NeverPaidE2E', 'old.withcoti.neverpaid.e2e@example.com', 1, '', DATE_SUB(NOW(), INTERVAL 5 YEAR));
      SELECT LAST_INSERT_ID();
    `);
    sql(`INSERT INTO compta (user_id, date, libele, sum, type_id) VALUES (${oldWithCoti}, NOW(), 'Don', 50, 3)`);
    const fresh = sql(`
      INSERT INTO contact (firstName, lastName, email, status, comment, creationDate)
      VALUES ('Fresh', 'NeverPaidE2E', 'fresh.neverpaid.e2e@example.com', 1, '', NOW());
      SELECT LAST_INSERT_ID();
    `);

    await page.goto(`/index.php?segment=${FILTER_NEVER_PAID_OLD}`);
    await expect(page.locator('tbody')).toContainText('OldNoCoti');
    await expect(page.locator('tbody')).not.toContainText('OldWithCoti');
    await expect(page.locator('tbody')).not.toContainText('Fresh NeverPaidE2E');

    // Clean up so later tests in this file (which assert on exact counts) start fresh.
    sql(`DELETE FROM compta WHERE user_id=${oldWithCoti}`);
    sql(`DELETE FROM contact WHERE id IN (${oldNoCoti}, ${oldWithCoti}, ${fresh})`);
  });
});

test.describe.serial('FILTER_NEVER_PAID_OLD — bulk actions', () => {
  let memberId: string;

  test.beforeAll(() => {
    memberId = sql(`
      INSERT INTO contact (firstName, lastName, email, status, comment, creationDate)
      VALUES ('BulkTarget', 'NeverPaidE2E', 'bulk.target.neverpaid.e2e@example.com', 1, '', DATE_SUB(NOW(), INTERVAL 5 YEAR));
      SELECT LAST_INSERT_ID();
    `);
  });

  test.afterAll(() => {
    sql(`DELETE FROM contact_segment WHERE user_id=${memberId}`);
    sql(`DELETE FROM contact WHERE id=${memberId}`);
  });

  test('manager: create a segment from the filter', async ({ page }) => {
    await page.goto(`/index.php?segment=${FILTER_NEVER_PAID_OLD}`);
    await expect(page.locator('tbody')).toContainText('BulkTarget');

    await page.locator('button', { hasText: 'Créer un segment' }).click();
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForLoadState('load'),
      page.locator('#modal-never-paid-create-segment button[type="submit"]').click(),
    ]);
    await expect(page).toHaveURL(/view=updateSegment&id=\d+/);
    // Member list lives inside a collapsed <details> — check membership via
    // the DB directly rather than expanding incidental UI structure.
    const newSegmentId = new URL(page.url()).searchParams.get('id');
    const memberIds = sql(`SELECT user_id FROM contact_segment WHERE segment_id=${newSegmentId}`);
    expect(memberIds.split('\n')).toContain(memberId);
  });

  test('manager: add to an existing segment', async ({ page }) => {
    const targetSegmentId = sql(`
      INSERT INTO segment (name, hidden) VALUES ('NeverPaid target E2E', 0);
      SELECT LAST_INSERT_ID();
    `);

    await page.goto(`/index.php?segment=${FILTER_NEVER_PAID_OLD}`);
    await page.locator('button', { hasText: 'Ajouter à un segment existant' }).click();
    await page.locator('#modal-never-paid-add-segment select[name="targetSegmentId"]').selectOption(targetSegmentId);
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForLoadState('load'),
      page.locator('#modal-never-paid-add-segment button[type="submit"]').click(),
    ]);
    await expect(page).toHaveURL(new RegExp(`view=updateSegment&id=${targetSegmentId}`));
    const memberIds = sql(`SELECT user_id FROM contact_segment WHERE segment_id=${targetSegmentId}`);
    expect(memberIds.split('\n')).toContain(memberId);

    sql(`DELETE FROM contact_segment WHERE segment_id=${targetSegmentId}`);
    sql(`DELETE FROM segment WHERE id=${targetSegmentId}`);
  });

  test('manager (non-admin) does not see the archive button', async ({ browser }) => {
    const ctx = await browser.newContext({
      storageState: require('path').resolve(__dirname, '.auth/manager.json'),
    });
    const mgr = await ctx.newPage();
    await mgr.goto(`/index.php?segment=${FILTER_NEVER_PAID_OLD}`);
    await expect(mgr.locator('button', { hasText: 'Archiver en masse' })).toHaveCount(0);
    await ctx.close();
  });

  test('manager: server-side archiveNeverPaidUsers action is 403', async ({ browser }) => {
    const ctx = await browser.newContext({
      storageState: require('path').resolve(__dirname, '.auth/manager.json'),
    });
    const mgr = await ctx.newPage();
    await mgr.goto(`/index.php?segment=${FILTER_NEVER_PAID_OLD}`);
    const csrf = await mgr.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    const resp = await mgr.request.post('/index.php', {
      form: { action: 'archiveNeverPaidUsers', year: String(new Date().getFullYear()), csrf },
    });
    expect(resp.status()).toBe(403);
    await ctx.close();
  });

  test('admin: archive shows a warning and deactivates the members', async ({ page }) => {
    await page.goto(`/index.php?segment=${FILTER_NEVER_PAID_OLD}`);
    await expect(page.locator('tbody')).toContainText('BulkTarget');

    await page.locator('button', { hasText: 'Archiver en masse' }).click();
    await expect(page.locator('#modal-never-paid-archive .alert-warning')).toBeVisible();

    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForLoadState('load'),
      page.locator('#modal-never-paid-archive button[type="submit"]').click(),
    ]);

    const status = sql(`SELECT status FROM contact WHERE id=${memberId}`);
    expect(status).toBe('0');

    // Reactivate so the afterAll cleanup's DELETE isn't blocked by any
    // status-dependent guard, and to leave the fixture in a known state.
    sql(`UPDATE contact SET status=1 WHERE id=${memberId}`);
  });
});
