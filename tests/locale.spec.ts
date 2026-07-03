/**
 * E2E tests — per-user UI locale (change language, persistence, fallback)
 *
 * The default locale is French; a user can switch to en/de/es from the
 * password page. The choice is stored in app_users.locale and applied to
 * the session immediately.
 *
 * Uses a dedicated login session (not the shared storageState): the locale
 * is cached in the PHP session at login, so switching it here cannot leak
 * into the other specs' shared admin session. The last test restores 'fr'
 * in app_users anyway.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, Page } from '@playwright/test';
import { login } from './helpers/login';

test.use({ storageState: { cookies: [], origins: [] } });

async function switchLocale(page: Page, code: string, selectLabel: string) {
  await page.goto('/index.php?view=changePassword');
  await page.getByLabel(selectLabel).selectOption(code);
  await page.locator('form:has(input[name="action"][value="changeLocale"]) button[type="submit"]').click();
  await page.waitForURL(/view=changePassword/);
}

test.describe('UI locale', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ page }) => {
    await login(page, 'testadmin', 'TestPassword1!');
  });

  test('default UI is French and the language card is visible', async ({ page }) => {
    await page.goto('/index.php?view=changePassword');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.getByLabel("Langue de l'interface")).toHaveValue('fr');
  });

  test('switching to English translates the UI and persists', async ({ page }) => {
    await switchLocale(page, 'en', "Langue de l'interface");

    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.getByLabel('Interface language')).toHaveValue('en');

    // Another page renders in English too (session-wide).
    await page.goto('/index.php?view=settings&tab=health');
    await expect(page.locator('#main-content')).toContainText('System health');
  });

  test('locale persists across a new login session', async ({ page }) => {
    // beforeEach logged in fresh — the EN choice stored in app_users applies.
    await page.goto('/index.php?view=changePassword');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  });

  test('switching back to French restores the default', async ({ page }) => {
    await switchLocale(page, 'fr', 'Interface language');
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.getByLabel("Langue de l'interface")).toHaveValue('fr');
  });
});
