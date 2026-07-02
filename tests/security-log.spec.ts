/**
 * E2E tests — security logging (#91)
 *
 * Failed logins and denied access must land in the audit log.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import * as path from 'path';

test.describe('Security logging', () => {
  test('a failed login is recorded (loginFailed)', async ({ browser, page }) => {
    // Fresh unauthenticated context (empty storageState) — drive the login UI
    // with a wrong password, mirroring the known-good auth.spec flow, and wait
    // for the error alert instead of a navigation event.
    const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const anon = await ctx.newPage();
    await anon.goto('/login.php');
    await anon.fill('#username', 'testadmin');
    await anon.fill('#password', 'definitely-wrong-password');
    await anon.click('button[type="submit"]');
    await expect(anon.locator('.alert.alert-danger')).toBeVisible({ timeout: 10_000 });
    await ctx.close();

    // Admin (default storageState) sees it in the audit log.
    await page.goto('/index.php?view=settings&tab=audit');
    await expect(page.locator('#tab-audit')).toContainText('loginFailed', { timeout: 10_000 });
  });

  test('a denied view access is recorded (accessDenied)', async ({ browser, page }) => {
    // readonly hits a canWrite-guarded view → guard rejects and logs.
    const ctx = await browser.newContext({
      storageState: path.resolve(__dirname, '.auth/readonly.json'),
    });
    const ro = await ctx.newPage();
    await ro.goto('/index.php?view=addUser');
    await expect(ro.locator('text=Accès refusé')).toBeVisible({ timeout: 10_000 });
    await ctx.close();

    await page.goto('/index.php?view=settings&tab=audit');
    await expect(page.locator('#tab-audit')).toContainText('accessDenied', { timeout: 10_000 });
  });
});
