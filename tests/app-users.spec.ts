/**
 * E2E tests — application user management (list, create, delete app users)
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('App users management', () => {
  let createdUserId: string;

  test('view app users list', async ({ page }) => {
    await page.goto('/index.php?view=manageAppUsers');
    await expect(page.locator('table.table')).toBeVisible();
    // Admin user from seed should be listed
    await expect(page.locator('table.table td strong', { hasText: 'testadmin' }).first()).toBeVisible();
  });

  test('create a new app user', async ({ page }) => {
    await page.goto('/index.php?view=manageAppUsers');
    // Open create modal
    await page.click('button[data-bs-target="#modal-create-user"]');
    await expect(page.locator('#modal-create-user')).toBeVisible({ timeout: 5_000 });

    await page.fill('#au_username', 'e2etestuser');
    await page.fill('#au_display_name', 'E2E Test User');
    await page.fill('#au_email', 'e2euser@example.com');
    await page.fill('#au_password', 'TestPassword99!');

    // Remove hx-boost so form submits as regular POST
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.click('#modal-create-user button[type="submit"]'),
    ]);

    await expect(page.locator('table.table td strong', { hasText: 'e2etestuser' }).first()).toBeVisible({ timeout: 10_000 });

    // Capture id from the delete button's data-user-id (the modal form is populated by JS,
    // so reading the hidden input directly won't work until the modal is opened).
    const deleteBtn = page.locator('button[data-bs-target="#modal-delete-app-user"][data-username="e2etestuser"]');
    createdUserId = await deleteBtn.getAttribute('data-user-id') ?? '';
  });

  test('delete the created app user', async ({ page }) => {
    if (!createdUserId) throw new Error('No user id to delete');
    await page.goto('/index.php?view=manageAppUsers');

    // Submit delete form for that user id
    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      page.evaluate((id) => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/index.php';
        const csrfTok = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        for (const [name, value] of [['action', 'deleteAppUser'], ['target_id', id], ['csrf', csrfTok]] as [string, string][]) {
          const el = document.createElement('input');
          el.name = name; el.value = value;
          form.appendChild(el);
        }
        document.body.appendChild(form);
        form.submit();
      }, createdUserId),
    ]);

    await page.goto('/index.php?view=manageAppUsers');
    await expect(page.locator('text=e2etestuser')).toHaveCount(0);
  });
});
