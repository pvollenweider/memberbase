/**
 * E2E tests — contact type field on the member fiche (#165 follow-up)
 *
 * Every contact defaults to contact_type_id=1 ("Donateur privé") — the
 * general data tab must show this and let a manager+ change it inline
 * (same Alpine.js + PATCH /api/contacts/{id} pattern as gender/address...).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Contact type field — member fiche', () => {
  test('view mode shows the default "Donateur privé" type', async ({ page }) => {
    await page.goto('/index.php?view=generalData&userid=1');
    await expect(page.locator('.ca-field-label', { hasText: 'Type de contact' })).toBeVisible();
    await expect(page.locator('.ca-field-value', { hasText: 'Donateur privé' })).toBeVisible();
  });

  test('edit mode offers the 4 contact types and persists a change', async ({ page }) => {
    await page.goto('/index.php?view=generalData&userid=1');
    await page.locator('[x-show="!editing"]').first().click();

    const select = page.locator('#gd-contact-type');
    await expect(select).toBeVisible();
    await expect(select.locator('option')).toHaveCount(4);

    await select.selectOption({ label: 'Institution' });
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/contacts/1') && r.request().method() === 'PATCH'),
      page.locator('button', { hasText: 'Enregistrer' }).click(),
    ]);

    await expect(page.locator('.ca-field-value', { hasText: 'Institution' })).toBeVisible();

    // Persisted server-side: reload from scratch
    await page.goto('/index.php?view=generalData&userid=1');
    await expect(page.locator('.ca-field-value', { hasText: 'Institution' })).toBeVisible();

    // Revert — this suite runs alongside contact-types.spec.ts (classification
    // review tool) in the full run; leaving user 1 as "Institution" with no
    // matching compta signal would show up there as a spurious extra diff.
    await page.request.patch('/api/contacts/1', { data: { contactTypeId: 1 } });
  });
});
