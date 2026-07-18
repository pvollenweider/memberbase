/**
 * E2E tests — "Mon compte" page: self-service profile editing and language switcher
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Mon compte — profile editing (updateOwnProfile)', () => {
  test('editing display name and email persists', async ({ page }) => {
    await page.goto('/index.php?view=changePassword');
    await page.fill('#profile_display_name', 'E2E Renamed Admin');
    await page.fill('#profile_email', 'e2e-renamed@example.com');

    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('form').filter({ has: page.locator('input[name="action"][value="updateOwnProfile"]') })
        .locator('button[type="submit"]').click(),
    ]);

    await page.goto('/index.php?view=changePassword');
    await expect(page.locator('#profile_display_name')).toHaveValue('E2E Renamed Admin');
    await expect(page.locator('#profile_email')).toHaveValue('e2e-renamed@example.com');

    // Sidebar footer reflects the new display name immediately.
    await expect(page.locator('body')).toContainText('E2E Renamed Admin');
  });

  test('updateOwnProfile is recorded in the audit log', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=audit');
    await expect(page.locator('#tab-audit')).toContainText('updateOwnProfile', { timeout: 10_000 });
  });
});

test.describe('Mon compte — language switcher', () => {
  test('changing the interface language persists across a fresh navigation', async ({ page }) => {
    await page.goto('/index.php?view=changePassword');
    await expect(page.locator('#locale')).toHaveValue('fr');

    await page.selectOption('#locale', 'en');
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('form').filter({ has: page.locator('input[name="action"][value="changeLocale"]') })
        .locator('button[type="submit"]').click(),
    ]);

    // A full reload refreshes <html lang> and every layout string.
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    await page.goto('/index.php?view=changePassword');
    await expect(page.locator('#locale')).toHaveValue('en');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    // Restore French so later tests in the suite see the expected strings.
    await page.selectOption('#locale', 'fr');
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('form').filter({ has: page.locator('input[name="action"][value="changeLocale"]') })
        .locator('button[type="submit"]').click(),
    ]);
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
  });
});
