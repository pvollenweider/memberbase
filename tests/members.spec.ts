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
    await page.waitForLoadState('networkidle');
    const rows = page.locator('table.table tbody tr');
    await expect(rows.first()).toBeVisible();
    // The matched row should contain "Dupont"
    await expect(rows.first()).toContainText('Dupont');
  });

  test('add a new member and verify appears in list', async ({ page }) => {
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'Testmembre');
    await page.fill('#firstName', 'E2E');
    await page.fill('#email', 'e2e@example.com');
    await page.click('button[type="submit"].btn-success');

    // App redirects to the member view after add
    await page.waitForLoadState('networkidle');

    // Navigate back to list and search for the new member
    await page.goto('/index.php?action=search&searchString=Testmembre');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table.table tbody tr').first()).toContainText('Testmembre');
  });

  test('edit a member firstname and verify updated', async ({ page }) => {
    // Open Alice Dupont (user id=1 from seed)
    await page.goto('/index.php?view=generalData&userid=1');
    await page.waitForLoadState('networkidle');

    const firstNameField = page.locator('#firstName');
    await firstNameField.fill('AliceModified');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Reload the edit form and verify the value persisted
    await page.goto('/index.php?view=generalData&userid=1');
    await expect(page.locator('#firstName')).toHaveValue('AliceModified');

    // Restore original value
    await page.locator('#firstName').fill('Alice');
    await page.click('button[type="submit"]');
  });

  test('delete a member via confirmation page', async ({ page }) => {
    // First create a throwaway member via the add form
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'ToDelete');
    await page.fill('#firstName', 'Temp');
    await page.click('button[type="submit"].btn-success');
    await page.waitForLoadState('networkidle');

    // Extract the userid from the redirect URL
    const url = page.url();
    const match = url.match(/userid=(\d+)/);
    if (!match) throw new Error('Could not determine new user id from URL: ' + url);
    const uid = match[1];

    // Navigate to delete confirmation
    await page.goto(`/index.php?view=deleteUser&id=${uid}`);
    await expect(page.locator('.card-title')).toBeVisible();

    // Select "delete permanently" radio and confirm
    await page.check('input[name="dispose"][value="delete"]');
    await page.click('button[type="submit"].btn-danger');
    await page.waitForLoadState('networkidle');

    // Should land back on the member list
    await expect(page.locator('table.table')).toBeVisible();
  });

  test('navigate to member from group view — membership checkbox visible', async ({ page }) => {
    await page.goto('/index.php?view=updateTeam&id=1');
    await page.waitForLoadState('networkidle');
    // The team view lists members; at least one membership checkbox should exist
    await expect(page.locator('input[type="checkbox"][name^="import"], input[name="addToFromTeam"]').first()).toBeVisible();
  });
});
