import { Page } from '@playwright/test';

/**
 * Log in via the login form. Use this only in auth.spec.ts tests that
 * exercise the login flow itself. All other specs get auth state injected
 * via storageState (global-setup.ts).
 */
export async function login(page: Page, username: string, password: string) {
  await page.goto('/login.php');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
}
