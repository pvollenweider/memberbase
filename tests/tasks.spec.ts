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

test.describe.serial('Tasks — auto-generation (#149)', () => {
  // User 4 (seed): paid 2025 cotisation, not 2026, only in segment 1 (Membre 2025).
  // Assign to segment 2 (Membre 2026, = membre_segment setting) so they match
  // FILTER_UNPAID_COTI_CURRENT for 2026 — a real "unpaid this year" candidate.
  const LAPSED_USER_ID = 4;

  test('generate button creates a reminder task for an unpaid member', async ({ page }) => {
    await page.goto(`/index.php?view=generalData&userid=${LAPSED_USER_ID}`);
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    await page.request.post('/index.php', {
      form: { action: 'assignSegment', id: String(LAPSED_USER_ID), segmentId: '2', csrf },
    });

    await page.goto('/index.php?view=tasks');
    const generateForm = page.locator('form', { has: page.locator('button', { hasText: 'Générer les tâches de relance cotisation' }) });
    await expect(generateForm).toBeVisible();
    await generateForm.locator('button[type="submit"]').click();
    await expect(page.locator('.alert-success')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('tr', { hasText: 'Relance cotisation' })).toBeVisible();
  });

  test('re-running generation does not duplicate the task (dedup via rule_key)', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const rowCountBefore = await page.locator('tr', { hasText: 'Relance cotisation' }).count();
    const generateForm = page.locator('form', { has: page.locator('button', { hasText: 'Générer les tâches de relance cotisation' }) });
    await generateForm.locator('button[type="submit"]').click();
    await expect(page.locator('.alert-success')).toBeVisible({ timeout: 10_000 });
    const rowCountAfter = await page.locator('tr', { hasText: 'Relance cotisation' }).count();
    expect(rowCountAfter).toBe(rowCountBefore);
  });

  test('"Envoyer le rappel" button on the task opens the same email preview modal', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const sendBtn = page.locator('.js-task-send-coti').first();
    await expect(sendBtn).toBeVisible();

    // Same pattern as tests/cotisation-reminders.spec.ts: opening the modal
    // fires a preview-only POST — cancel must not trigger an actual send.
    let sendRequestFired = false;
    const onRequest = (req: any) => {
      if (req.url().includes('index.php') && req.method() === 'POST'
          && (req.postData() ?? '').includes('action=sendCotisationReminderOne')) {
        sendRequestFired = true;
      }
    };
    page.on('request', onRequest);

    await sendBtn.click();
    const modal = page.locator('#taskCotiPreviewModal');
    await expect(modal).toBeVisible();
    await expect(page.locator('#task-coti-modal-frame')).toBeVisible({ timeout: 10_000 });

    await modal.locator('button[data-bs-dismiss="modal"].btn-outline-secondary').click();
    await expect(modal).toBeHidden();
    await page.waitForTimeout(300);
    page.off('request', onRequest);

    expect(sendRequestFired).toBe(false);
    // Task still open — cancelling the preview must not close it.
    await expect(page.locator('tr', { hasText: 'Relance cotisation' })).toBeVisible();
  });

  test('regenerating auto-closes the task once the member pays another way', async ({ page }) => {
    // Record a 2026 cotisation for the lapsed member directly (simulates a
    // payment entered through the normal compta flow, not via the reminder).
    await page.goto(`/index.php?view=compta&userid=${LAPSED_USER_ID}`);
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    const addResp = await page.request.post('/index.php', {
      form: {
        action: 'addCompta', view: 'compta', userid: String(LAPSED_USER_ID),
        type_id: '1', date: '01/06/2026', libele: 'Cotisation E2E', sum: '50', csrf,
      },
    });
    expect(addResp.status()).toBe(200);

    await page.goto('/index.php?view=tasks');
    const generateForm = page.locator('form', { has: page.locator('button', { hasText: 'Générer les tâches de relance cotisation' }) });
    await generateForm.locator('button[type="submit"]').click();
    await expect(page.locator('.alert-success')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('.alert-success')).toContainText('résolue');
    await expect(page.locator('tr', { hasText: 'Relance cotisation' })).not.toBeVisible();
  });
});
