# MemberBase v3.5.1

> Release date: 2026-06-28

## What's in this release

This is a maintenance release focused on code quality, test coverage, and project structure. No user-facing behaviour changes.

---

## Internal refactoring

The `html/includes/` directory has been reorganised into conventional subdirectories:

| Ancien nom | Nouveau chemin | Rôle |
|---|---|---|
| `declarations.php` | `lib/bootstrap.php` | PDO, app settings, helpers |
| `auth.php` | `lib/auth.php` | Session, login, requireLogin() |
| `manage_views.php` | `routing/views.php` | View router |
| `manage_actions.php` | `routing/actions.php` | POST action dispatcher |
| `view_users.php` | `views/users_list.php` | Member list |
| `update_user_form.php` | `views/users_edit_form.php` | Edit member |
| `resume.php` | `views/donors_summary.php` | Contributions KPIs |
| `settings_form.php` | `views/settings_general.php` | App settings |
| `menu.php` | `partials/menu.php` | Nav sidebar |
| `_donor_table.php` | `partials/donor_table.php` | Donor table partial |
| _(and 27 more view files)_ | `views/` | Domain-prefixed fragments |

All `include` calls now use `__DIR__`-relative paths — previously bare relative paths resolved against Apache's CWD, which would break when files moved into subdirectories.

---

## Test coverage

A full Playwright E2E suite (55 tests) was added and passes on every run:

- Authentication (login, logout, wrong password, password change)
- Member CRUD (create, view, edit, deactivate, delete, anonymize, merge)
- Compta & suivi (create, edit, delete entries)
- Groups, categories, filters, compta types, app users
- Integrity tab, audit log, settings

A GitHub Actions workflow (`e2e.yml`) runs the suite automatically on every push and pull request against a fresh test database.

---

## Upgrading from v3.5.0

No database changes. No configuration changes.

If you have custom scripts or integrations that reference files under `html/includes/` by their old names, update those paths to the new structure. The `.htaccess` in `html/includes/` already denied direct HTTP access, so the restructuring has no security impact.

---

## Files changed

- 56 files changed (37 renames + path/include fixes)
- `CHANGELOG.md` updated
- `README.md` file tree updated
- `doc/admin.md` DB config reference corrected
