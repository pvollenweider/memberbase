/**
 * E2E tests — JSON REST API endpoints
 *
 * Uses Playwright `request` fixture (inherits storageState → admin session).
 * For the auth guard test, a fresh context is created via `playwright.request`.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:8080';

// ── Auth guard ────────────────────────────────────────────────────────────────

test('API returns 401 without session', async ({ playwright }) => {
  const unauth = await playwright.request.newContext({ baseURL: BASE, storageState: { cookies: [], origins: [] } });
  const resp = await unauth.get('/api/contacts');
  expect(resp.status()).toBe(401);
  const body = await resp.json();
  expect(body).toHaveProperty('error');
  await unauth.dispose();
});

// ── GET /api/compta-types ────────────────────────────────────────────────────

test.describe('compta-types API', () => {
  test('GET /api/compta-types — returns list', async ({ request }) => {
    const resp = await request.get('/api/compta-types');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(1);
    expect(body.data[0]).toMatchObject({
      id:                     expect.any(Number),
      label:                  expect.any(String),
      sortOrder:              expect.any(Number),
      isCotisation:           expect.any(Boolean),
      isExcludedFromDonation: expect.any(Boolean),
    });
  });

  test('POST /api/compta-types — 405 Method Not Allowed', async ({ request }) => {
    const resp = await request.post('/api/compta-types', { data: {} });
    expect(resp.status()).toBe(405);
  });
});

// ── /api/contacts ─────────────────────────────────────────────────────────────

test.describe.serial('members API', () => {
  let createdId: number;

  test('GET /api/contacts — paginated list', async ({ request }) => {
    const resp = await request.get('/api/contacts');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.meta).toMatchObject({ page: 1, limit: 25 });
    expect(body.meta.total).toBeGreaterThanOrEqual(2);
  });

  test('GET /api/contacts?search=Dupont — search filter', async ({ request }) => {
    const resp = await request.get('/api/contacts?search=Dupont');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.length).toBeGreaterThanOrEqual(1);
    expect(body.data[0].lastName).toBe('Dupont');
  });

  test('GET /api/contacts?search=zzznomatch — empty result', async ({ request }) => {
    const resp = await request.get('/api/contacts?search=zzznomatch');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data).toEqual([]);
    expect(body.meta.total).toBe(0);
  });

  test('POST /api/contacts — creates a member', async ({ request }) => {
    const resp = await request.post('/api/contacts', {
      data: { lastName: 'ApiTest', firstName: 'Playwright', email: 'apitest@example.com' },
    });
    expect(resp.status()).toBe(201);
    const body = await resp.json();
    expect(body.data).toMatchObject({ lastName: 'ApiTest', firstName: 'Playwright' });
    createdId = body.data.id;
  });

  test('POST /api/contacts — 422 when lastName missing', async ({ request }) => {
    const resp = await request.post('/api/contacts', { data: { firstName: 'NoName' } });
    expect(resp.status()).toBe(422);
  });

  // CSRF hardening (#89): a mutation without Content-Type application/json is
  // rejected (a cross-site "simple" POST can't set that header without preflight).
  test('POST /api/contacts — 415 when body is not application/json', async ({ request }) => {
    const resp = await request.post('/api/contacts', {
      headers: { 'Content-Type': 'text/plain' },
      data: JSON.stringify({ lastName: 'X', firstName: 'Y' }),
    });
    expect(resp.status()).toBe(415);
  });

  test('GET /api/contacts/{id} — returns created member', async ({ request }) => {
    const resp = await request.get(`/api/contacts/${createdId}`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.id).toBe(createdId);
    expect(body.data.lastName).toBe('ApiTest');
    expect(body.data.firstName).toBe('Playwright');
  });

  test('GET /api/contacts/999999 — 404 for unknown id', async ({ request }) => {
    const resp = await request.get('/api/contacts/999999');
    expect(resp.status()).toBe(404);
  });

  test('PUT /api/contacts/{id} — updates a field', async ({ request }) => {
    const resp = await request.put(`/api/contacts/${createdId}`, {
      data: { firstName: 'Updated' },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.firstName).toBe('Updated');
    expect(body.data.lastName).toBe('ApiTest');
  });

  test('GET /api/contacts/{id}/groups — empty for new member', async ({ request }) => {
    const resp = await request.get(`/api/contacts/${createdId}/groups`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data).toEqual([]);
  });

  test('GET /api/contacts/1/groups — seeded member has 2 groups', async ({ request }) => {
    const resp = await request.get('/api/contacts/1/groups');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.length).toBeGreaterThanOrEqual(2);
    expect(body.data[0]).toMatchObject({ id: expect.any(Number), name: expect.any(String) });
  });

  test('DELETE /api/contacts/{id} — deactivates member (204)', async ({ request }) => {
    const resp = await request.delete(`/api/contacts/${createdId}`);
    expect(resp.status()).toBe(204);
  });

  test('GET /api/contacts — deactivated member absent from list', async ({ request }) => {
    const resp = await request.get('/api/contacts?search=ApiTest');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.find((m: { id: number }) => m.id === createdId)).toBeUndefined();
  });
});

// ── /api/compta ───────────────────────────────────────────────────────────────

test.describe.serial('compta API', () => {
  const MEMBER_ID = 1;
  const TYPE_ID   = 1;
  let createdId: number;

  test('GET /api/compta — 400 without memberId', async ({ request }) => {
    const resp = await request.get('/api/compta');
    expect(resp.status()).toBe(400);
  });

  test('GET /api/compta?memberId=1 — returns list', async ({ request }) => {
    const resp = await request.get(`/api/compta?memberId=${MEMBER_ID}`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(1);
    expect(body.data[0]).toMatchObject({ id: expect.any(Number), memberId: MEMBER_ID });
  });

  test('POST /api/compta — creates entry', async ({ request }) => {
    const resp = await request.post('/api/compta', {
      data: { memberId: MEMBER_ID, typeId: TYPE_ID, date: '2025-03-01', amount: 120 },
    });
    expect(resp.status()).toBe(201);
    const body = await resp.json();
    expect(body.data).toMatchObject({ memberId: MEMBER_ID, amount: 120, date: '2025-03-01' });
    createdId = body.data.id;
  });

  test('POST /api/compta — 422 when amount missing', async ({ request }) => {
    const resp = await request.post('/api/compta', {
      data: { memberId: MEMBER_ID, typeId: TYPE_ID, date: '2025-03-01' },
    });
    expect(resp.status()).toBe(422);
  });

  test('POST /api/compta — 422 for unknown typeId', async ({ request }) => {
    const resp = await request.post('/api/compta', {
      data: { memberId: MEMBER_ID, typeId: 9999, date: '2025-03-01', amount: 50 },
    });
    expect(resp.status()).toBe(422);
  });

  test('GET /api/compta/{id} — returns created entry', async ({ request }) => {
    const resp = await request.get(`/api/compta/${createdId}`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.id).toBe(createdId);
    expect(body.data.amount).toBe(120);
  });

  test('PUT /api/compta/{id} — updates label', async ({ request }) => {
    const resp = await request.put(`/api/compta/${createdId}`, {
      data: { label: 'Updated label' },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.label).toBe('Updated label');
  });

  test('GET /api/compta?memberId=1&year=2025 — year filter', async ({ request }) => {
    const resp = await request.get(`/api/compta?memberId=${MEMBER_ID}&year=2025`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.every((e: { date: string }) => e.date?.startsWith('2025'))).toBe(true);
  });

  test('DELETE /api/compta/{id} — deletes entry (204)', async ({ request }) => {
    const resp = await request.delete(`/api/compta/${createdId}`);
    expect(resp.status()).toBe(204);
  });

  test('GET /api/compta/{id} — 404 after delete', async ({ request }) => {
    const resp = await request.get(`/api/compta/${createdId}`);
    expect(resp.status()).toBe(404);
  });
});

// ── /api/suivi ────────────────────────────────────────────────────────────────

test.describe.serial('suivi API', () => {
  const MEMBER_ID = 1;
  let createdId: number;

  test('GET /api/suivi — 400 without memberId', async ({ request }) => {
    const resp = await request.get('/api/suivi');
    expect(resp.status()).toBe(400);
  });

  test('GET /api/suivi?memberId=1 — returns list', async ({ request }) => {
    const resp = await request.get(`/api/suivi?memberId=${MEMBER_ID}`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('POST /api/suivi — creates entry', async ({ request }) => {
    const resp = await request.post('/api/suivi', {
      data: { memberId: MEMBER_ID, date: '2025-06-15', note: 'API test note' },
    });
    expect(resp.status()).toBe(201);
    const body = await resp.json();
    expect(body.data).toMatchObject({ memberId: MEMBER_ID, note: 'API test note', date: '2025-06-15' });
    createdId = body.data.id;
  });

  test('POST /api/suivi — 422 when note missing', async ({ request }) => {
    const resp = await request.post('/api/suivi', {
      data: { memberId: MEMBER_ID, date: '2025-06-15' },
    });
    expect(resp.status()).toBe(422);
  });

  test('GET /api/suivi/{id} — returns created entry', async ({ request }) => {
    const resp = await request.get(`/api/suivi/${createdId}`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.id).toBe(createdId);
    expect(body.data.note).toBe('API test note');
  });

  test('PUT /api/suivi/{id} — updates note', async ({ request }) => {
    const resp = await request.put(`/api/suivi/${createdId}`, {
      data: { note: 'Updated note' },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.note).toBe('Updated note');
  });

  test('PUT /api/suivi/{id} — updates date', async ({ request }) => {
    const resp = await request.put(`/api/suivi/${createdId}`, {
      data: { date: '2025-09-01' },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.date).toBe('2025-09-01');
  });

  test('DELETE /api/suivi/{id} — deletes entry (204)', async ({ request }) => {
    const resp = await request.delete(`/api/suivi/${createdId}`);
    expect(resp.status()).toBe(204);
  });

  test('GET /api/suivi/{id} — 404 after delete', async ({ request }) => {
    const resp = await request.get(`/api/suivi/${createdId}`);
    expect(resp.status()).toBe(404);
  });
});

// ── /api/groups ───────────────────────────────────────────────────────────────

test.describe.serial('groups API', () => {
  let createdId: number;

  test('GET /api/groups — returns seeded groups', async ({ request }) => {
    const resp = await request.get('/api/groups');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(2);
    expect(body.data[0]).toMatchObject({
      id:          expect.any(Number),
      name:        expect.any(String),
      hidden:      expect.any(Boolean),
      memberCount: expect.any(Number),
    });
  });

  test('GET /api/groups/1 — single group with memberCount', async ({ request }) => {
    const resp = await request.get('/api/groups/1');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.id).toBe(1);
    expect(body.data.memberCount).toBeGreaterThanOrEqual(2);
  });

  test('GET /api/groups/999 — 404 for unknown group', async ({ request }) => {
    const resp = await request.get('/api/groups/999');
    expect(resp.status()).toBe(404);
  });

  test('GET /api/groups/1/members — returns seeded members', async ({ request }) => {
    const resp = await request.get('/api/groups/1/members');
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.length).toBeGreaterThanOrEqual(2);
    expect(body.data[0]).toMatchObject({ id: expect.any(Number), lastName: expect.any(String) });
  });

  test('POST /api/groups — creates a group', async ({ request }) => {
    const resp = await request.post('/api/groups', { data: { name: 'API Test Group' } });
    expect(resp.status()).toBe(201);
    const body = await resp.json();
    expect(body.data).toMatchObject({ name: 'API Test Group', hidden: false, memberCount: 0 });
    createdId = body.data.id;
  });

  test('POST /api/groups — 422 when name missing', async ({ request }) => {
    const resp = await request.post('/api/groups', { data: {} });
    expect(resp.status()).toBe(422);
  });

  test('GET /api/groups/{id} — returns created group', async ({ request }) => {
    const resp = await request.get(`/api/groups/${createdId}`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.id).toBe(createdId);
    expect(body.data.name).toBe('API Test Group');
  });

  test('PUT /api/groups/{id} — renames group', async ({ request }) => {
    const resp = await request.put(`/api/groups/${createdId}`, {
      data: { name: 'API Test Group Renamed' },
    });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.name).toBe('API Test Group Renamed');
  });

  test('PUT /api/groups/{id} — toggles hidden', async ({ request }) => {
    const resp = await request.put(`/api/groups/${createdId}`, { data: { hidden: true } });
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.hidden).toBe(true);
  });

  test('POST /api/groups/{id}/members — adds member 1', async ({ request }) => {
    const resp = await request.post(`/api/groups/${createdId}/members`, { data: { memberId: 1 } });
    expect(resp.status()).toBe(204);
  });

  test('POST /api/groups/{id}/members — idempotent (INSERT IGNORE)', async ({ request }) => {
    const resp = await request.post(`/api/groups/${createdId}/members`, { data: { memberId: 1 } });
    expect(resp.status()).toBe(204);
  });

  test('GET /api/groups/{id}/members — member 1 listed', async ({ request }) => {
    const resp = await request.get(`/api/groups/${createdId}/members`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.find((m: { id: number }) => m.id === 1)).toBeDefined();
  });

  test('DELETE /api/groups/1 — 409 when group has members', async ({ request }) => {
    const resp = await request.delete('/api/groups/1');
    expect(resp.status()).toBe(409);
  });

  test('DELETE /api/groups/{id}/members — removes member 1', async ({ request }) => {
    const resp = await request.delete(`/api/groups/${createdId}/members`, { data: { memberId: 1 } });
    expect(resp.status()).toBe(204);
  });

  test('GET /api/groups/{id}/members — empty after removal', async ({ request }) => {
    const resp = await request.get(`/api/groups/${createdId}/members`);
    expect(resp.status()).toBe(200);
    const body = await resp.json();
    expect(body.data.find((m: { id: number }) => m.id === 1)).toBeUndefined();
  });

  test('DELETE /api/groups/{id} — deletes empty group (204)', async ({ request }) => {
    const resp = await request.delete(`/api/groups/${createdId}`);
    expect(resp.status()).toBe(204);
  });

  test('GET /api/groups/{id} — 404 after delete', async ({ request }) => {
    const resp = await request.get(`/api/groups/${createdId}`);
    expect(resp.status()).toBe(404);
  });
});
