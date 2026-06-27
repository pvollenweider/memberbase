# Changelog

## [3.2.0] — en cours

---

## [3.1.1] — 2026-06-27

### Added
- **lastEntryCompta** : colonne Type affichée en badge coloré (avant Libellé)
- **lastEntryCompta** : dropdown filtre type avec badges colorés
- **lastEntryCompta** : filtres "12 derniers mois" et "24 derniers mois" dans la dropdown années

### Changed
- **lastEntryCompta** : lignes sans colorisation de fond (couleur portée uniquement par le badge de type)

---

## [3.1.0] — 2026-06-27

### Migration base de données

Voir `MIGRATION_PROD.md` pour le détail complet. Résumé :

```sql
-- AUTO_INCREMENT sur team, users, compta
ALTER TABLE team   MODIFY id INT        NOT NULL AUTO_INCREMENT;
ALTER TABLE users  MODIFY id INT(8)     NOT NULL AUTO_INCREMENT;
ALTER TABLE compta MODIFY id INT(8)     NOT NULL AUTO_INCREMENT;
DELETE FROM maxval WHERE parameter IN ('teamid','userid','comptaid');

-- Colonne is_institutional sur compta_type
ALTER TABLE compta_type ADD COLUMN is_institutional TINYINT(1) NOT NULL DEFAULT 0 AFTER is_excluded_from_donation;

-- Colonne status sur users
ALTER TABLE users ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1 AFTER modificationDate;

-- audit_log : sujet membre + username nullable
ALTER TABLE audit_log
    ADD COLUMN subject_user_id INT UNSIGNED NULL DEFAULT NULL,
    ADD INDEX idx_subject_user (subject_user_id);
ALTER TABLE audit_log MODIFY COLUMN username VARCHAR(100) NULL;

-- Réglage member_no_coti_team (via l'interface Réglages)
INSERT INTO app_settings (`key`, `value`) VALUES ('member_no_coti_team', '0') ON DUPLICATE KEY UPDATE `value`=`value`;
```

### Added
- **Archivage / membres inactifs** : colonne `users.status` (1=actif, 0=inactif) ; vue "Membres masqués" ; boutons Réactiver / Anonymiser / Supprimer selon contexte
- **Fusion de profils** (`?view=mergeUsers`) : sélection champ par champ entre deux profils, transfert compta + suivi + groupes, suppression du doublon (Alpine.js)
- **Filtre rapide -6666** : donateurs non institutionnels actifs en année N-1 (type `is_institutional=0`) avec description explicative
- **Filtre rapide -5555** : "Aucun versement ces 10 dernières années" — colonne "Historique compta" (cotisations + total) dans le listing ; pre-fetch agrégat (N+1 → 1 requête)
- **Filtre rapide -3333** refactorisé : "membre sans cotisation active ces 3 ans" — tout profil ayant payé une cotisation mais pas depuis 3 ans, sans restriction de groupe
- **Paramètre `member_no_coti_team`** : groupe exclu du filtre -3333 (bénévoles, comité) configurable dans les réglages
- **Mini-dashboard profil** (sidebar) : dons cette année / année précédente / total depuis YYYY ; bloc "Autres versements (Cotisation, …)" séparé avec même structure ; ligne "Ensemble des versements depuis YYYY"
- **Toggle "Dons uniquement"** dans la vue compta d'un profil ; indicateur "non-don" par ligne
- **Pastilles discrètes** sur les onglets Compta et Suivi (count d'entrées)
- **Historique par membre** (`?view=userHistory&userid=X`) : journal de toutes les actions pour un membre donné
- **Doublons potentiels** dans l'onglet Intégrité : détection par nom et par email

### Changed
- **Migration AUTO_INCREMENT** : `team`, `users`, `compta` utilisent désormais `lastInsertId()` — `maxval` conservé uniquement pour `metagroup_id` et `userpropertiesid`
- `auditLog()` : paramètre optionnel `$subjectUserId` — toutes les actions membre transmettent l'ID du profil concerné
- Label -5555 : "Aucun versement ces 10 dernières années" (était "Aucun don")
- Label -6666 : "Donateur non institutionnel actif en YYYY" (dynamique)
- Descriptions explicatives sous la barre de filtres rapides pour tous les filtres spéciaux
- DataTable listing : "_TOTAL_ profils" (était "_TOTAL_ membres")
- Exceptions hardcodées supprimées des filtres -5555 et -3333
- `startswith()` supprimée de `declarations.inc` (inutilisée)

### Fixed
- Alerte "modifications non sauvegardées" déclenchée par le toggle "Dons uniquement" (ajout `data-no-dirty`)
- Filtres du journal d'activité : dropdowns utilisateur + action avec export CSV/Excel/Impression
- Sections Intégrité collapsées par défaut (issue #13)
- Box "Dons" affichait les entrées "Excl. don" dans le comptage

---

## [3.0.1] — 2026-06-26

### Added
- **Renommage rapide** des groupes depuis l'onglet Groupes : crayon inline, sauvegarde sans rechargement de page
- **Bouton Annuler** dans le toast de modification d'appartenance métagroupe/catégorie (fenêtre de 4 s)
- **Import groupé par catégorie** dans le formulaire d'ajout de groupe (section "Importer les membres d'autres groupes")

### Changed
- Page Réglages (`?view=settings`) : navigation horizontale remplacée par une barre latérale verticale (desktop) / sélecteur (mobile), avec séparateur Administration
- Onglet Groupes : chaque ligne a maintenant deux boutons d'action — crayon (renommer) et engrenage (page de réglages complète)

### Fixed
- Renommage de groupe : avertissement PHP `Undefined variable $oldName»` polluait la réponse JSON (le caractère `»` était interprété comme faisant partie du nom de variable)
- `?view=manageTeam` décommissionné : redirige désormais vers `?view=settings&tab=groups`

---

## [3.0.0] — 2026-06-26

> **Migration base de données requise** — voir section Migration ci-dessous avant de déployer.

### Migration base de données

```sql
-- Contrainte unique (user_id, parameter) remplacée par index simple
-- (nécessaire pour plusieurs entrées de suivi par membre)
ALTER TABLE user_properties DROP INDEX uniq_user_param;
ALTER TABLE user_properties ADD INDEX idx_user_param (user_id, parameter);

-- Journal d'activité
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME     NOT NULL DEFAULT NOW(),
  username   VARCHAR(100) NOT NULL,
  action     VARCHAR(100) NOT NULL,
  detail     TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Added
- **htmx 1.9 + Alpine.js** intégrés comme couche d'interactivité (zéro CDN, auto-hébergés)
- **Journal d'activité** (`audit_log`) : toutes les actions add/update/delete loguées avec utilisateur, action et détail (noms au lieu d'IDs)
- **Onglet Intégrité** (`?view=settings&tab=integrity`) : détecte les groupes masqués encore assignés à une catégorie ou un métagroupe, avec lien direct vers l'édition
- **Gestion des groupes** refactorisée en onglets dédiés dans les réglages : Groupes, Catégories, Métagroupes (`manage_groups.inc`, `manage_categories.inc`, `manage_filters.inc`)
- **Lien d'invitation** à durée limitée (`?view=invite&token=…`) pour créer un compte sans passer par un admin
- **Générateur de mot de passe** aléatoire sur le formulaire de création d'utilisateur
- **Environnement Docker** de développement : PHP 8.2 + MariaDB 11 + Adminer (`make up`)

### Changed
- **DataTables** mis à jour 1.13.7 → 2.2.2 + Buttons 3.1.2
- Point d'entrée unique `index.php` : branche htmx (`HX-Request: true`) retourne uniquement le fragment HTML, sans layout — performance et cohérence
- Avertissement "modifications non sauvegardées" (`beforeunload`) désactivé sur submit de formulaire (la sauvegarde est intentionnelle)
- `log4php` entièrement supprimé
- Bouton retour sur la page d'édition métagroupe : libellé "Retour aux métagroupes" / "Retour aux catégories" selon le type

### Fixed
- Doublons dans l'onglet Intégrité : `SELECT DISTINCT` sur les deux requêtes
- `$view` non défini dans le chemin htmx (sautait `menu.inc`)
- htmx : défilement automatique sur boost désactivé (`scrollIntoViewOnBoost: false`)
- `strftime()` déprécié en PHP 8.2 remplacé par tableau de noms de mois
- DataTable : destruction avant re-init pour éviter les fuites mémoire (événements `datahref` namespacés)

---

## [2.2.2] — 2026-06-26

### Added
- Avertissement "modifications non sauvegardées" si on quitte la page sans sauver (beforeunload)

### Fixed
- Doublons dans le sélecteur de catégorie de groupe (GROUP BY id sur la requête metagroup)

### Changed
- Page d'édition d'un groupe: lien "Voir la liste →" pré-filtré sur ce groupe
- Page d'édition d'un métagroupe: lien "Voir la liste filtrée →" (filtres uniquement), groupes affichés par catégorie, badge count membres inline après le nom
- Documentation: guides utilisateur et administrateur ajoutés dans `doc/`

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
