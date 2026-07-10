/**
 * E2E tests — application settings (navigation, save)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Settings', () => {
  test('navigate to general settings tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#tab-settings')).toBeVisible();
    await expect(page.locator('#s_org_name')).toBeVisible();
  });

  test('navigate to groups tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();
  });

  test('navigate to categories tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=categories');
    await expect(page.locator('#tab-categories')).toBeVisible();
  });

  test('navigate to compta types tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=compta');
    await expect(page.locator('#tab-compta')).toBeVisible();
  });

  test('change default_segment setting and verify saved', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_default_segment')).toBeVisible();

    await page.selectOption('#s_default_segment', '1');
    await page.click('button[type="submit"].btn-primary');

    // After htmx save, wait for success toast element
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });

    // Reload and confirm persisted value
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_default_segment')).toHaveValue('1');

    // Restore — navigate again so the form is present; restore to segment 2
    await page.goto('/index.php?view=settings&tab=settings');
    await page.selectOption('#s_default_segment', '2');
    await page.click('button[type="submit"].btn-primary');
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });
  });
});
