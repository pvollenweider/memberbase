/**
 * E2E tests — filter lapsed/new donors by contact type (#178)
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

async function csrf(page: any): Promise<string> {
  await page.goto('/index.php');
  return page.evaluate(() => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '');
}

test.describe('Lapsed donors — filter by contact type (#178)', () => {
  test('donors_lapsed.php: contactTypeId narrows the list to that type', async ({ page }) => {
    const year = new Date().getFullYear();
    // Contact 6 "NoCoti Frank" has no compta entries in the seed — give him a
    // donation dated last year only, so he shows up as a genuine lapsed donor.
    const c = await csrf(page);
    const resp = await page.request.post('/index.php', {
      form: { action: 'addCompta', view: 'compta', userid: '6', type_id: '3', date: `01/06/${year - 1}`, libele: 'Don E2E lapsed', sum: '50', csrf: c },
    });
    expect(resp.status()).toBe(200);
    await page.request.patch('/api/contacts/6', { data: { contactTypeId: 4 } }); // 4 = Entreprise

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors');
    await expect(page.locator('button', { hasText: 'Tous les types' }).first()).toBeVisible();
    await expect(page.locator('body')).toContainText('NoCoti');

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors&contactTypeId=4');
    await expect(page.locator('body')).toContainText('NoCoti');

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors&contactTypeId=1');
    await expect(page.locator('body')).not.toContainText('NoCoti');
  });

  test('"Créer segment" button uses the type name instead of "Donateurs" when a type filter is active', async ({ page }) => {
    const year = new Date().getFullYear();
    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors&year=' + year);
    await expect(page.locator('button', { hasText: `Donateurs à relancer ${year}` })).toBeVisible();

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors&year=' + year + '&contactTypeId=4');
    await expect(page.locator('button', { hasText: `Entreprise à relancer ${year}` })).toBeVisible();
    await expect(page.locator('button', { hasText: `Donateurs à relancer ${year}` })).toHaveCount(0);
  });
});

test.describe('New donors — filter by contact type (#178)', () => {
  test('donors_new.php: contactTypeId narrows the list to that type', async ({ page }) => {
    const year = new Date().getFullYear();
    // Fresh contact (not the seed's contact 6, which the lapsed-donor test
    // above already gives a prior-year donation to — that would disqualify
    // it from "new donor" here) with a single this-year donation.
    const contact = await (await page.request.post('/api/contacts', {
      data: { lastName: 'NewDonorTypeFilter E2E', contactTypeId: 3 },
    })).json();
    const uid = contact.data.id;
    const c = await csrf(page);
    const resp = await page.request.post('/index.php', {
      form: { action: 'addCompta', view: 'compta', userid: String(uid), type_id: '3', date: `01/06/${year}`, libele: 'Don E2E new', sum: '50', csrf: c },
    });
    expect(resp.status()).toBe(200);

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors&cohort=new');
    await expect(page.locator('body')).toContainText('NewDonorTypeFilter');

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors&cohort=new&contactTypeId=3');
    await expect(page.locator('body')).toContainText('NewDonorTypeFilter');

    await page.goto('/index.php?view=peopleFinance&tab=lapsedDonors&cohort=new&contactTypeId=1');
    await expect(page.locator('body')).not.toContainText('NewDonorTypeFilter');
  });
});
