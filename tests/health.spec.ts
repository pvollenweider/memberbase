/**
 * E2E tests — health / observability page (#74)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Health page', () => {
  test('admin: health tab renders system status', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=health');
    await expect(page.locator('#tab-health')).toBeVisible();
    await expect(page.locator('#tab-health')).toContainText('Santé du système');
    // Key sections present.
    await expect(page.locator('#tab-health')).toContainText('Application');
    await expect(page.locator('#tab-health')).toContainText('Base de données');
    await expect(page.locator('#tab-health')).toContainText('Migrations');
  });

  test('/health.php returns a valid JSON status', async ({ request }) => {
    const resp = await request.get('/health.php');
    // ok → 200, degraded → 503; both are valid, non-sensitive responses.
    expect([200, 503]).toContain(resp.status());
    const body = await resp.json();
    expect(['ok', 'degraded']).toContain(body.status);
    // Must not leak anything beyond the status field.
    expect(Object.keys(body)).toEqual(['status']);
  });
});
