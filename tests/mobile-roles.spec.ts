/**
 * E2E tests — role-gated elements of the mobile nav (fixed topbar + slide-in
 * sidebar, replacing the old separate `.d-lg-none` menu bar)
 *
 * The topbar (partials/topbar.php) only carries the sidebar toggle, brand,
 * and search now — everything role-gated (Tâches, Administration) lives
 * exclusively in the sidebar, which slides in from off-screen via
 * #sidebarToggle below the 991.98px breakpoint (.ca-sidebar-panel in
 * custom.css). roles.spec.ts already covers the isManager() gate at desktop
 * viewport; this spec re-checks it at a phone viewport, behind the slide-in
 * toggle, so a breakpoint-specific regression can't slip through.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, Browser } from '@playwright/test';
import * as path from 'path';

const MOBILE_VIEWPORT = { width: 393, height: 852 }; // Pixel 7

function stateFile(role: string): string {
  return path.resolve(__dirname, `.auth/${role}.json`);
}

async function openMobileAs(browser: Browser, role: string) {
  const ctx = await browser.newContext({
    storageState: stateFile(role),
    viewport: MOBILE_VIEWPORT,
    isMobile: true,
    hasTouch: true,
  });
  const page = await ctx.newPage();
  return { page, ctx };
}

test.describe('mobile bar — Administration entry (isManager)', () => {
  for (const role of ['readonly', 'user']) {
    test(`${role}: Administration hidden on mobile`, async ({ browser }) => {
      const { page, ctx } = await openMobileAs(browser, role);
      await page.goto('/index.php');
      await expect(page.locator('#ca-sidebar-col a[data-bs-target="#collapseAdmin"]')).toHaveCount(0);
      await ctx.close();
    });
  }

  for (const role of ['manager', 'admin']) {
    test(`${role}: Administration visible on mobile`, async ({ browser }) => {
      const { page, ctx } = await openMobileAs(browser, role);
      await page.goto('/index.php');
      await page.locator('#sidebarToggle').click();
      await expect(page.locator('#ca-sidebar-col a[data-bs-target="#collapseAdmin"]')).toBeVisible();
      await ctx.close();
    });
  }
});

test.describe('mobile bar — common elements visible for every role', () => {
  for (const role of ['readonly', 'user', 'manager', 'admin']) {
    test(`${role}: sidebar toggle opens nav icons`, async ({ browser }) => {
      const { page, ctx } = await openMobileAs(browser, role);
      await page.goto('/index.php');
      await page.locator('#sidebarToggle').click();
      await expect(page.locator('#ca-sidebar-col a[href*="view=peopleFinance"]').first()).toBeVisible();
      await ctx.close();
    });
  }
});

test.describe('mobile — guarded views still denied by direct URL', () => {
  // The router guard is viewport-independent; this locks it on mobile too.
  test('readonly: addUser denied on mobile', async ({ browser }) => {
    const { page, ctx } = await openMobileAs(browser, 'readonly');
    await page.goto('/index.php?view=addUser');
    await expect(page.locator('#main-content')).toContainText('Accès refusé');
    await ctx.close();
  });

  test('user: importStep1 denied on mobile', async ({ browser }) => {
    const { page, ctx } = await openMobileAs(browser, 'user');
    await page.goto('/index.php?view=importStep1');
    await expect(page.locator('#main-content')).toContainText('Accès refusé');
    await ctx.close();
  });
});
