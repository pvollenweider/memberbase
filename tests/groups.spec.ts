/**
 * E2E tests — group (team) management (view, create, rename, delete)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

// Sequential because rename/delete depend on create
test.describe.serial('Groups (teams)', () => {
  test('view group list in settings', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();
    // Team name links use ?team=N href
    await expect(page.locator('#tab-groups a[href*="?team="]').first()).toBeVisible();
  });

  test('create a new group', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();

    const addForm = page.locator('form:has(input[name="action"][value="addSegmentWithImport"])');
    await addForm.locator('#name').fill('Membre E2E');
    await addForm.locator('button[type="submit"]').click();

    // addSegmentWithImport emits HX-Location to ?view=updateSegment&id=N
    await page.waitForURL(/view=updateSegment/, { timeout: 10_000 });
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();
    // Team name link uses ?team=N
    await expect(
      page.locator('#tab-groups a[href*="?team="]').filter({ hasText: 'Membre E2E' }).first()
    ).toBeVisible({ timeout: 10_000 });
  });

  test('rename a group inline', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();

    // Get the team id from data-team-id on the row
    const row = page.locator('#tab-groups tr[data-team-id]').filter({ hasText: 'Membre E2E' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });
    const segmentId = await row.getAttribute('data-team-id');
    if (!segmentId) throw new Error('data-team-id not found for Membre E2E');

    // Read CSRF token from the meta tag (same cookie jar = same session)
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });

    // POST rename directly — avoids all htmx/form-submit timing issues
    await page.request.post('/index.php', {
      form: { action: 'updateSegment', view: 'settings', tab: 'groups', id: segmentId, name: 'Membre E2E Renamed', csrf },
    });

    // Navigate to settings to verify rename persisted in DB
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();
    await expect(page.locator('#tab-groups a[href*="?team="]').filter({ hasText: 'Membre E2E Renamed' }).first()).toBeVisible({ timeout: 10_000 });
  });

  test('delete a group via POST', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();

    const row = page.locator('#tab-groups tr[data-team-id]').filter({ hasText: 'Membre E2E Renamed' }).first();
    await expect(row).toBeVisible({ timeout: 10_000 });
    const segmentId = await row.getAttribute('data-team-id');
    if (!segmentId) throw new Error('data-team-id not found for Membre E2E Renamed');

    // Submit deleteSegment — regular form submit (full page)
    const [deleteResponse] = await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.evaluate(({ id }) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/index.php';
        const csrfTok = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        for (const [name, value] of [['action','deleteSegment'],['view','settings'],['tab','groups'],['id',id],['csrf',csrfTok]] as [string,string][]) {
          const el = document.createElement('input');
          el.name = name; el.value = value;
          form.appendChild(el);
        }
        document.body.appendChild(form);
        form.submit();
      }, { id: segmentId }),
    ]);
    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('#tab-groups')).toBeVisible();
    await expect(page.locator('#tab-groups a[href*="?team="]').filter({ hasText: 'Membre E2E Renamed' })).toHaveCount(0);
  });

  test('open a group settings page', async ({ page }) => {
    await page.goto('/index.php?view=updateSegment&id=1');
    // updateSegment is inside the settings page at #tab-groups
    await expect(page.locator('#tab-groups')).toBeVisible({ timeout: 10_000 });
    // The update team form should be present
    await expect(page.locator('#name')).toBeVisible({ timeout: 10_000 });
  });
});
