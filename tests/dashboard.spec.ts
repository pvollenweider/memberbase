/**
 * E2E tests — dashboard landing view (#153)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test('reachable via ?view=dashboard, shows KPI and task panels', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('h1', { hasText: 'Tableau de bord' })).toBeVisible();
    await expect(page.locator('.card-header', { hasText: 'En un coup d’œil' }).or(page.locator('.card-header', { hasText: "En un coup d'œil" }))).toBeVisible();
    await expect(page.locator('.card-header', { hasText: 'Documentation' })).toBeVisible();
  });

  test('unpaid cotisation KPI links to the lapsed members view', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const kpiLink = page.locator('a', { hasText: 'Cotisation' });
    await expect(kpiLink).toBeVisible();
    await kpiLink.click();
    await expect(page).toHaveURL(/view=lapsedMembers/);
  });

  test('nav bar exposes a dashboard shortcut, active state highlights it', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const navLink = page.locator('.navbar-nav .nav-item.active .nav-link', { hasText: 'Tableau de bord' });
    await expect(navLink).toBeVisible();
  });

  test('member list "Ajouter" shortcut still reachable from the list icon', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await page.locator('.navbar-nav .nav-link', { hasText: 'Listes' }).click();
    await expect(page).toHaveURL(/view=list/);
  });

  test('bare landing (no view param) is unaffected — still the member list', async ({ page }) => {
    await page.goto('/index.php');
    await expect(page.locator('h1', { hasText: 'Tableau de bord' })).not.toBeVisible();
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
});
