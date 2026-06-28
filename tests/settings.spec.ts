import { test, expect } from '@playwright/test';

test.describe('Settings', () => {
  test('navigate to general settings tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#tab-settings')).toBeVisible();
    await expect(page.locator('#s_org_name')).toBeVisible();
  });

  test('navigate to groups tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#tab-groups')).toBeVisible();
  });

  test('navigate to categories tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=categories');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#tab-categories')).toBeVisible();
  });

  test('navigate to compta types tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=compta');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#tab-compta')).toBeVisible();
  });

  test('change default_team setting and verify saved', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await page.waitForLoadState('networkidle');

    // Switch default_team to team id=1 (Membre 2025)
    await page.selectOption('#s_default_team', '1');
    await page.click('button[type="submit"].btn-primary');
    await page.waitForLoadState('networkidle');

    // Reload and confirm the selection persisted
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_default_team')).toHaveValue('1');

    // Restore to team id=2 (Membre 2026, per seed)
    await page.selectOption('#s_default_team', '2');
    await page.click('button[type="submit"].btn-primary');
    await page.waitForLoadState('networkidle');
  });
});
