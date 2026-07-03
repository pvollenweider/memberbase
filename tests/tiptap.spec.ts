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
    await page.goto('/index.php?view=addUser');
    // TipTap turns #tiptap-comment into a ProseMirror contenteditable once the
    // vendored bundle loads and initialises (no external CDN).
    await expect(page.locator('#tiptap-comment .ProseMirror')).toBeVisible({ timeout: 10_000 });
  });
});
