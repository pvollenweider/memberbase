/**
 * E2E tests — member CRUD (list, search, add, edit, delete)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Members', () => {
  test('view member list — table visible with at least 2 rows', async ({ page }) => {
    await page.goto('/index.php');
    const rows = page.locator('table.table tbody tr');
    await expect(rows.first()).toBeVisible();
    expect(await rows.count()).toBeGreaterThanOrEqual(2);
  });

  test('search for a member by name', async ({ page }) => {
    await page.goto('/index.php?action=search&searchString=Dupont');
    const rows = page.locator('table.table tbody tr');
    await expect(rows.first()).toBeVisible();
    await expect(rows.first()).toContainText('Dupont');
  });

  test('add a new member and verify appears in list', async ({ page }) => {
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'Testmembre');
    await page.fill('#firstName', 'E2E');
    await page.fill('#email', 'e2e@example.com');
    await page.click('button[type="submit"].btn-success');

    // htmx swaps in updateUser form (no HX-Location, just renders the new user's form)
    // Wait for the updateUser form's hidden id field (scoped to avoid status-toggle-form)
    await expect(page.locator('form[name="updateUser"] input[type="hidden"][name="id"]')).toBeAttached({ timeout: 15_000 });

    await page.goto('/index.php?action=search&searchString=Testmembre');
    await expect(page.locator('table.table tbody tr').first()).toContainText('Testmembre');
  });

  test('edit a member firstname and verify updated', async ({ page }) => {
    await page.goto('/index.php?view=generalData&userid=1');

    await page.locator('#firstName').fill('AliceModified');
    await page.click('button[type="submit"].btn-primary');

    // updateUser response injects #casa-save-ok into the swapped content
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });

    await page.goto('/index.php?view=generalData&userid=1');
    await expect(page.locator('#firstName')).toHaveValue('AliceModified');

    // Restore
    await page.locator('#firstName').fill('Alice');
    await page.click('button[type="submit"].btn-primary');
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });
  });

  test('delete a member via confirmation page', async ({ page }) => {
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'ToDelete');
    await page.fill('#firstName', 'Temp');
    await page.click('button[type="submit"].btn-success');

    // Wait for updateUser form, then extract new user id from hidden input
    await expect(page.locator('form[name="updateUser"] input[type="hidden"][name="id"]')).toBeAttached({ timeout: 15_000 });
    const uid = await page.locator('form[name="updateUser"] input[type="hidden"][name="id"]').getAttribute('value');
    if (!uid) throw new Error('Could not determine new user id');

    await page.goto(`/index.php?view=deleteUser&id=${uid}`);
    await expect(page.locator('.card-title, h4, h5').first()).toBeVisible();

    await page.check('input[name="dispose"][value="delete"]');
    await page.click('button[type="submit"].btn-danger');

    await expect(page.locator('table.table')).toBeVisible({ timeout: 15_000 });
  });

  test('navigate to group settings — page loads', async ({ page }) => {
    await page.goto('/index.php?view=updateTeam&id=1');
    await expect(page.locator('#tab-groups')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#name')).toBeVisible({ timeout: 10_000 });
  });
});
