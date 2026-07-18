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

    // Compta rows open the edit form in a modal (data-compta-href, not
    // data-href — see the comment in compta_list.php); a real but visually
    // hidden <a href> sits in the row too (ca-row-link-anchor), grab that
    // instead of clicking through the modal.
    const row = page.locator('tr.ca-row-link').filter({ hasText: 'ToDeleteEntry' }).first();
    const editHref = await row.locator('a.ca-row-link-anchor').getAttribute('href');
    if (!editHref) throw new Error('Could not find row edit link for ToDeleteEntry');

    // Navigate to edit form
    await page.goto(editHref.startsWith('/') ? editHref : '/' + editHref);

    // Click delete link on edit form → goes to removeCompta confirmation page
    await page.locator('a[href*="view=removeCompta"]').click();

    // On removeCompta confirmation, submit the POST delete form (action=deleteComptaEntry)
    await page.locator('button.btn-danger').click();

    // After deletion the action redirects back to the member's compta view
    await expect(page.locator('form[name="addCompta"]')).toBeVisible({ timeout: 15_000 });
  });
});

// ─── default entry label (compta_type.default_libele) ───────────────────────

test.describe('Default entry label autofill', () => {
  test.describe.configure({ mode: 'serial' });
  const USER_ID = 1;

  test('set a default label on the Cotisation type', async ({ page }) => {
    await page.goto('/index.php?view=manageComptaTypes');
    // Open the inline edit row of type id=1 (Cotisation, from seed)
    await page.locator('#row-1 button:has-text("Edit"), #row-1 button:has-text("Édit"), #row-1 button.btn-outline-secondary').first().click();
    const editRow = page.locator('#edit-1');
    await expect(editRow).toBeVisible();
    await editRow.locator('input[name="default_libele"]').fill('Cotisation');
    await editRow.locator('button[type="submit"].btn-primary').click();
    // Redirects back to the types tab
    await expect(page.locator('#ct-tbody')).toBeVisible({ timeout: 10_000 });
  });

  test('libele is prefilled with default + year and respects manual edits', async ({ page }) => {
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);
    const form = page.locator('form[name="addCompta"]');
    const libele = form.locator('input[name="libele"]');
    const yearSelect = form.locator('select#ca-coti-year');

    // Type id=1 (Cotisation) selected → default + current year
    await form.locator('select[name="type_id"]').selectOption('1');
    const year = await yearSelect.inputValue();
    await expect(libele).toHaveValue(`Cotisation ${year}`);

    // Changing the cotisation year updates the untouched label
    const otherYear = String(Number(year) - 1);
    await yearSelect.selectOption(otherYear);
    await expect(libele).toHaveValue(`Cotisation ${otherYear}`);

    // A hand-edited label is never overwritten
    await libele.fill('Mon libellé perso');
    await yearSelect.selectOption(year);
    await expect(libele).toHaveValue('Mon libellé perso');
  });

  test('"Dons uniquement" toggle hides non-donation entries', async ({ page }) => {
    // Alice (user 1) has a "Vente brocante" entry (type Vente, excluded from
    // donation) alongside her cotisation and don entries — see seed.sql.
    await page.goto(`/index.php?view=compta&userid=${USER_ID}`);
    await expect(page.locator('body')).toContainText('Vente brocante');

    await page.locator('#dons-only-toggle').check();
    await page.waitForURL(/dons_only=1/, { timeout: 10_000 });
    await expect(page.locator('body')).not.toContainText('Vente brocante');
    await expect(page.locator('#dons-only-toggle')).toBeChecked();

    await page.locator('#dons-only-toggle').uncheck();
    await page.waitForURL((url) => !url.search.includes('dons_only'), { timeout: 10_000 });
    await expect(page.locator('body')).toContainText('Vente brocante');
  });
});
