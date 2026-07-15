/**
 * E2E tests -- data integrity checks (Réglages → Intégrité).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Integrity — compta date checks', () => {
  test('an entry dated today (current time) is not flagged as a future date', async ({ page }) => {
    // Regression test: the check used to compare compta.date against SQL
    // NOW(), which runs on the DB server's own timezone (often UTC), while
    // the app forces PHP to Europe/Zurich -- a same-day entry saved with
    // today's Zurich time could be hours "ahead" of the DB's NOW() and get
    // spuriously flagged as a future date.
    const USER_ID = 1;
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, '0');
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yyyy = today.getFullYear();
    await page.request.post('/index.php', {
      form: {
        action: 'addCompta', view: 'compta', userid: String(USER_ID),
        type_id: '1', date: `${dd}/${mm}/${yyyy}`, libele: 'Cotisation modifiee E2E', sum: '50', csrf,
      },
    });

    await page.goto('/index.php?view=settings&tab=integrity');
    await expect(page.locator('#tab-integrity')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('td', { hasText: 'Cotisation modifiee E2E' })).toHaveCount(0);
  });
});
