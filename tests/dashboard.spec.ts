/**
 * E2E tests — dashboard landing view (#153)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test('reachable via ?view=dashboard, shows shortcuts and documentation panels', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('h1', { hasText: 'MemberBase Test' })).toBeVisible();
    await expect(page.locator('.card-header', { hasText: 'Raccourcis' })).toBeVisible();
    await expect(page.locator('.card-header', { hasText: 'Documentation' })).toBeVisible();
  });

  test('shows "Dons par type de contact" right after "Répartition des dons", CHF per contact type (#177)', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('text=Répartition des dons')).toBeVisible();
    await expect(page.locator('text=Dons par type de contact')).toBeVisible();
    await expect(page.locator('#dashboardPie')).toBeVisible();
    await expect(page.locator('#dashboardContactTypeBars')).toBeVisible();

    // Seed: every donor defaults to contact_type "Donateur privé" — sum of
    // donations (CHF), not a contact count, grouped by the donor's type,
    // with the share of the total — a single row is 100%.
    await expect(page.locator('#dashboardContactTypeBars')).toContainText('Donateur privé');
    await expect(page.locator('#dashboardContactTypeBars')).toContainText('CHF');
    await expect(page.locator('#dashboardContactTypeBars')).toContainText('(100%)');
  });

  test('"Dons par type de contact" shows the vs-last-year delta per row, same period as Contributions (#177 follow-up)', async ({ page }) => {
    const year = new Date().getFullYear();
    await page.goto('/index.php');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    // Give Alice (user 1, contact_type "Donateur privé") a prior-year donation
    // inside the YTD window, so the per-row delta isn't null.
    const resp = await page.request.post('/index.php', {
      form: { action: 'addCompta', view: 'compta', userid: '1', type_id: '3', date: `01/06/${year - 1}`, libele: 'Don E2E prev', sum: '200', csrf },
    });
    expect(resp.status()).toBe(200);

    await page.goto('/index.php?view=dashboard');
    const bars = page.locator('#dashboardContactTypeBars');
    await expect(bars).toContainText('Donateur privé');
    await expect(bars).toContainText('CHF (+');
    await expect(bars).toContainText('%)');
  });

  test('"Dons par type de contact" shows a "Nouveau" badge when the same-period-last-year base is exactly zero (not blank)', async ({ page }) => {
    const year = new Date().getFullYear();
    // Fresh contact, contact_type "Entreprise" (4, no prior activity in the
    // seed for that type) — a this-year-only donation means the "même
    // période" comparison for that type is a real 0, not "not applicable".
    const contact = await (await page.request.post('/api/contacts', {
      data: { lastName: 'NewBadge E2E', contactTypeId: 4 },
    })).json();
    await page.goto('/index.php');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    const resp = await page.request.post('/index.php', {
      form: { action: 'addCompta', view: 'compta', userid: String(contact.data.id), type_id: '3', date: `01/06/${year}`, libele: 'Don E2E new type', sum: '944', csrf },
    });
    expect(resp.status()).toBe(200);

    await page.goto('/index.php?view=dashboard');
    const bars = page.locator('#dashboardContactTypeBars');
    await expect(bars).toContainText('Entreprise');
    await expect(bars).toContainText('Nouveau');
  });

  test('"Dons par type de contact" labels link to the filtered lapsed-donors list for that type (#177 follow-up)', async ({ page }) => {
    const year = new Date().getFullYear();
    await page.goto('/index.php?view=dashboard');
    const link = page.locator('#dashboardContactTypeBars a', { hasText: 'Donateur privé' });
    await expect(link).toBeVisible();
    const href = await link.getAttribute('href');
    expect(href).toContain('view=peopleFinance');
    expect(href).toContain('tab=lapsedDonors');
    expect(href).toContain(`year=${year}`);
    expect(href).toContain('contactTypeId=1'); // 1 = Donateur privé in the seed

    await link.click();
    await expect(page).toHaveURL(/tab=lapsedDonors/);
    await expect(page).toHaveURL(/contactTypeId=1/);
  });

  test('shortcut "Donateur non institutionnel actif depuis N-4" links to the 5-year quick filter (#176)', async ({ page }) => {
    // Seed donors (Alice/Bob) already made a non-institutional payment this
    // year, so the shortcut is present without extra setup.
    await page.goto('/index.php?view=dashboard');
    const link = page.locator('a', { hasText: 'Donateur non institutionnel actif depuis' });
    await expect(link).toBeVisible();
    const href = await link.getAttribute('href');
    expect(href).toContain('segment=-8888');

    await link.click();
    await expect(page).toHaveURL(/segment=-8888/);
  });

  test('admin guide link is no longer shown, user guide link stays', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('a', { hasText: 'Guide utilisateur' })).toBeVisible();
    await expect(page.locator('a', { hasText: 'Guide administrateur' })).toHaveCount(0);
  });

  test('no dedicated tasks card on the dashboard, but the nav link is present', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await expect(page.locator('.card-header', { hasText: 'Tâches à traiter' })).toHaveCount(0);
    await expect(page.locator('#ca-sidebar-col a.nav-link[href*="view=tasks"]')).toBeVisible();
  });

  test('unpaid cotisation KPI links to the lapsed members tab in the hub', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    // Scoped by href, not just visible text — "Activités récentes" can
    // independently contain an unrelated "cotisation" mention (e.g. a
    // logged reminder email subject), which a loose text match also picks up.
    const kpiLink = page.locator('a[href*="tab=lapsed"][href*="cohort=lapsed"]').first();
    await expect(kpiLink).toBeVisible();
    await kpiLink.click();
    await expect(page).toHaveURL(/view=peopleFinance/);
    await expect(page).toHaveURL(/tab=lapsed/);
    await expect(page.locator('#pf-tab-lapsed-btn')).toHaveClass(/active/);
  });

  test('sidebar exposes a dashboard shortcut, active state highlights it', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    // Top-level sidebar links signal "active" via the *absence* of the
    // Bootstrap accordion's "collapsed" class, not a dedicated .active class
    // (that's reserved for submenu items — see sidebar_nav.php).
    const navLink = page.locator('#ca-sidebar-col a.nav-link[href*="view=dashboard"]');
    await expect(navLink).toBeVisible();
    await expect(navLink).not.toHaveClass(/collapsed/);
  });

  test('member list reachable from the Membres nav link', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    await page.locator('#ca-sidebar-col a.nav-link[href*="view=peopleFinance&tab=members"]').click();
    await expect(page).toHaveURL(/view=peopleFinance/);
  });

  test('bare landing (no view param) defaults to the dashboard', async ({ page }) => {
    await page.goto('/index.php');
    await expect(page.locator('h1', { hasText: 'MemberBase Test' })).toBeVisible();
  });

  test('compta search shortcut: typing a name shows results, clicking jumps to the member\'s Compta tab', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const input = page.locator('#dashboard-compta-search');
    await expect(input).toBeVisible();
    await input.fill('Dupont');

    const result = page.locator('#dashboard-compta-results [data-user-id]', { hasText: 'Dupont' });
    await expect(result).toBeVisible({ timeout: 5_000 });
    await result.click();

    await expect(page).toHaveURL(/view=compta&userid=\d+/);
  });

  test('compta search shortcut: no match shows a "no results" message', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const input = page.locator('#dashboard-compta-search');
    await input.fill('Zzzznomatch');

    await expect(page.locator('#dashboard-compta-results')).toContainText('Aucun résultat', { timeout: 5_000 });
  });

  test('KPI cards (contributions, donors, active members) are shown', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const cards = page.locator('.ca-resume-cards');
    await expect(cards).toBeVisible();
    await expect(cards).toContainText('Contributions');
    await expect(cards).toContainText('CHF');
    await expect(cards).toContainText('Donateurs');
    await expect(cards.locator('a', { hasText: 'fidèles' })).toBeVisible();
    // End-anchored only (not start — the anchor's raw textContent carries
    // leading whitespace/newlines from the template, e.g.
    // "\n        4 Nouveaux      "). Must stay anchored at the end though:
    // the dashboard's "Raccourcis" card has its own unrelated "N Nouveaux
    // membres" link that an unanchored match also picks up.
    await expect(cards.locator('a', { hasText: /\d+ Nouveaux\s*$/ })).toBeVisible();
  });

  test('KPI cards are absent for a role without write access', async ({ page, browser }) => {
    const ctx = await browser.newContext({ storageState: require('path').resolve(__dirname, '.auth/readonly.json') });
    const p = await ctx.newPage();
    await p.goto('/index.php?view=dashboard');
    await expect(p.locator('.ca-resume-cards')).toHaveCount(0);
    await ctx.close();
  });

  test('quick search input auto-focuses on load', async ({ page }) => {
    await page.goto('/index.php?view=list');
    await page.goto('/index.php?view=dashboard');
    await expect.poll(() => page.evaluate(() => document.activeElement?.id))
      .toBe('dashboard-compta-search');
  });
});

test.describe('Global navigation shortcuts (Alt/Option+Cmd+1/2/3)', () => {
  test('navigates to Contacts, Journaux, then back to the dashboard', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');

    await page.keyboard.down('Alt');
    await page.keyboard.down('Meta');
    await page.keyboard.press('2');
    await page.keyboard.up('Meta');
    await page.keyboard.up('Alt');
    await expect(page).toHaveURL(/view=peopleFinance/);

    await page.keyboard.down('Alt');
    await page.keyboard.down('Meta');
    await page.keyboard.press('3');
    await page.keyboard.up('Meta');
    await page.keyboard.up('Alt');
    await expect(page).toHaveURL(/view=journals/);

    await page.keyboard.down('Alt');
    await page.keyboard.down('Meta');
    await page.keyboard.press('1');
    await page.keyboard.up('Meta');
    await page.keyboard.up('Alt');
    await expect(page).toHaveURL(/view=dashboard/);
  });
});
