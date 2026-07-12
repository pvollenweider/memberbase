/**
 * E2E tests — segment auto-assignment rules (#154)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Segment cascade rules', () => {
  const USER_ID = 1;
  let sourceId: string;
  let targetId: string;

  test('create two fresh segments to isolate the test', async ({ page }) => {
    const ids: string[] = [];
    for (const name of ['Cascade E2E Source', 'Cascade E2E Target']) {
      await page.goto('/index.php?view=settings&tab=groups');
      const form = page.locator('form[name="addSegment"]');
      await form.locator('input[name="name"]').fill(name);
      await form.locator('button[type="submit"]').click();
      // addSegmentWithImport redirects to ?view=updateSegment&id=N (the new
      // segment's own edit page), not back to the groups list.
      await page.waitForURL(/view=updateSegment&id=\d+/, { timeout: 15_000 });
      const url = new URL(page.url());
      ids.push(url.searchParams.get('id')!);
    }
    [sourceId, targetId] = ids;
    expect(sourceId).toBeTruthy();
    expect(targetId).toBeTruthy();
  });

  test('add a cascade rule source -> target', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    const form = page.locator('form').filter({ has: page.locator('select[name="sourceSegmentId"]') });
    await form.locator('select[name="sourceSegmentId"]').selectOption(sourceId);
    await form.locator('select[name="targetSegmentId"]').selectOption(targetId);
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('li', { hasText: 'Cascade E2E Source' }).filter({ hasText: 'Cascade E2E Target' })).toBeVisible({ timeout: 10_000 });
  });

  test('assigning the member to the source segment also assigns the target segment', async ({ page }) => {
    await page.goto(`/index.php?view=generalData&userid=${USER_ID}`);
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    const resp = await page.request.post('/index.php', {
      form: { action: 'assignSegment', id: String(USER_ID), segmentId: sourceId, csrf },
    });
    expect(resp.status()).toBe(200);

    await page.goto(`/index.php?view=generalData&userid=${USER_ID}`);
    await expect(page.locator('a', { hasText: 'Cascade E2E Source' })).toBeVisible();
    await expect(page.locator('a', { hasText: 'Cascade E2E Target' })).toBeVisible();
  });

  test('delete the cascade rule', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=groups');
    const ruleRow = page.locator('li', { hasText: 'Cascade E2E Source' }).filter({ hasText: 'Cascade E2E Target' });
    await expect(ruleRow).toBeVisible();
    await ruleRow.locator('button[type="submit"]').click();
    await expect(page.locator('li', { hasText: 'Cascade E2E Source' }).filter({ hasText: 'Cascade E2E Target' })).not.toBeVisible({ timeout: 10_000 });
  });
});
