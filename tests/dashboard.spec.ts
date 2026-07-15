/**
 * E2E tests — dashboard landing view (#153)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test('reachable via ?view=dashboard, shows shortcuts and documentation panels', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('h1', { hasText: 'MemberBase Test' })).toBeVisible();
    await expect(page.locator('.card-header', { hasText: 'Raccourcis' })).toBeVisible();
    await expect(page.locator('.card-header', { hasText: 'Documentation' })).toBeVisible();
  });

  test('admin guide link is no longer shown, user guide link stays', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('a', { hasText: 'Guide utilisateur' })).toBeVisible();
    await expect(page.locator('a', { hasText: 'Guide administrateur' })).toHaveCount(0);
  });

  test('no dedicated tasks card on the dashboard, but the nav link is present', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('.card-header', { hasText: 'Tâches à traiter' })).toHaveCount(0);
    await expect(page.locator('.navbar-nav .nav-link', { hasText: 'Tâches' })).toBeVisible();
  });

  test('unpaid cotisation KPI links to the lapsed members tab in the hub', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const kpiLink = page.locator('a', { hasText: 'Cotisation' });
    await expect(kpiLink).toBeVisible();
    await kpiLink.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=lapsed/);
    await expect(page.locator('#pf-tab-lapsed-btn')).toHaveClass(/active/);
  });

  test('nav bar exposes a dashboard shortcut, active state highlights it', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const navLink = page.locator('.navbar-nav .nav-item.active .nav-link', { hasText: 'Tableau de bord' });
    await expect(navLink).toBeVisible();
  });

  test('member list reachable from the Membres & finances nav link', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await page.locator('.navbar-nav .nav-link', { hasText: 'Membres & finances' }).click();
    await expect(page).toHaveURL(/view=peopleFinance/);
  });

  test('bare landing (no view param) defaults to the dashboard', async ({ page }) => {
    await page.goto('/index.php');
    await expect(page.locator('h1', { hasText: 'MemberBase Test' })).toBeVisible();
  });

  test('compta search shortcut: typing a name shows results, clicking jumps to the member\'s Compta tab', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const input = page.locator('#dashboard-compta-search');
    await expect(input).toBeVisible();
    await input.fill('Dupont');

    const result = page.locator('#dashboard-compta-results [data-user-id]', { hasText: 'Dupont' });
    await expect(result).toBeVisible({ timeout: 5_000 });
    await result.click();

    await expect(page).toHaveURL(/view=compta&userid=\d+/);
  });

  test('compta search shortcut: no match shows a "no results" message', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const input = page.locator('#dashboard-compta-search');
    await input.fill('Zzzznomatch');

    await expect(page.locator('#dashboard-compta-results')).toContainText('Aucun résultat', { timeout: 5_000 });
  });

  test('KPI cards (contributions, donors, active members) are shown', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const cards = page.locator('.ca-resume-cards');
    await expect(cards).toBeVisible();
    await expect(cards).toContainText('Contributions');
    await expect(cards).toContainText('CHF');
    await expect(cards).toContainText('Donateurs');
    await expect(cards.locator('a', { hasText: 'fidèles' })).toBeVisible();
    await expect(cards.locator('a', { hasText: /^\d+ Nouveaux$/ })).toBeVisible();
  });

  test('KPI cards are absent for a role without write access', async ({ page, browser }) => {
    const ctx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/readonly.json') });
    const p = await ctx.newPage();
    await p.goto('/index.php?view=dashboard');
    await expect(p.locator('.ca-resume-cards')).toHaveCount(0);
    await ctx.close();
  });

  test('quick search input auto-focuses on load', async ({ page }) => {
    await page.goto('/index.php?view=list');
    await page.goto('/index.php?view=dashboard');
    await expect.poll(() => page.evaluate(() => document.activeElement?.id))
      .toBe('dashboard-compta-search');
  });
});

test.describe('Global navigation shortcuts (Alt/Option+Cmd+1/2/3)', () => {
  test('navigates to Membres & finances, Journaux, then back to the dashboard', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');

    await page.keyboard.down('Alt');
    await page.keyboard.down('Meta');
    await page.keyboard.press('2');
    await page.keyboard.up('Meta');
    await page.keyboard.up('Alt');
    await expect(page).toHaveURL(/view=peopleFinance/);

    await page.keyboard.down('Alt');
    await page.keyboard.down('Meta');
    await page.keyboard.press('3');
    await page.keyboard.up('Meta');
    await page.keyboard.up('Alt');
    await expect(page).toHaveURL(/view=journals/);

    await page.keyboard.down('Alt');
    await page.keyboard.down('Meta');
    await page.keyboard.press('1');
    await page.keyboard.up('Meta');
    await page.keyboard.up('Alt');
    await expect(page).toHaveURL(/view=dashboard/);
  });
});
