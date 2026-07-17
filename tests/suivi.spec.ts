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
    // Suivi rows open an edit form in a modal (fetch + innerHTML, see
    // suivi_list.php) rather than navigating to a standalone page — the
    // form itself still boosts (inherits hx-boost from <body>), so
    // submitting swaps the updated list straight into #main-content.
    await page.goto(`/index.php?view=suivi&userid=${USER_ID}`);
    const row = page.locator('tr.ca-row-link').filter({ hasText: 'Note E2E test entry' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });
    await row.click();

    const modal = page.locator('#suivi-edit-modal');
    await expect(modal.locator('form[name="updateSuivi"]')).toBeVisible({ timeout: 10_000 });
    await modal.locator('textarea[name="value"]').fill('Note E2E edited');
    await modal.locator('button[type="submit"].btn-primary').click();

    await expect(page.locator('#main-content')).toContainText('Note E2E edited', { timeout: 10_000 });
  });

  test('delete a suivi entry via confirmation page', async ({ page }) => {
    // Suivi rows open the edit form in a modal now (see suivi_list.php) —
    // reach it the same way, then navigate to the delete link's own href
    // directly (rather than clicking it inside the modal) to avoid the
    // still-open modal visually overlapping the boosted #main-content swap
    // behind it.
    await page.goto(`/index.php?view=suivi&userid=${USER_ID}`);
    const row = page.locator('tr.ca-row-link').filter({ hasText: 'Note E2E edited' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });
    await row.click();

    const modal = page.locator('#suivi-edit-modal');
    await expect(modal.locator('form[name="updateSuivi"]')).toBeVisible({ timeout: 10_000 });

    const deleteLink = modal.locator('a.btn-outline-danger');
    const deleteHref = await deleteLink.getAttribute('href');
    if (!deleteHref) throw new Error('Delete link href not found');
    await page.goto(deleteHref.startsWith('/') ? deleteHref : '/' + deleteHref);

    // Confirmation page — submit the POST delete form (action=deleteSuiviEntry).
    // This submit boosts too (hx-boost inherited from <body>), so it's an
    // AJAX swap rather than a real navigation — rely on the resulting
    // content assertion instead of waitForNavigation.
    await expect(page.locator('button.btn-danger')).toBeVisible({ timeout: 10_000 });
    await page.locator('button.btn-danger').click();
    // After delete, renders suivi list (view=suivi inside update_user_form.php)
    await expect(page.locator('form[name="addSuivi"]')).toBeVisible({ timeout: 15_000 });
  });
});
