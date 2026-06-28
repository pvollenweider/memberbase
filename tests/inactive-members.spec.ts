/**
 * E2E tests — archived members (deactivate, list, reactivate)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Inactive members', () => {
  let archivedId: string;

  test('deactivate a member to set up fixture', async ({ page }) => {
    // Create a temp member
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'Archived');
    await page.fill('#firstName', 'Temp');
    await page.click('button[type="submit"].btn-success');
    await expect(page.locator('form[name="updateUser"] input[type="hidden"][name="id"]')).toBeAttached({ timeout: 15_000 });
    archivedId = await page.locator('form[name="updateUser"] input[type="hidden"][name="id"]').getAttribute('value') ?? '';
    if (!archivedId) throw new Error('Could not get new user id');

    // Deactivate via deleteUser page (choose deactivate, not delete)
    await page.goto(`/index.php?view=deleteUser&id=${archivedId}`);
    await page.check('input[name="dispose"][value="deactivate"]');
    await page.click('button[type="submit"].btn-danger');
    await expect(page.locator('table.table')).toBeVisible({ timeout: 15_000 });
  });

  test('view inactive members list', async ({ page }) => {
    await page.goto('/index.php?view=inactiveUsers');
    await expect(page.locator('table')).toBeVisible();
    await expect(page.locator('text=Archived')).toBeVisible();
  });

  test('reactivate the archived member', async ({ page }) => {
    await page.goto('/index.php?view=inactiveUsers');
    const row = page.locator('tr').filter({ hasText: 'Archived' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });

    // Click the "Désarchiver" button to open Bootstrap modal
    await row.locator('button[data-unarchive-id]').click();
    await expect(page.locator('#unarchive-modal')).toBeVisible({ timeout: 5_000 });

    // Confirm — form.submit() navigates away
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.locator('#unarchive-confirm-btn').click(),
    ]);
    await expect(page.locator('text=Archived')).toHaveCount(0);

    // Verify member is back in main list
    await page.goto('/index.php?action=search&searchString=Archived');
    await expect(page.locator('table.table tbody tr').first()).toContainText('Archived');

    // Cleanup: delete the member
    if (archivedId) {
      await page.goto(`/index.php?view=deleteUser&id=${archivedId}`);
      await page.check('input[name="dispose"][value="delete"]');
      await page.click('button[type="submit"].btn-danger');
    }
  });
});
