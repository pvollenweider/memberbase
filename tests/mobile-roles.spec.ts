/**
 * E2E tests — role-gated elements of the MOBILE menu bar
 *
 * The mobile icon bar (partials/menu.php, `.d-lg-none` block) is separate
 * markup from the desktop navbar and carries its own isManager() gate for
 * the settings gear. roles.spec.ts only targets the desktop navbar
 * (.navbar-collapse) — this spec covers the mobile variant at a phone
 * viewport, so a regression in the mobile block cannot slip through.
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

const MOBILE_BAR = '.d-lg-none';

test.describe('mobile bar — settings gear (isManager)', () => {
  for (const role of ['readonly', 'user']) {
    test(`${role}: gear hidden on mobile`, async ({ browser }) => {
      const { page, ctx } = await openMobileAs(browser, role);
      await page.goto('/index.php');
      await expect(page.locator(`${MOBILE_BAR} a[href*="view=settings"]`)).toHaveCount(0);
      await ctx.close();
    });
  }

  for (const role of ['manager', 'admin']) {
    test(`${role}: gear visible on mobile`, async ({ browser }) => {
      const { page, ctx } = await openMobileAs(browser, role);
      await page.goto('/index.php');
      await expect(page.locator(`${MOBILE_BAR} a[href*="view=settings"]`).first()).toBeVisible();
      await ctx.close();
    });
  }
});

test.describe('mobile bar — common elements visible for every role', () => {
  for (const role of ['readonly', 'user', 'manager', 'admin']) {
    test(`${role}: nav icons and search toggle visible`, async ({ browser }) => {
      const { page, ctx } = await openMobileAs(browser, role);
      await page.goto('/index.php');
      await expect(page.locator(`${MOBILE_BAR} a[href*="view=peopleFinance"]`).first()).toBeVisible();
      await expect(page.locator('#mobile-search-toggle')).toBeVisible();
      // Desktop navbar collapsed away at this viewport
      await expect(page.locator('.navbar-collapse')).not.toBeVisible();
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
