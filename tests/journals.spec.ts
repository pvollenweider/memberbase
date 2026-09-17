/**
 * E2E tests — "Journaux" hub
 *
 * Server renders only the active tab's pane per request (id="jh-tab-<tab>",
 * class="jh-active-pane") — same single-pane-per-request architecture as the
 * "Contacts" hub (#164). There's no local tab bar anymore (the
 * nav-architecture rework replaced it with direct sidebar entries: "Journal
 * suivi" as a top-level link, "Journal compta" inside the sidebar's
 * "Finances" submenu — no single combined "Journaux" nav entry survives).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Journals hub', () => {
  test('reachable via ?view=journals, Compta tab active by default', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await expect(page.locator('h1', { hasText: 'Finances' })).toBeVisible();
    await expect(page.locator('#jh-tab-compta table.export')).toBeVisible();
    // Sidebar's "Journal compta" submenu entry (inside the auto-expanded
    // "Finances" group) carries the active state now, not a local tab bar.
    await expect(page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=compta"]')).toHaveClass(/active/);
  });

  test('Suivi tab shows the merged suivi/email log', async ({ page }) => {
    await page.goto('/index.php?view=journals&tab=suivi');
    await expect(page.locator('h1', { hasText: 'Journaux' })).toBeVisible();
    await expect(page.locator('#jh-tab-suivi #suivi-table')).toBeVisible();
    // "Journal suivi" is a top-level sidebar link — active state is the
    // *absence* of the accordion's "collapsed" class (see sidebar_nav.php).
    await expect(page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=suivi"]')).not.toHaveClass(/collapsed/);
  });

  test('each tab renders independently without variable collisions', async ({ page }) => {
    await page.goto('/index.php?view=journals&tab=compta');
    await expect(page.locator('#jh-tab-compta table.export')).toBeAttached();
    await page.goto('/index.php?view=journals&tab=suivi');
    await expect(page.locator('#jh-tab-suivi #suivi-table')).toBeVisible();
  });

  test('switching tabs via the sidebar works and updates the URL', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=suivi"]').click();
    await expect(page).toHaveURL(/[?&]tab=suivi/);
    await expect(page.locator('#jh-tab-suivi')).toBeVisible();
    await expect(page.locator('#jh-tab-compta')).toHaveCount(0);
  });

  test('reachable for every role (open route, like the two it replaces)', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await expect(page.locator('#main-content')).not.toContainText('Accès refusé');
  });

  test('Compta tab: changing the year filter stays inside the hub', async ({ page }) => {
    await page.goto('/index.php?view=journals');
    await page.locator('#jh-tab-compta .dropdown-toggle', { hasText: String(new Date().getFullYear()) }).click();
    // "Toutes années" is always offered regardless of the facet (seed data
    // is all dated "today", so only the current year has real entries).
    const yearLink = page.locator('#jh-tab-compta .dropdown-menu.show a', { hasText: 'Toutes' }).first();
    await yearLink.click();
    await expect(page).toHaveURL(/view=journals/);
    await expect(page).toHaveURL(/tab=compta/);
    await expect(page.locator('#jh-tab-compta')).toBeVisible();
  });

  test('Compta tab: a Dec 31 entry timestamped after midnight does not leak into the next year\'s filter', async ({ page }) => {
    const year = new Date().getFullYear();
    // Reproduces a reported bug: the year boundary used to be computed as
    // "day 0 of January" (== Dec 31 of the previous year, 00:00:00) with an
    // exclusive ">" comparison, so any Dec 31 entry stamped later than exact
    // midnight leaked into the *next* year's list. addCompta's date field is
    // 'd/m/Y' only (no time) — PHP's DateTime::createFromFormat backfills the
    // missing H:i:s from the current wall-clock time, not midnight, so a
    // plain date-only submission already reproduces this deterministically
    // (the test only fails if run at exactly 00:00:00.000).
    await page.goto('/index.php');
    const csrf = await page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
    await page.request.post('/index.php', {
      form: {
        action: 'addCompta', view: 'compta', userid: '1', type_id: '1',
        date: `31/12/${year - 1}`, libele: 'Coti E2E Dec31 boundary', sum: '77', csrf,
      },
    });

    await page.goto(`/index.php?view=lastEntryCompta&year=${year}`);
    await expect(page.locator('body')).not.toContainText('Coti E2E Dec31 boundary');

    await page.goto(`/index.php?view=lastEntryCompta&year=${year - 1}`);
    await expect(page.locator('body')).toContainText('Coti E2E Dec31 boundary');
  });

  test('Compta tab: filter by contact type narrows the list (#178 follow-up)', async ({ page }) => {
    // Alice (id 1, contact_type "Donateur privé" by default) has compta
    // entries in the seed — switch her to "Entreprise" and confirm the type
    // filter includes/excludes her rows accordingly.
    await page.request.patch('/api/contacts/1', { data: { contactTypeId: 4 } }); // 4 = Entreprise

    await page.goto('/index.php?view=journals&tab=compta&contactTypeId=4');
    await expect(page.locator('#jh-tab-compta')).toContainText('Dupont');

    await page.goto('/index.php?view=journals&tab=compta&contactTypeId=1');
    await expect(page.locator('#jh-tab-compta')).not.toContainText('Dupont');

    // Dropdown shows the active type's label instead of "Tous les types".
    await page.goto('/index.php?view=journals&tab=compta&contactTypeId=4');
    await expect(page.locator('#jh-tab-compta .ca-filter-btn', { hasText: 'Entreprise' })).toBeVisible();

    await page.request.patch('/api/contacts/1', { data: { contactTypeId: 1 } }); // revert for other tests
  });
});

test.describe('Journals hub — sidebar', () => {
  test('sidebar "Journal suivi" link reaches the hub', async ({ page }) => {
    await page.goto('/index.php?view=dashboard');
    const link = page.locator('#ca-sidebar-col a.nav-link[href*="view=journals&tab=suivi"]');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/view=journals/);
  });
});
