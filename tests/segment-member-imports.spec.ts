/**
 * E2E tests — one-off copy imports on the segment edit page
 * (importSegmentMembers, importCotisants, importDonors)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

async function createSegment(page: any, name: string): Promise<number> {
  const resp = await page.request.post('/api/segments', { data: { name } });
  const { data } = await resp.json();
  return data.id;
}

/**
 * POST as application/x-www-form-urlencoded, repeating the key for each value
 * in an array (real `name[]` checkbox semantics). Playwright's `form` option
 * coerces array values via String(arr) (comma-joined) instead of repeating
 * the key, which silently drops all but the first value once PHP casts the
 * combined string with (int) — build the body by hand to avoid that trap.
 */
async function postFormMulti(page: any, fields: Record<string, string | string[]>) {
  const params = new URLSearchParams();
  for (const [key, val] of Object.entries(fields)) {
    for (const v of Array.isArray(val) ? val : [val]) params.append(key, v);
  }
  return page.request.post('/index.php', {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    data: params.toString(),
  });
}

test.describe('Segment edit — import members from other segments', () => {
  test('importSegmentMembers copies members from a source segment', async ({ page }) => {
    // Segment 1 ("Membre 2025") has active members in the seed.
    const targetId = await createSegment(page, 'Import Members Target E2E');

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'importSegmentMembers', id: String(targetId), 'importFrom[]': '1' },
    });
    expect(resp.status()).not.toBe(403);

    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    expect(members.data.length).toBeGreaterThan(0);
  });
});

test.describe('Segment edit — import cotisants of one or more years', () => {
  test('importCotisants copies members who paid a cotisation-type entry that year', async ({ page }) => {
    const targetId = await createSegment(page, 'Import Cotisants Target E2E');
    const year = new Date().getFullYear();

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'importCotisants', id: String(targetId), 'cotis_years[]': String(year) },
    });
    expect(resp.status()).not.toBe(403);

    // Alice (id 1) and Bob (id 2) both paid a cotisation this year per seed.
    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    const ids = members.data.map((m: any) => m.id);
    expect(ids).toContain(1);
    expect(ids).toContain(2);
  });

  test('importCotisants accepts multiple years via checkboxes', async ({ page }) => {
    const targetId = await createSegment(page, 'Import Cotisants Multi-Year E2E');
    const year = new Date().getFullYear();

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await postFormMulti(page, {
      csrf, action: 'importCotisants', id: String(targetId), 'cotis_years[]': [String(year), String(year - 1)],
    });
    expect(resp.status()).not.toBe(403);

    // Alice (id 1, paid current year) and Carol (id 4, lapsed — paid only year-1) per seed.
    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    const ids = members.data.map((m: any) => m.id);
    expect(ids).toContain(1);
    expect(ids).toContain(4);
  });
});

test.describe('Segment edit — import donors of a year', () => {
  test('importDonors copies donors meeting the type/year/minSum filter', async ({ page }) => {
    const targetId = await createSegment(page, 'Import Donors Target E2E');
    const year = new Date().getFullYear();

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: {
        csrf, action: 'importDonors', id: String(targetId),
        donor_compta_type_all: '1', 'donor_years[]': String(year), donor_minsum: '1',
      },
    });
    expect(resp.status()).not.toBe(403);

    // Alice (Don libre) and Bob (Don institutionnel + Don libre) donated this year per seed.
    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    const ids = members.data.map((m: any) => m.id);
    expect(ids).toContain(1);
    expect(ids).toContain(2);
  });

  test('importDonors filters by a specific compta type', async ({ page }) => {
    // compta_type 2 ("Institution") has a single entry this year, for Bob (user 2).
    const targetId = await createSegment(page, 'Import Donors By Type E2E');
    const year = new Date().getFullYear();

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: {
        csrf, action: 'importDonors', id: String(targetId),
        'donor_compta_type[]': '2', 'donor_years[]': String(year), donor_minsum: '1',
      },
    });
    expect(resp.status()).not.toBe(403);

    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    const ids = members.data.map((m: any) => m.id);
    expect(ids).toContain(2);
    expect(ids).not.toContain(1);
  });

  test('importDonors with no type selected imports nothing and warns instead of claiming success', async ({ page }) => {
    const targetId = await createSegment(page, 'Import Donors No Type E2E');
    const year = new Date().getFullYear();

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: {
        csrf, action: 'importDonors', id: String(targetId),
        'donor_years[]': String(year), donor_minsum: '1',
      },
    });
    expect(resp.status()).not.toBe(403);
    expect(await resp.text()).toContain('imported=donors_notype');

    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    expect(members.data.length).toBe(0);

    await page.goto(`/index.php?view=updateSegment&id=${targetId}&imported=donors_notype`);
    await expect(page.locator('.alert-warning', { hasText: 'type de don' })).toBeVisible();
  });

  test('importDonors with no year selected imports nothing and warns instead of claiming success', async ({ page }) => {
    const targetId = await createSegment(page, 'Import Donors No Year E2E');

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: {
        csrf, action: 'importDonors', id: String(targetId),
        donor_compta_type_all: '1', donor_minsum: '1',
      },
    });
    expect(resp.status()).not.toBe(403);
    expect(await resp.text()).toContain('imported=donors_noyear');

    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    expect(members.data.length).toBe(0);

    await page.goto(`/index.php?view=updateSegment&id=${targetId}&imported=donors_noyear`);
    await expect(page.locator('.alert-warning', { hasText: 'année' })).toBeVisible();
  });

  test('importDonors with "toutes les années" ignores the year filter', async ({ page }) => {
    const targetId = await createSegment(page, 'Import Donors All Years E2E');

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: {
        csrf, action: 'importDonors', id: String(targetId),
        donor_compta_type_all: '1', donor_year_all: '1', donor_minsum: '1',
      },
    });
    expect(resp.status()).not.toBe(403);

    // Same donors as the current-year import — seed data only has this year's entries —
    // but the request must succeed with donor_year_all=1 (no date restriction applied).
    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    const ids = members.data.map((m: any) => m.id);
    expect(ids).toContain(1);
    expect(ids).toContain(2);
  });

  test('importDonors accepts multiple years via checkboxes (OR across years)', async ({ page }) => {
    // Seed compta.date is always NOW() (only cotisation_year distinguishes accounting
    // years for cotisation-type rows) — so year-1 has no donor entries. Checking two
    // years must still find the same current-year donors as the single-year case,
    // proving the years are combined with OR rather than narrowing the match.
    const targetId = await createSegment(page, 'Import Donors Multi-Year E2E');
    const year = new Date().getFullYear();

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await postFormMulti(page, {
      csrf, action: 'importDonors', id: String(targetId),
      donor_compta_type_all: '1', 'donor_years[]': [String(year), String(year - 1)], donor_minsum: '1',
    });
    expect(resp.status()).not.toBe(403);

    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    const ids = members.data.map((m: any) => m.id);
    expect(ids).toContain(1);
    expect(ids).toContain(2);
  });

  test('non-manager role cannot import donors', async ({ playwright }) => {
    const targetId = await (async () => {
      const admin = await playwright.request.newContext({
        baseURL: 'http://localhost:8080', storageState: 'tests/.auth/admin.json',
      });
      const resp = await admin.post('/api/segments', { data: { name: 'Import Donors Guard E2E' } });
      const { data } = await resp.json();
      await admin.dispose();
      return data.id;
    })();

    const api = await playwright.request.newContext({
      baseURL: 'http://localhost:8080', storageState: 'tests/.auth/user.json',
    });
    const html = await (await api.get('/index.php')).text();
    const csrf = (html.match(/name="csrf-token" content="([^"]+)"/) ?? [])[1] ?? '';
    const resp = await api.post('/index.php', {
      form: { csrf, action: 'importDonors', id: String(targetId), donor_compta_type_all: '1', 'donor_years[]': '2026', donor_minsum: '1' },
    });
    expect(resp.status()).toBe(403);
    await api.dispose();
  });
});
