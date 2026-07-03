/**
 * E2E test — TipTap rich-text editor is served self-hosted (#93)
 *
 * Guards against the self-hosted bundle (html/js/vendor/tiptap.bundle.js)
 * breaking: if the module fails to load, no ProseMirror editor mounts.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { test, expect } from '@playwright/test';

test.describe('TipTap editor (self-hosted)', () => {
  test('rich-text editor mounts on the member form', async ({ page }) => {
    // The comment editor lives on the member "general data" view (member 1 = seed).
    await page.goto('/index.php?view=generalData&userid=1');
    // TipTap turns #tiptap-comment into a ProseMirror contenteditable once the
    // vendored bundle loads and initialises (no external CDN). Asserting the
    // element exists proves the local module loaded.
    await expect(page.locator('#tiptap-comment .ProseMirror')).toHaveCount(1, { timeout: 10_000 });
  });
});
