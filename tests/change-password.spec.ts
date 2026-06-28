/**
 * E2E tests — password change flow
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

const ORIGINAL_PASSWORD = 'TestPassword1!';
const TEMP_PASSWORD = 'TempPassword2!';

test.describe.serial('Change password', () => {
  async function changePassword(page: any, current: string, next: string) {
    await page.goto('/index.php?view=changePassword');
    await expect(page.locator('#pw_new')).toBeVisible();
    await page.fill('#pw_current', current);
    await page.fill('#pw_new', next);
    await page.fill('#pw_confirm', next);
    // POST via fetch (shares page cookies). redirect:'follow' follows the Location header,
    // resp.url is the final URL after redirects. On success auth.php → /index.php.
    const finalUrl = await page.evaluate(
      async ([cur, nxt]: string[]) => {
        const body = new URLSearchParams({
          action: 'changePassword',
          pw_current: cur,
          pw_new: nxt,
          pw_confirm: nxt,
        });
        const resp = await fetch('/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString(),
        });
        return resp.url;
      },
      [current, next],
    );
    if (finalUrl.includes('pw_error') || finalUrl.includes('changePassword')) {
      throw new Error(`Password change failed: landed on ${finalUrl}`);
    }
    await page.goto(finalUrl);
  }

  test('change password to temp value', async ({ page }) => {
    await changePassword(page, ORIGINAL_PASSWORD, TEMP_PASSWORD);
    // Success should land on member list (no changePassword in URL)
    expect(page.url()).not.toContain('changePassword');
  });

  test('restore original password', async ({ page }) => {
    await changePassword(page, TEMP_PASSWORD, ORIGINAL_PASSWORD);
    expect(page.url()).not.toContain('changePassword');
  });
});
