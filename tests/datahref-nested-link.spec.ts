/**
 * E2E test — datahref2.jquery.js plugin: nested links inside a clickable row.
 *
 * Regression: several views (compta_list, donors_summary, suivi_list,
 * compta_last_entry) render a "clickable row" (class ca-row-link,
 * data-href="...") that also contains its own real links or buttons (e.g.
 * the donation attestation PDF icon in donors_summary.php). The vendor
 * datahref plugin bound a click handler directly on the row and always
 * called preventDefault() + window.open(row's own href), hijacking clicks
 * that bubbled up from a nested <a>, so the PDF link was never reachable.
 *
 * This test loads the actual served jQuery + datahref2.jquery.js against a
 * minimal static fixture — independent of app data/DB — so it exercises the
 * real plugin code without depending on seed data producing a donor row.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('datahref plugin — nested link/button', () => {
  test('clicking a nested <a> opens its own href, not the row href', async ({ page, context }) => {
    await page.goto('/js/vendor/jquery-3.7.1.min.js'); // warm origin, avoids about:blank script loading quirks
    await page.setContent(`
      <table>
        <tbody>
          <tr class="ca-row-link" data-href="/index.php?view=rowTarget" style="cursor:pointer">
            <td>Name</td>
            <td><a href="/index.php?view=nestedLink" target="_blank" id="nested-link">PDF</a></td>
          </tr>
        </tbody>
      </table>
      <script src="/js/vendor/jquery-3.7.1.min.js"></script>
      <script src="/js/vendor/datahref2.jquery.js"></script>
      <script>$('table').datahref();</script>
    `);

    const [popup] = await Promise.all([
      context.waitForEvent('page'),
      page.locator('#nested-link').click(),
    ]);
    await popup.waitForLoadState('domcontentloaded');
    expect(popup.url()).toContain('view=nestedLink');
    await popup.close();

    // The original page must not have navigated to the row's own href.
    expect(page.url()).not.toContain('view=rowTarget');
  });

  test('clicking the row itself (not the link) still navigates to the row href', async ({ page }) => {
    // Default plugin target is '_self' (same tab) unless overridden.
    await page.goto('/js/vendor/jquery-3.7.1.min.js');
    await page.setContent(`
      <table>
        <tbody>
          <tr class="ca-row-link" data-href="/js/vendor/jquery-3.7.1.min.js?rowTarget=1" style="cursor:pointer">
            <td id="plain-cell">Name</td>
            <td><a href="/index.php?view=nestedLink" target="_blank">PDF</a></td>
          </tr>
        </tbody>
      </table>
      <script src="/js/vendor/jquery-3.7.1.min.js"></script>
      <script src="/js/vendor/datahref2.jquery.js"></script>
      <script>$('table').datahref();</script>
    `);

    await page.locator('#plain-cell').click();
    await page.waitForURL(/rowTarget=1/);
    expect(page.url()).toContain('rowTarget=1');
  });
});
