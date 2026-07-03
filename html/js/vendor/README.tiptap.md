# tiptap.bundle.js

Self-hosted bundle of the TipTap rich-text editor (member comment field),
replacing the former `https://esm.sh/@tiptap/...` CDN imports so the front has
**no external origin** (lets the CSP tighten — see #93).

- Exposes `Editor` (from `@tiptap/core`) and `StarterKit` (from
  `@tiptap/starter-kit`), imported by `../tiptap-editor.js`.
- Committed as a vendored asset (like the other `js/vendor/` files); the runtime
  does **not** need npm.

## Rebuild (only when bumping TipTap)

```bash
mkdir tt && cd tt
npm init -y
npm install @tiptap/core@^2 @tiptap/starter-kit@^2 esbuild
cat > entry.js <<'EOF'
export { Editor } from '@tiptap/core';
export { default as StarterKit } from '@tiptap/starter-kit';
EOF
./node_modules/.bin/esbuild entry.js --bundle --format=esm --minify \
  --legal-comments=none --outfile=tiptap.bundle.js
# then copy tiptap.bundle.js over html/js/vendor/tiptap.bundle.js
```

Built with @tiptap/core & @tiptap/starter-kit **2.27.2**. After a rebuild,
check the member form still shows the editor (a `.ProseMirror` element appears);
`tests/tiptap.spec.ts` covers this in CI.
