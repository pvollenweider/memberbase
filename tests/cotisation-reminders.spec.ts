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

/** Open the send-reminder modal and wait for it to finish animating. */
async function openReminderModal(page: any): Promise<void> {
  await page.click('[data-bs-target="#modal-send-coti-reminders"]');
  // Wait for Bootstrap fade animation to finish (modal gets class "show" after ~300 ms)
  await page.waitForFunction(
    () => document.getElementById('modal-send-coti-reminders')?.classList.contains('show')
  );
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

  test('year selector changes the list', async ({ page }) => {
    // No lapsed members for 2025 (nobody paid 2024 in our seed)
    await page.goto(`/index.php?view=lapsedMembers&year=2025`);
    await page.waitForLoadState('load');
    await expect(page.locator('[role="status"].alert-warning')).toContainText('0 membre');
  });
});

// ─── send cotisation reminders action ───────────────────────────────────────

test.describe('Send cotisation reminders', () => {
  test('sends emails to lapsed members with address, skips those without', async ({ page, request }) => {
    await purgeMailpit(request);

    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);

    // Open confirm modal (wait for Bootstrap fade) and click send
    await openReminderModal(page);
    await page.click('#btn-send-coti-reminders');

    // Modal should show a success alert
    await expect(page.locator('#coti-reminder-result .alert-success')).toBeVisible({ timeout: 15_000 });

    // Exactly 1 email delivered (Carol has email; Dave does not)
    const msgs = await mailpitMessages(request);
    expect(msgs).toHaveLength(1);
    expect(msgs[0].To[0].Address).toBe('carol@example.com');
  });

  test('email subject is "Rappel de cotisation"', async ({ page, request }) => {
    await purgeMailpit(request);

    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);
    await openReminderModal(page);
    await page.click('#btn-send-coti-reminders');
    await expect(page.locator('#coti-reminder-result .alert-success')).toBeVisible({ timeout: 15_000 });

    const msgs = await mailpitMessages(request);
    expect(msgs[0].Subject).toBe('Rappel de cotisation');
  });

  test('email body contains year, membership URL and org name', async ({ page, request }) => {
    await purgeMailpit(request);

    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);
    await openReminderModal(page);
    await page.click('#btn-send-coti-reminders');
    await expect(page.locator('#coti-reminder-result .alert-success')).toBeVisible({ timeout: 15_000 });

    const msgs = await mailpitMessages(request);
    const body  = await mailpitBody(request, msgs[0].ID);

    expect(body).toContain(String(YEAR));
    expect(body).toContain('MemberBase Test');
    expect(body).toContain('www.memberbase.test/devenir-membre');
    expect(body).toContain('sauf erreur');
  });

  test('email is logged in email_log for the member', async ({ page, request }) => {
    await purgeMailpit(request);

    await page.goto(`/index.php?view=lapsedMembers&year=${YEAR}`);
    await openReminderModal(page);
    await page.click('#btn-send-coti-reminders');
    await expect(page.locator('#coti-reminder-result .alert-success')).toBeVisible({ timeout: 15_000 });

    // Check the email appears in Carol's (user 4) suivi tab
    await page.goto('/index.php?view=suivi&userid=4');
    await page.waitForLoadState('load');
    await expect(page.locator('text=Rappel de cotisation')).toBeVisible();
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
    await page.goto('/index.php?view=settings&tab=general');
    await page.locator('#s_membership_url').scrollIntoViewIfNeeded();
    await expect(page.locator('#s_membership_url')).toBeVisible();
    // Pre-filled from seed
    await expect(page.locator('#s_membership_url')).toHaveValue('https://www.memberbase.test/devenir-membre');
  });

  test('saving a new URL persists it', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=general');
    await page.fill('#s_membership_url', 'https://example.org/join');
    await page.locator('button[type="submit"].btn-primary').first().click();
    await page.waitForTimeout(500);

    await page.reload();
    await expect(page.locator('#s_membership_url')).toHaveValue('https://example.org/join');
  });
});

// ─── email template ──────────────────────────────────────────────────────────

test.describe('Email template — cotisation reminder', () => {
  test('template is editable in settings', async ({ page }) => {
    await page.goto('/index.php?view=settings&tab=email');
    await page.waitForLoadState('load');

    // Select the cotisation reminder template
    await page.selectOption('select[name="tpl_key"]', 'tpl_cotisation_reminder');
    await expect(page.locator('textarea[name="tpl_body"]')).toBeVisible();
    await expect(page.locator('input[name="tpl_subject"]')).toHaveValue('Rappel de cotisation');
  });
});
