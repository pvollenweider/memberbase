/**
 * E2E tests — authentication (login, logout, access control without session)
 *
 * These tests run WITHOUT pre-injected auth state because they exercise
 * the login/logout flow itself.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
import { test, expect } from '@playwright/test';
import { login } from './helpers/login';

// Opt out of the global storageState for this file
test.use({ storageState: { cookies: [], origins: [] } });

test('login with wrong password shows error message', async ({ page }) => {
  await login(page, 'testadmin', 'wrong-password');
  await expect(page.locator('.alert.alert-danger')).toBeVisible({ timeout: 5_000 });
});

test('login with correct credentials redirects to dashboard', async ({ page }) => {
  await login(page, 'testadmin', 'TestPassword1!');
  await expect(page.locator('h1', { hasText: 'MemberBase Test' })).toBeVisible({ timeout: 10_000 });
});

test('logout redirects to login page', async ({ page }) => {
  await login(page, 'testadmin', 'TestPassword1!');
  await expect(page.locator('h1', { hasText: 'MemberBase Test' })).toBeVisible({ timeout: 10_000 });

  // Submit logout via form POST directly (avoiding dropdown interaction flakiness)
  await page.evaluate(() => {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/index.php';
    (form as HTMLFormElement).setAttribute('hx-boost', 'false');
    const input = document.createElement('input');
    input.name = 'action';
    input.value = 'logout';
    form.appendChild(input);
    const csrf = document.createElement('input');
    csrf.name = 'csrf';
    csrf.value = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
  });

  await expect(page).toHaveURL(/login\.php/, { timeout: 10_000 });
});
