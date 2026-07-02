/**
 * E2E tests — global dirty-form guard (js/app.js, extracted in #58)
 *
 * The guard marks the page dirty on change/input in form fields and
 * intercepts htmx navigation with a confirm() dialog. Elements carrying
 * data-no-dirty (or listed exclusions) must NOT trigger it.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import * as path from 'path';

const ADMIN_STATE = path.resolve(__dirname, '.auth/admin.json');

test.use({ storageState: ADMIN_STATE });

test('modified form field triggers confirm dialog on navigation', async ({ page }) => {
  await page.goto('/index.php?view=addUser');
  await page.fill('#firstName', 'Brouillon');

  let dialogShown = false;
  page.once('dialog', async (dialog) => {
    dialogShown = true;
    expect(dialog.message()).toContain('modifications non sauvegardées');
    await dialog.accept(); // let navigation proceed
  });

  await page.locator('a[href*="view=resume"]:visible').first().click();
  await expect(page.locator('#main-content')).not.toContainText('Brouillon');
  expect(dialogShown, 'confirm dialog should fire for dirty form').toBe(true);
});

test('dismissing the dialog cancels navigation and keeps input', async ({ page }) => {
  await page.goto('/index.php?view=addUser');
  await page.fill('#firstName', 'Brouillon');

  page.once('dialog', (dialog) => dialog.dismiss());
  await page.locator('a[href*="view=resume"]:visible').first().click();

  // Navigation blocked — the form and its value are still there
  await expect(page.locator('#firstName')).toHaveValue('Brouillon');
});

test('clean page navigates without dialog', async ({ page }) => {
  await page.goto('/index.php?view=addUser');

  page.once('dialog', async (dialog) => {
    throw new Error(`unexpected dialog: ${dialog.message()}`);
  });

  await page.locator('a[href*="view=resume"]:visible').first().click();
  await expect(page.locator('#firstName')).toHaveCount(0);
});

test('team filter input (data-no-dirty exclusion) does not mark dirty', async ({ page }) => {
  await page.goto('/index.php');
  // #team-filter-input is in the guard's exclusion list
  await page.click('#navbarDropdown');
  await page.fill('#team-filter-input', 'xyz');

  page.once('dialog', async (dialog) => {
    throw new Error(`unexpected dialog: ${dialog.message()}`);
  });

  await page.locator('a[href*="view=resume"]:visible').first().click();
  await expect(page.locator('#main-content')).toBeVisible();
});
