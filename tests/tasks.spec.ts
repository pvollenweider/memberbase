/**
 * E2E tests — tasks (suivi_task) CRUD, global view, overdue badge (#117)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe.serial('Tasks', () => {
  const USER_ID = 1;

  test('view tasks tab for a member', async ({ page }) => {
    await page.goto(`/index.php?view=memberTasks&userid=${USER_ID}`);
    await expect(page.locator('form[name="addTask"]')).toBeVisible();
  });

  test('add a task with a past due date', async ({ page }) => {
    await page.goto(`/index.php?view=memberTasks&userid=${USER_ID}`);
    const form = page.locator('form[name="addTask"]');
    await form.locator('input[name="title"]').fill('Task E2E overdue');
    // The due_date field starts empty, and the datetimepicker widget
    // auto-fills it with today's date on focus (useCurrent) — racing with
    // fill(), which can leave the two values concatenated. Focus first, then
    // select-all + type to reliably replace whatever the widget just inserted.
    const dueField = form.locator('input[name="due_date"]');
    await dueField.click();
    await dueField.press('Control+A');
    await dueField.pressSequentially('01/01/2020');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('text=Task E2E overdue')).toBeVisible({ timeout: 10_000 });
  });

  test('overdue task shows red badge on member fiche tab', async ({ page }) => {
    await page.goto(`/index.php?view=generalData&userid=${USER_ID}`);
    const tasksTab = page.locator('a', { hasText: 'Tâches' });
    await expect(tasksTab.locator('.badge.bg-danger')).toBeVisible({ timeout: 10_000 });
  });

  test('overdue task appears in global tasks view', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('text=Task E2E overdue')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('.text-danger', { hasText: '01.01.2020' })).toBeVisible();
  });

  test('edit a task', async ({ page }) => {
    await page.goto(`/index.php?view=memberTasks&userid=${USER_ID}`);
    const editLink = page.locator('a[href*="view=updateTask"]').first();
    await editLink.click();
    await expect(page.locator('form[name="updateTask"]')).toBeVisible();
    await page.fill('input[name="title"]', 'Task E2E edited');
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.click('button[type="submit"].btn-primary'),
    ]);
    await expect(page.locator('text=Task E2E edited').first()).toBeVisible({ timeout: 10_000 });
  });

  test('close a task removes it from open badge count', async ({ page }) => {
    await page.goto(`/index.php?view=memberTasks&userid=${USER_ID}`);
    const row = page.locator('tr', { hasText: 'Task E2E edited' });
    await row.locator('button[title="Marquer comme terminée"]').click();
    await expect(page.locator('tr', { hasText: 'Task E2E edited' }).locator('text=Terminée')).toBeVisible({ timeout: 10_000 });
  });

  test('closed task no longer appears in global open tasks view', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('text=Task E2E edited')).not.toBeVisible();
  });

  test('delete a task via confirmation page', async ({ page }) => {
    await page.goto(`/index.php?view=memberTasks&userid=${USER_ID}`);
    const row = page.locator('tr', { hasText: 'Task E2E edited' });
    const deleteLink = row.locator('a[href*="view=removeTask"]');
    const deleteHref = await deleteLink.getAttribute('href');
    if (!deleteHref) throw new Error('Delete link href not found');
    await page.goto(deleteHref.startsWith('/') ? deleteHref : '/' + deleteHref);

    await expect(page.locator('button.btn-danger')).toBeVisible({ timeout: 10_000 });
    await Promise.all([
      page.waitForNavigation({ timeout: 10_000 }),
      page.locator('button.btn-danger').click(),
    ]);
    await expect(page.locator('text=Task E2E edited')).not.toBeVisible();
  });

  test('add a global task (no member) from the global tasks view', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const form = page.locator('form[name="addTask"]');
    await expect(form).toBeVisible();
    await form.locator('input[name="title"]').fill('Task E2E global');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('text=Task E2E global')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('tr', { hasText: 'Task E2E global' }).locator('text=Tâche générale')).toBeVisible();
  });
});
