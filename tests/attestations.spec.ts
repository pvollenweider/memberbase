/**
 * E2E tests — attestation bulk workflows (résumé dons "Attestations AAAA" menu)
 *
 * Individual send/download and the off-season gate are covered by
 * preview-send-modal.spec.ts and roles.spec.ts's download role-guard block.
 * This file covers what those don't: bulk PDF download, bulk email send
 * (including the already-sent/force-resend list), BCC on the bulk send, and
 * regenerating a previously-sent attestation from the email log.
 *
 * Requires Mailpit running on port 8025 (http://localhost:8025).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, APIRequestContext } from '@playwright/test';
import { execFileSync } from 'child_process';

const MAILPIT_URL = 'http://localhost:8025';
const YEAR = new Date().getFullYear();

/**
 * The email log tab (Réglages → Email → Journal) has no row-click-through to
 * email_detail.php for attestation sends — only compta-recap/reminder rows
 * reach it, via a suivi-note link that attestation sends don't create. The
 * regenerate button in email_detail.php is real (verified in the view source),
 * it's just not linked to from anywhere for this template today. A direct
 * read-only lookup is the only way to get a valid emailid without adding a
 * navigation path that doesn't exist yet.
 */
function latestAttestationEmailLogId(): number {
  const out = execFileSync(
    'docker',
    [
      'compose', 'exec', '-T', 'mariadb',
      'mariadb', '-u', 'root', '-proot', 'members_test', '-N', '-e',
      "SELECT id FROM email_log WHERE subject LIKE '%Attestation%' ORDER BY id DESC LIMIT 1",
    ],
    { cwd: __dirname + '/..' }
  ).toString().trim();
  const id = parseInt(out, 10);
  if (!id) throw new Error(`No attestation email_log row found (raw: ${out})`);
  return id;
}

async function purgeMailpit(request: APIRequestContext): Promise<void> {
  await request.delete(`${MAILPIT_URL}/api/v1/messages`);
}

async function mailpitMessages(request: APIRequestContext): Promise<any[]> {
  const resp = await request.get(`${MAILPIT_URL}/api/v1/messages`);
  const body = await resp.json();
  return body.messages ?? [];
}

async function csrfFor(page: any): Promise<string> {
  return page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
}

test.describe('Bulk PDF download (attestation_bulk.php)', () => {
  test('downloads a single merged PDF for all qualifying donors', async ({ page }) => {
    await page.goto(`/index.php?view=resume&minSum=1`);
    const resp = await page.request.get(`/attestation_bulk.php?year=${YEAR}&minSum=1`);
    expect(resp.status()).toBe(200);
    expect(resp.headers()['content-type']).toContain('application/pdf');
  });

  test('stamp=1 also succeeds (tampon/signature variant)', async ({ page }) => {
    await page.goto(`/index.php?view=resume&minSum=1`);
    const resp = await page.request.get(`/attestation_bulk.php?year=${YEAR}&minSum=1&stamp=1`);
    expect(resp.status()).toBe(200);
    expect(resp.headers()['content-type']).toContain('application/pdf');
  });
});

test.describe.serial('Bulk email send — list, force-resend, BCC', () => {
  test('previewAttestationsBulkList lists qualifying donors for the year', async ({ page }) => {
    await page.goto(`/index.php?view=resume&minSum=1`);
    const csrf = await csrfFor(page);
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'previewAttestationsBulkList', year: String(YEAR), minSum: '1' },
    });
    expect(resp.status()).toBe(200);
    const json = await resp.json();
    expect(json.ok).toBe(true);
    expect(json.donors.length).toBeGreaterThanOrEqual(2); // Alice (id 1) and Bob (id 2) both want attestations
    expect(json.donors.every((d: any) => d.alreadySent === null)).toBe(true);
  });

  test('sendAttestationsBulk sends to every qualifying donor with an email', async ({ page, request }) => {
    await purgeMailpit(request);
    await page.goto(`/index.php?view=resume&minSum=1`);
    const csrf = await csrfFor(page);
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'sendAttestationsBulk', year: String(YEAR), minSum: '1' },
    });
    expect(resp.status()).toBe(200);
    const json = await resp.json();
    expect(json.ok).toBe(true);
    expect(json.sent).toBeGreaterThanOrEqual(2);

    const msgs = await mailpitMessages(request);
    expect(msgs.length).toBe(json.sent);
    expect(msgs.some((m) => m.To[0].Address === 'alice@example.com')).toBe(true);
  });

  test('re-running without force_ids skips already-sent donors', async ({ page, request }) => {
    await purgeMailpit(request);
    await page.goto(`/index.php?view=resume&minSum=1`);
    const csrf = await csrfFor(page);

    const listResp = await page.request.post('/index.php', {
      form: { csrf, action: 'previewAttestationsBulkList', year: String(YEAR), minSum: '1' },
    });
    const list = await listResp.json();
    // Dave (no email) is never actually sent to, so his alreadySent stays
    // null forever — only assert on donors that do have an address.
    const withEmail = list.donors.filter((d: any) => d.email);
    expect(withEmail.length).toBeGreaterThanOrEqual(3);
    expect(withEmail.every((d: any) => d.alreadySent !== null)).toBe(true);

    const sendResp = await page.request.post('/index.php', {
      form: { csrf, action: 'sendAttestationsBulk', year: String(YEAR), minSum: '1' },
    });
    const sendJson = await sendResp.json();
    expect(sendJson.sent).toBe(0);
    expect(sendJson.already).toBeGreaterThanOrEqual(2);

    const msgs = await mailpitMessages(request);
    expect(msgs).toHaveLength(0);
  });

  test('force_ids resends to an explicitly selected already-sent donor', async ({ page, request }) => {
    await purgeMailpit(request);
    await page.goto(`/index.php?view=resume&minSum=1`);
    const csrf = await csrfFor(page);

    // Alice is member id 1 (see tests/fixtures/seed.sql).
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'sendAttestationsBulk', year: String(YEAR), minSum: '1', force_ids: '1' },
    });
    const json = await resp.json();
    expect(json.sent).toBe(1);

    const msgs = await mailpitMessages(request);
    expect(msgs).toHaveLength(1);
    expect(msgs[0].To[0].Address).toBe('alice@example.com');
  });

  test('bcc=1 also delivers a copy to the configured contact address', async ({ page, request }) => {
    // Configure a contact email so the BCC option is honored server-side.
    // saveSmtp only writes keys present in $strKeys when present in the
    // request, EXCEPT smtp_port which it always overwrites with a `?? 587`
    // default — pass the seeded host/port explicitly or the reply-to save
    // silently repoints SMTP away from Mailpit (587 instead of 1025).
    await page.goto('/index.php?view=settings&tab=email');
    const setupCsrf = await csrfFor(page);
    await page.request.post('/index.php', {
      form: {
        csrf: setupCsrf, action: 'saveSmtp', view: 'settings',
        smtp_reply_to: 'contact@example.com', smtp_host: 'mailpit', smtp_port: '1025',
      },
    });

    await purgeMailpit(request);
    await page.goto(`/index.php?view=resume&minSum=1`);
    const csrf = await csrfFor(page);
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'sendAttestationsBulk', year: String(YEAR), minSum: '1', force_ids: '1', bcc: '1' },
    });
    const json = await resp.json();
    expect(json.sent).toBe(1);

    const msgs = await mailpitMessages(request);
    // One message to Alice, plus a BCC copy to the contact address.
    expect(msgs.some((m) => m.To[0].Address === 'alice@example.com')).toBe(true);
    expect(msgs.some((m) => (m.Bcc ?? []).some((a: any) => a.Address === 'contact@example.com'))).toBe(true);

    // Restore: smtp_reply_to is a shared app_settings row — other spec files
    // (preview-send-modal.spec.ts, people-finance.spec.ts) assert the BCC
    // checkbox is ABSENT because the seed leaves it unconfigured.
    await page.request.post('/index.php', {
      form: { csrf, action: 'saveSmtp', view: 'settings', smtp_reply_to: '', smtp_host: 'mailpit', smtp_port: '1025' },
    });
  });
});

test.describe('Regenerate a previously sent attestation (email log)', () => {
  test('email_detail.php shows the regenerate button for a tpl_attestation_don entry', async ({ page, request }) => {
    // Send one attestation so an email_log row with tpl_attestation_don exists.
    await purgeMailpit(request);
    await page.goto(`/index.php?view=resume&minSum=1`);
    const csrf = await csrfFor(page);
    await page.request.post('/index.php', {
      form: { csrf, action: 'sendAttestationsBulk', year: String(YEAR), minSum: '1', force_ids: '1' },
    });

    const emailId = latestAttestationEmailLogId();
    await page.goto(`/index.php?view=emailDetail&emailid=${emailId}`);
    const regenLink = page.locator(`a[href*="attestation_don.php?emailid=${emailId}"]`);
    await expect(regenLink).toBeVisible({ timeout: 10_000 });
  });

  test('attestation_don.php?emailid= regenerates the PDF from the log entry', async ({ playwright }) => {
    const emailId = latestAttestationEmailLogId();
    const api = await playwright.request.newContext({
      baseURL: 'http://localhost:8080',
      storageState: 'tests/.auth/admin.json',
    });
    const resp = await api.get(`/attestation_don.php?emailid=${emailId}`);
    expect(resp.status()).toBe(200);
    expect(resp.headers()['content-type']).toContain('application/pdf');
    await api.dispose();
  });
});
