/**
 * E2E tests — vue/API parity for virtual filters
 *
 * The virtual filters (negative team IDs) are resolved by the shared
 * MemberFilter class (issue #57). The members list view and /api/members
 * MUST return the same member set for every filter. This spec locks that
 * invariant: it compares the API total against the number of rows the
 * view renders, without hardcoding counts (works on any seed).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';
import * as path from 'path';

const ADMIN_STATE = path.resolve(__dirname, '.auth/admin.json');

// Keep in sync with FILTER_* constants in html/includes/lib/bootstrap.php
const VIRTUAL_FILTERS: Record<string, number> = {
  FILTER_ALL_EXCEPT_ARCHIVES:  -3,
  FILTER_UNPAID_COTI_CURRENT:  -4,
  FILTER_UNPAID_COTI_3Y:       -3333,
  FILTER_NO_ACTIVITY_10Y:      -5555,
  FILTER_NON_INSTIT_LAST_YEAR: -6666,
};

test.use({ storageState: ADMIN_STATE });

for (const [name, filterId] of Object.entries(VIRTUAL_FILTERS)) {
  test(`${name} (${filterId}): view row count matches API total`, async ({ page, request }) => {
    const res = await request.get(`/api/members?team=${filterId}&limit=1`);
    expect(res.ok()).toBeTruthy();
    const apiTotal = (await res.json()).meta.total;

    await page.goto(`/index.php?team=${filterId}`);
    const viewRows = await page.locator('tbody tr.ca-row-link').count();

    expect(viewRows, `view rows should equal API total for ${name}`).toBe(apiTotal);
  });
}
