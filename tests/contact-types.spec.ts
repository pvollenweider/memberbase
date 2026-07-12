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
