/**
 * E2E tests — "Membres & finances" hub (#164)
 *
 * Phase 1: Membres tab fully ported (reuses users_list.php unchanged).
 * Phase 2: Relances cotisation tab fully ported (reuses compta_recap.php,
 * isolated via a closure scope since both real tabs' PHP now runs in the
 * same request — Bootstrap tabs render every pane server-side).
 * Dons & attestations remains a stub linking to its legacy page (phase 3).
 * Not yet linked from the navbar — reachable via ?view=peopleFinance.
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

  test('Membres and Relances tabs both execute in one request without variable collisions', async ({ page }) => {
    // Regression guard for the closure-isolation fix: both panes' own content
    // (which independently define similarly-named variables like $year) must
    // render correctly side by side.
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    await expect(page.locator('#pf-tab-members table.export')).toBeAttached();
    await expect(page.locator('#pf-tab-members')).toContainText('Dupont');
    await expect(page.locator('#pf-tab-recap')).toBeVisible();
  });

  test('Dons & attestations tab is a stub linking to the legacy page', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=dons');
    await expect(page.locator('#pf-tab-dons-btn')).toHaveClass(/active/);
    const link = page.locator('#pf-tab-dons a', { hasText: 'Ouvrir' });
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/view=resume/);
  });

  test('switching tabs client-side works without reload', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await page.locator('#pf-tab-dons-btn').click();
    await expect(page.locator('#pf-tab-dons')).toBeVisible();
    await expect(page.locator('#pf-tab-members')).toBeHidden();
  });
});

test.describe('People/finance hub — role guard on Relances tab', () => {
  test.use({ storageState: require('path').resolve(__dirname, '.auth/user.json') });

  test('Relances cotisation tab is hidden for a non-manager role', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await expect(page.locator('#pf-tab-recap-btn')).toHaveCount(0);
  });
});
