/**
 * E2E tests — CSV/TSV contact import wizard (3 steps)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

function csvBuffer(rows: string[][]): Buffer {
  return Buffer.from(rows.map((r) => r.join(',')).join('\n'), 'utf-8');
}

test.describe('Import wizard — step 1 (upload)', () => {
  test('rejects submission without a file', async ({ page }) => {
    await page.goto('/index.php?view=importStep1');
    await expect(page.locator('input[name="csv"]')).toHaveAttribute('required', '');
  });

  test('uploading a well-formed CSV advances to step 2 with auto-detected mapping', async ({ page }) => {
    await page.goto('/index.php?view=importStep1');
    await page.setInputFiles('input[name="csv"]', {
      name: 'import-e2e.csv',
      mimeType: 'text/csv',
      buffer: csvBuffer([
        ['Nom', 'Prénom', 'Email'],
        ['Wizard', 'Alice', 'wizard.alice.e2e@example.com'],
        ['Wizard', 'Bob', 'wizard.bob.e2e@example.com'],
      ]),
    });
    await Promise.all([
      page.waitForURL(/view=importStep2/, { timeout: 15_000 }),
      page.locator('form:has(input[name="csv"]) button[type="submit"]').click(),
    ]);

    await expect(page.locator('body')).toContainText('2 ligne'); // rowsDetected
    // Auto-detected mapping: "Nom" -> lastName, "Prénom" -> firstName, "Email" -> email
    const rows = page.locator('table tbody tr');
    await expect(rows).toHaveCount(3);
    await expect(rows.nth(0).locator('select')).toHaveValue('lastName');
    await expect(rows.nth(1).locator('select')).toHaveValue('firstName');
    await expect(rows.nth(2).locator('select')).toHaveValue('email');
  });
});

test.describe.serial('Import wizard — step 2 → 3 (no duplicates)', () => {
  test('completing the mapping with "create new segment" creates contacts and a segment', async ({ page }) => {
    await page.goto('/index.php?view=importStep1');
    await page.setInputFiles('input[name="csv"]', {
      name: 'import-e2e-2.csv',
      mimeType: 'text/csv',
      buffer: csvBuffer([
        ['Nom', 'Prénom', 'Email'],
        ['ImportE2E', 'Carla', 'importe2e.carla@example.com'],
        ['ImportE2E', 'Dan', 'importe2e.dan@example.com'],
      ]),
    });
    await Promise.all([
      page.waitForURL(/view=importStep2/, { timeout: 15_000 }),
      page.locator('form:has(input[name="csv"]) button[type="submit"]').click(),
    ]);

    // Default "segment_mode=auto" creates a named segment automatically —
    // just submit the mapping form as auto-detected.
    await Promise.all([
      page.waitForURL(/view=importStep3/, { timeout: 15_000 }),
      page.locator('form input[name="action"][value="importApply"]').locator('xpath=ancestor::form').locator('button[type="submit"]').click(),
    ]);

    await expect(page.locator('.alert-success')).toContainText('2'); // contactsCreated
    await expect(page.locator('.alert-info')).toContainText('2'); // contactsAddedToSegment
    await expect(page.locator('a[href*="view=updateUser"]')).toHaveCount(0); // no duplicate cards

    // Contacts are searchable afterward.
    const search = await page.request.get('/api/contacts?search=ImportE2E');
    const { data } = await search.json();
    expect(data.length).toBe(2);
  });
});

test.describe.serial('Import wizard — step 3 (duplicate resolution)', () => {
  test('a row matching an existing contact by email is flagged as a duplicate', async ({ page }) => {
    // Alice Dupont (member id 1, alice@example.com) is in the seed — reuse
    // her email so the importer's duplicate detector matches on it.
    await page.goto('/index.php?view=importStep1');
    await page.setInputFiles('input[name="csv"]', {
      name: 'import-e2e-dup.csv',
      mimeType: 'text/csv',
      buffer: csvBuffer([
        ['Nom', 'Prénom', 'Email', 'Ville'],
        ['Dupont', 'Alice', 'alice@example.com', 'Nouvelle-Ville-E2E'],
      ]),
    });
    await Promise.all([
      page.waitForURL(/view=importStep2/, { timeout: 15_000 }),
      page.locator('form:has(input[name="csv"]) button[type="submit"]').click(),
    ]);
    // Map the 4th column ("Ville") to npa/city-ish field so "fill" has
    // something to write — importFieldLabels includes 'npa' for that role.
    await page.locator('table tbody tr').nth(3).locator('select').selectOption('npa');

    await Promise.all([
      page.waitForURL(/view=importStep3/, { timeout: 15_000 }),
      page.locator('form input[name="action"][value="importApply"]').locator('xpath=ancestor::form').locator('button[type="submit"]').click(),
    ]);

    await expect(page.locator('body')).toContainText('doublon'); // duplicatesDetected(Count)
    const dupCard = page.locator('.card', { hasText: 'Alice' }).filter({ has: page.locator('input[name^="choice"]') });
    await expect(dupCard).toBeVisible();

    // Default choice is "ignore" — apply with defaults, no field should change.
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.locator('form button[type="submit"]', { hasText: 'Appliquer' }).click(),
    ]);

    const check = await page.request.get('/api/contacts/1');
    const { data } = await check.json();
    expect(data.npa).not.toBe('Nouvelle-Ville-E2E');
  });

  test('choosing "fill" on a re-triggered duplicate writes empty fields only', async ({ page }) => {
    // Confirm member 1's npa is empty before this run (seed has no npa for Alice).
    const before = await (await page.request.get('/api/contacts/1')).json();
    expect(before.data.npa ?? '').toBe('');

    await page.goto('/index.php?view=importStep1');
    await page.setInputFiles('input[name="csv"]', {
      name: 'import-e2e-dup2.csv',
      mimeType: 'text/csv',
      buffer: csvBuffer([
        ['Nom', 'Prénom', 'Email', 'Ville'],
        ['Dupont', 'Alice', 'alice@example.com', 'Ville-Remplie-E2E'],
      ]),
    });
    await Promise.all([
      page.waitForURL(/view=importStep2/, { timeout: 15_000 }),
      page.locator('form:has(input[name="csv"]) button[type="submit"]').click(),
    ]);
    await page.locator('table tbody tr').nth(3).locator('select').selectOption('npa');
    await Promise.all([
      page.waitForURL(/view=importStep3/, { timeout: 15_000 }),
      page.locator('form input[name="action"][value="importApply"]').locator('xpath=ancestor::form').locator('button[type="submit"]').click(),
    ]);

    await page.locator('input[name^="choice"][value="fill"]').check();
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.locator('form button[type="submit"]', { hasText: 'Appliquer' }).click(),
    ]);

    const after = await (await page.request.get('/api/contacts/1')).json();
    expect(after.data.npa).toBe('Ville-Remplie-E2E');
  });
});
