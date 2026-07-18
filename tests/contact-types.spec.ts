/**
 * E2E tests — contact type management (#165)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Compta types settings — new flag columns', () => {
  test('Établissement financier and Entreprise toggle columns are present and togglable', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=compta');
    await expect(page.locator('th[title*="établissement financier"]').or(page.locator('th[title*="entreprise"]')).first()).toBeVisible();

    const row = page.locator('tr[data-id="2"]');
    const financialToggle = row.locator('td').nth(8).locator('button');
    await expect(financialToggle).toBeVisible();
    await financialToggle.click();
    await expect(page).toHaveURL(/tab=compta/);
    // Toggled on: icon should now be the "checked" state
    await expect(page.locator('tr[data-id="2"] td').nth(8).locator('i.fa-check-circle')).toBeVisible();
  });
});

test.describe('Contact type management — access guard', () => {
  test.use({ storageState: require('path').resolve(__dirname, '.auth/manager.json') });

  test('manager (non-admin) cannot reach the standalone route', async ({ page }) => {
    await page.goto('/index.php?view=contactTypes');
    await expect(page.locator('#main-content')).toContainText('Accès refusé');
  });
});

test.describe('Contact type category management', () => {
  test('shows the 4 seeded categories and lets an admin rename a label', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=contactTypes');
    const table = page.locator('#contact-type-management-table');
    await expect(table.locator('tbody tr')).toHaveCount(4);
    const labelValues = await table.locator('input[name="label"]').evaluateAll(
      (els) => els.map((el) => (el as HTMLInputElement).value)
    );
    expect(labelValues).toEqual(expect.arrayContaining([
      'Donateur privé', 'Institution', 'Établissement financier', 'Entreprise',
    ]));

    const row = table.locator('tbody tr').filter({ has: page.locator('input[value="Entreprise"]') });
    await row.locator('input[name="label"]').fill('Entreprise E2E');
    await row.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/contactTypeLabelSaved=/);
    await expect(page.locator('#tab-contactTypes .alert-success')).toBeVisible();
    const renamedRow = table.locator('tbody tr').filter({ has: page.locator('input[value="Entreprise E2E"]') });
    await expect(renamedRow).toBeVisible();

    // Revert to keep the seed label stable for other tests/runs.
    await renamedRow.locator('input[name="label"]').fill('Entreprise');
    await renamedRow.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/contactTypeLabelSaved=/);
  });

  test('add a custom contact type, then delete it (unused types are deletable)', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=contactTypes');
    const table = page.locator('#contact-type-management-table');
    await expect(table.locator('tbody tr')).toHaveCount(4);

    const addForm = page.locator('#add-contact-type-form');
    await addForm.locator('input[name="label"]').fill('Bénévole E2E');
    await addForm.locator('input[name="icon"]').fill('hand-holding-heart');
    await addForm.locator('button[type="submit"]').click();

    await expect(table.locator('tbody tr')).toHaveCount(5);
    const newRow = table.locator('tbody tr').filter({ has: page.locator('input[value="Bénévole E2E"]') });
    await expect(newRow).toBeVisible();
    // Auto-generated code, editable for custom types, no contacts using it yet — delete button present.
    await expect(newRow.locator('input[name="code"]')).toHaveValue('benevole_e2e');
    await expect(newRow.locator('button', { hasText: 'Suppr' })).toBeVisible();

    // Extract delete parameters from the button's data-href, then POST
    // the action directly with the CSRF token — avoids Bootstrap modal
    // animation and htmx redirect timing issues in the test runner.
    const deleteHref = await newRow.locator('button', { hasText: 'Suppr' }).getAttribute('data-href') ?? '';
    const deleteUrl = new URL(deleteHref, 'http://localhost');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    const form: Record<string, string> = { csrf };
    deleteUrl.searchParams.forEach((val, key) => { form[key] = val; });
    await page.request.post('/index.php', { form });
    await page.reload();

    await expect(table.locator('tbody tr')).toHaveCount(4);
    await expect(table.locator('tbody tr').filter({ has: page.locator('input[value="Bénévole E2E"]') })).toHaveCount(0);
  });
});

test.describe('Contact type × compta type matrix — auto-save', () => {
  test('toggling a cell auto-saves without a page reload, column header toggles the whole column', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=contactTypes');
    const matrix = page.locator('#contact-type-matrix-table');
    await expect(matrix).toBeVisible();

    const firstCell = matrix.locator('tbody tr').first().locator('.ctm-cell').first();
    await expect(firstCell).toBeChecked();
    await firstCell.uncheck();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });
    // No navigation happened — still on the same page, cell state persists after reload.
    await expect(page).toHaveURL(/tab=contactTypes/);
    await page.reload();
    await expect(matrix.locator('tbody tr').first().locator('.ctm-cell').first()).not.toBeChecked();

    // Re-check to restore the unrestricted default for other tests.
    await matrix.locator('tbody tr').first().locator('.ctm-cell').first().check();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });

    // Column header toggle: unchecks every cell in that column, then re-checking restores it.
    const colToggle = matrix.locator('.ctm-col-toggle').first();
    const colId = await colToggle.getAttribute('data-contact-type-id');
    const colCells = matrix.locator(`.ctm-cell[data-contact-type-id="${colId}"]`);
    await colToggle.click();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });
    for (const cb of await colCells.all()) { await expect(cb).not.toBeChecked(); }

    await colToggle.click();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });
    for (const cb of await colCells.all()) { await expect(cb).toBeChecked(); }
  });
});

test.describe('Contact type × compta type matrix — default type (#165 phase 2)', () => {
  test('picking a default radio pre-selects that compta type in the add-entry form', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=contactTypes');
    const matrix = page.locator('#contact-type-matrix-table');
    await expect(matrix).toBeVisible();

    // First contact_type column, second compta_type row (first row is often
    // already the implicit default via list order — pick the second to make
    // the assertion meaningful).
    const rows = matrix.locator('tbody tr');
    const secondRowRadio = rows.nth(1).locator('.ctm-default-cell').first();
    const colId = await secondRowRadio.getAttribute('data-contact-type-id');
    const comptaTypeId = await secondRowRadio.getAttribute('value');

    await secondRowRadio.check();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });
    await page.reload();
    await expect(matrix.locator(`.ctm-default-cell[data-contact-type-id="${colId}"][value="${comptaTypeId}"]`)).toBeChecked();

    // Restore "Aucun" so other tests/manual QA see the unrestricted default.
    await matrix.locator(`.ctm-default-cell[data-contact-type-id="${colId}"][value=""]`).check();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });
  });

  test('unchecking a compta type disables and clears it as default', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=contactTypes');
    const matrix = page.locator('#contact-type-matrix-table');
    const firstRow = matrix.locator('tbody tr').first();
    const cell = firstRow.locator('.ctm-cell').first();
    const radio = firstRow.locator('.ctm-default-cell').first();
    const colId = await cell.getAttribute('data-contact-type-id');

    await radio.check();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });

    await cell.uncheck();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });
    await expect(radio).toBeDisabled();
    await expect(matrix.locator(`.ctm-default-cell[data-contact-type-id="${colId}"][value=""]`)).toBeChecked();

    // Restore.
    await cell.check();
    await expect(page.locator('#contact-type-matrix-status')).toContainText('enregistrée', { timeout: 5_000 });
  });
});
