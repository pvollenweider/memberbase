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
  await expect(page.locator('.alert.alert-danger')).toBeVisible();
});

test('login with correct credentials redirects to member list', async ({ page }) => {
  await login(page, 'testadmin', 'TestPassword1!');
  // After login we land on index.php (member list)
  await expect(page).toHaveURL(/index\.php|^\//);
  await expect(page.locator('table.table')).toBeVisible();
});

test('logout redirects to login page', async ({ page }) => {
  // First log in
  await login(page, 'testadmin', 'TestPassword1!');
  await page.waitForURL((url) => !url.pathname.includes('login.php'));

  // Click logout (form button with action=logout)
  await page.click('button[type="submit"]:near(input[name="action"][value="logout"])');

  await expect(page).toHaveURL(/login\.php/);
});
