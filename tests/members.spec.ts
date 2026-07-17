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
    await page.goto('/index.php?view=list');
    const rows = page.locator('table.table tbody tr');
    await expect(rows.first()).toBeVisible();
    expect(await rows.count()).toBeGreaterThanOrEqual(2);
  });

  test('search for a member by name', async ({ page }) => {
    await page.goto('/index.php?view=list&action=search&searchString=Dupont');
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
    const uid = await getNewUserId(page);

    // Navigate directly to the new member's profile to confirm creation
    await page.goto(`/index.php?view=generalData&userid=${uid}`);
    await expect(page.locator('.ca-view-zone')).toContainText('Testmembre', { timeout: 10_000 });
  });

  test('add a new member with a non-default contact type', async ({ page }) => {
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'Testmembre2');
    await page.fill('#firstName', 'E2E');
    await expect(page.locator('#contact_type_id option')).toHaveCount(4);
    await page.selectOption('#contact_type_id', { label: 'Institution' });
    await page.click('button[type="submit"].btn-success');

    const uid = await getNewUserId(page);
    await page.goto(`/index.php?view=generalData&userid=${uid}`);
    await expect(page.locator('.ca-field-value .badge', { hasText: 'Institution' })).toBeVisible();
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
    await page.goto('/index.php?view=updateSegment&id=1');
    await expect(page.locator('#tab-groups')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#name')).toBeVisible({ timeout: 10_000 });
  });

  test('filter the member list by contact type via quick filters', async ({ page }) => {
    // 'Testmembre2' (contactTypeId=Institution) was created by the earlier
    // "non-default contact type" test in this file and never deleted — the
    // one real Institution-type contact this test relies on finding.
    await page.goto('/index.php?view=list');
    const allRowCount = await page.locator('table.table tbody tr').count();

    await page.locator('#navbarDropdown').click();
    const ctLink = page.locator('.dropdown-menu.show a.dropdown-item[href*="contactTypeId="]', { hasText: 'Institution' });
    // The link must reset segment=0 — otherwise it silently combines with the
    // org's default_segment (most institutions aren't in the membership
    // segment), hiding almost all results (bug: only 1/74 shown in prod).
    await expect(ctLink).toHaveAttribute('href', /segment=0/);
    await ctLink.click();
    await expect(page).toHaveURL(/contactTypeId=/);

    // Regression guard: a client-side AJAX interceptor for segment-dropdown
    // clicks used to silently ignore contactTypeId and show the unfiltered
    // list (bug found in prod: filter appeared to do nothing).
    await expect(page.locator('table.table tbody')).toContainText('Testmembre2');
    const filteredRowCount = await page.locator('table.table tbody tr').count();
    expect(filteredRowCount).toBeLessThan(allRowCount);
  });

  test('bare ?contactTypeId= URL is not silently scoped by the default segment', async ({ page }) => {
    await page.goto('/index.php?contactTypeId=2');
    await expect(page.locator('table.table tbody')).toContainText('Testmembre2');
    await expect(page.locator('table.table tbody')).not.toContainText('No data available');
  });

  test('member fiche header shows a prominent name and uses a button-group tab switcher, tasks tab hidden', async ({ page }) => {
    await page.goto('/index.php?view=generalData&userid=1');
    await expect(page.locator('h1.ca-hero-title')).toBeVisible();
    await expect(page.locator('[role="group"] .btn-light', { hasText: 'Données' })).toBeVisible();
    await expect(page.locator('[role="group"] a', { hasText: 'Compta' })).toBeVisible();
    await expect(page.locator('[role="group"] a', { hasText: 'Suivi' })).toBeVisible();
    await expect(page.locator('[role="group"] a', { hasText: 'Tâches' })).toHaveCount(0);
  });
});
