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
  test('reachable via ?view=budgets, lists non-archived compta types with 3 year columns', async ({ page }) => {
    await page.goto('/index.php?view=budgets');
    await expect(page.locator('h1', { hasText: 'Budgets' })).toBeVisible();
    const year = new Date().getFullYear();
    await expect(page.locator('#budgets-table')).toContainText(`Budget ${year - 1}`);
    await expect(page.locator('#budgets-table')).toContainText(`Budget ${year}`);
    await expect(page.locator('#budgets-table')).toContainText(`Budget ${year + 1}`);
    await expect(page.locator('#budgets-table')).toContainText(`Collecté ${year}`);
    // Seed has a "Cotisation" compta type.
    await expect(page.locator('#budgets-table')).toContainText('Cotisation');
  });

  test('editing a budget input auto-saves and persists after reload', async ({ page }) => {
    await page.goto('/index.php?view=budgets');
    const year = new Date().getFullYear();
    const input = page.locator(`.budget-input[data-year="${year}"]`).first();
    await input.fill('12345');
    await input.blur();
    await expect(page.locator('#budgets-status')).toContainText('Enregistré', { timeout: 5000 });

    await page.reload();
    await expect(page.locator(`.budget-input[data-year="${year}"]`).first()).toHaveValue('12345.00');

    // Column total reflects the saved amount.
    await expect(page.locator(`[data-total-year="${year}"]`)).toContainText("12'345");
  });

  test('manager role can also reach and save (isManager guard, not isAdmin)', async ({ browser }) => {
    const ctx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/manager.json') });
    const page = await ctx.newPage();
    await page.goto('/index.php?view=budgets');
    await expect(page.locator('#budgets-table')).toBeVisible();
    await ctx.close();
  });

  test('a non-manager role is denied access', async ({ browser }) => {
    const ctx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/user.json') });
    const page = await ctx.newPage();
    await page.goto('/index.php?view=budgets');
    await expect(page.locator('.alert-danger')).toBeVisible();
    await expect(page.locator('#budgets-table')).toHaveCount(0);
    await ctx.close();
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
  });
});
