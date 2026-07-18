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

test.describe('Segment edit — import cotisants of a year', () => {
  test('importCotisants copies members who paid a cotisation-type entry that year', async ({ page }) => {
    const targetId = await createSegment(page, 'Import Cotisants Target E2E');
    const year = new Date().getFullYear();

    await page.goto(`/index.php?view=updateSegment&id=${targetId}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const resp = await page.request.post('/index.php', {
      form: { csrf, action: 'importCotisants', id: String(targetId), cotis_year: String(year) },
    });
    expect(resp.status()).not.toBe(403);

    // Alice (id 1) and Bob (id 2) both paid a cotisation this year per seed.
    const members = await (await page.request.get(`/api/segments/${targetId}/members`)).json();
    const ids = members.data.map((m: any) => m.id);
    expect(ids).toContain(1);
    expect(ids).toContain(2);
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
        donor_type: 'all', donor_year: String(year), donor_minsum: '1',
      },
    });
    expect(resp.status()).not.toBe(403);

    // Alice (Don libre) and Bob (Don institutionnel + Don libre) donated this year per seed.
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
      form: { csrf, action: 'importDonors', id: String(targetId), donor_type: 'all', donor_year: '2026', donor_minsum: '1' },
    });
    expect(resp.status()).toBe(403);
    await api.dispose();
  });
});
