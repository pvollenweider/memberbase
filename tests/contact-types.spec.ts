/**
 * E2E tests — contact type classification (#165)
 *
 * Seed: compta_type id=2 "Institution" has is_institutional=1. User 2 (Bob
 * Martin) has a compta entry of that type — the only seed contact that
 * should suggest a non-default (private) contact type.
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

test.describe('Contact type classification tool', () => {
  test('admin sees a suggested-diff row for the contact with an institutional payment', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=contactTypes');
    await expect(page.locator('h1, p.form-section-title', { hasText: 'Type de contact' }).first()).toBeVisible();
    await expect(page.locator('table tbody tr', { hasText: 'Martin' })).toBeVisible();
    await expect(page.locator('table tbody tr', { hasText: 'Martin' })).toContainText('Institution');
  });

  test('applying the selection updates the contact and clears the diff', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=contactTypes');
    const row = page.locator('table tbody tr', { hasText: 'Martin' });
    await expect(row).toBeVisible();

    // Scope the submission to Martin only — other rows may transiently be
    // diffs too (e.g. tests/contact-type-fiche.spec.ts runs concurrently
    // and briefly changes a different contact's type), so don't rely on
    // "select all" or an exact applied-count.
    await page.locator('#ct-check-all').uncheck();
    await row.locator('.ct-row-check').check();

    await page.locator('button[type="submit"]', { hasText: 'Appliquer' }).click();
    await expect(page).toHaveURL(/contactTypesApplied=/);
    await expect(page.locator('#tab-contactTypes .alert-success')).toBeVisible();

    // Re-visit fresh: no more diff for this contact
    await page.goto('/index.php?view=settings&tab=contactTypes');
    await expect(page.locator('table tbody tr', { hasText: 'Martin' })).toHaveCount(0);
  });
});

test.describe('Contact type classification tool — access guard', () => {
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
