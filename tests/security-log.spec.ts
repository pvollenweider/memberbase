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
  test('a failed login is recorded (loginFailed)', async ({ playwright, page }) => {
    // Fresh (unauthenticated) request context: fetch the login CSRF token, then
    // POST a wrong password. Avoids driving the login UI in a manual context.
    const anon = await playwright.request.newContext({ baseURL: 'http://localhost:8080' });
    const html = await (await anon.get('/login.php')).text();
    const m = html.match(/name="csrf" value="([^"]+)"/);
    if (!m) throw new Error('login CSRF token not found');
    await anon.post('/login.php', {
      form: { csrf: m[1], username: 'testadmin', password: 'definitely-wrong-password' },
    });
    await anon.dispose();

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
