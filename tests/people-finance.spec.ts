/**
 * E2E tests — "Membres & finances" hub (#164)
 *
 * All three tabs are now fully ported: Membres (users_list.php), Relances
 * cotisation (compta_recap.php, managers only), Dons & attestations
 * (donors_summary.php — KPI cards/pie suppressed here, they live on the
 * dashboard, #153). Each tab's require goes through an isolated closure
 * since Bootstrap tabs render every pane server-side (all three views'
 * PHP runs in the same request; none of them were designed to coexist).
 * Linked from the navbar as "Membres & finances" (see menu.php tests).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('People/finance hub — Phase 1', () => {
  test('reachable via ?view=peopleFinance, Membres tab active by default with the member table', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await expect(page.locator('h1', { hasText: 'Membres & finances' })).toBeVisible();
    await expect(page.locator('#pf-tab-members-btn')).toHaveClass(/active/);
    await expect(page.locator('#pf-tab-members table.export')).toBeVisible();
    await expect(page.locator('#pf-tab-members')).toContainText('Dupont');
  });

  test('Relances cotisation tab shows the full compta recap content (manager)', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    await expect(page.locator('#pf-tab-recap-btn')).toHaveClass(/active/);
    // No duplicate title inside the embedded pane (suppressed via $_pfEmbedded)
    await expect(page.locator('#pf-tab-recap h1')).toHaveCount(0);
    await expect(page.locator('#pf-tab-recap #recap-extended-toggle')).toBeVisible();
  });

  test('all three tabs execute in one request without variable collisions', async ({ page }) => {
    // Regression guard for the closure-isolation fix: all three panes' own
    // content (which independently define similarly-named variables like
    // $year, $rows, $email...) must render correctly side by side.
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    await expect(page.locator('#pf-tab-members table.export')).toBeAttached();
    await expect(page.locator('#pf-tab-members')).toContainText('Dupont');
    await expect(page.locator('#pf-tab-recap')).toBeVisible();
    await expect(page.locator('#pf-tab-dons table.resume-export')).toBeAttached();
  });

  test('Dons & attestations tab shows the contributor table, no duplicate KPI cards', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=dons');
    await expect(page.locator('#pf-tab-dons-btn')).toHaveClass(/active/);
    await expect(page.locator('#pf-tab-dons table.resume-export')).toBeVisible();
    // KPI cards (total contributions, donors, pie chart) live on the dashboard now (#153)
    await expect(page.locator('#pf-tab-dons .ca-resume-cards')).toHaveCount(0);
  });

  test('switching tabs client-side works without reload', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await page.locator('#pf-tab-dons-btn').click();
    await expect(page.locator('#pf-tab-dons')).toBeVisible();
    await expect(page.locator('#pf-tab-members')).toBeHidden();
  });

  test('switching tabs updates the URL for direct linking', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await page.locator('#pf-tab-recap-btn').click();
    await expect(page).toHaveURL(/[?&]tab=recap/);
    await page.locator('#pf-tab-dons-btn').click();
    await expect(page).toHaveURL(/[?&]tab=dons/);
  });

  test('Relances tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    await page.locator('#pf-tab-recap .dropdown-toggle', { hasText: String(new Date().getFullYear()) }).click();
    const yearLink = page.locator('#pf-tab-recap .dropdown-menu.show a', { hasText: String(new Date().getFullYear() - 1) }).first();
    await yearLink.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=recap/);
    await expect(page.locator('#pf-tab-recap-btn')).toHaveClass(/active/);
  });

  test('Dons tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=dons');
    await page.locator('#pf-tab-dons .dropdown-toggle').filter({ has: page.locator('.fa-calendar-days') }).click();
    const yearLink = page.locator('#pf-tab-dons .dropdown-menu.show a', { hasText: String(new Date().getFullYear() - 1) }).first();
    await yearLink.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=dons/);
    await expect(page.locator('#pf-tab-dons-btn')).toHaveClass(/active/);
  });

  test('Relances tab: bulk-send redirect stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    const sendBtn = page.locator('#pf-tab-recap form button[type="submit"]', { hasText: 'Envoyer' });
    if (await sendBtn.count() === 0) test.skip(true, 'no pending entries to send in seed data');
    await sendBtn.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=recap/);
  });
});

test.describe('People/finance hub — role guard on Relances tab', () => {
  test.use({ storageState: require('path').resolve(__dirname, '.auth/user.json') });

  test('Relances cotisation tab is hidden for a non-manager role', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await expect(page.locator('#pf-tab-recap-btn')).toHaveCount(0);
  });
});
