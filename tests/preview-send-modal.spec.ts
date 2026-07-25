/**
 * E2E tests — shared preview/send modal component (#152)
 *
 * Locks down behavior of the two views built on the shared component
 * (js/preview-send-modal.js + includes/partials/preview_send_modal.php):
 * compta_recap.php and donors_summary.php's per-row attestation send.
 *
 * Requires Mailpit running on port 8025 (http://localhost:8025).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, APIRequestContext } from '@playwright/test';

const MAILPIT_URL = 'http://localhost:8025';

async function purgeMailpit(request: APIRequestContext): Promise<void> {
  await request.delete(`${MAILPIT_URL}/api/v1/messages`);
}

async function mailpitMessages(request: APIRequestContext): Promise<any[]> {
  const resp = await request.get(`${MAILPIT_URL}/api/v1/messages`);
  const body = await resp.json();
  return body.messages ?? [];
}

test.describe.serial('Compta recap — row preview/send modal', () => {
  test('clicking a row opens the modal and loads a preview', async ({ page, request }) => {
    await purgeMailpit(request);
    // Other spec files (tasks.spec.ts's "send first pending recap task",
    // cotisation-reminders.spec.ts, people-finance.spec.ts's bulk-send)
    // compete for the same seeded "Dupont has an unnotified compta entry"
    // fixture across the full suite — whichever runs first in the actual
    // worker-interleaved order consumes it. Create a fresh one here so this
    // test doesn't depend on what's left over from the seed.
    await page.request.post('/api/compta', {
      data: { memberId: 1, typeId: 1, date: `${new Date().getFullYear()}-05-01`, amount: 55 },
    });
    await page.goto('/index.php?view=comptaRecap');
    await page.waitForLoadState('load');

    const row = page.locator('.recap-row').filter({ hasText: 'Dupont' }).first();
    await expect(row).toBeVisible();
    await row.click();

    await expect(page.locator('#recap-modal')).toBeVisible();
    await expect(page.locator('#recap-modal-frame')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#btn-recap-send')).toBeEnabled();
  });

  test('sending marks entries as notified and delivers the email', async ({ page, request }) => {
    await page.goto('/index.php?view=comptaRecap');
    await page.waitForLoadState('load');

    const row = page.locator('.recap-row').filter({ hasText: 'Dupont' }).first();
    await row.click();
    await expect(page.locator('#recap-modal-frame')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#btn-recap-send')).toBeEnabled();

    await Promise.all([
      page.waitForResponse((r) => r.url().includes('index.php') && r.request().method() === 'POST'
        && (r.request().postData() ?? '').includes('sendComptaRecapOne')),
      page.locator('#btn-recap-send').click(),
    ]);
    await page.waitForLoadState('load'); // page reloads on success

    // Row no longer pending (entries marked notified_at)
    await expect(page.locator('.recap-row').filter({ hasText: 'Dupont' })).toHaveCount(0);

    const msgs = await mailpitMessages(request);
    expect(msgs.length).toBeGreaterThanOrEqual(1);
    expect(msgs.some((m) => m.To[0].Address === 'alice@example.com')).toBe(true);
  });
});

test.describe('Compta recap — attestation fiscal note threshold (CHF 300)', () => {
  const year = new Date().getFullYear();

  test('below CHF 300: mentions the threshold, the year, and the on-request email', async ({ page }) => {
    const createResp = await page.request.post('/api/contacts', {
      data: { firstName: 'ThresholdLow', lastName: 'RecapE2E', email: 'threshold.low.recap.e2e@example.com' },
    });
    const { data: contact } = await createResp.json();
    await page.request.post('/api/compta', {
      data: { memberId: contact.id, typeId: 1, date: `${year}-05-02`, amount: 55 },
    });

    await page.goto('/index.php?view=comptaRecap');
    await page.waitForLoadState('load');
    const row = page.locator('.recap-row').filter({ hasText: 'RecapE2E' }).filter({ hasText: 'ThresholdLow' }).first();
    await row.click();
    const frame = page.frameLocator('#recap-modal-frame');
    await expect(frame.locator('body')).toContainText(
      `Pour un montant inférieur à CHF 300 pour l'année ${year}, une attestation peut être demandée`,
      { timeout: 10_000 }
    );
  });

  test('at/above CHF 300: no "montant inférieur" clause (already gets an automatic attestation)', async ({ page }) => {
    const createResp = await page.request.post('/api/contacts', {
      data: { firstName: 'ThresholdHigh', lastName: 'RecapE2E', email: 'threshold.high.recap.e2e@example.com' },
    });
    const { data: contact } = await createResp.json();
    await page.request.post('/api/compta', {
      data: { memberId: contact.id, typeId: 1, date: `${year}-05-03`, amount: 350 },
    });

    await page.goto('/index.php?view=comptaRecap');
    await page.waitForLoadState('load');
    const row = page.locator('.recap-row').filter({ hasText: 'RecapE2E' }).filter({ hasText: 'ThresholdHigh' }).first();
    await row.click();
    const frame = page.frameLocator('#recap-modal-frame');
    await expect(frame.locator('body')).toContainText('Attestation de dons', { timeout: 10_000 });
    await expect(frame.locator('body')).not.toContainText('Pour un montant inférieur');
  });
});

test.describe.serial('Donors summary — attestation row preview/send modal', () => {
  test('off-season gate disables send until confirmed', async ({ page, request }) => {
    await purgeMailpit(request);
    await page.goto('/index.php?view=resume&minSum=1');
    await page.waitForLoadState('load');

    const btn = page.locator('.js-preview-attest-row[data-name*="Martin"]').first();
    await expect(btn).toBeVisible();
    await btn.click();

    await expect(page.locator('#attest-row-modal')).toBeVisible();
    await expect(page.locator('#attest-row-modal-frame')).toBeVisible({ timeout: 10_000 });

    // BCC checkbox absent — seed has no smtp_reply_to configured
    await expect(page.locator('#attest-row-bcc')).toHaveCount(0);

    // Off-season gate present (test runs outside January) and blocks send
    const gate = page.locator('#attest-row-off-season-confirm');
    await expect(gate).toBeVisible();
    await expect(page.locator('#btn-attest-row-send')).toBeDisabled();

    await gate.check();
    await expect(page.locator('#btn-attest-row-send')).toBeEnabled();
  });

  test('sending swaps the row icon and delivers the email with a PDF attachment', async ({ page, request }) => {
    await page.goto('/index.php?view=resume&minSum=1');
    await page.waitForLoadState('load');

    const btn = page.locator('.js-preview-attest-row[data-name*="Martin"]').first();
    await btn.click();
    await expect(page.locator('#attest-row-modal-frame')).toBeVisible({ timeout: 10_000 });
    await page.locator('#attest-row-off-season-confirm').check();

    await Promise.all([
      page.waitForResponse((r) => r.url().includes('index.php') && r.request().method() === 'POST'
        && (r.request().postData() ?? '').includes('sendAttestationOne')),
      page.locator('#btn-attest-row-send').click(),
    ]);

    await expect(page.locator('#attest-row-modal')).toBeHidden({ timeout: 10_000 });
    await expect(btn.locator('i.fa-check')).toBeVisible({ timeout: 10_000 });

    const msgs = await mailpitMessages(request);
    expect(msgs.length).toBeGreaterThanOrEqual(1);
    const msg = msgs.find((m) => m.To[0].Address === 'bob@example.com');
    expect(msg).toBeTruthy();
    expect(msg.Attachments).toBeGreaterThan(0);
  });
});
