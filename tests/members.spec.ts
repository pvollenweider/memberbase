/**
 * E2E tests — member CRUD (list, search, add, edit, delete)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, Page } from '@playwright/test';

async function getNewUserId(page: Page): Promise<string> {
  // After addUser submit, htmx swaps in generalData without changing the URL.
  // The "Données" tab link reliably contains the new userid in its href.
  const link = page.locator('a[href*="view=generalData&userid="]').first();
  await expect(link).toBeAttached({ timeout: 15_000 });
  const href = await link.getAttribute('href') ?? '';
  return new URLSearchParams(href.split('?')[1]).get('userid') ?? '';
}

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

    // htmx swaps in generalData without changing URL — confirm swap completed
    await getNewUserId(page);

    await page.goto('/index.php?action=search&searchString=Testmembre');
    await expect(page.locator('table.table tbody tr').first()).toContainText('Testmembre');
  });

  test('edit a member firstname and verify updated', async ({ page }) => {
    await page.goto('/index.php?view=generalData&userid=1');

    // Enter edit mode via hover zone click
    await page.locator('.ca-view-zone').click();
    await expect(page.locator('#gd-firstName')).toBeVisible({ timeout: 5_000 });

    await page.locator('#gd-firstName').fill('AliceModified');
    await page.click('button:has-text("Enregistrer")');

    // Alpine injects #casa-save-ok after successful save
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });

    // View mode shows updated name without page reload
    await expect(page.locator('.ca-view-zone')).toContainText('AliceModified', { timeout: 5_000 });

    // Restore
    await page.locator('.ca-view-zone').click();
    await page.locator('#gd-firstName').fill('Alice');
    await page.click('button:has-text("Enregistrer")');
    await expect(page.locator('#casa-save-ok')).toBeAttached({ timeout: 10_000 });
  });

  test('delete a member via confirmation page', async ({ page }) => {
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'ToDelete');
    await page.fill('#firstName', 'Temp');
    await page.click('button[type="submit"].btn-success');

    // htmx swaps in generalData without changing URL — extract userid from Données link
    const uid = await getNewUserId(page);
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
