# Contributing to MemberBase

Contributions are welcome — bug fixes, new features, documentation improvements, translations.

## Getting started

1. Fork the repo and clone it locally
2. Set up the dev environment with Docker:
   ```bash
   make up          # start PHP + MariaDB containers
   make migrate     # apply any pending DB migrations
   make seed        # load the test fixture (optional)
   ```
3. Run the installer at `http://localhost:8080/install.php` (fresh install only)
4. Make your changes on a feature branch

## Stack

- PHP 8.2, PDO/MariaDB 11
- Bootstrap 5.3, htmx 2, Alpine.js
- TipTap rich-text editor (self-hosted bundle at `html/js/vendor/tiptap.bundle.js`)
- Font Awesome 6 (self-hosted)
- No frontend build step for the app itself — plain PHP + vanilla JS

## Guidelines

### Code style

- **No credentials** — never commit `conf/db.php` or any real passwords
- **Stay generic** — MemberBase is not tied to any specific organisation; avoid hardcoding org-specific logic
- **PHP style** — PDO prepared statements everywhere, `htmlspecialchars()` on all output, no raw `$_GET`/`$_POST` without validation
- **Comments in English** — all inline code comments (PHP, JS, CSS, SQL) must be in English regardless of the UI language
- **No hardcoded labels** — every user-visible string goes through `html/locales/resources_fr.php` (`$GLOBAL['key']`). Add the key there, reference it in the view

### CSRF

Every state-changing POST is checked against a session CSRF token by `actions.php` before dispatch. Propagation is automatic for htmx requests and native `<form method="post">` elements via `app.js`. If you add a raw `fetch()` POST, pass the header manually:

```js
headers: { 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '' }
```

### Database migrations

Every schema change must be a numbered SQL file under `html/migrations/`:

```
html/migrations/NNNN_short_description.sql
```

- Use the next available number
- Write migrations idempotently when possible (`ADD COLUMN IF NOT EXISTS`)
- Never edit a migration once it has been committed — create a new one
- The runner applies them in filename order and tracks state in `schema_migrations`

```bash
make migrate          # apply pending migrations
make migrate-status   # view applied / pending
```

### JavaScript / dirty-form guard

`html/js/app.js` contains a global dirty-form guard. Follow these rules to avoid false "unsaved changes" prompts:

- Set `window.__dirtyOverride = true` before any programmatic `window.location = ...`
- Add `data-no-dirty` on any `<select>` or `<input>` used for navigation/filtering (not actual form data)

### Commits

- Short imperative subject line, present tense (`fix modal backdrop`, not `fixed`)
- One concern per commit; one concern per PR
- Large refactors should be discussed in an issue first

## Tests

Two complementary test suites — run both before opening a PR:

### E2E (Playwright)

```bash
make test          # full Playwright suite (requires Docker stack up)
```

Specs live in `tests/*.spec.ts`. Each spec uses the shared seed DB (`make seed`). Add a spec for any new user-visible behaviour.

### Unit (PHPUnit)

```bash
make test-unit     # PHPUnit suite (composer install required)
```

Pure-PHP unit tests live in `tests/unit/`. They cover business logic with no DB dependency. Testable pure functions live in `html/includes/lib/pure.php` and `html/includes/lib/import_fields.php`. Do not introduce PDO dependencies in these files.

### CI

Four jobs run on every push / PR:

| Job | What it checks |
|---|---|
| `e2e` | Full Playwright browser suite |
| `phpunit` | Unit tests (PHP 8.2) |
| `upgrade` | Legacy DB → migrate → schema convergence |
| `backup-restore` | seed → dump → drop → restore → verify |

All four must be green before merging.

## Reporting bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.yml). Include PHP/MariaDB versions and steps to reproduce.

## Suggesting features

Open a [feature request](.github/ISSUE_TEMPLATE/feature_request.yml) before starting work on a significant change — it avoids duplicate effort.

## Licence

By contributing, you agree your code is released under the [AGPL-3.0](LICENSE) licence.
