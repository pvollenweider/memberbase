/**
 * TipTap rich text editor wiring for the member comment field.
 * Loaded as an ES module; re-initialized on every htmx swap.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// Self-hosted bundle (no external CDN) — see html/js/vendor/README.tiptap.md
// to rebuild. Removing the esm.sh dependency lets the CSP tighten (#93).
import { Editor, StarterKit } from './vendor/tiptap.bundle.js';

function initTiptap(root) {
    var el = (root && root.querySelector) ? root.querySelector('#tiptap-comment') : document.getElementById('tiptap-comment');
    if (!el || el._tt) return;
    var hidden = document.getElementById('comment');
    var editor = new Editor({
        element: el,
        extensions: [StarterKit],
        content: hidden ? hidden.value : '',
        onUpdate: function(_ref) {
            if (hidden) hidden.value = _ref.editor.getHTML();
        },
        onSelectionUpdate: function(_ref) { updateToolbar(_ref.editor); },
        onTransaction: function(_ref) { updateToolbar(_ref.editor); },
    });
    el._tt = editor;

    // Toolbar button wiring
    var wrap = el.closest('.tiptap-wrap');
    if (wrap) {
        wrap.querySelectorAll('.tt-btn').forEach(function(btn) {
            btn.addEventListener('mousedown', function(e) {
                e.preventDefault();
                var cmd = btn.dataset.tt;
                if      (cmd === 'bold')        editor.chain().focus().toggleBold().run();
                else if (cmd === 'italic')      editor.chain().focus().toggleItalic().run();
                else if (cmd === 'bulletList')  editor.chain().focus().toggleBulletList().run();
                else if (cmd === 'orderedList') editor.chain().focus().toggleOrderedList().run();
                else if (cmd === 'undo')        editor.chain().focus().undo().run();
                else if (cmd === 'redo')        editor.chain().focus().redo().run();
            });
        });
    }
}

function updateToolbar(editor) {
    var wrap = document.querySelector('.tiptap-wrap');
    if (!wrap) return;
    wrap.querySelectorAll('.tt-btn[data-tt]').forEach(function(btn) {
        var cmd = btn.dataset.tt;
        var active = false;
        if (cmd === 'bold')        active = editor.isActive('bold');
        else if (cmd === 'italic') active = editor.isActive('italic');
        else if (cmd === 'bulletList')  active = editor.isActive('bulletList');
        else if (cmd === 'orderedList') active = editor.isActive('orderedList');
        btn.classList.toggle('is-active', active);
    });
}

function destroyTiptap(root) {
    var el = (root && root.querySelector) ? root.querySelector('#tiptap-comment') : document.getElementById('tiptap-comment');
    if (el && el._tt) { el._tt.destroy(); el._tt = null; }
}

initTiptap(document);
document.addEventListener('htmx:beforeSwap', function(e) { destroyTiptap(e.detail.target); });
document.addEventListener('htmx:afterSwap',  function(e) { initTiptap(e.detail.target); });
