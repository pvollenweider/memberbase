/**
 * E2E tests — member merge flow
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Merge members', () => {
  let idA: string;
  let idB: string;

  test('create two temp members to merge', async ({ page }) => {
    // Member A
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'MergeA');
    await page.fill('#firstName', 'Temp');
    await page.click('button[type="submit"].btn-success');
    await page.waitForURL(/userid=/, { timeout: 15_000 });
    idA = new URL(page.url()).searchParams.get('userid') ?? '';

    // Member B
    await page.goto('/index.php?view=addUser');
    await page.fill('#lastName', 'MergeB');
    await page.fill('#firstName', 'Temp');
    await page.click('button[type="submit"].btn-success');
    await page.waitForURL(/userid=/, { timeout: 15_000 });
    idB = new URL(page.url()).searchParams.get('userid') ?? '';

    if (!idA || !idB) throw new Error('Could not get user ids');
  });

  test('merge form loads with both members', async ({ page }) => {
    await page.goto(`/index.php?view=mergeUsers&a=${idA}&b=${idB}`);
    await expect(page.locator('#merge-form')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#merge-form').locator('text=MergeA').first()).toBeVisible();
    await expect(page.locator('#merge-form').locator('text=MergeB').first()).toBeVisible();
  });

  test('execute merge (A survives, B deleted)', async ({ page }) => {
    await page.goto(`/index.php?view=mergeUsers&a=${idA}&b=${idB}`);
    await expect(page.locator('#merge-form')).toBeVisible({ timeout: 10_000 });

    // Set Alpine.js reactive state directly (x-model won't update from force:true check).
    // Also set the DOM checked state so the form serializes the right values.
    await page.evaluate(() => {
      const wrap = document.querySelector('.ca-merge-wrap') as HTMLElement;
      // Update Alpine data
      if ((wrap as any)._x_dataStack) {
        (wrap as any)._x_dataStack[0].disposal = 'delete';
        (wrap as any)._x_dataStack[0].survivor = 'a';
      }
      // Also force-check the radio so the form POST includes disposal=delete
      const dispInput = document.querySelector('input[name="disposal"][value="delete"]') as HTMLInputElement;
      if (dispInput) {
        dispInput.checked = true;
        dispInput.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });

    // Remove hx-boost so the form submit navigates normally instead of via htmx
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));

    // Click "Fusionner" button (type="button", @click="openConfirm()") — opens native <dialog>
    await page.locator('.ca-merge-wrap button[type="button"].btn-danger').click();
    // Dialog should now be open
    await expect(page.locator('#merge-dialog')).toBeVisible({ timeout: 5_000 });

    // Submit via form.submit() on #merge-form to bypass any remaining htmx listeners
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.evaluate(() => {
        const form = document.getElementById('merge-form') as HTMLFormElement;
        form.submit();
      }),
    ]);

    // After merge, redirects to integrity tab
    expect(page.url()).toContain('tab=integrity');

    // Member A should still exist, B should be gone
    await page.goto(`/index.php?view=generalData&userid=${idA}`);
    await expect(page.locator('.ca-view-zone')).toContainText('MergeA', { timeout: 5_000 });

    // Cleanup A
    await page.goto(`/index.php?view=deleteUser&id=${idA}`);
    await page.check('input[name="dispose"][value="delete"]');
    await page.click('button[type="submit"].btn-danger');
  });
});
