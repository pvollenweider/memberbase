/**
 * E2E tests — "Membres & finances" hub (#164)
 *
 * Server renders only the active tab's pane per request (id="pf-tab-<tab>",
 * class="pf-active-pane") — navigating between tabs is a real (htmx-boosted)
 * request, not a client-side show/hide of coexisting panes. The tab bar
 * itself (#pf-tab-*-btn) is a mobile-only substitute for the sidebar (hidden
 * ≥991.98px, see custom.css) — tests here run at desktop viewport, so those
 * links exist in the DOM (their "active" class is still assertable) but are
 * not visible/clickable; real navigation goes through page.goto() or the
 * sidebar, same as a desktop user would use.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('People/finance hub — Phase 1', () => {
  test('reachable via ?view=peopleFinance, Membres tab active by default with the member table', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await expect(page.locator('h1', { hasText: 'Membres & finances' })).toBeVisible();
    await expect(page.locator('#pf-tab-members-btn')).toHaveClass(/active/);
    await expect(page.locator('#pf-tab-members table.export')).toBeVisible();
    await expect(page.locator('#pf-tab-members')).toContainText('Dupont');
  });

  test('Relances cotisation tab shows the full compta recap content (manager)', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    await expect(page.locator('#pf-tab-recap-btn')).toHaveClass(/active/);
    // No duplicate title inside the embedded pane (suppressed via $_pfEmbedded)
    await expect(page.locator('#pf-tab-recap h1')).toHaveCount(0);
    await expect(page.locator('#pf-tab-recap #recap-extended-toggle')).toBeVisible();
    // BCC checkbox absent — seed has no smtp_reply_to configured
    await expect(page.locator('#pf-tab-recap #recap-bulk-bcc')).toHaveCount(0);
  });

  test('Relances tab: extended-mode toggle reveals already-notified members', async ({ page }) => {
    // Notify Alice's compta entry first so there's an "already sent" row to
    // reveal — same fixture as preview-send-modal.spec.ts / the bulk-send
    // test below, created fresh so this doesn't depend on suite ordering.
    await page.request.post('/api/compta', {
      data: { memberId: 1, typeId: 1, date: `${new Date().getFullYear()}-05-01`, amount: 42 },
    });
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    await page.request.post('/index.php', {
      form: { csrf, action: 'sendComptaRecap', view: 'peopleFinance', tab: 'recap', year: String(new Date().getFullYear()) },
    });

    await page.goto('/index.php?view=peopleFinance&tab=recap');
    await expect(page.locator('#pf-tab-recap #recap-extended-toggle')).not.toBeChecked();
    await expect(page.locator('#pf-tab-recap')).not.toContainText('Déjà notifiés');

    await Promise.all([
      page.waitForURL(/extended=1/, { timeout: 10_000 }),
      page.locator('#pf-tab-recap #recap-extended-toggle').click(),
    ]);
    await expect(page.locator('#pf-tab-recap')).toContainText('Dupont');
  });

  test('each tab renders independently without variable collisions', async ({ page }) => {
    // Regression guard for the closure-isolation fix: each tab's own content
    // (which independently defines similarly-named variables like $year,
    // $rows, $email...) must render correctly on its own request.
    await page.goto('/index.php?view=peopleFinance&tab=members');
    await expect(page.locator('#pf-tab-members table.export')).toBeAttached();
    await expect(page.locator('#pf-tab-members')).toContainText('Dupont');

    await page.goto('/index.php?view=peopleFinance&tab=recap');
    await expect(page.locator('#pf-tab-recap')).toBeVisible();

    await page.goto('/index.php?view=peopleFinance&tab=dons');
    await expect(page.locator('#pf-tab-dons table.resume-export')).toBeAttached();
  });

  test('Dons & attestations tab shows the contributor table, no duplicate KPI cards', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=dons');
    await expect(page.locator('#pf-tab-dons-btn')).toHaveClass(/active/);
    await expect(page.locator('#pf-tab-dons table.resume-export')).toBeVisible();
    // KPI cards (total contributions, donors, pie chart) live on the dashboard now (#153)
    await expect(page.locator('#pf-tab-dons .ca-resume-cards')).toHaveCount(0);
  });

  test('navigating between tabs via the sidebar updates the active pane and URL', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    // "Dons" lives in the sidebar's "Finances" submenu, collapsed by default
    // when the active tab is "members" — expand it before clicking through.
    await page.locator('#ca-sidebar-col a.nav-link', { hasText: 'Finances' }).click();
    await page.locator('#ca-sidebar-col a.nav-link[href*="tab=dons"]').click();
    await expect(page).toHaveURL(/[?&]tab=dons/);
    await expect(page.locator('#pf-tab-dons')).toBeVisible();
    await expect(page.locator('#pf-tab-members')).toHaveCount(0);
  });

  test('Relances tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    // The recap year picker only lists years actually holding a compta entry
    // (compta_recap.php) — seed data is single-year, so pick whichever other
    // year is on offer instead of assuming last year specifically exists.
    await page.locator('#pf-tab-recap .dropdown-toggle', { hasText: String(new Date().getFullYear()) }).click();
    const otherYearLinks = page.locator('#pf-tab-recap .dropdown-menu.show a:not(.active)');
    if (await otherYearLinks.count() === 0) test.skip(true, 'seed data has only one compta year');
    await otherYearLinks.first().click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=recap/);
    await expect(page.locator('#pf-tab-recap-btn')).toHaveClass(/active/);
  });

  test('Dons tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=dons');
    await page.locator('#pf-tab-dons .dropdown-toggle').filter({ has: page.locator('.fa-calendar-days') }).click();
    const yearLink = page.locator('#pf-tab-dons .dropdown-menu.show a', { hasText: String(new Date().getFullYear() - 1) }).first();
    await yearLink.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=dons/);
    await expect(page.locator('#pf-tab-dons-btn')).toHaveClass(/active/);
  });

  test('Relances tab: bulk-send delivers to pending members and redirects inside the hub', async ({ page }) => {
    // Seed a fresh unnotified entry so this doesn't depend on what other
    // spec files left behind (same rationale as preview-send-modal.spec.ts).
    await page.request.post('/api/compta', {
      data: { memberId: 1, typeId: 1, date: `${new Date().getFullYear()}-05-02`, amount: 60 },
    });
    await page.goto('/index.php?view=peopleFinance&tab=recap');
    const sendBtn = page.locator('#pf-tab-recap form button[type="submit"]', { hasText: 'Envoyer' });
    await expect(sendBtn).toBeVisible({ timeout: 10_000 });
    await sendBtn.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=recap/);
    // sendComptaRecap redirects with recapOk=<sentCount> — the actual
    // delivery count, more reliable here than a Mailpit round-trip.
    await expect(page).toHaveURL(/recapOk=[1-9]/);

    // The member no longer has a pending recap after the send.
    await expect(page.locator('#pf-tab-recap')).not.toContainText('Dupont');
  });

  test('Cotisations non renouvelées tab shows the lapsed members table (manager)', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=lapsed');
    await expect(page.locator('#pf-tab-lapsed-btn')).toHaveClass(/active/);
    await expect(page.locator('#pf-cohort-members-lapsed table')).toBeVisible();
    // No redundant "back to donation overview" link — this is a peer tab now
    await expect(page.locator('#pf-tab-lapsed', { hasText: 'aperçu des dons' })).toHaveCount(0);
  });

  test('Cotisations non renouvelées tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=lapsed');
    await page.locator('#pf-cohort-members-lapsed .dropdown-toggle', { hasText: String(new Date().getFullYear()) }).click();
    const yearLink = page.locator('#pf-cohort-members-lapsed .dropdown-menu.show a', { hasText: String(new Date().getFullYear() - 1) }).first();
    await yearLink.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=lapsed/);
    await expect(page.locator('#pf-tab-lapsed-btn')).toHaveClass(/active/);
  });

  test('Mouvements donateurs tab: manager can create a "Donateurs à relancer" segment', async ({ page }) => {
    // Seed has no donor who gave last year but not this year — create one so
    // the "lapsed donors" query (and the resulting segment) isn't empty.
    const lastYear = new Date().getFullYear() - 1;
    const createResp = await page.request.post('/api/contacts', {
      data: { firstName: 'Lapsed', lastName: 'DonorE2E', email: 'lapseddonor.e2e@example.com' },
    });
    const { data: newContact } = await createResp.json();
    await page.request.post('/api/compta', {
      data: { memberId: newContact.id, typeId: 3, date: `${lastYear}-06-01`, amount: 90 },
    });

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors');
    const openBtn = page.locator('[data-bs-target="#modal-create-lapsed-donors"]');
    await expect(openBtn).toBeVisible();
    await openBtn.click();

    const modal = page.locator('#modal-create-lapsed-donors');
    await expect(modal).toBeVisible({ timeout: 5_000 });

    await page.evaluate(() => document.body.removeAttribute('hx-boost'));
    await Promise.all([
      page.waitForNavigation({ timeout: 15_000 }),
      modal.locator('button[type="submit"]').click(),
    ]);

    await page.goto('/index.php?view=settings&tab=groups');
    await expect(page.locator('body')).toContainText('Donateurs à relancer', { timeout: 10_000 });
  });

  test('standalone ?view=lapsedMembers no longer exists — redirects into the hub', async ({ page }) => {
    await page.goto('/index.php?view=lapsedMembers&year=2025');
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=lapsed/);
    await expect(page).toHaveURL(/year=2025/);
    await expect(page.locator('#pf-tab-lapsed-btn')).toHaveClass(/active/);
  });
});

test.describe('People/finance hub — role guard on Relances tab', () => {
  test.use({ storageState: require('path').resolve(__dirname, '.auth/user.json') });

  test('Relances cotisation tab is hidden for a non-manager role', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance');
    await expect(page.locator('#pf-tab-recap-btn')).toHaveCount(0);
  });

  test('Mouvements membres/donateurs tabs are visible but read-only for a non-manager role', async ({ page }) => {
    await page.goto('/index.php?view=peopleFinance&tab=lapsed');
    // "Visible" as in role-gated presence, not desktop-viewport visibility —
    // #pf-tab-lapsed-btn is the mobile-only tab bar, css-hidden ≥991.98px.
    await expect(page.locator('#pf-tab-lapsed-btn')).toBeAttached();
    await expect(page.locator('#pf-cohort-members-lapsed table')).toBeVisible();
    // Manager-only action triggers (create segment, send reminders) must not render.
    await expect(page.locator('#pf-cohort-members-lapsed button', { hasText: 'segment' })).toHaveCount(0);
    await expect(page.locator('#pf-cohort-members-lapsed [data-bs-target="#modal-send-coti-reminders"]')).toHaveCount(0);

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors');
    await expect(page.locator('#pf-tab-lapsedDonors-btn')).toBeAttached();
    await expect(page.locator('#pf-cohort-donors-lapsed table')).toBeVisible();
    await expect(page.locator('#pf-cohort-donors-lapsed button', { hasText: 'segment' })).toHaveCount(0);
  });
});
