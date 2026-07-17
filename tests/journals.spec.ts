/**
 * E2E tests — "Journaux" hub
 *
 * Server renders only the active tab's pane per request (id="jh-tab-<tab>",
 * class="jh-active-pane") — same single-pane-per-request architecture as the
 * "Membres & finances" hub (#164). There's no local tab bar anymore (the
 * nav-architecture rework replaced it with direct sidebar entries: "Journal
 * suivi" as a top-level link, "Journal compta" inside the sidebar's
 * "Finances" submenu — no single combined "Journaux" nav entry survives).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Journals hub', () => {
  test('reachable via ?view=journals, Compta tab active by default', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await expect(page.locator('h1', { hasText: 'Journaux' })).toBeVisible();
    await expect(page.locator('#jh-tab-compta table.export')).toBeVisible();
    // Sidebar's "Journal compta" submenu entry (inside the auto-expanded
    // "Finances" group) carries the active state now, not a local tab bar.
    await expect(page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=compta"]')).toHaveClass(/active/);
  });

  test('Suivi tab shows the merged suivi/email log', async ({ page }) => {
    await page.goto('/index.php?view=journals&tab=suivi');
    await expect(page.locator('#jh-tab-suivi #suivi-table')).toBeVisible();
    // "Journal suivi" is a top-level sidebar link — active state is the
    // *absence* of the accordion's "collapsed" class (see sidebar_nav.php).
    await expect(page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=suivi"]')).not.toHaveClass(/collapsed/);
  });

  test('each tab renders independently without variable collisions', async ({ page }) => {
    await page.goto('/index.php?view=journals&tab=compta');
    await expect(page.locator('#jh-tab-compta table.export')).toBeAttached();
    await page.goto('/index.php?view=journals&tab=suivi');
    await expect(page.locator('#jh-tab-suivi #suivi-table')).toBeVisible();
  });

  test('switching tabs via the sidebar works and updates the URL', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=suivi"]').click();
    await expect(page).toHaveURL(/[?&]tab=suivi/);
    await expect(page.locator('#jh-tab-suivi')).toBeVisible();
    await expect(page.locator('#jh-tab-compta')).toHaveCount(0);
  });

  test('reachable for every role (open route, like the two it replaces)', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await expect(page.locator('#main-content')).not.toContainText('Accès refusé');
  });

  test('Compta tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await page.locator('#jh-tab-compta .dropdown-toggle', { hasText: String(new Date().getFullYear()) }).click();
    // "Toutes années" is always offered regardless of the facet (seed data
    // is all dated "today", so only the current year has real entries).
    const yearLink = page.locator('#jh-tab-compta .dropdown-menu.show a', { hasText: 'Toutes' }).first();
    await yearLink.click();
    await expect(page).toHaveURL(/view=journals/);
    await expect(page).toHaveURL(/tab=compta/);
    await expect(page.locator('#jh-tab-compta')).toBeVisible();
  });
});

test.describe('Journals hub — sidebar', () => {
  test('sidebar "Journal suivi" link reaches the hub', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const link = page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=suivi"]');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/view=journals/);
  });
});
