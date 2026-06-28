# Contributing to MemberBase

Contributions are welcome — bug fixes, new features, documentation improvements, translations.

## Getting started

1. Fork the repo and clone it locally
2. Set up the dev environment with Docker (see README)
3. Run the installer at `http://localhost:8080/install.php`
4. Make your changes on a feature branch

## Stack

- PHP 8.2, PDO/MariaDB
- Bootstrap 5.3, htmx 2, Alpine.js
- Font Awesome 6 (self-hosted)
- No build step — plain PHP, no npm required

## Guidelines

- **No credentials** — never commit `conf/db.php` or any real passwords
- **Stay generic** — MemberBase is not tied to any specific organisation; avoid hardcoding org-specific logic
- **PHP style** — follow the existing patterns (PDO prepared statements, `htmlspecialchars()` on all output, no raw `$_GET`/`$_POST` without validation)
- **Commits** — short imperative subject line, present tense (`fix modal backdrop`, not `fixed` or `fixes`)
- **One concern per PR** — keep pull requests focused; large refactors should be discussed in an issue first

## Reporting bugs

Use the [bug report template](.github/ISSUE_TEMPLATE/bug_report.yml). Include PHP/MariaDB versions and steps to reproduce.

## Suggesting features

Open a [feature request](.github/ISSUE_TEMPLATE/feature_request.yml) before starting work on a significant change — it avoids duplicate effort.

## Licence

By contributing, you agree your code is released under the [AGPL-3.0](LICENSE) licence.
