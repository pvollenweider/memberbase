/**
 * E2E tests — committed JS/CSS bundles are actually served (and with the
 * right charset), per the "zero build in prod" deployment model (5.3.0)
 * and the `AddCharset UTF-8 .css .js` Apache directive (5.3.1).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Asset bundles', () => {
  test('a loaded page references the committed dist bundles, not individual vendor files', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const html = await page.content();
    expect(html).toContain('css/dist/app.min.css');
    expect(html).toContain('js/dist/vendor.min.js');
    expect(html).toContain('js/dist/app.min.js');
  });

  for (const path of ['css/dist/app.min.css', 'js/dist/vendor.min.js', 'js/dist/app.min.js']) {
    test(`${path} is served with a UTF-8 charset`, async ({ page }) => {
      const resp = await page.request.get(`/${path}`);
      expect(resp.status()).toBe(200);
      expect((resp.headers()['content-type'] ?? '').toLowerCase()).toContain('charset=utf-8');
    });
  }
});
