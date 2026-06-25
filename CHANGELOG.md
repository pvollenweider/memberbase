# Changelog

## [2.2.1] — 2026-06-25

### Security
- Protect unauthenticated endpoints: `attestation_don.php`, `attestation_bulk.php` now require login
- Fix XSS: htmlspecialchars on member names in delete confirm view, int cast on ID params in hrefs
- Fix open redirect: sanitize Content-Disposition filename in quittancedon.php (CRLF)
- Input validation: `comptaid` int cast, `groupType` whitelist, `teamId` int cast in manage_actions.inc
- Session cookie `secure` flag hardcoded to true (was dynamic, broke behind TLS proxy)
- Password reset temp token stored in session flash instead of URL (was leaking into Apache logs)
- `ob_start()` in index.php so header() redirects work after HTML output has started

### Removed
- `quittancedon.php` decommissioned (old Word export, no longer used)
- `label.php`, `xls.php`, `test.php`, `confirm-membership.php` removed (dead/insecure files)
- Dead views: `resume2`, `resume3`, `resume4`, `resume-non-coti`, `resume_dna`, `lastEntryCompta2`, `manage_metagroups`

### Fixed
- Metagroup name blank after creation (`lookupMetagroup` was missing `name IS NOT NULL` filter)
- Thousands separator missing on individual amounts in lastEntryCompta
- `wants_attestation` was editable toggle in compta list — now read-only indicator
- `autocapitalize="none"` on login username field (was capitalizing on iOS)

### Changed
- manageTeam: Catégories and Filtres de groupes split into separate tabs (was one combined tab)
- manageTeam: "Organisation" tab renamed to "Catégories"
- Metagroup list view shows aggregated group names below the filter button

## [2.2.0] — 2026-06-25

### Changed
- Replace htaccess with PHP-based user authentication (admin/user roles, bcrypt, force password change)
- Simplify navbar user dropdown (less markup, more right spacing)

## [2.1.0] — 2026-06-25

### Added

#### Tableau de bord Contributions (résumé)
- **Comparaison même période** : delta CHF + % vs jan–mois N-1 (comparaison apples-to-apples, remplace la comparaison partiel/complet trompeuse)
- **Progression vs année précédente complète** : "Il manque X CHF pour atteindre N-1 (total) — Y% atteint" ou "Total N-1 dépassé de +X CHF (+Y%)"
- **Donateurs fidèles cliquables** : lien "X fidèles" → vue liste des donateurs récurrents (ont donné en N et N-1) avec comparaison des deux années
- **Nouveaux donateurs cliquables** : lien "X nouveaux" → vue liste des primo-donateurs (ont donné en N, pas en N-1) avec date de premier don
- Même période N-1 affiché aussi sur la card Donateurs (delta % + count)
- Nouvelles vues `loyalDonors` et `newDonors` avec DataTable, export, sélecteur d'année et retour vers résumé

#### Suppression d'écriture compta
- Page de confirmation redessinée : card centrée, récap structuré (date / libellé / montant), bouton Annuler en premier, Supprimer en rouge

#### Groupes dans la fiche membre (generalData)
- Ordre des catégories de groupes aligné sur manageTeam (`cat_sort` au lieu de l'ordre alphabétique)

#### Assets locaux (zéro CDN)
- Bootstrap, DataTables, DT Buttons, moment.js, jszip, pdfmake, Chart.js et Inter téléchargés localement (`css/vendor/`, `js/vendor/`, `fonts/inter/`)
- Polyfills IE8 (html5shiv, respond.js) supprimés de `confirm-membership.php`

### Changed

- Bootstrap 5.3.3 → **5.3.8**
- moment.js 2.21.0 → **2.30.1**
- jszip 3.1.3 → **3.10.1**
- pdfmake 0.1.36 → **0.3.11**
- Label "Sexe" → **"Genre"** partout (locales + UI)
- Icônes PDF attestations dans la vue résumé : bordure supprimée (`btn btn-sm py-0 px-1 text-muted`)
- Padding et font-size réduits dans le dropdown de filtre par groupe ; séparateur entre catégories
- Dropdown groupes : label en `font-size: 0.75rem`, items indentés (`padding-left: 1.5rem`)
- Description `membre_team` dans les réglages clarifiée

---

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
