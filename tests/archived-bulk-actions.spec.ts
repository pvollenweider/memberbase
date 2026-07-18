/**
 * E2E tests — bulk delete/anonymize of archived members (Réglages → Archivés)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

async function createArchivedContact(page: any, lastName: string, withCompta: boolean): Promise<number> {
  const createResp = await page.request.post('/api/contacts', {
    data: { firstName: 'E2E', lastName, email: `${lastName.toLowerCase()}@example.com` },
  });
  const { data } = await createResp.json();
  if (withCompta) {
    await page.request.post('/api/compta', {
      data: { memberId: data.id, typeId: 1, date: `${new Date().getFullYear()}-01-01`, amount: 50 },
    });
  }
  await page.request.delete(`/api/contacts/${data.id}`); // deactivate, no dispose=delete
  return data.id;
}

test.describe('Archived members — bulk delete (no compta)', () => {
  test('bulk-deleting an eligible (no-compta) archived member removes it permanently', async ({ page }) => {
    const id = await createArchivedContact(page, 'BulkDeleteE2E', false);

    await page.goto('/index.php?view=inactiveUsers');
    const row = page.locator('tr', { hasText: 'BulkDeleteE2E' });
    await expect(row).toBeVisible({ timeout: 10_000 });
    await row.locator('.iu-bulk-cb').check();

    await page.locator('#iu-bulk-bar button', { hasText: 'Supprimer' }).click();
    const modal = page.locator('#iu-bulk-modal');
    await expect(modal).toBeVisible({ timeout: 5_000 });
    await expect(modal.locator('#iu-bulk-modal-warning')).toBeHidden(); // no mixed-eligibility warning
    await expect(modal.locator('#iu-bulk-modal-names')).toContainText('BulkDeleteE2E');

    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      modal.locator('#iu-bulk-confirm-btn').click(),
    ]);

    await page.goto('/index.php?view=inactiveUsers');
    await expect(page.locator('tr', { hasText: 'BulkDeleteE2E' })).toHaveCount(0);

    const check = await page.request.get(`/api/contacts/${id}`);
    expect(check.status()).toBe(404);
  });
});

test.describe('Archived members — bulk anonymize (has compta)', () => {
  test('bulk-anonymizing a member with compta history erases personal data but keeps entries', async ({ page }) => {
    const id = await createArchivedContact(page, 'BulkAnonymizeE2E', true);

    await page.goto('/index.php?view=inactiveUsers');
    const row = page.locator('tr', { hasText: 'BulkAnonymizeE2E' });
    await expect(row).toBeVisible({ timeout: 10_000 });
    await row.locator('.iu-bulk-cb').check();

    await page.locator('#iu-bulk-bar button', { hasText: 'Anonymiser' }).click();
    const modal = page.locator('#iu-bulk-modal');
    await expect(modal).toBeVisible({ timeout: 5_000 });
    await expect(modal.locator('#iu-bulk-modal-warning')).toBeHidden();

    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      modal.locator('#iu-bulk-confirm-btn').click(),
    ]);

    // The row disappears from Archivés (no longer matches original name) but
    // the contact itself still exists — only its personal data is scrubbed.
    await page.goto('/index.php?view=inactiveUsers');
    await expect(page.locator('tr', { hasText: 'BulkAnonymizeE2E' })).toHaveCount(0);

    const check = await page.request.get(`/api/contacts/${id}`);
    expect(check.status()).toBe(200);
    const { data } = await check.json();
    expect(data.lastName).not.toBe('BulkAnonymizeE2E');
  });

  test('mixed selection (eligible + ineligible) shows the mismatch warning', async ({ page }) => {
    const eligibleId = await createArchivedContact(page, 'MixDeleteE2E', false);
    const ineligibleId = await createArchivedContact(page, 'MixAnonymizeE2E', true);

    await page.goto('/index.php?view=inactiveUsers');
    await page.locator('tr', { hasText: 'MixDeleteE2E' }).locator('.iu-bulk-cb').check();
    await page.locator('tr', { hasText: 'MixAnonymizeE2E' }).locator('.iu-bulk-cb').check();

    await page.locator('#iu-bulk-bar button', { hasText: 'Supprimer' }).click();
    const modal = page.locator('#iu-bulk-modal');
    await expect(modal).toBeVisible({ timeout: 5_000 });
    await expect(modal.locator('#iu-bulk-modal-warning')).toBeVisible();
    await modal.locator('button[data-bs-dismiss="modal"]').click();

    // Cleanup: cancel path shouldn't have deleted/anonymized anything.
    expect((await page.request.get(`/api/contacts/${eligibleId}`)).status()).toBe(200);
    expect((await page.request.get(`/api/contacts/${ineligibleId}`)).status()).toBe(200);
  });
});
