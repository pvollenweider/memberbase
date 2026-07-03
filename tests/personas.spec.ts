/**
 * E2E tests — permission audit by persona (#94)
 *
 * A single truth table drives, for each persona (readonly / user / manager /
 * admin), what is ALLOWED and what is DENIED — at the view guard (server-side,
 * rendered as "Accès refusé") and at the REST API (HTTP 403). Adding a new
 * capability means adding a row here, which forces the allow/deny decision to
 * be explicit for every persona.
 *
 * Non-destructive: view probes are GET-only; the one API write probe is
 * idempotent (sets firstName to its seed value); destructive admin actions are
 * only exercised on their DENIED side (a 403 mutates nothing).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, Browser, APIRequestContext } from '@playwright/test';
import * as path from 'path';

const ROLES = ['readonly', 'user', 'manager', 'admin'] as const;
type Role = (typeof ROLES)[number];

const ACTIVE_MEMBER_ID = 1;   // Alice Dupont, active
const ARCHIVED_MEMBER_ID = 3; // archived, eligible for delete

function stateFile(role: Role): string {
  return path.resolve(__dirname, `.auth/${role}.json`);
}

async function openAs(browser: Browser, role: Role) {
  const ctx = await browser.newContext({ storageState: stateFile(role) });
  const page = await ctx.newPage();
  return { page, ctx };
}

async function apiAs(playwright: { request: { newContext: Function } }, role: Role): Promise<APIRequestContext> {
  return playwright.request.newContext({ baseURL: 'http://localhost:8080', storageState: stateFile(role) });
}

// ── View guards (server-side, GET-only) ─────────────────────────────────────
// Each guarded view + the roles allowed to see it. Everyone else must get the
// "Accès refusé" message from the view router guard.
const VIEW_MATRIX: { view: string; guard: string; allowed: Role[] }[] = [
  { view: 'addUser',       guard: 'canWrite',  allowed: ['user', 'manager', 'admin'] },
  { view: 'importStep1',   guard: 'isManager', allowed: ['manager', 'admin'] },
  { view: 'mergeUsers',    guard: 'isManager', allowed: ['manager', 'admin'] },
  { view: 'deleteUser',    guard: 'isAdmin',   allowed: ['admin'] },
  { view: 'anonymizeUser', guard: 'isAdmin',   allowed: ['admin'] },
];

test.describe('Personas — view guards', () => {
  for (const { view, guard, allowed } of VIEW_MATRIX) {
    for (const role of ROLES) {
      const shouldAllow = allowed.includes(role);
      test(`${role}: view=${view} (${guard}) → ${shouldAllow ? 'allowed' : 'denied'}`, async ({ browser }) => {
        const { page, ctx } = await openAs(browser, role);
        // id is harmless for views that ignore it; guard runs before rendering.
        await page.goto(`/index.php?view=${view}&id=${ARCHIVED_MEMBER_ID}&a=${ACTIVE_MEMBER_ID}&b=2`);
        const denied = page.locator('text=Accès refusé');
        if (shouldAllow) {
          await expect(denied).toHaveCount(0);
        } else {
          await expect(denied).toBeVisible({ timeout: 10_000 });
        }
        await ctx.close();
      });
    }
  }
});

// ── REST API capabilities (HTTP 403) ────────────────────────────────────────
test.describe('Personas — REST API', () => {
  // Read (canRead): allowed for everyone logged in.
  for (const role of ROLES) {
    test(`${role}: GET /api/members → not 403`, async ({ playwright }) => {
      const api = await apiAs(playwright, role);
      const r = await api.get('/api/members');
      expect(r.status()).not.toBe(403);
      await api.dispose();
    });
  }

  // Write (canWrite): idempotent PUT (firstName back to its seed value).
  const writeAllowed: Role[] = ['user', 'manager', 'admin'];
  for (const role of ROLES) {
    const shouldAllow = writeAllowed.includes(role);
    test(`${role}: PUT /api/members/${ACTIVE_MEMBER_ID} → ${shouldAllow ? 'not 403' : '403'}`, async ({ playwright }) => {
      const api = await apiAs(playwright, role);
      const r = await api.put(`/api/members/${ACTIVE_MEMBER_ID}`, { data: { firstName: 'Alice' } });
      if (shouldAllow) expect(r.status()).not.toBe(403);
      else             expect(r.status()).toBe(403);
      await api.dispose();
    });
  }

  // Permanent delete (isAdmin): only the DENIED side is exercised (a 403
  // mutates nothing); the admin case is destructive, covered elsewhere.
  for (const role of ['readonly', 'user', 'manager'] as Role[]) {
    test(`${role}: DELETE /api/members/${ARCHIVED_MEMBER_ID}?dispose=delete → 403`, async ({ playwright }) => {
      const api = await apiAs(playwright, role);
      const r = await api.delete(`/api/members/${ARCHIVED_MEMBER_ID}?dispose=delete`);
      expect(r.status()).toBe(403);
      await api.dispose();
    });
  }
});
