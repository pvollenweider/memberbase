import { test, expect } from '@playwright/test';

test.describe('Resume (statistics)', () => {
  test('view resume page — KPI cards visible', async ({ page }) => {
    await page.goto('/index.php?view=resume');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.ca-resume-cards')).toBeVisible();
  });

  test('change year filter', async ({ page }) => {
    const currentYear = new Date().getFullYear();
    const prevYear = currentYear - 1;

    await page.goto(`/index.php?view=resume&year=${prevYear}`);
    await page.waitForLoadState('networkidle');

    // The year dropdown button should reflect the selected year
    await expect(page.locator(`button[aria-label="Année"]`)).toContainText(String(prevYear));
  });

  test('change min amount filter', async ({ page }) => {
    await page.goto('/index.php?view=resume&minSum=100');
    await page.waitForLoadState('networkidle');

    // Page loads without error and KPI cards are still present
    await expect(page.locator('.ca-resume-cards')).toBeVisible();
    // The amount filter button reflects the selection
    await expect(page.locator('button[aria-label="Montant minimum"]')).toContainText('100');
  });
});
