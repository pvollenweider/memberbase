/**
 * E2E tests — compta type settings (add, delete)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Compta types settings', () => {
  test('navigate to compta types tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=compta');
    await expect(page.locator('#tab-compta')).toBeVisible();
  });

  test('add a new compta type', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=compta');
    await expect(page.locator('#tab-compta')).toBeVisible();

    const addForm = page.locator('#tab-compta form:has(input[name="action"][value="addComptaType"])');
    await addForm.locator('input[name="label"]').fill('TypeE2ETest');
    await addForm.locator('button[type="submit"]').click();

    // addComptaType sends HX-Location back to settings?tab=compta
    await page.waitForURL(/tab=compta/, { timeout: 10_000 });
    await expect(page.locator('table td').filter({ hasText: /^TypeE2ETest$/ }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('delete the created compta type', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=compta');
    await expect(page.locator('#tab-compta')).toBeVisible();

    const typeRow = page.locator('#tab-compta tr').filter({ hasText: 'TypeE2ETest' }).first();
    await expect(typeRow).toBeVisible({ timeout: 10_000 });

    // Extract delete parameters from the button's data-href, then POST
    // the action directly with the CSRF token — avoids Bootstrap modal
    // animation and htmx redirect timing issues in the test runner.
    const deleteBtn = typeRow.locator('button[data-bs-target="#modal-delete-compta-type"]');
    const deleteHref = await deleteBtn.getAttribute('data-href') ?? '';
    const deleteUrl = new URL(deleteHref, 'http://localhost');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    const form: Record<string, string> = { csrf };
    deleteUrl.searchParams.forEach((val, key) => { form[key] = val; });
    await page.request.post('/index.php', { form });
    await page.goto('/index.php?view=settings&tab=compta');

    await expect(page.locator('table td').filter({ hasText: /^TypeE2ETest$/ })).toHaveCount(0);
  });
});
