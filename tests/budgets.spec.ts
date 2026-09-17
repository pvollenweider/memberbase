/**
 * E2E tests — Budgets page (Finances submenu) and dashboard budget-gap KPI line.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import { execFileSync } from 'child_process';

const REPO_ROOT = __dirname + '/..';

function sql(query: string): string {
  return execFileSync(
    'docker',
    ['compose', 'exec', '-T', 'mariadb', 'mariadb', '-u', 'root', '-proot', 'members_test', '-N', '-e', query],
    { cwd: REPO_ROOT }
  ).toString().trim();
}

test.describe('Budgets page', () => {
  test('reachable via ?view=budgets, past year is display-only, current/next years editable, split by donation exclusion', async ({ page }) => {
    await page.goto('/index.php?view=budgets');
    await expect(page.locator('h1', { hasText: 'Budgets' })).toBeVisible();
    const year = new Date().getFullYear();
    const donationTable = page.locator('#budgets-table-donation');
    const excludedTable = page.locator('#budgets-table-excluded');
    // Past year: collected-only, no "Budget <year-1>" column/input.
    await expect(donationTable).toContainText(`Collecté ${year - 1}`);
    await expect(donationTable).not.toContainText(`Budget ${year - 1}`);
    await expect(donationTable.locator(`.budget-input[data-year="${year - 1}"]`)).toHaveCount(0);
    // Current/next years: editable budget input each, current year also has a collected column.
    await expect(donationTable).toContainText(`Budget ${year}`);
    await expect(donationTable).toContainText(`Budget ${year + 1}`);
    await expect(donationTable).toContainText(`Collecté ${year}`);
    await expect(donationTable.locator(`.budget-input[data-year="${year}"]`)).not.toHaveCount(0);
    await expect(donationTable.locator(`.budget-input[data-year="${year + 1}"]`)).not.toHaveCount(0);
    // Seed's "Vente" compta type is the one flagged is_excluded_from_donation=1
    // (Cotisation is NOT excluded in this fixture, unlike prod's real config).
    await expect(excludedTable).toContainText('Vente');
    await expect(donationTable).not.toContainText('Vente');
  });

  test('editing a budget input auto-saves and persists after reload', async ({ page }) => {
    await page.goto('/index.php?view=budgets');
    const year = new Date().getFullYear();
    const table = page.locator('#budgets-table-donation');
    const input = table.locator(`.budget-input[data-year="${year}"]`).first();
    await input.fill('12345');
    await input.blur();
    await expect(page.locator('#budgets-status')).toContainText('Enregistré', { timeout: 5000 });

    await page.reload();
    await expect(page.locator('#budgets-table-donation').locator(`.budget-input[data-year="${year}"]`).first()).toHaveValue('12345.00');

    // Column total reflects the saved amount.
    await expect(page.locator('#budgets-table-donation').locator(`[data-total-year="${year}"]`)).toContainText("12'345");
  });

  test('collected cell shows a red/green delta badge against the budget, updates live as the budget changes', async ({ page }) => {
    const year = new Date().getFullYear();
    // compta_type 3 = "Don" (donation table) — seed a known amount for this
    // year so collected > 0 and the green case (budget <= collected) is reachable.
    await page.goto('/index.php');
    const csrf0 = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    await page.request.post('/index.php', {
      form: { action: 'addCompta', view: 'compta', userid: '1', type_id: '3', date: `01/06/${year}`, libele: 'Don E2E budget badge', sum: '1000', csrf: csrf0 },
    });

    await page.goto('/index.php?view=budgets');
    const table = page.locator('#budgets-table-donation');
    const input = table.locator(`.budget-input[data-compta-type-id="3"][data-year="${year}"]`);
    const cell = table.locator(`.collected-cell[data-compta-type-id="3"][data-year="${year}"]`);

    // Budget far above collected -> red (behind).
    await input.fill('1000000');
    await input.blur();
    await expect(cell.locator('.text-danger')).toBeVisible();

    // Budget at/below collected -> green (on track / exceeded).
    await input.fill('1');
    await input.blur();
    await expect(cell.locator('.text-success')).toBeVisible();

    await input.fill('');
    await input.blur();
    sql(`DELETE FROM compta WHERE libele = 'Don E2E budget badge'`);
  });

  test('manager role can also reach and save (isManager guard, not isAdmin)', async ({ browser }) => {
    const ctx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/manager.json') });
    const page = await ctx.newPage();
    await page.goto('/index.php?view=budgets');
    await expect(page.locator('.budgets-table')).toHaveCount(2);
    await ctx.close();
  });

  test('a non-manager role is denied access', async ({ browser }) => {
    const ctx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/user.json') });
    const page = await ctx.newPage();
    await page.goto('/index.php?view=budgets');
    await expect(page.locator('.alert-danger')).toBeVisible();
    await expect(page.locator('.budgets-table')).toHaveCount(0);
    await ctx.close();
  });

  test('server rejects updateComptaBudget for a past year even via direct POST', async ({ page }) => {
    await page.goto('/index.php?view=budgets');
    const year = new Date().getFullYear();
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'updateComptaBudget', compta_type_id: '3', year: String(year - 1), amount: '500' },
    });
    const json = await resp.json();
    expect(json.ok).toBe(false);
  });
});

test.describe('Dashboard — budget-gap KPI line', () => {
  const year = new Date().getFullYear();

  test.beforeAll(() => {
    // compta_budget is summed across ALL non-excluded types for the KPI —
    // clear the year first so this test's total is deterministic regardless
    // of what the "Budgets page" describe block above left behind.
    sql(`DELETE FROM compta_budget WHERE year = ${year}`);
  });
  test.afterAll(() => {
    sql(`DELETE FROM compta_budget WHERE year = ${year}`);
  });

  test('shows the gap-to-budget line once a budget is set for the current year, links to the Budgets page', async ({ page }) => {
    await page.goto('/index.php?view=budgets');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');

    // Give a non-excluded-from-donation compta type (id=3, "Don" in the seed)
    // a large budget so the gap is comfortably positive and deterministic.
    await page.request.post('/index.php', {
      form: { csrf, action: 'updateComptaBudget', compta_type_id: '3', year: String(year), amount: '999999' },
    });

    await page.goto('/index.php?view=dashboard');
    const contribBox = page.locator('.ca-kpi-box', { hasText: 'Contributions' }).first();
    await expect(contribBox.locator('a[href*="view=budgets"]')).toBeVisible();
    await expect(contribBox).toContainText(String(year));
    await expect(contribBox).toContainText("999'999");
    // Mutually exclusive with the vs-last-year fallback line.
    await expect(contribBox).toContainText('pour atteindre le budget');
    await expect(contribBox).not.toContainText(/pour atteindre \d{4} \(/);

    // Reset before the fallback test below runs.
    await page.request.post('/index.php', {
      form: { csrf, action: 'updateComptaBudget', compta_type_id: '3', year: String(year), amount: '0' },
    });
  });

  test('falls back to the vs-last-year comparison when no Contributions budget is set', async ({ page }) => {
    await page.goto('/index.php');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    // Huge amount so kTotal1 (last year) is comfortably above kTotal (this
    // year) regardless of what other spec files left in the current year —
    // deterministically exercises the "gap" branch, not "exceeded".
    await page.request.post('/index.php', {
      form: { action: 'addCompta', view: 'compta', userid: '1', type_id: '3', date: `15/03/${year - 1}`, libele: 'Don E2E budget fallback', sum: '5000000', csrf },
    });

    await page.goto('/index.php?view=dashboard');
    const contribBox = page.locator('.ca-kpi-box', { hasText: 'Contributions' }).first();
    await expect(contribBox).toContainText(new RegExp(`pour atteindre ${year - 1} \\(`));
    await expect(contribBox).not.toContainText('pour atteindre le budget');

    // This artificially huge prior-year entry would otherwise skew every
    // other spec file's prior-year delta computations — remove it.
    sql("DELETE FROM compta WHERE libele = 'Don E2E budget fallback'");
  });
});

test.describe('Dashboard — "Membres" KPI budget comparison (cotisations)', () => {
  const year = new Date().getFullYear();

  test.beforeAll(() => {
    // Membres KPI only renders when app_settings.default_segment points at a
    // real segment (seed default is 0 = "no filter") — same setup as the
    // cotisation-sum test in dashboard.spec.ts. Restored unconditionally.
    sql("UPDATE app_settings SET value='2' WHERE `key`='default_segment'");
    sql(`DELETE FROM compta_budget WHERE year = ${year}`);
  });
  test.afterAll(() => {
    sql("UPDATE app_settings SET value='0' WHERE `key`='default_segment'");
    sql(`DELETE FROM compta_budget WHERE year = ${year}`);
  });

  test('shows gap-to-budget when a cotisation budget is set for the year', async ({ page }) => {
    await page.goto('/index.php?view=budgets');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');

    // contact_type "Cotisation" = compta_type id 1 in the seed.
    await page.request.post('/index.php', {
      form: { csrf, action: 'updateComptaBudget', compta_type_id: '1', year: String(year), amount: '888888' },
    });

    await page.goto('/index.php?view=dashboard');
    const membresBox = page.locator('.ca-kpi-box', { hasText: 'Membres' }).first();
    await expect(membresBox.locator('a[href*="view=budgets"]')).toBeVisible();
    await expect(membresBox).toContainText("888'888");

    await page.request.post('/index.php', {
      form: { csrf, action: 'updateComptaBudget', compta_type_id: '1', year: String(year), amount: '0' },
    });
  });

  test('falls back to last year\'s total when no cotisation budget is set', async ({ page }) => {
    await page.goto('/index.php');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    // Alice (user 1) gets a prior-year cotisation entry so kCotiSum1 > 0.
    await page.request.post('/index.php', {
      form: { action: 'addCompta', view: 'compta', userid: '1', type_id: '1', date: `01/03/${year - 1}`, libele: 'Coti E2E prev budget test', sum: '100', csrf },
    });

    await page.goto('/index.php?view=dashboard');
    const membresBox = page.locator('.ca-kpi-box', { hasText: 'Membres' }).first();
    await expect(membresBox.locator('a[href*="view=budgets"]')).toBeVisible();
    await expect(membresBox).toContainText(String(year - 1));
  });
});
