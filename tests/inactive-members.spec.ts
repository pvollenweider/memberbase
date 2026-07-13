/**
 * E2E tests — archived members (deactivate, list, reactivate)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, Page } from '@playwright/test';

async function getNewUserId(page: Page): Promise<string> {
  const link = page.locator('a[href*="view=generalData&userid="]').first();
  await expect(link).toBeAttached({ timeout: 15_000 });
  const href = await link.getAttribute('href') ?? '';
  return new URLSearchParams(href.split('?')[1]).get('userid') ?? '';
}

test.describe.serial('Inactive members', () => {
  let archivedId: string;

  test('deactivate a member to set up fixture', async ({ page }) => {
    // Create a temp member
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'Archived');
    await page.fill('#firstName', 'Temp');
    await page.click('button[type="submit"].btn-success');
    archivedId = await getNewUserId(page);
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
    await expect(page.locator('text=Archived Temp')).toBeVisible();
  });

  test('reactivate the archived member', async ({ page }) => {
    await page.goto('/index.php?view=inactiveUsers');
    const row = page.locator('tr').filter({ hasText: 'Archived Temp' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });

    // Click the "Désarchiver" button to open Bootstrap modal
    await row.locator('button[data-unarchive-id]').click();
    await expect(page.locator('#unarchive-modal')).toBeVisible({ timeout: 5_000 });

    // Confirm — form.submit() navigates away
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.locator('#unarchive-confirm-btn').click(),
    ]);
    await expect(page.locator('tr').filter({ hasText: 'Archived Temp' })).toHaveCount(0);

    // Verify member is active again — navigate directly to their profile
    await page.goto(`/index.php?view=generalData&userid=${archivedId}`);
    await expect(page.locator('.ca-view-zone')).toContainText('Archived', { timeout: 10_000 });

    // Cleanup: delete the member
    if (archivedId) {
      await page.goto(`/index.php?view=deleteUser&id=${archivedId}`);
      await page.check('input[name="dispose"][value="delete"]');
      await page.click('button[type="submit"].btn-danger');
    }
  });
});

test.describe('Settings sidebar consistency', () => {
  test('?view=inactiveUsers shares the same sidebar items as ?view=settings (issue: stale hardcoded duplicate)', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    const realNavLabels = await page.locator('.ca-settings-nav .ca-settings-nav-btn').allTextContents();

    await page.goto('/index.php?view=inactiveUsers');
    const inactiveNavLabels = await page.locator('.ca-settings-nav .ca-settings-nav-btn').allTextContents();

    expect(inactiveNavLabels.map((s) => s.trim())).toEqual(realNavLabels.map((s) => s.trim()));
    // Regression guard: these three were missing from the old hardcoded copy.
    expect(inactiveNavLabels.join(' ')).toContain('Type de contact');
    expect(inactiveNavLabels.join(' ')).toContain('Santé');
  });

  test('mobile select is present on ?view=inactiveUsers (previously absent entirely)', async ({ page }) => {
    await page.goto('/index.php?view=inactiveUsers');
    await expect(page.locator('#settings-select')).toBeAttached();
  });
});
