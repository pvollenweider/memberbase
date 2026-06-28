/**
 * E2E tests — metagroupe / filter management (create, rename, delete)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Métagroupes / filtres', () => {
  let createdId: string;

  test('view filters tab', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=filters');
    await expect(page.locator('#tab-filters')).toBeVisible();
  });

  test('create a new filter', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=filters');
    await expect(page.locator('#tab-filters')).toBeVisible();

    // Add form inside #tab-filters
    const addForm = page.locator('#tab-filters form:has(input[name="action"][value="addMetagroup"])');
    await addForm.locator('input[name="name"]').fill('FiltreE2ETest');
    await addForm.locator('button[type="submit"]').click();

    // addMetagroup sends HX-Location to updateMetagroup?id=N&created=1
    await page.waitForURL(/view=updateMetagroup/, { timeout: 10_000 });

    // Extract id from URL
    const url = new URL(page.url());
    createdId = url.searchParams.get('id') ?? '';
    if (!createdId) throw new Error('Could not get metagroup id from URL');

    // Navigate back to filters tab to verify it appears
    await page.goto('/index.php?view=settings&tab=filters');
    await expect(page.locator('#tab-filters')).toBeVisible();
    await expect(page.locator('#tab-filters a').filter({ hasText: 'FiltreE2ETest' }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('rename the filter', async ({ page }) => {
    if (!createdId) throw new Error('No metagroup id');
    await page.goto(`/index.php?view=updateMetagroup&id=${createdId}`);
    await expect(page.locator('#mgname')).toBeVisible({ timeout: 10_000 });
    await page.fill('#mgname', 'FiltreE2ERenomme');
    // Use form.submit() to bypass htmx event listeners (hx-boost attribute removal
    // doesn't unregister htmx's form submit listener already attached to the DOM).
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.evaluate(() => {
        const form = document.querySelector<HTMLFormElement>('form:has(input[name="action"][value="updateMetagroup"])');
        if (!form) throw new Error('updateMetagroup form not found');
        form.submit();
      }),
    ]);
    await page.goto('/index.php?view=settings&tab=filters');
    await expect(page.locator('#tab-filters a').filter({ hasText: 'FiltreE2ERenomme' }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('delete the filter', async ({ page }) => {
    if (!createdId) throw new Error('No metagroup id');
    await page.goto('/index.php?view=settings&tab=filters');
    await expect(page.locator('#tab-filters')).toBeVisible();

    // Submit deleteMetagroup via programmatic form
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.evaluate((id) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/index.php';
        for (const [n, v] of [['action', 'deleteMetagroup'], ['id', id], ['view', 'settings'], ['tab', 'filters']] as [string, string][]) {
          const el = document.createElement('input');
          el.name = n; el.value = v;
          form.appendChild(el);
        }
        document.body.appendChild(form);
        form.submit();
      }, createdId),
    ]);

    await page.goto('/index.php?view=settings&tab=filters');
    await expect(page.locator('#tab-filters a').filter({ hasText: 'FiltreE2ERenomme' })).toHaveCount(0);
  });
});
