# Changelog

## [2.0.0] — 2026-06

Complete overhaul of the members management application. All changes listed here represent improvements over the v1 codebase (`membres.casa-alianza.ch.old`); they were developed outside of git history and are consolidated as a single release.

### Added

#### Attestations de dons (PDF)
- New `attestation_don.php`: generates a signed donation-receipt PDF for a single member and year using the official AcroForm template (`assets/attestation.pdf`) from the Administration fiscale cantonale de Genève
- New `attestation_bulk.php`: generates all donors' attestations for a given year merged into a single downloadable PDF; respects the same minSum threshold as the résumé view
- Attestation button with year dropdown added to individual member compta view (`compta_generic.inc`)
- PDF icon per row added to résumé view, with correct stopPropagation so row click is not triggered
- FDF generation in pure PHP (no Composer dependency): field names encoded as ISO-8859-1, field values encoded as UTF-16 BE hex to correctly handle accented characters

#### Types de compta configurable
- New `manage_compta_types.inc`: full CRUD UI for compta types — label, Bootstrap color class, `is_cotisation` flag, `is_excluded_from_donation` flag, `is_archived` flag
- `compta.type_id` INT foreign key replaces the old varchar `type` slug
- Archived types hidden from the entry form but remain visible on existing compta lines
- Types sorted alphabetically everywhere (entry form, admin list, activity views)
- Row coloring per type in all compta tables (Bootstrap subtle background variables)

#### Vues et filtres
- Year filter added to résumé view (dropdown with last 10 years + "toutes les années")
- "Toutes les attestations YYYY" button on résumé view, visible when a specific year is selected
- `ca-filter-btn` pill-button design system applied consistently across all views: index (members list), lastEntryCompta, lastEntrySuivi, résumé
- DataTable export buttons standardized to compact `btn btn-dt` collection style across all views
- `lastEntrySuivi.inc`: filter bar replaced legacy Bootstrap navbar; `$type` variable properly initialized
- `view_users.inc`: filter bar replaced legacy Bootstrap navbar; "Add user" link aligned right
- Creation-date column hidden by default in member list DataTable

#### Architecture et qualité
- N+1 query in résumé view eliminated: single aggregated SQL query with `GROUP BY` + `HAVING` + `EXISTS` subquery for team membership; `is_excluded_from_donation` applied at query level
- `manage_teams.inc`: complete UI overhaul including metagroup support, team visibility toggle, member management with search
- `update_team_form.inc`, `update_metagroup_form.inc`, `memberOf.inc`: new or substantially rewritten for team management
- `manage_metagroups.inc`: metagroup creation and management
- `settings_form.inc`: application settings (default group, reference group, archives group)
- Explicit pencil edit button per compta row replaces unreliable `data-href` TR click
- `stopPropagation()` on all inner action links inside `data-href` rows

### Fixed

- Double-encoded HTML entity placeholders: `resources_fr.inc` rewritten with raw UTF-8 strings; calling `htmlentities()` on values that were already HTML entities produced `&amp;eacute;` etc.
- PDF character encoding: FDF values sent as UTF-16 BE hex (BOM + content), field names converted to ISO-8859-1 via `iconv` to match the 2010 PDF's internal encoding
- Checkbox `Case à cocher2` now correctly checked (field name with accented char now round-trips through Latin-1 iconv)
- Duplicate team entries in manageTeam nav dropdown

### Changed

- Bootstrap upgraded to 5.3 (dark-mode aware CSS variables, subtle background utilities)
- DataTables upgraded to 1.13
- Font Awesome upgraded to 6
- `declarations.inc`: `$comptaTypes` loaded `ORDER BY label ASC` (was `sort_order ASC, label ASC`)
- `minSum` input in résumé view validated against a whitelist `[1, 200, 500, 1000]` to prevent SQL injection via unsanitized integer interpolation

### Infrastructure

- `assets/attestation.pdf`: official AcroForm template added to repository
- `pdftk` required on server (`apt install pdftk-java`)
