/**
 * E2E tests — "Journaux" hub
 *
 * Tab shell over the two global activity logs that used to be separate
 * destinations: journal compta (compta_last_entry.php) and journal suivi
 * (suivi_last_entry.php), same pattern as the "Membres & finances" hub
 * (#164). Each tab's require is isolated in its own closure since Bootstrap
 * tabs render every pane server-side (both views' PHP runs in the same
 * request; neither was designed to coexist).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Journals hub', () => {
  test('reachable via ?view=journals, Compta tab active by default', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await expect(page.locator('h1', { hasText: 'Journaux' })).toBeVisible();
    await expect(page.locator('#jh-tab-compta-btn')).toHaveClass(/active/);
    await expect(page.locator('#jh-tab-compta table.export')).toBeVisible();
  });

  test('Suivi tab shows the merged suivi/email log', async ({ page }) => {
    await page.goto('/index.php?view=journals&tab=suivi');
    await expect(page.locator('#jh-tab-suivi-btn')).toHaveClass(/active/);
    await expect(page.locator('#jh-tab-suivi #suivi-table')).toBeVisible();
  });

  test('both tabs execute in one request without variable collisions', async ({ page }) => {
    await page.goto('/index.php?view=journals&tab=suivi');
    await expect(page.locator('#jh-tab-compta table.export')).toBeAttached();
    await expect(page.locator('#jh-tab-suivi #suivi-table')).toBeVisible();
  });

  test('switching tabs client-side works without reload', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await page.locator('#jh-tab-suivi-btn').click();
    await expect(page.locator('#jh-tab-suivi')).toBeVisible();
    await expect(page.locator('#jh-tab-compta')).toBeHidden();
  });

  test('reachable for every role (open route, like the two it replaces)', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await expect(page.locator('#main-content')).not.toContainText('Accès refusé');
  });

  test('switching tabs updates the URL for direct linking', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await page.locator('#jh-tab-suivi-btn').click();
    await expect(page).toHaveURL(/[?&]tab=suivi/);
  });

  test('Compta tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await page.locator('#jh-tab-compta .dropdown-toggle', { hasText: String(new Date().getFullYear()) }).click();
    const yearLink = page.locator('#jh-tab-compta .dropdown-menu.show a', { hasText: String(new Date().getFullYear() - 1) }).first();
    await yearLink.click();
    await expect(page).toHaveURL(/view=journals/);
    await expect(page).toHaveURL(/tab=compta/);
    await expect(page.locator('#jh-tab-compta-btn')).toHaveClass(/active/);
  });
});

test.describe('Journals hub — navbar', () => {
  test('desktop nav link points to the hub, single entry replaces Compta/Suivi', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const link = page.locator('.navbar-nav .nav-link', { hasText: 'Journaux' });
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/view=journals/);
  });
});
