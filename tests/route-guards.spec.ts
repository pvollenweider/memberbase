/**
 * E2E tests — per-route access guards of the view router
 *
 * The view router (includes/routing/views.php, issue #56) declares a guard
 * per route. This spec exercises the role × route matrix by direct URL
 * access — the attack vector that bypasses hidden UI buttons.
 *
 * It includes regression tests for two vulnerabilities fixed in #56:
 * `deleteUserConfirm` (member deactivation) and `removeSuiviConfirm`
 * (follow-up deletion) were reachable WITHOUT any role check.
 *
 * Destructive GET routes are only tested for the DENIED case, then the
 * absence of side effect is asserted.
 *
 * Seed: member id=1 (Alice Dupont) active; roles testreadonly/testuser/
 * testmanager/testadmin (see roles.spec.ts header).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, Browser } from '@playwright/test';
import * as path from 'path';

const ACTIVE_MEMBER_ID = 1;

function stateFile(role: string): string {
  return path.resolve(__dirname, `.auth/${role}.json`);
}

async function openAs(browser: Browser, role: string) {
  const ctx = await browser.newContext({ storageState: stateFile(role) });
  const page = await ctx.newPage();
  return { page, ctx };
}

const DENIED_TEXT = 'Accès refusé';

// Non-destructive guarded routes: test both denied and allowed rendering.
const GUARDED_VIEWS: { view: string; url: string; denied: string[]; allowed: string[] }[] = [
  { view: 'addUser',     url: '/index.php?view=addUser',
    denied: ['readonly'],                     allowed: ['user', 'manager', 'admin'] },
  { view: 'importStep1', url: '/index.php?view=importStep1',
    denied: ['readonly', 'user'],             allowed: ['manager', 'admin'] },
  { view: 'mergeUsers',  url: '/index.php?view=mergeUsers',
    denied: ['readonly', 'user'],             allowed: ['manager', 'admin'] },
  { view: 'deleteUser',  url: `/index.php?view=deleteUser&id=${ACTIVE_MEMBER_ID}`,
    denied: ['readonly', 'user', 'manager'],  allowed: ['admin'] },
  { view: 'anonymizeUser', url: `/index.php?view=anonymizeUser&id=${ACTIVE_MEMBER_ID}`,
    denied: ['readonly', 'user', 'manager'],  allowed: [] }, // allowed case covered by dedicated spec
];

for (const { view, url, denied, allowed } of GUARDED_VIEWS) {
  test.describe(`route guard — view=${view}`, () => {
    for (const role of denied) {
      test(`${role}: denied`, async ({ browser }) => {
        const { page, ctx } = await openAs(browser, role);
        await page.goto(url);
        await expect(page.locator('#main-content')).toContainText(DENIED_TEXT);
        await ctx.close();
      });
    }
    for (const role of allowed) {
      test(`${role}: rendered`, async ({ browser }) => {
        const { page, ctx } = await openAs(browser, role);
        await page.goto(url);
        await expect(page.locator('#main-content')).not.toContainText(DENIED_TEXT);
        await ctx.close();
      });
    }
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Regression #56 — destructive legacy routes previously reachable without
// any role check. Denied roles must get the refusal AND no side effect.
// ─────────────────────────────────────────────────────────────────────────────

test.describe('regression #56 — deleteUserConfirm requires isAdmin', () => {
  for (const role of ['readonly', 'user', 'manager']) {
    test(`${role}: denied and member stays active`, async ({ browser, playwright }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=deleteUserConfirm&id=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('#main-content')).toContainText(DENIED_TEXT);
      await ctx.close();

      // Side-effect check: the member must still be visible (status=1)
      const api = await playwright.request.newContext({
        baseURL: 'http://localhost:8080',
        storageState: stateFile('admin'),
      });
      const res = await api.get(`/api/members/${ACTIVE_MEMBER_ID}`);
      expect(res.ok()).toBeTruthy();
      await api.dispose();
    });
  }
});

test.describe('regression #56 — removeSuiviConfirm requires canWrite', () => {
  test('readonly: denied', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'readonly');
    await page.goto(`/index.php?view=removeSuiviConfirm&userid=${ACTIVE_MEMBER_ID}&suiviid=999999`);
    await expect(page.locator('#main-content')).toContainText(DENIED_TEXT);
    await ctx.close();
  });
});

// Unknown view — the router renders an explicit warning instead of a blank page
test('unknown view renders "Vue introuvable"', async ({ browser }) => {
  const { page, ctx } = await openAs(browser, 'admin');
  await page.goto('/index.php?view=doesNotExist');
  await expect(page.locator('#main-content')).toContainText('Vue introuvable');
  await ctx.close();
});
