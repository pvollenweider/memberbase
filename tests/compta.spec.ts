/**
 * E2E tests — accounting entries (view, add, edit, delete)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Compta (accounting)', () => {
  const USER_ID = 1; // Alice Dupont from seed

  test('view compta for a member', async ({ page }) => {
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);
    await expect(page.locator('form[name="addCompta"]')).toBeVisible();
  });

  test('add a compta entry', async ({ page }) => {
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);

    const form = page.locator('form[name="addCompta"]');
    await form.locator('select[name="type_id"]').selectOption({ index: 0 });
    await form.locator('input[name="date"]').fill('01.01.2025');
    await form.locator('input[name="libele"]').fill('E2E test entry');
    await form.locator('input[name="sum"]').fill('99');
    await form.locator('button[type="submit"]').click();

    // htmx swaps page content in place — wait for entry to appear in the table
    await expect(page.locator('text=E2E test entry')).toBeVisible({ timeout: 10_000 });
  });

  test('edit an existing compta entry', async ({ page }) => {
    await page.goto(`/index.php?view=updateCompta&comptaid=1&userid=${USER_ID}`);

    await page.fill('#libele', 'Cotisation modifiee');
    await page.click('button[type="submit"].btn-primary');

    // updateCompta has no redirect — htmx replaces content with compta view
    // Wait for the compta form to re-appear (page content swapped)
    await expect(page.locator('form[name="addCompta"]')).toBeVisible({ timeout: 10_000 });

    await page.goto(`/index.php?view=updateCompta&comptaid=1&userid=${USER_ID}`);
    await expect(page.locator('#libele')).toHaveValue('Cotisation modifiee');
  });

  test('delete a compta entry via confirmation page', async ({ page }) => {
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);

    const form = page.locator('form[name="addCompta"]');
    await form.locator('select[name="type_id"]').selectOption({ index: 0 });
    await form.locator('input[name="date"]').fill('02.02.2025');
    await form.locator('input[name="libele"]').fill('ToDeleteEntry');
    await form.locator('input[name="sum"]').fill('10');
    await form.locator('button[type="submit"]').click();

    await expect(page.locator('text=ToDeleteEntry')).toBeVisible({ timeout: 10_000 });

    // Get the edit link for the new entry (data-href on the row, or ca-row-link-anchor)
    const row = page.locator('tr.ca-row-link').filter({ hasText: 'ToDeleteEntry' }).first();
    const dataHref = await row.getAttribute('data-href');
    if (!dataHref) throw new Error('Could not find row data-href for ToDeleteEntry');

    // Navigate to edit form
    await page.goto(dataHref.startsWith('/') ? dataHref : '/' + dataHref);

    // Click delete link on edit form → goes to removeCompta confirmation page
    await page.locator('a[href*="view=removeCompta"]').click();

    // On removeCompta confirmation, click the danger confirm button
    await page.locator('a.btn-danger').click();

    // After deleteComptaConfirm, page renders compta view inline (no redirect)
    await expect(page.locator('form[name="addCompta"]')).toBeVisible({ timeout: 15_000 });
  });
});
