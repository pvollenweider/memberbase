/**
 * E2E tests — read-only reporting views (audit log, donors, history)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

// Read-only reporting views — just verify they load without error
test.describe('Reporting views', () => {
  test('audit log loads', async ({ page }) => {
    await page.goto('/index.php?view=auditLog');
    await expect(page.locator('table, .alert').first()).toBeVisible({ timeout: 10_000 });
  });

  test('user history loads', async ({ page }) => {
    await page.goto('/index.php?view=userHistory&userid=1');
    await expect(page.locator('table, .alert').first()).toBeVisible({ timeout: 10_000 });
  });

  test('last compta entry view loads', async ({ page }) => {
    await page.goto('/index.php?view=lastEntryCompta');
    await expect(page.locator('table').first()).toBeVisible({ timeout: 10_000 });
  });

  test('last suivi entry view loads', async ({ page }) => {
    await page.goto('/index.php?view=lastEntrySuivi');
    await expect(page.locator('table, .alert').first()).toBeVisible({ timeout: 10_000 });
  });

  test('loyal donors view loads', async ({ page }) => {
    await page.goto('/index.php?view=loyalDonors');
    await expect(page.locator('table').first()).toBeVisible({ timeout: 10_000 });
  });

  test('lapsed donors view loads', async ({ page }) => {
    // Redirects into the peopleFinance hub (its own tab) — the hub renders
    // every tab's table server-side, only one visible at a time.
    await page.goto('/index.php?view=lapsedDonors');
    await expect(page.locator('#pf-cohort-donors-lapsed table')).toBeVisible({ timeout: 10_000 });
  });

  test('new donors view loads', async ({ page }) => {
    await page.goto('/index.php?view=newDonors');
    await expect(page.locator('table').first()).toBeVisible({ timeout: 10_000 });
  });

  test('integrity check view loads', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=integrity');
    await expect(page.locator('#tab-integrity')).toBeVisible({ timeout: 10_000 });
  });
});
