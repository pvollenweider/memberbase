/**
 * E2E tests — application settings (navigation, save)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Settings', () => {
  test('navigate to general settings tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#tab-settings')).toBeVisible();
    await expect(page.locator('#s_org_name')).toBeVisible();
  });

  test('navigate to groups tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();
  });

  test('navigate to categories tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=categories');
    await expect(page.locator('#tab-categories')).toBeVisible();
  });

  test('navigate to compta types tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=compta');
    await expect(page.locator('#tab-compta')).toBeVisible();
  });

  test('change default_segment setting and verify saved', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_default_segment')).toBeVisible();

    await page.selectOption('#s_default_segment', '1');
    await page.click('button[type="submit"].btn-primary');

    // After htmx save, wait for success toast element
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });

    // Reload and confirm persisted value
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_default_segment')).toHaveValue('1');

    // Restore — navigate again so the form is present; restore to segment 2
    await page.goto('/index.php?view=settings&tab=settings');
    await page.selectOption('#s_default_segment', '2');
    await page.click('button[type="submit"].btn-primary');
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });
  });

  test('org fields (but statutaire, statut fiscal, description du montant) persist', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await page.fill('#s_org_purpose', 'But statutaire E2E');
    await page.fill('#s_org_tax_status', 'Exonérée AFC-GE depuis 2018 (E2E)');
    await page.fill('#s_org_coti_amount_desc', 'min. CHF 50.- / pers. (E2E)');
    await Promise.all([
      page.waitForSelector('#settings-save-msg #casa-save-ok', { state: 'attached', timeout: 10_000 }),
      page.locator('button[type="submit"].btn-primary').first().click(),
    ]);

    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_org_purpose')).toHaveValue('But statutaire E2E');
    await expect(page.locator('#s_org_tax_status')).toHaveValue('Exonérée AFC-GE depuis 2018 (E2E)');
    await expect(page.locator('#s_org_coti_amount_desc')).toHaveValue('min. CHF 50.- / pers. (E2E)');
  });

  test('IBAN field persists', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await page.fill('#s_org_iban', 'CH93 0076 2011 6238 5295 7');
    await Promise.all([
      page.waitForSelector('#settings-save-msg #casa-save-ok', { state: 'attached', timeout: 10_000 }),
      page.locator('button[type="submit"].btn-primary').first().click(),
    ]);
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_org_iban')).toHaveValue('CH93 0076 2011 6238 5295 7');
  });
});

test.describe('Settings — segment categories CRUD', () => {
  test('create, rename, and delete a category', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=categories');
    await page.fill('#tab-categories input[name="name"]', 'Catégorie E2E');
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('#tab-categories form:has(input[value="addCombinedSegment"]) button[type="submit"]').click(),
    ]);

    // addCombinedSegment redirects straight to the edit page, not the list.
    await expect(page).toHaveURL(/view=updateCombinedSegment/);
    await expect(page.locator('#mgname')).toHaveValue('Catégorie E2E');

    await page.fill('#mgname', 'Catégorie E2E Renommée');
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('form:has(input[value="updateCombinedSegment"]) button[type="submit"]').click(),
    ]);
    // updateCombinedSegment redirects back to the categories list.
    await expect(page).toHaveURL(/tab=categories/);

    await page.goto('/index.php?view=settings&tab=categories');
    await expect(page.locator('#cat-order-tbody')).toContainText('Catégorie E2E Renommée');

    await page.locator('#cat-order-tbody tr').filter({ has: page.getByText('Catégorie E2E Renommée', { exact: true }) })
      .locator('a[href*="view=updateCombinedSegment"]').click();
    await page.locator('button[data-bs-target="#modal-delete-combined-segment"]').click();
    await expect(page.locator('#modal-delete-combined-segment')).toBeVisible({ timeout: 5_000 });
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('#modal-delete-combined-segment button.btn-danger').click(),
    ]);

    // All settings tabs render server-side regardless of which is active
    // (including Journal, whose audit entries legitimately mention this
    // category's old/new name) — scope the "gone" check to the categories
    // list itself, not the whole page.
    await page.goto('/index.php?view=settings&tab=categories');
    await expect(page.locator('#tab-categories')).not.toContainText('Catégorie E2E Renommée');
  });
});
