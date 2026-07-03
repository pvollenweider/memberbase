/**
 * E2E tests — admin DB maintenance (SQL export + apply migrations UI)
 *
 * Covers the SQL export endpoint (auth + content). Applying migrations is not
 * clicked here (it would mutate the shared test DB); the button rendering and
 * the server guard are what matter.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import * as path from 'path';

test.describe('Admin DB maintenance', () => {
  test('admin: /export.php streams a SQL dump', async ({ request }) => {
    const resp = await request.get('/export.php');
    expect(resp.status()).toBe(200);
    expect(resp.headers()['content-disposition'] || '').toContain('.sql');
    const body = await resp.text();
    expect(body).toContain('CREATE TABLE');
    expect(body).toContain('INSERT INTO');
  });

  test('readonly: /export.php is forbidden', async ({ playwright }) => {
    const ro = await playwright.request.newContext({
      baseURL: 'http://localhost:8080',
      storageState: path.resolve(__dirname, '.auth/readonly.json'),
    });
    const resp = await ro.get('/export.php');
    expect(resp.status()).toBe(403);
    await ro.dispose();
  });

  test('admin: health tab shows the export button', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=health');
    await expect(page.locator('#tab-health a[href="export.php"]')).toBeVisible({ timeout: 10_000 });
  });
});
