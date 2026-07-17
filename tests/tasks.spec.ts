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

  test('tasks tab is hidden from the member fiche nav for now', async ({ page }) => {
    await page.goto(`/index.php?view=generalData&userid=${USER_ID}`);
    await expect(page.locator('.nav-tabs a', { hasText: 'Tâches' })).toHaveCount(0);
    // Direct navigation to the route still works — only the nav entry is hidden.
    await page.goto(`/index.php?view=memberTasks&userid=${USER_ID}`);
    await expect(page.locator('text=Task E2E overdue')).toBeVisible({ timeout: 10_000 });
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

  test('closed task no longer appears in the open tasks table, only in the completed section', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('#tasks-table tr', { hasText: 'Task E2E edited' })).toHaveCount(0);
    await expect(page.locator('td.text-decoration-line-through', { hasText: 'Task E2E edited' })).toBeVisible();
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
    // The "Générer" button now hides itself once nothing is left to generate
    // (admin-only, conditional visibility) — post the action directly to
    // verify the underlying dedup logic instead of clicking a button that's
    // legitimately gone by this point.
    await page.goto('/index.php?view=tasks');
    const rowCountBefore = await page.locator('tr', { hasText: 'Relance cotisation' }).count();
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    await page.request.post('/index.php', { form: { action: 'generateUnpaidCotiTasks', csrf } });
    await page.goto('/index.php?view=tasks');
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

    // Button may already be hidden at this point (nothing new pending once
    // the lapsed member just paid) — post the action directly, same as the
    // dedup test above.
    await page.request.post('/index.php', { form: { action: 'generateUnpaidCotiTasks', csrf } });
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('#tasks-table tr', { hasText: 'Relance cotisation' })).toHaveCount(0);
  });
});

test.describe.serial('Tasks — payment notification auto-generation', () => {
  test('manager (non-admin) does not see the generate buttons, even with pending members', async ({ browser }) => {
    const ctx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/manager.json') });
    const page = await ctx.newPage();
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('button', { hasText: 'Générer les tâches de relance cotisation' })).toHaveCount(0);
    await expect(page.locator('button', { hasText: 'notification de versement' })).toHaveCount(0);
    await ctx.close();
  });

  test('generate button creates one task per member with unnotified compta entries', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const generateForm = page.locator('form', { has: page.locator('button', { hasText: 'notification de versement' }) });
    await expect(generateForm).toBeVisible();
    await generateForm.locator('button[type="submit"]').click();
    await expect(page.locator('.alert-success')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('tr', { hasText: 'Notification de versement' }).first()).toBeVisible();
  });

  test('re-running generation does not duplicate the task (dedup via rule_key)', async ({ page }) => {
    // Same reasoning as the cotisation dedup test above: the button is gone
    // once nothing is left to generate, so post the action directly.
    await page.goto('/index.php?view=tasks');
    const rowCountBefore = await page.locator('tr', { hasText: 'Notification de versement' }).count();
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    await page.request.post('/index.php', { form: { action: 'generateComptaRecapTasks', csrf } });
    await page.goto('/index.php?view=tasks');
    const rowCountAfter = await page.locator('tr', { hasText: 'Notification de versement' }).count();
    expect(rowCountAfter).toBe(rowCountBefore);
  });

  test('generate buttons are hidden once nothing is left to generate', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('button', { hasText: 'Générer les tâches de relance cotisation' })).toHaveCount(0);
    await expect(page.locator('button', { hasText: 'notification de versement' })).toHaveCount(0);
  });

  test('"Envoyer la notification" opens a preview and closes the task on send', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const rowsBefore = await page.locator('.js-task-send-recap').count();
    expect(rowsBefore).toBeGreaterThan(0);

    await page.locator('.js-task-send-recap').first().click();
    await expect(page.locator('#taskRecapPreviewModal')).toBeVisible();
    await expect(page.locator('#task-recap-modal-subject')).not.toHaveText('');

    await page.locator('#btn-task-recap-send').click();
    await page.waitForTimeout(1000);
    const rowsAfter = await page.locator('.js-task-send-recap').count();
    expect(rowsAfter).toBe(rowsBefore - 1);
  });
});

test.describe.serial('Tasks — paused state', () => {
  test('pausing a global task hides it from the open table and the nav badge', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const form = page.locator('form[name="addTask"]');
    await form.locator('input[name="title"]').fill('Task E2E pause test');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('text=Task E2E pause test')).toBeVisible({ timeout: 15000 });

    await page.goto('/index.php?view=tasks');
    const badgeLocator = page.locator('#ca-sidebar-col a[href*="view=tasks"] .badge');
    const badgeCountBefore = (await badgeLocator.count()) > 0 ? Number(await badgeLocator.innerText()) : 0;

    const row = page.locator('#tasks-table tbody tr', { hasText: 'Task E2E pause test' });
    await row.locator('button[title="Mettre en pause"]').click();
    await page.waitForTimeout(400);
    await page.goto('/index.php?view=tasks');

    await expect(page.locator('#tasks-table tr', { hasText: 'Task E2E pause test' })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: /tâche\(s\) en pause/ })).toBeVisible();
    await expect(page.locator('#paused-tasks-table td', { hasText: 'Task E2E pause test' })).toBeVisible();

    // Badge disappears entirely once no open (non-paused) task is left,
    // rather than showing "0" — read it defensively either way.
    const badgeCountAfter = (await badgeLocator.count()) > 0 ? Number(await badgeLocator.innerText()) : 0;
    expect(badgeCountAfter).toBe(badgeCountBefore - 1);
  });

  test('resuming a paused task brings it back to the open table', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const row = page.locator('#paused-tasks-table tbody tr', { hasText: 'Task E2E pause test' });
    await row.locator('button[title="Reprendre"]').click();
    await page.waitForTimeout(400);
    await page.goto('/index.php?view=tasks');

    await expect(page.locator('#tasks-table tr', { hasText: 'Task E2E pause test' })).toBeVisible();
  });

  test('a paused reminder task does not show its send button, but still blocks a duplicate from being generated', async ({ page, browser }) => {
    // Fresh member, untouched by the earlier auto-generation describe block
    // (which already pays off LAPSED_USER_ID=4). Needs an email (required
    // for the send-reminder button) and no 2026 cotisation entry.
    const adminCtx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/admin.json') });
    const adminPage = await adminCtx.newPage();
    await adminPage.goto('/index.php?view=list');
    const csrf = await adminPage.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    await adminPage.request.post('/index.php', {
      form: { action: 'addUser', firstName: 'Pause', lastName: 'TestUser', email: 'pause-test@example.com', csrf },
    });
    const apiResp = await adminPage.request.get('/api/contacts?search=pause-test%40example.com');
    const apiJson = await apiResp.json();
    const NEW_USER_ID = apiJson.data[0].id;

    await adminPage.request.post('/index.php', { form: { action: 'assignSegment', id: String(NEW_USER_ID), segmentId: '2', csrf } });
    await adminPage.request.post('/index.php', { form: { action: 'generateUnpaidCotiTasks', csrf } });
    await adminCtx.close();

    await page.goto(`/index.php?view=memberTasks&userid=${NEW_USER_ID}`);
    const row = page.locator('tr', { hasText: 'Relance cotisation' });
    await expect(row.locator('.js-task-send-coti')).toBeVisible();
    await row.locator('button[title="Mettre en pause"]').click();
    await page.waitForTimeout(400);

    const pausedRow = page.locator('tr', { hasText: 'Relance cotisation' });
    await expect(pausedRow.locator('.js-task-send-coti')).toHaveCount(0);
    await expect(pausedRow.locator('button[title="Reprendre"]')).toBeVisible();

    // Regenerating must not create a duplicate while the task is paused,
    // the dedup query only checks done_at, paused_at is irrelevant to it.
    const csrf2 = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    await page.request.post('/index.php', { form: { action: 'generateUnpaidCotiTasks', csrf: csrf2 } });

    await page.goto(`/index.php?view=memberTasks&userid=${NEW_USER_ID}`);
    await expect(page.locator('tr', { hasText: 'Relance cotisation' })).toHaveCount(1);
  });
});

test.describe.serial('Tasks — closing a payment-notification task without sending', () => {
  test('closing via the checkmark (no email on file) still marks compta entries as notified', async ({ page }) => {
    // User 5 (Lapsed Dave, seed): has no email on file -- the send-recap
    // button never appears for them, only the generic close checkmark.
    // The earlier "payment notification auto-generation" describe block
    // (above) already runs a global generate + send-first-task cycle across
    // the whole suite, which can consume user 5's original seeded unnotified
    // entry before this block ever runs (order-dependent across the file) —
    // create a fresh one here so this test doesn't depend on what's left
    // over from that fixture.
    await page.goto(`/index.php?view=generalData&userid=5`);
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    // generateComptaRecapTasks defaults to the CURRENT year (date('Y'))
    // when no ?year= is posted — the entry must be dated within it.
    const currentYear = new Date().getFullYear();
    await page.request.post('/api/compta', {
      data: { memberId: 5, typeId: 1, date: `${currentYear}-04-01`, amount: 42 },
    });

    await page.goto('/index.php?view=tasks');
    await page.request.post('/index.php', { form: { action: 'generateComptaRecapTasks', csrf } });

    await page.goto('/index.php?view=memberTasks&userid=5');
    const row = page.locator('tr', { hasText: 'Notification de versement' });
    await expect(row).toBeVisible();
    await expect(row.locator('.js-task-send-recap')).toHaveCount(0);
    const closeBtn = row.locator('button[title*="notifié"]');
    await expect(closeBtn).toBeVisible();
    await closeBtn.click();
    await page.waitForTimeout(400);

    // Regenerating must not recreate an open task for user 5: the underlying
    // compta entries are now notified, so they no longer match the
    // generator's "pending" criteria.
    await page.request.post('/index.php', { form: { action: 'generateComptaRecapTasks', csrf } });
    await page.goto('/index.php?view=memberTasks&userid=5');
    await expect(page.locator('tr', { hasText: 'Notification de versement' }).filter({ hasText: 'Ouverte' })).toHaveCount(0);
  });
});

test.describe.serial('Tasks — duplicate contacts and hidden segments', () => {
  test('generates a task for a genuine full-name duplicate, dedups on re-run', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    // Seed has no duplicate names -- create one. Post generation directly
    // (not via the UI button) so this test doesn't depend on whether some
    // other pending group already made the button visible/hidden by this
    // point in the file.
    await page.request.post('/index.php', {
      form: { action: 'addUser', firstName: 'Alice', lastName: 'Dupont', email: 'alice2@example.com', csrf },
    });
    await page.request.post('/index.php', { form: { action: 'generateDuplicateTasks', csrf } });

    await page.goto('/index.php?view=tasks');
    await expect(page.locator('td', { hasText: 'Doublon potentiel : Alice Dupont' })).toBeVisible();

    const rowCountBefore = await page.locator('tr', { hasText: 'Doublon potentiel' }).count();
    await page.request.post('/index.php', { form: { action: 'generateDuplicateTasks', csrf } });
    await page.goto('/index.php?view=tasks');
    const rowCountAfter = await page.locator('tr', { hasText: 'Doublon potentiel' }).count();
    expect(rowCountAfter).toBe(rowCountBefore);
  });

  test('generates a task for a hidden segment still holding active members', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    // Segment 1 ("Membre 2025") has active members (1, 2, 4) in the seed --
    // hide it to trigger the check.
    await page.request.post('/index.php', { form: { action: 'updateSegment', id: '1', name: 'Membre 2025', hidden: '1', csrf } });
    await page.request.post('/index.php', { form: { action: 'generateHiddenSegmentTasks', csrf } });

    await page.goto('/index.php?view=tasks');
    await expect(page.locator('td', { hasText: 'Segment masqué encore assigné' })).toBeVisible();
  });

  test('fixing the duplicate closes the task on the next generation run (what cron does)', async ({ page }) => {
    await page.goto('/index.php?view=tasks');
    const csrf = await page.evaluate(() => {
      const m = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      return m?.content ?? '';
    });
    // Delete the duplicate created in the previous test -- same effect as
    // fixing it via Réglages → Intégrité (merge or delete), without needing
    // to click "Marquer comme terminée" on the task at all.
    const apiResp = await page.request.get('/api/contacts?search=alice2%40example.com');
    const { data } = await apiResp.json();
    await page.request.post('/index.php', {
      form: { action: 'deleteOrDeactivateUser', id: String(data[0].id), dispose: 'delete', csrf },
    });

    // Re-running generation (what the cron does every 5 min) must close the
    // now-obsolete task on its own, no manual close needed.
    await page.request.post('/index.php', { form: { action: 'generateDuplicateTasks', csrf } });
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('#tasks-table td', { hasText: 'Doublon potentiel : Alice Dupont' })).toHaveCount(0);
  });
});
