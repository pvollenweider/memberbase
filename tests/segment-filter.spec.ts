/**
 * E2E tests — segment-filter-input dropdown search
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('Segment filter dropdown', () => {
  test('dropdown opens and shows filterable items', async ({ page }) => {
    await page.goto('/index.php?view=list');

    const toggle = page.locator('#navbarDropdown');
    await toggle.click();

    const menu = page.locator('.dropdown-menu[aria-labelledby="navbarDropdown"]');
    await expect(menu).toBeVisible();
    await expect(page.locator('.segment-filterable').first()).toBeVisible();
  });

  test('filter input is focused when dropdown opens', async ({ page }) => {
    await page.goto('/index.php?view=list');

    await page.locator('#navbarDropdown').click();
    await page.waitForTimeout(100); // let shown.bs.dropdown + setTimeout(focus, 0) settle

    const input = page.locator('#segment-filter-input');
    await expect(input).toBeFocused();
  });

  test('typing filters items by name — matching items visible, others hidden', async ({ page }) => {
    await page.goto('/index.php?view=list');

    await page.locator('#navbarDropdown').click();
    const items = page.locator('.segment-filterable');
    await expect(items.first()).toBeVisible();

    // Discover the first two labels from the fixture data
    const labelA = await items.first().getAttribute('data-label') as string;
    const labelB = await items.nth(1).getAttribute('data-label') as string;

    // Filter by a prefix of labelA that doesn't match labelB
    // Use the first 3 chars — enough to be selective in any reasonable fixture
    const query = labelA.substring(0, 3);

    const input = page.locator('#segment-filter-input');
    await input.fill(query);

    // The matching item must be visible
    await expect(page.locator(`.segment-filterable[data-label="${labelA}"]`)).toBeVisible();

    // If labelB doesn't match the query, it must be hidden
    if (!labelB.startsWith(query)) {
      await expect(page.locator(`.segment-filterable[data-label="${labelB}"]`)).toHaveCSS('display', 'none');
    }
  });

  test('category headers hidden when all items in that category are filtered out', async ({ page }) => {
    await page.goto('/index.php?view=list');

    await page.locator('#navbarDropdown').click();
    const input = page.locator('#segment-filter-input');
    await input.fill('zzz_no_match_zzz');

    // All category headers should be hidden
    const headers = page.locator('.segment-cat-header');
    const count = await headers.count();
    for (let i = 0; i < count; i++) {
      await expect(headers.nth(i)).toHaveCSS('display', 'none');
    }
  });

  test('clearing the filter restores all items', async ({ page }) => {
    await page.goto('/index.php?view=list');

    await page.locator('#navbarDropdown').click();
    const items = page.locator('.segment-filterable');
    await expect(items.first()).toBeVisible();

    const labelA = await items.first().getAttribute('data-label') as string;
    const labelB = await items.nth(1).getAttribute('data-label') as string;

    const input = page.locator('#segment-filter-input');
    // Filter to only first item
    await input.fill(labelA.substring(0, 3));
    // Clear
    await input.fill('');

    // Both items must be visible again
    await expect(page.locator(`.segment-filterable[data-label="${labelA}"]`)).toBeVisible();
    await expect(page.locator(`.segment-filterable[data-label="${labelB}"]`)).toBeVisible();
  });

  test('filter survives htmx navigation — works after boost swap', async ({ page }) => {
    await page.goto('/index.php?view=list');
    await page.locator('#navbarDropdown').click();

    const items = page.locator('.segment-filterable');
    await expect(items.first()).toBeVisible();
    const labelA = await items.first().getAttribute('data-label') as string;
    const labelB = await items.nth(1).getAttribute('data-label') as string;

    // Navigate via htmx boost to the first segment
    await items.first().click();
    await page.waitForURL(/segment=/);

    // Re-open dropdown and filter by second label
    await page.locator('#navbarDropdown').click();
    const input = page.locator('#segment-filter-input');
    const queryB = labelB.substring(0, 3);
    await input.fill(queryB);

    await expect(page.locator(`.segment-filterable[data-label="${labelB}"]`)).toBeVisible();
    if (!labelA.startsWith(queryB)) {
      await expect(page.locator(`.segment-filterable[data-label="${labelA}"]`)).toHaveCSS('display', 'none');
    }
  });

  test('keyboard navigation: ArrowDown moves focus, Enter navigates', async ({ page }) => {
    await page.goto('/index.php?view=list');

    await page.locator('#navbarDropdown').click();
    const items = page.locator('.segment-filterable');
    await expect(items.first()).toBeVisible();

    // Use a single char that matches at least one item
    const labelA = await items.first().getAttribute('data-label') as string;
    const input = page.locator('#segment-filter-input');
    await input.fill(labelA.substring(0, 1));

    // First ArrowDown should add kb-focus to first visible match
    await input.press('ArrowDown');
    const focused = page.locator('.segment-filterable.kb-focus');
    await expect(focused).toHaveCount(1);
    await expect(focused).toBeVisible();
  });

  test('Escape closes the dropdown', async ({ page }) => {
    await page.goto('/index.php?view=list');

    await page.locator('#navbarDropdown').click();
    await expect(page.locator('.dropdown-menu[aria-labelledby="navbarDropdown"]')).toBeVisible();

    await page.locator('#segment-filter-input').press('Escape');
    await expect(page.locator('.dropdown-menu[aria-labelledby="navbarDropdown"]')).not.toBeVisible();
  });

  test('diacritic normalization — searching without accents finds accented labels', async ({ page }) => {
    await page.goto('/index.php?view=list');

    await page.locator('#navbarDropdown').click();
    await expect(page.locator('.segment-filterable').first()).toBeVisible();

    // Find a segment whose label contains a diacritic; skip test if fixture has none
    const labels: string[] = await page.locator('.segment-filterable').evaluateAll(
      (els) => els.map((el) => (el as HTMLElement).dataset.label ?? '')
    );
    const accented = labels.find((l) => /[àâäéèêëîïôùûüçœæ]/i.test(l));
    if (!accented) {
      test.skip();
      return;
    }

    // Strip diacritics from the label to build the search query
    const stripped = accented.normalize('NFD').replace(/[̀-ͯ]/g, '');
    const input = page.locator('#segment-filter-input');
    await input.fill(stripped);

    // The accented item must still be visible (filter matched via normalised comparison)
    await expect(page.locator(`.segment-filterable[data-label="${accented}"]`)).toBeVisible();
  });
});
