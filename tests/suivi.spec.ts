/**
 * E2E tests — suivi (notes) CRUD (view, add, edit, delete)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Suivi (notes)', () => {
  const USER_ID = 1;

  test('view suivi list for a member', async ({ page }) => {
    await page.goto(`/index.php?view=suivi&userid=${USER_ID}`);
    await expect(page.locator('form[name="addSuivi"]')).toBeVisible();
  });

  test('add a suivi entry', async ({ page }) => {
    await page.goto(`/index.php?view=suivi&userid=${USER_ID}`);
    const form = page.locator('form[name="addSuivi"]');
    await form.locator('input[name="date"]').fill('15/06/2025');
    await form.locator('textarea[name="value"]').fill('Note E2E test entry');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('text=Note E2E test entry')).toBeVisible({ timeout: 10_000 });
  });

  test('edit a suivi entry and verify updated', async ({ page }) => {
    await page.goto(`/index.php?view=suivi&userid=${USER_ID}`);
    const row = page.locator('tr.ca-row-link').filter({ hasText: 'Note E2E test entry' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });
    const dataHref = await row.getAttribute('data-href');
    if (!dataHref) throw new Error('Could not find data-href on suivi row');
    await page.goto(dataHref.startsWith('/') ? dataHref : '/' + dataHref);
    await expect(page.locator('form[name="updateSuivi"]')).toBeVisible();
    await page.fill('textarea[name="value"]', 'Note E2E edited');
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.click('button[type="submit"].btn-primary'),
    ]);
    await page.goto(`/index.php?view=suivi&userid=${USER_ID}`);
    await expect(page.locator('text=Note E2E edited').first()).toBeVisible({ timeout: 10_000 });
  });

  test('delete a suivi entry via confirmation page', async ({ page }) => {
    await page.goto(`/index.php?view=suivi&userid=${USER_ID}`);
    const row = page.locator('tr.ca-row-link').filter({ hasText: 'Note E2E edited' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });

    // Navigate to the updateSuivi edit form (via data-href on the row)
    const editHref = await row.getAttribute('data-href');
    if (!editHref) throw new Error('data-href not found on row');
    await page.goto(editHref.startsWith('/') ? editHref : '/' + editHref);
    await expect(page.locator('form[name="updateSuivi"]')).toBeVisible({ timeout: 10_000 });

    // Click the delete link on the edit form (btn-outline-danger) → goes to removeSuivi confirmation
    const deleteLink = page.locator('a.btn-outline-danger');
    const deleteHref = await deleteLink.getAttribute('href');
    if (!deleteHref) throw new Error('Delete link href not found');
    await page.goto(deleteHref.startsWith('/') ? deleteHref : '/' + deleteHref);

    // Confirmation page — click the danger confirm link (view=removeSuiviConfirm)
    await expect(page.locator('a.btn-danger')).toBeVisible({ timeout: 10_000 });
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('a.btn-danger').click(),
    ]);
    // After delete, renders suivi list (view=suivi inside update_user_form.php)
    await expect(page.locator('form[name="addSuivi"]')).toBeVisible({ timeout: 15_000 });
  });
});
