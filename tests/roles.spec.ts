/**
 * E2E tests — role-based access control
 *
 * For each role (readonly / user / manager / admin):
 *   1. UI  — action elements visible or hidden as expected
 *   2. API — forced HTTP calls blocked at the server (HTTP 403)
 *
 * Seed users (all with password TestPassword1!):
 *   testreadonly → role=readonly
 *   testuser     → role=user
 *   testmanager  → role=manager
 *   testadmin    → role=admin
 *
 * Seed members used:
 *   id=1  Alice Dupont   — active, has compta
 *   id=2  Bob Martin     — active, no compta
 *   id=3  Archived Member — status=0, no compta → eligible for delete
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect, Browser, APIRequestContext } from '@playwright/test';
import * as path from 'path';

// ── constants ─────────────────────────────────────────────────────────────────

const ACTIVE_MEMBER_ID  = 1;  // Alice Dupont, active, has compta
const ARCHIVED_MEMBER_ID = 3; // Archived member, no compta

function stateFile(role: string): string {
  return path.resolve(__dirname, `.auth/${role}.json`);
}

// ── UI helpers ────────────────────────────────────────────────────────────────

/** Open a new page authenticated as the given role. Caller must close ctx. */
async function openAs(browser: Browser, role: string) {
  const ctx = await browser.newContext({ storageState: stateFile(role) });
  const page = await ctx.newPage();
  return { page, ctx };
}

// ── API helper ────────────────────────────────────────────────────────────────

/** Create a request context authenticated as the given role. */
async function apiAs(playwright: { request: { newContext: Function } }, role: string): Promise<APIRequestContext> {
  return playwright.request.newContext({
    baseURL: 'http://localhost:8080',
    storageState: stateFile(role),
  });
}

/**
 * Fetch the session CSRF token exposed in the page <meta> for an authenticated
 * context. Needed for direct POST /index.php action calls (the CSRF guard runs
 * before the role guard), so these tests exercise the ROLE guard, not CSRF.
 */
async function csrfFor(api: APIRequestContext): Promise<string> {
  const html = await (await api.get('/index.php')).text();
  const m = html.match(/name="csrf-token" content="([^"]+)"/);
  if (!m) throw new Error('CSRF token meta not found');
  return m[1];
}

// ─────────────────────────────────────────────────────────────────────────────
// UI — navigation bar
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — Administration entry (isManager)', () => {
  for (const role of ['readonly', 'user']) {
    test(`${role}: Administration hidden`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto('/index.php');
      await expect(page.locator('a[href*="view=settings"]')).toHaveCount(0);
      await ctx.close();
    });
  }

  for (const role of ['manager', 'admin']) {
    test(`${role}: Administration visible`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto('/index.php');
      // Administration lives only in the sidebar now (no topbar gear).
      await expect(page.locator('#ca-sidebar-col a[data-bs-target="#collapseAdmin"]')).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — member list: "Add member" button (canWrite)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — add-member button (canWrite)', () => {
  test('readonly: add-member button hidden', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'readonly');
    await page.goto('/index.php');
    await expect(page.locator('a[href*="view=addUser"]')).toHaveCount(0);
    await ctx.close();
  });

  for (const role of ['user', 'manager', 'admin']) {
    test(`${role}: add-member button visible`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto('/index.php');
      await expect(page.locator('a[href*="view=addUser"]').first()).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — member profile: click-to-edit (canWrite)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — click-to-edit on member profile (canWrite)', () => {
  test('readonly: edit hint absent, view zone has pe-none', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'readonly');
    await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
    await expect(page.locator('.ca-edit-hint')).toHaveCount(0);
    await expect(page.locator('.ca-view-zone.pe-none')).toBeVisible();
    await ctx.close();
  });

  for (const role of ['user', 'manager', 'admin']) {
    test(`${role}: edit hint present and view zone is clickable`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('.ca-edit-hint').first()).toBeAttached();
      await expect(page.locator('.ca-view-zone:not(.pe-none)').first()).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — compta add row (canWrite)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — compta add row (canWrite)', () => {
  test('readonly: add row absent', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'readonly');
    await page.goto(`/index.php?view=compta&userid=${ACTIVE_MEMBER_ID}`);
    await expect(page.locator('form[name="addCompta"] select[name="type_id"]')).toHaveCount(0);
    await ctx.close();
  });

  for (const role of ['user', 'manager', 'admin']) {
    test(`${role}: add row visible`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=compta&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('form[name="addCompta"] select[name="type_id"]')).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — suivi add row (canWrite)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — suivi add row (canWrite)', () => {
  test('readonly: add row absent', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'readonly');
    await page.goto(`/index.php?view=suivi&userid=${ACTIVE_MEMBER_ID}`);
    await expect(page.locator('form[name="addSuivi"] input[name="date"]')).toHaveCount(0);
    await ctx.close();
  });

  for (const role of ['user', 'manager', 'admin']) {
    test(`${role}: add row visible`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=suivi&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('form[name="addSuivi"] input[name="date"]')).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — archive toggle (isManager)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — archive/activate toggle (isManager)', () => {
  for (const role of ['readonly', 'user']) {
    test(`${role}: archive toggle absent — status shown as text`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('form#status-toggle-form')).toHaveCount(0);
      await expect(page.locator('text=Actif')).toBeVisible();
      await ctx.close();
    });
  }

  for (const role of ['manager', 'admin']) {
    test(`${role}: archive toggle visible`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('form#status-toggle-form')).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — group management on member profile (isManager)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — group management (isManager)', () => {
  for (const role of ['readonly', 'user']) {
    test(`${role}: group pills are plain spans (no remove link)`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('a.member-pill')).toHaveCount(0);
      await expect(page.locator('span.member-pill').first()).toBeVisible();
      await ctx.close();
    });

    test(`${role}: add-group details section absent`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('details.ca-integrity-section')).toHaveCount(0);
      await ctx.close();
    });
  }

  for (const role of ['manager', 'admin']) {
    test(`${role}: group pills are remove links`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('a.member-pill').first()).toBeVisible();
      await ctx.close();
    });

    test(`${role}: add-group details section present`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ACTIVE_MEMBER_ID}`);
      await expect(page.locator('details.ca-integrity-section')).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — tasks nav link (isManager)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — tasks nav link (isManager)', () => {
  for (const role of ['readonly', 'user']) {
    test(`${role}: tasks nav link hidden`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto('/index.php');
      await expect(page.locator('a[href*="view=tasks"]')).toHaveCount(0);
      await ctx.close();
    });
  }

  for (const role of ['manager', 'admin']) {
    test(`${role}: tasks nav link visible`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto('/index.php');
      // Sidebar link is unconditional for managers; the topbar badge link
      // only renders when there are open tasks (see topbar.php).
      await expect(page.locator('#ca-sidebar-col a[href*="view=tasks"]')).toBeVisible();
      await ctx.close();
    });
  }
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — delete / anonymize on archived profile (isAdmin)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — delete/anonymize on archived profile (isAdmin)', () => {
  for (const role of ['readonly', 'user', 'manager']) {
    test(`${role}: delete button absent on archived profile`, async ({ browser }) => {
      const { page, ctx } = await openAs(browser, role);
      await page.goto(`/index.php?view=generalData&userid=${ARCHIVED_MEMBER_ID}`);
      await expect(page.locator(`a[href*="view=deleteUser"]`)).toHaveCount(0);
      await expect(page.locator(`a[href*="view=anonymizeUser"]`)).toHaveCount(0);
      await ctx.close();
    });
  }

  test('admin: delete button visible on archived profile (no compta)', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'admin');
    await page.goto(`/index.php?view=generalData&userid=${ARCHIVED_MEMBER_ID}`);
    await expect(page.locator(`a[href*="view=deleteUser"]`)).toBeVisible();
    await ctx.close();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// UI — view-level access guards (redirect to access-denied message)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('UI — view access guards', () => {
  test('readonly: view=addUser shows access denied', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'readonly');
    await page.goto('/index.php?view=addUser');
    await expect(page.locator('.alert-danger')).toBeVisible();
    await ctx.close();
  });

  test('user: view=deleteUser shows access denied', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'user');
    await page.goto(`/index.php?view=deleteUser&id=${ARCHIVED_MEMBER_ID}`);
    await expect(page.locator('.alert-danger')).toBeVisible();
    await ctx.close();
  });

  test('user: view=mergeUsers shows access denied', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'user');
    await page.goto(`/index.php?view=mergeUsers&a=${ACTIVE_MEMBER_ID}&b=2`);
    await expect(page.locator('.alert-danger')).toBeVisible();
    await ctx.close();
  });

  test('manager: view=anonymizeUser shows access denied', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'manager');
    // User 1 has compta so anonymize link would appear for admin; manager gets blocked
    await page.goto(`/index.php?view=anonymizeUser&id=${ACTIVE_MEMBER_ID}`);
    await expect(page.locator('.alert-danger')).toBeVisible();
    await ctx.close();
  });

  test('manager: view=deleteUser shows access denied', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'manager');
    await page.goto(`/index.php?view=deleteUser&id=${ARCHIVED_MEMBER_ID}`);
    await expect(page.locator('.alert-danger')).toBeVisible();
    await ctx.close();
  });

  test('user: view=tasks shows access denied', async ({ browser }) => {
    const { page, ctx } = await openAs(browser, 'user');
    await page.goto('/index.php?view=tasks');
    await expect(page.locator('.alert-danger')).toBeVisible();
    await ctx.close();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Server enforcement — actions/contacts.php (HTTP 403)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Server — members action guards', () => {
  // readonly blocked by top-level canWrite() guard in contacts.php
  test('readonly: action=updateUser → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'updateUser', id: String(ACTIVE_MEMBER_ID), firstName: 'Hacked' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  // user blocked for manager-level actions
  test('user: action=deactivateUser → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'deactivateUser', id: String(ACTIVE_MEMBER_ID) } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: action=reactivateUser → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'reactivateUser', id: String(ARCHIVED_MEMBER_ID) } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: action=mergeUsers → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'mergeUsers', idA: String(ACTIVE_MEMBER_ID), idB: '2', survivor: 'a', disposal: 'deactivate' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  // user blocked for admin-level actions
  test('user: action=anonymizeUser → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'anonymizeUser', id: String(ACTIVE_MEMBER_ID) } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: action=deleteOrDeactivateUser → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'deleteOrDeactivateUser', id: String(ARCHIVED_MEMBER_ID), dispose: 'delete' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  // manager blocked for admin-only actions
  test('manager: action=anonymizeUser → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'anonymizeUser', id: String(ACTIVE_MEMBER_ID) } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('manager: action=deleteOrDeactivateUser dispose=delete → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'deleteOrDeactivateUser', id: String(ARCHIVED_MEMBER_ID), dispose: 'delete' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  // positive: manager CAN deactivate
  test('manager: action=deactivateUser on active member → not 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'deactivateUser', id: '2' } });
    expect(r.status()).not.toBe(403);
    await api.dispose();
    // Restore: reactivate via admin
    const admin = await apiAs(playwright, 'admin');
    const adminCsrf = await csrfFor(admin);
    await admin.post('/index.php', { form: { csrf: adminCsrf, action: 'reactivateUser', id: '2' } });
    await admin.dispose();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Server enforcement — CSRF guard on POST actions (#69)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Server — CSRF guard', () => {
  // Even an admin (allowed by role) is rejected without a valid CSRF token.
  test('admin: POST action without CSRF token → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'admin');
    const r = await api.post('/index.php', { form: { action: 'deactivateUser', id: '2' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('admin: POST action with a wrong CSRF token → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'admin');
    const r = await api.post('/index.php', { form: { csrf: 'not-a-valid-token', action: 'deactivateUser', id: '2' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  // GET actions are gated too: a forged cross-site GET (e.g. <img src>) driving
  // a $_REQUEST-backed mutation must be rejected without a valid token.
  test('admin: GET action without CSRF token → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'admin');
    const r = await api.get('/index.php?action=deactivateUser&id=2');
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  // Same action WITH a valid token passes the CSRF guard (then role/logic apply).
  test('admin: POST action with a valid CSRF token → not 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'admin');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'deactivateUser', id: '2' } });
    expect(r.status()).not.toBe(403);
    await api.dispose();
    // Restore
    const admin = await apiAs(playwright, 'admin');
    const rc = await csrfFor(admin);
    await admin.post('/index.php', { form: { csrf: rc, action: 'reactivateUser', id: '2' } });
    await admin.dispose();
  });

  // Negative control for the scoping rule: an action= value NOT present in
  // $ACTION_MAP (e.g. the "search" view hint) must NOT be gated — plain GET
  // page loads that carry no token would otherwise 403.
  test('admin: GET with an unmapped action= is NOT gated by CSRF', async ({ playwright }) => {
    const api = await apiAs(playwright, 'admin');
    const r = await api.get('/index.php?view=list&action=search&searchString=Dupont');
    expect(r.status()).not.toBe(403);
    await api.dispose();
  });

  // A rejected CSRF attempt must be traceable in the audit log.
  test('a CSRF rejection is recorded (csrfRejected)', async ({ playwright, page }) => {
    const api = await apiAs(playwright, 'admin');
    const r = await api.post('/index.php', { form: { action: 'deactivateUser', id: '2' } });
    expect(r.status()).toBe(403);
    await api.dispose();

    await page.goto('/index.php?view=settings&tab=audit');
    await expect(page.locator('#tab-audit')).toContainText('csrfRejected', { timeout: 10_000 });
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Server enforcement — attestation downloads (html/attestation_don.php,
// html/attestation_bulk.php) — nominative donation data, Manager+/Admin only
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Server — attestation download guards', () => {
  test('readonly: attestation_don.php → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const r = await api.get(`/attestation_don.php?userid=${ACTIVE_MEMBER_ID}&year=${new Date().getFullYear()}`);
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: attestation_don.php → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const r = await api.get(`/attestation_don.php?userid=${ACTIVE_MEMBER_ID}&year=${new Date().getFullYear()}`);
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('readonly: attestation_bulk.php → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const r = await api.get(`/attestation_bulk.php?year=${new Date().getFullYear()}`);
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('manager: attestation_don.php → not 403 (returns the PDF)', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    const r = await api.get(`/attestation_don.php?userid=${ACTIVE_MEMBER_ID}&year=${new Date().getFullYear()}`);
    expect(r.status()).not.toBe(403);
    expect(r.headers()['content-type']).toContain('application/pdf');
    await api.dispose();
  });

  test('admin: attestation_bulk.php → not 403 (returns the PDF)', async ({ playwright }) => {
    const api = await apiAs(playwright, 'admin');
    const r = await api.get(`/attestation_bulk.php?year=${new Date().getFullYear()}`);
    expect(r.status()).not.toBe(403);
    expect(r.headers()['content-type']).toContain('application/pdf');
    await api.dispose();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Server enforcement — actions/compta.php (HTTP 403)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Server — compta action guards', () => {
  test('readonly: action=addCompta → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: {
      csrf, action: 'addCompta', userid: String(ACTIVE_MEMBER_ID),
      type_id: '1', date: '01/01/2025', libele: 'Hack', sum: '50',
    }});
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('readonly: action=updateCompta → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: {
      csrf, action: 'updateCompta', comptaid: '1', userid: String(ACTIVE_MEMBER_ID),
      type_id: '1', date: '01/01/2025', libele: 'Hack', sum: '99',
    }});
    expect(r.status()).toBe(403);
    await api.dispose();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Server enforcement — actions/suivi.php (HTTP 403)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Server — suivi action guards', () => {
  test('readonly: action=addSuivi → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: {
      csrf, action: 'addSuivi', userid: String(ACTIVE_MEMBER_ID),
      parameter: 'suivi', date: '01/01/2025', value: 'Hack',
    }});
    expect(r.status()).toBe(403);
    await api.dispose();
  });
});

test.describe('Server — task action guards', () => {
  test('readonly: action=addTask → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: {
      csrf, action: 'addTask', userid: String(ACTIVE_MEMBER_ID), title: 'Hack',
    }});
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: action=generateUnpaidCotiTasks → 403 (admin-only)', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'generateUnpaidCotiTasks' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('manager: action=generateUnpaidCotiTasks → 403 (admin-only)', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'generateUnpaidCotiTasks' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('manager: action=generateComptaRecapTasks → 403 (admin-only)', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'generateComptaRecapTasks' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Server enforcement — actions/segments.php (HTTP 403)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Server — group action guards', () => {
  test('readonly: action=assignSegment → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'assignSegment', id: String(ACTIVE_MEMBER_ID), segmentId: '1' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: action=unassignSegment → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'unassignSegment', id: String(ACTIVE_MEMBER_ID), segmentId: '1' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: action=addSegmentCascadeRule → 403 (manager-only)', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const csrf = await csrfFor(api);
    const r = await api.post('/index.php', { form: { csrf, action: 'addSegmentCascadeRule', sourceSegmentId: '1', targetSegmentId: '2' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Server enforcement — REST API /api/contacts (HTTP 403)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Server — REST API /api/contacts guards', () => {
  test('readonly: PUT /api/contacts/{id} → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const r = await api.put(`/api/contacts/${ACTIVE_MEMBER_ID}`, { data: { firstName: 'Hacked' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('readonly: POST /api/contacts → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'readonly');
    const r = await api.post('/api/contacts', { data: { firstName: 'X', lastName: 'Y' } });
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('user: DELETE /api/contacts/{id} with dispose=delete → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const r = await api.delete(`/api/contacts/${ARCHIVED_MEMBER_ID}?dispose=delete`);
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  test('manager: DELETE /api/contacts/{id} with dispose=delete → 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    const r = await api.delete(`/api/contacts/${ARCHIVED_MEMBER_ID}?dispose=delete`);
    expect(r.status()).toBe(403);
    await api.dispose();
  });

  // positive: user CAN update a member via REST API
  test('user: PUT /api/contacts/{id} → not 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'user');
    const r = await api.put(`/api/contacts/${ACTIVE_MEMBER_ID}`, { data: { firstName: 'Alice' } });
    expect(r.status()).not.toBe(403);
    await api.dispose();
  });

  // positive: manager CAN delete (deactivate, not permanent) via REST API
  test('manager: DELETE /api/contacts/{id} (deactivate) → not 403', async ({ playwright }) => {
    const api = await apiAs(playwright, 'manager');
    // Member 2 (Bob) — deactivate only (no dispose=delete)
    const r = await api.delete(`/api/contacts/2`);
    expect(r.status()).not.toBe(403);
    await api.dispose();
    // Restore
    const admin = await apiAs(playwright, 'admin');
    const rc = await csrfFor(admin);
    await admin.post('/index.php', { form: { csrf: rc, action: 'reactivateUser', id: '2' } });
    await admin.dispose();
  });
});
