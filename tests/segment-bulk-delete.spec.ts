/**
 * E2E test — bulk delete of hidden segments from the "Masqués" table
 * (Réglages → Groupes).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Segment bulk delete (hidden segments)', () => {
  let segmentId: string;
  let visibleSegmentId: string;
  const SEGMENT_NAME = 'BulkDelete E2E Segment';
  const VISIBLE_SEGMENT_NAME = 'BulkDelete E2E Visible Sibling';

  test('create a fresh segment to isolate the test', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    const form = page.locator('form[name="addSegment"]');
    await form.locator('input[name="name"]').fill(SEGMENT_NAME);
    await form.locator('button[type="submit"]').click();
    await page.waitForURL(/view=updateSegment&id=\d+/, { timeout: 15_000 });
    segmentId = new URL(page.url()).searchParams.get('id')!;
    expect(segmentId).toBeTruthy();

    // A second, deliberately-left-visible segment so test 3 can build a
    // mixed (visible + hidden) selection without depending on seed data.
    await page.goto('/index.php?view=settings&tab=groups');
    const form2 = page.locator('form[name="addSegment"]');
    await form2.locator('input[name="name"]').fill(VISIBLE_SEGMENT_NAME);
    await form2.locator('button[type="submit"]').click();
    await page.waitForURL(/view=updateSegment&id=\d+/, { timeout: 15_000 });
    visibleSegmentId = new URL(page.url()).searchParams.get('id')!;
    expect(visibleSegmentId).toBeTruthy();
  });

  test('hide the segment via the bulk bar', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    const row = page.locator(`tr[data-segment-id="${segmentId}"]`);
    await expect(row).toBeVisible({ timeout: 10_000 });
    await row.locator('.bulk-cb').check();
    await page.locator('#bulk-bar button', { hasText: 'Masquer' }).click();
    await page.waitForURL(/view=settings.*tab=groups/, { timeout: 15_000 });

    // Expand the "Masqués" card and confirm the segment moved there.
    await page.locator('.card-header', { hasText: 'Masqués' }).click();
    const hiddenRow = page.locator('#hidden-segments-body').locator(`tr[data-segment-id="${segmentId}"]`);
    await expect(hiddenRow).toBeVisible({ timeout: 10_000 });
  });

  test('the delete button only appears when the whole selection is hidden segments', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await page.locator('.card-header', { hasText: 'Masqués' }).click();
    const hiddenRow = page.locator('#hidden-segments-body').locator(`tr[data-segment-id="${segmentId}"]`);
    await expect(hiddenRow).toBeVisible({ timeout: 10_000 });

    // Select one visible segment alongside the hidden one — delete must stay hidden.
    const visibleRow = page.locator(`tr[data-segment-id="${visibleSegmentId}"]`);
    await expect(visibleRow).toBeVisible({ timeout: 10_000 });
    await visibleRow.locator('.bulk-cb').check();
    await hiddenRow.locator('.bulk-cb').check();
    await expect(page.locator('#bulk-delete-btn')).toBeHidden();

    // Deselect the visible one — now the selection is hidden-only, delete appears.
    await visibleRow.locator('.bulk-cb').uncheck();
    await expect(page.locator('#bulk-delete-btn')).toBeVisible({ timeout: 5_000 });
  });

  test('bulk delete removes the segment after confirmation', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    await page.locator('.card-header', { hasText: 'Masqués' }).click();
    const hiddenRow = page.locator('#hidden-segments-body').locator(`tr[data-segment-id="${segmentId}"]`);
    await expect(hiddenRow).toBeVisible({ timeout: 10_000 });
    await hiddenRow.locator('.bulk-cb').check();

    await page.locator('#bulk-delete-btn').click();

    const modal = page.locator('#bulk-delete-segments-modal');
    await expect(modal).toBeVisible({ timeout: 5_000 });
    await expect(modal.locator('#bulk-delete-segments-names')).toContainText(SEGMENT_NAME);

    await modal.locator('#bulk-delete-segments-confirm-btn').click();
    await page.waitForURL(/view=settings.*tab=groups/, { timeout: 15_000 });

    // The "Masqués" card only renders while at least one hidden segment
    // remains — if this was the last one, the card (and its header) is
    // gone entirely, so assert on the row directly rather than expanding it.
    await expect(page.locator(`tr[data-segment-id="${segmentId}"]`)).toHaveCount(0);
  });
});
