/**
 * Auth tests — these run WITHOUT pre-injected auth state because they exercise
 * the login/logout flow itself.
 */
import { test, expect } from '@playwright/test';
import { login } from './helpers/login';

// Opt out of the global storageState for this file
test.use({ storageState: { cookies: [], origins: [] } });

test('login with wrong password shows error message', async ({ page }) => {
  await login(page, 'testadmin', 'wrong-password');
  await page.waitForURL(/login\.php/, { timeout: 10_000 });
  await expect(page.locator('.alert.alert-danger')).toBeVisible();
});

test('login with correct credentials redirects to member list', async ({ page }) => {
  await login(page, 'testadmin', 'TestPassword1!');
  await page.waitForURL(/index\.php/, { timeout: 10_000 });
  await expect(page.locator('table.table')).toBeVisible({ timeout: 10_000 });
});

test('logout redirects to login page', async ({ page }) => {
  await login(page, 'testadmin', 'TestPassword1!');
  await page.waitForURL(/index\.php/, { timeout: 10_000 });

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
    document.body.appendChild(form);
    form.submit();
  });

  await expect(page).toHaveURL(/login\.php/, { timeout: 10_000 });
});
