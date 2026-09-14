/**
 * E2E tests — email consent checkbox on the member fiche (#175)
 *
 * Same Alpine.js + PATCH /api/contacts/{id} pattern as gender/contact
 * type (see contact-type-fiche.spec.ts).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Email consent field — member fiche (#175)', () => {
  test('edit mode shows an unchecked checkbox by default and persists a check', async ({ page }) => {
    await page.goto('/index.php?view=generalData&userid=1');
    await page.locator('[x-show="!editing"]').first().click();

    const checkbox = page.locator('#gd-emailConsent');
    await expect(checkbox).toBeVisible();
    await expect(checkbox).not.toBeChecked();

    await checkbox.check();
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/contacts/1') && r.request().method() === 'PATCH'),
      page.locator('button', { hasText: 'Enregistrer' }).click(),
    ]);

    // Persisted server-side: reload from scratch, re-enter edit mode.
    await page.goto('/index.php?view=generalData&userid=1');
    await page.locator('[x-show="!editing"]').first().click();
    await expect(page.locator('#gd-emailConsent')).toBeChecked();

    // Revert for other tests.
    await page.request.patch('/api/contacts/1', { data: { emailConsent: false } });
  });

  test('/api/contacts/1 exposes emailConsent', async ({ page }) => {
    const resp = await page.request.get('/api/contacts/1');
    const json = await resp.json();
    expect(json.data).toHaveProperty('emailConsent', false);
  });

  test('member list — Consentement email column is hidden by default, togglable via colvis', async ({ page }) => {
    await page.request.patch('/api/contacts/1', { data: { emailConsent: true } });

    await page.goto('/index.php?view=peopleFinance&tab=members');
    const table = page.locator('table.export');
    await expect(table).toBeVisible();

    // DataTables removes non-visible columns' <th>/<td> from the rendered
    // DOM entirely (not just CSS-hidden) — so absent here confirms hidden
    // by default, same as the pre-existing sexe/address/npa/creationDate columns.
    await expect(table.locator('thead th', { hasText: 'Consentement à recevoir des e-mails' })).toHaveCount(0);

    // Reveal it via the "Colonnes" (colvis) button — its dropdown items are
    // <a class="dt-button dropdown-item buttons-columnVisibility">, not <button>.
    await page.locator('button', { hasText: 'Colonnes' }).click();
    await page.locator('.dt-button-collection a', { hasText: 'Consentement à recevoir des e-mails' }).click();

    const header = table.locator('thead th', { hasText: 'Consentement à recevoir des e-mails' });
    await expect(header).toBeVisible();

    // Alice (id 1) now has consent — a green check should show in her row.
    const row = page.locator('tr', { hasText: 'Dupont' }).first();
    await expect(row.locator('td .fa-check')).toBeVisible();

    // Revert.
    await page.request.patch('/api/contacts/1', { data: { emailConsent: false } });
  });

  test('regression: AJAX live search does not desync column count (row/thead mismatch)', async ({ page }) => {
    // Adding the emailConsent <th> (server-rendered) without also adding a
    // matching <td> in the client-side buildRow() (used by the AJAX search
    // box on this segment=0 view) left DataTables with a <thead> one column
    // wider than each AJAX-built <tr> — "Requested unknown parameter" console
    // warning and misaligned cells. Both must produce the same column count.
    const consoleWarnings: string[] = [];
    page.on('console', (msg) => { if (msg.text().includes('DataTables warning')) consoleWarnings.push(msg.text()); });

    await page.goto('/index.php?view=peopleFinance&tab=members');
    const table = page.locator('table.export');
    await expect(table).toBeVisible();
    const headerCount = await table.locator('thead th').count();

    await page.locator('#main-search-form [name="searchString"]').fill('Dupont');
    await page.waitForResponse((r) => r.url().includes('/api/contacts') && r.url().includes('search='));
    await page.waitForTimeout(300); // caInitDT() re-init after the fetch resolves

    const row = table.locator('tbody tr').first();
    await expect(row).toBeVisible();
    expect(await row.locator('td').count()).toBe(headerCount);
    expect(consoleWarnings).toEqual([]);
  });
});
