/**
 * E2E tests — cotisation reminder emails and lapsed-members view
 *
 * Requires Mailpit running on port 8025 (http://localhost:8025).
 * The seed configures SMTP → mailpit:1025 so all emails are captured.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, APIRequestContext } from '@playwright/test';

const MAILPIT_URL = 'http://localhost:8025';
const YEAR        = 2026; // lapsed members paid 2025, not 2026 (see seed)

// ─── helpers ────────────────────────────────────────────────────────────────

/** Open the send-reminder modal and wait for Bootstrap's shown.bs.modal event (fires after animation). */
async function openReminderModal(page: any): Promise<void> {
  // Register the shown.bs.modal listener BEFORE triggering the modal so we don't miss it.
  const shown = page.evaluate(() =>
    new Promise<void>(resolve => {
      document.getElementById('modal-send-coti-reminders')
        ?.addEventListener('shown.bs.modal', () => resolve(), { once: true });
    })
  );
  await page.click('[data-bs-target="#modal-send-coti-reminders"]');
  await shown; // waits until Bootstrap finishes the fade-in animation
}

async function csrfToken(api: APIRequestContext): Promise<string> {
  const html = await (await api.get('/index.php')).text();
  const m    = html.match(/name="csrf-token" content="([^"]+)"/);
  if (!m) throw new Error('CSRF token not found in page');
  return m[1];
}

/** Delete all messages in Mailpit so each test starts clean. */
async function purgeMailpit(request: APIRequestContext): Promise<void> {
  await request.delete(`${MAILPIT_URL}/api/v1/messages`);
}

/** Return all messages currently in Mailpit. */
async function mailpitMessages(request: APIRequestContext): Promise<any[]> {
  const resp = await request.get(`${MAILPIT_URL}/api/v1/messages`);
  const body = await resp.json();
  return body.messages ?? [];
}

/** Return the plain-text body of a Mailpit message by ID. */
async function mailpitBody(request: APIRequestContext, id: string): Promise<string> {
  const resp = await request.get(`${MAILPIT_URL}/api/v1/message/${id}`);
  const body = await resp.json();
  return body.Text ?? '';
}

// ─── lapsed-members view ────────────────────────────────────────────────────

test.describe('Lapsed members view', () => {
  test('page loads and shows lapsed count for 2026', async ({ page }) => {
    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);
    await page.waitForLoadState('load');

    // Two lapsed members: Carol (id=4, has email) and Dave (id=5, no email)
    await expect(page.locator('[role="status"].alert-warning')).toContainText('2 membre');
  });

  test('send button is present for manager (inside confirmation modal)', async ({ page }) => {
    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);
    // The button lives inside the confirmation modal; check it exists in the DOM
    await expect(page.locator('#btn-send-coti-reminders')).toBeAttached();
    // The modal trigger button must be visible
    await expect(page.locator('[data-bs-target="#modal-send-coti-reminders"]')).toBeVisible();
  });

  test('per-row send button asks for confirmation before sending', async ({ page }) => {
    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);
    await page.waitForLoadState('load');

    const sendOneBtn = page.locator('.js-send-one').first();
    await expect(sendOneBtn).toBeVisible();

    let dialogSeen = false;
    page.once('dialog', async (dialog) => {
      dialogSeen = true;
      expect(dialog.type()).toBe('confirm');
      await dialog.dismiss();
    });

    // No request should be sent when the confirmation is dismissed.
    let requestFired = false;
    const onRequest = (req: any) => {
      if (req.url().includes('index.php') && req.method() === 'POST') requestFired = true;
    };
    page.on('request', onRequest);
    await sendOneBtn.click();
    await page.waitForTimeout(300);
    page.off('request', onRequest);

    expect(dialogSeen).toBe(true);
    expect(requestFired).toBe(false);
    await expect(sendOneBtn).toBeEnabled();
  });

  test('year selector changes the list', async ({ page }) => {
    // No lapsed members for 2025 (nobody paid 2024 in our seed)
    await page.goto(`/index.php?view=lapsedMembers&year=2025`);
    await page.waitForLoadState('load');
    await expect(page.locator('[role="status"].alert-warning')).toContainText('0 membre');
  });
});

// ─── send cotisation reminders action ───────────────────────────────────────

test.describe('Send cotisation reminders', () => {
  test.describe.configure({ mode: 'serial' });

  test('sends emails to lapsed members with address, skips those without', async ({ page, request }) => {
    await purgeMailpit(request);

    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);
    await openReminderModal(page);

    // Wait for the POST response at the same time as clicking so we don't miss it
    const [resp] = await Promise.all([
      page.waitForResponse(
        (r: any) => r.url().includes('index.php') && r.request().method() === 'POST',
        { timeout: 15_000 }
      ),
      page.locator('#btn-send-coti-reminders').click({ force: true }),
    ]);
    const body = await resp.text();
    expect(resp.status(), `HTTP ${resp.status()} body: ${body.substring(0, 1000)}`).toBe(200);
    expect(body, `body: ${body.substring(0, 500)}`).not.toContain('<!DOCTYPE');
    const json = JSON.parse(body);
    expect(json.ok, `server response: ${JSON.stringify(json)}`).toBe(true);

    // Modal should show a success alert
    await expect(page.locator('#coti-reminder-result .alert-success')).toBeVisible({ timeout: 5_000 });

    // Exactly 1 email delivered (Carol has email; Dave does not)
    const msgs = await mailpitMessages(request);
    expect(msgs).toHaveLength(1);
    expect(msgs[0].To[0].Address).toBe('carol@example.com');
  });

  test('email subject is "Rappel de cotisation"', async ({ request }) => {
    // Serial mode: Mailpit still has Carol's email from the previous test.
    const msgs = await mailpitMessages(request);
    expect(msgs).toHaveLength(1);
    expect(msgs[0].Subject).toBe('Rappel de cotisation');
  });

  test('email body contains year, membership URL and org name', async ({ request }) => {
    // Serial mode: reuse email from the first send test.
    const msgs = await mailpitMessages(request);
    expect(msgs).toHaveLength(1);
    const body = await mailpitBody(request, msgs[0].ID);

    expect(body).toContain(String(YEAR));
    expect(body).toContain('MemberBase Test');
    expect(body).toContain('www.memberbase.test/devenir-membre');
    expect(body).toContain('Sauf erreur');
  });

  test('email is logged in email_log for the member', async ({ page }) => {
    // Serial mode: the first send test already logged this email. Just check suivi.
    await page.goto('/index.php?view=suivi&userid=4');
    await page.waitForLoadState('load');
    await expect(page.locator('text=Rappel de cotisation').first()).toBeVisible();
  });

  test('CSRF guard rejects unauthenticated POST', async ({ request }) => {
    const resp = await request.post('/index.php', {
      form: { action: 'sendCotisationReminders', year: String(YEAR) },
    });
    // No CSRF token → 403
    expect(resp.status()).toBe(403);
  });
});

// ─── membership_url setting ─────────────────────────────────────────────────

test.describe('Settings — membership URL', () => {
  test('membership URL field is present in settings', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_membership_url')).toBeVisible();
    // Pre-filled from seed
    await expect(page.locator('#s_membership_url')).toHaveValue('https://www.memberbase.test/devenir-membre');
  });

  test('saving a new URL persists it', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=settings');
    await page.fill('#s_membership_url', 'https://example.org/join');

    // The form uses hx-post; wait for the htmx response marker before navigating away
    await Promise.all([
      page.waitForSelector('#settings-save-msg #casa-save-ok', { state: 'attached', timeout: 10_000 }),
      page.locator('button[type="submit"].btn-primary').first().click(),
    ]);

    // Navigate fresh to the settings tab to verify persistence
    await page.goto('/index.php?view=settings&tab=settings');
    await expect(page.locator('#s_membership_url')).toHaveValue('https://example.org/join');
  });
});

// ─── email template ──────────────────────────────────────────────────────────

test.describe('Email template — cotisation reminder', () => {
  test('template is editable in settings', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=email');
    await page.waitForLoadState('load');

    // Templates are rendered as stacked cards (no dropdown) — find the cotisation reminder card
    // by its hidden tpl_key input, then assert the visible subject field in the same card.
    const card = page.locator('.card').filter({
      has: page.locator('input[name="tpl_key"][value="tpl_cotisation_reminder"]'),
    });
    await expect(card).toBeVisible();
    await expect(card.locator('input[name="tpl_subject"]')).toHaveValue('Rappel de cotisation');
    await expect(card.locator('textarea[name="tpl_body"]')).toBeVisible();
  });
});
