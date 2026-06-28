/**
 * E2E tests — donation summary view
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Resume (statistics)', () => {
  test('view resume page — KPI cards visible', async ({ page }) => {
    await page.goto('/index.php?view=resume');
    await page.waitForLoadState('load');
    await expect(page.locator('.ca-resume-cards')).toBeVisible();
  });

  test('change year filter', async ({ page }) => {
    const currentYear = new Date().getFullYear();
    const prevYear = currentYear - 1;

    await page.goto(`/index.php?view=resume&year=${prevYear}`);
    await page.waitForLoadState('load');

    // The year dropdown button should reflect the selected year
    await expect(page.locator(`button[aria-label="Année"]`)).toContainText(String(prevYear));
  });

  test('change min amount filter', async ({ page }) => {
    await page.goto('/index.php?view=resume&minSum=100');
    await page.waitForLoadState('load');

    // Page loads without error and KPI cards are still present
    await expect(page.locator('.ca-resume-cards')).toBeVisible();
    // The amount filter button reflects the selection
    await expect(page.locator('button[aria-label="Montant minimum"]')).toContainText('100');
  });
});
