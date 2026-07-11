# MemberBase — Guide d'accueil développeur

> Version **5.1.0** — dérivé du knowledge graph du projet (`.understand-anything/knowledge-graph.json`, commit `ece1d70`). Noms de tables/classes/routes API mis à jour à la main pour la v5.0.0 (renommage `users`→`contact`, `team`→`segment`) puis la v5.1.0 (`metagroup`→`combined_segment`, colonnes date `int`→`DATE`/`DATETIME`) ; une régénération complète du graphe (`/understand --full`) reste à faire.

Bienvenue. Ce guide vous fait entrer dans le code de **MemberBase**, une application PHP 8.2 de gestion des membres pour ONG et petites associations. Il suit l'ossature du graphe de connaissance du projet : ses 13 couches d'architecture et son tour guidé en 8 étapes.

---

## 1. Aperçu du projet

MemberBase est une application web auto-hébergée de gestion des **membres**, des **donateurs** et de la **comptabilité associative**. Terminologie centrale : un **Segment** regroupe des membres (implémenté par les tables `segment` et `combined_segment` en base — `team` avant la v5.0.0, `metagroup` avant la v5.1.0).

| | |
|---|---|
| **Langages** | PHP, JavaScript, SQL |
| **Frameworks / libs** | Bootstrap 5, htmx, Alpine.js, DataTables, Playwright |
| **Base de données** | MariaDB |
| **Infrastructure** | Docker (PHP/Apache + MariaDB + Adminer), CI GitHub Actions |

Architecture : **MVC en PHP pur**, sans framework applicatif ni ORM. Un front-controller unique (`html/index.php`), des classes de domaine style *active-record* sur PDO, et du HTML rendu côté serveur, enrichi par htmx et Alpine.

---

## 2. Couches d'architecture (13 couches)

Les couches proviennent directement du champ `layers` du graphe.

| # | Couche | Description | Fichiers clés |
|---|--------|-------------|---------------|
| 1 | **Point d'entrée & pages racine** | Front-controller et pages autonomes (login, install, attestations) | `html/index.php`, `html/login.php`, `html/install.php`, `html/set-password.php`, `html/attestation_bulk.php`, `html/attestation_don.php`, `html/locales/resources_fr.php` |
| 2 | **Bibliothèque cœur** | Infrastructure partagée incluse par chaque page | `html/includes/lib/bootstrap.php`, `html/includes/lib/auth.php`, `html/includes/lib/import_fields.php` |
| 3 | **Routage** | Dispatch GET/POST | `html/includes/routing/views.php`, `html/includes/routing/actions.php` |
| 4 | **Vues** | Templates PHP inclus dans le layout | `html/includes/views/*`, `html/includes/partials/menu.php`, `html/includes/partials/donor_table.php` |
| 5 | **Concepts transverses** | Notions applicatives (RBAC, dirty-form, import, segments…) | *(voir §3)* |
| 6 | **Classes de domaine** | Logique métier active-record | `html/classes/{contact,segment,compta,combined_segment,property,member_filter}_class.php` |
| 7 | **Handlers d'actions (POST)** | Validation + orchestration + audit | `html/includes/actions/*` |
| 8 | **API REST** | Endpoints JSON `/api/`, gardés par session | `html/api/_bootstrap.php`, `html/api/{contacts,segments,compta,suivi,compta-types}.php` |
| 9 | **Outils CLI** | Scripts de maintenance | `html/tools/{fix_encoding,guest2010,import}.php` |
| 10 | **Schéma base de données** | 16 tables MariaDB, FK réelles depuis la v5.1.0 | `schema.sql` |
| 11 | **Infrastructure** | Conteneurs et pipeline | `docker-compose.yml`, `docker-compose.test.yml`, `Dockerfile` |
| 12 | **Tests E2E** | Suite Playwright | `tests/*.spec.ts` |
| 13 | **Documentation** | Docs projet | `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `DESIGN.md`, `PRODUCT.md`, `MIGRATION_PROD.md`, `doc/*` |

---

## 3. Concepts clés

- **Routage htmx** — `index.php` reçoit toutes les requêtes web et distingue requête htmx (fragment) et chargement full-page. Les redirections après action utilisent `HX-Location` pour htmx, `Location` sinon (voir `CLAUDE.md`).
- **Alpine.js — mode view/edit inline** — les fiches basculent entre lecture et édition côté client sans rechargement, Alpine pilotant l'état local.
- **RBAC / rôles** — dans `html/includes/lib/auth.php` : `authUser`, gardes `canRead` / `canWrite` / `isManager` / `isAdmin`, plus `requireLogin` et `requirePasswordChange`. Sessions PHP + mots de passe bcrypt.
- **Active-record sans ORM** — 5 classes de domaine (`Contact`, `Segment`, `Compta`, `CombinedSegment`, `UserProperty`) encapsulent leur accès via le singleton `db()` directement, sans couche de mapping.
- **Dirty-form guard** — garde globale dans `index.php` qui marque le formulaire « modifié » sur `change`/`input` et intercepte `beforeunload` / `htmx:beforeRequest`. Toujours poser `window.__dirtyOverride = true` avant une navigation JS et `data-no-dirty` sur les selects/inputs de navigation (voir `CLAUDE.md`).
- **Assistant d'import (nouveauté 3.5.4)** — wizard CSV 3 étapes : `importUpload` → `importApply` → `importResolveDuplicates`. Source unique des champs importables dans `html/includes/lib/import_fields.php` ; détection de doublons par maps en mémoire ; création enveloppée dans une transaction ; possibilité d'ajouter les contacts importés à un **Segment**.
- **Journal d'audit** — helper `auditLog()` dans `bootstrap.php`, chaque handler POST trace ses écritures dans `audit_log`.
- **Gestion des Segments** — les Segments s'appuient sur les tables `segment` et `combined_segment`, avec appartenance en table de jointure `contact_segment` (EAV avant la v5.0.0) ; le panneau segments de la fiche membre gère l'appartenance.
- **Fusion de membres (transaction)** — la fusion de doublons est atomique (transaction PDO).

---

## 4. Tour guidé (8 étapes)

Suivez ces étapes dans l'ordre pour prendre le code en main.

1. **Vue d'ensemble & point d'entrée** — `html/index.php` reçoit toutes les requêtes et dispatche via `html/includes/routing/views.php` (GET) et `html/includes/routing/actions.php` (POST). Concept : routage htmx.
2. **Authentification & rôles (RBAC)** — `html/includes/lib/auth.php` : sessions, bcrypt, gardes `canRead`/`canWrite`/`isManager`/`isAdmin`.
3. **Cœur applicatif** — `html/includes/lib/bootstrap.php` : connexion PDO, helpers de date, `auditLog`, chargement des réglages.
4. **Classes de domaine** — `html/classes/contact_class.php` (`Contact`), `segment_class.php` (`Segment`), `compta_class.php` (`Compta`) — style active-record.
5. **Handlers d'actions** — traitement des POST par domaine : `html/includes/actions/{contacts,compta,segments}.php`.
6. **Import de contacts (nouveauté 3.5.4)** — assistant 3 étapes : `html/includes/actions/import.php`, `html/includes/lib/import_fields.php`, `html/includes/views/import_step2.php`. Mapping, doublons, ajout à un Segment.
7. **API REST** — endpoints JSON basés sur la session, gardés par rôle : `html/api/contacts.php`, `html/api/_bootstrap.php` (ex. `GET /api/contacts`).
8. **Schéma & tests** — modèle MariaDB (`schema.sql`, table `contact`) et suite E2E Playwright (`tests/roles.spec.ts`).

---

## 5. Carte des fichiers par couche

**Point d'entrée & racine**
`html/index.php` · `html/login.php` · `html/install.php` · `html/set-password.php` · `html/attestation_bulk.php` · `html/attestation_don.php` · `html/locales/resources_fr.php`

**Bibliothèque cœur**
`html/includes/lib/bootstrap.php` · `html/includes/lib/auth.php` · `html/includes/lib/import_fields.php`

**Routage**
`html/includes/routing/views.php` · `html/includes/routing/actions.php`

**Classes de domaine**
`html/classes/contact_class.php` (`Contact`) · `segment_class.php` (`Segment`) · `compta_class.php` (`Compta`) · `combined_segment_class.php` (`CombinedSegment`) · `property_class.php` (`UserProperty`)

**Handlers d'actions**
`html/includes/actions/` : `auth.php` · `contacts.php` · `compta.php` · `segments.php` · `combined_segments.php` · `import.php` · `settings.php` · `suivi.php`

**Vues**
`html/includes/views/` : `users_*` (liste, fiche, ajout, édition, fusion, anonymisation, inactifs, historique, appartenance) · `compta_*` · `suivi_*` · `donors_*` (résumé, nouveaux, fidèles, perdus) · `members_lapsed.php` · `import_step{1,2,3}.php` · `settings_*` (groupes, filtres, catégories, types compta, app users, général, intégrité, audit) · `auth_change_password.php`
Partiels : `html/includes/partials/menu.php` · `donor_table.php`

**API REST**
`html/api/_bootstrap.php` · `contacts.php` · `segments.php` · `compta.php` · `compta-types.php` · `suivi.php` · `.htaccess`

**Outils CLI**
`html/tools/fix_encoding.php` · `guest2010.php` · `import.php`

**Schéma**
`schema.sql` — tables `contact`, `segment`, `contact_segment`, `contact_properties`, `combined_segment`, `combined_segment_member`, `compta_type`, `compta`, `maxval`, `app_settings`, `app_users`, `audit_log`, `email_templates`, `email_log`, `api_rate_limit`, `schema_migrations`

**Tests E2E**
`tests/` : `api`, `app-users`, `auth`, `change-password`, `compta-types`, `compta`, `groups`, `combined_segments`, `inactive-members`, `members`, `merge-users`, `segment-filter`, `resume`, `roles`, `settings`, `suivi`, `views` (`.spec.ts`)

---

## 6. Points de complexité à aborder prudemment

Ces fichiers portent la complexité la plus élevée du graphe. Prévoyez du temps et relisez les tests associés avant d'y toucher.

> Le refactor #55 (issues #56–#59) a réduit plusieurs de ces points : `users_list.php`
> ne contient plus de SQL (déplacé vers `User::listWithFilters()` et consorts),
> les filtres virtuels sont centralisés dans `MemberFilter`
> (`classes/member_filter_class.php`), `index.php` n'a plus de JS inline
> (→ `js/app.js`, `js/tiptap-editor.js`), et le routage des vues est une table
> déclarative avec garde par route.

| Complexité | Fichier | Pourquoi c'est délicat |
|---|---|---|
| 9 | `html/includes/views/donors_summary.php` | Tableau de bord donateurs : agrégations, graphiques Chart.js, mode étendu/résumé |
| 9 | `html/includes/actions/import.php` | Wizard d'import 3 étapes, doublons, transaction |
| 9 | `html/includes/views/settings_group_edit.php` | Édition de groupe/segment : appartenances, catégories |
| 8 | `html/includes/views/users_list.php` | Liste membres filtrable : DataTables, rendu des filtres, bulk actions (le SQL vit dans les classes) |
| 8 | `html/api/contacts.php` | CRUD membres + pagination (filtres virtuels délégués à `MemberFilter`) |

À surveiller ensuite (complexité 8) : `html/install.php`, `html/includes/actions/{segments,contacts}.php`, `html/includes/views/{users_edit_form,users_merge,compta_last_entry,compta_list,settings_filter_edit,settings_general,settings_groups,settings_integrity}.php`, `html/api/segments.php`.

Tests dédiés à ces zones : `tests/filter-parity.spec.ts` (parité vue/API des filtres),
`tests/route-guards.spec.ts` (matrice rôles × routes), `tests/dirty-guard.spec.ts`
(guard formulaire), `tests/mobile-roles.spec.ts` (menu mobile).

---

## 7. Premiers pas

### Lancer en local (Docker)

```bash
make up            # docker compose up -d --build (PHP/Apache + MariaDB + Adminer)
make logs          # suivre les logs PHP
make shell         # shell dans le conteneur PHP
make db            # console MariaDB
make down          # arrêter
```

Application : `http://localhost:8080` — Adminer : `http://localhost:8082`.
Premier lancement : passer par `install.php` pour initialiser le schéma et le premier compte admin.

Importer un dump SQL :

```bash
make import DUMP=chemin/vers/dump.sql
```

### Lancer les tests (Playwright E2E)

```bash
make test              # npx playwright test
make test-ui           # mode interactif --ui
make test-reset-db     # réinitialiser la base de test (tests/fixtures/reset-db.sh)
```

La stack de test utilise `docker-compose.test.yml`. La CI GitHub Actions exécute la suite E2E (`pipeline:e2e`).

---

## Références

`README.md` · `CHANGELOG.md` · `CONTRIBUTING.md` · `DESIGN.md` · `PRODUCT.md` · `MIGRATION_PROD.md` · `CLAUDE.md` · `doc/architecture.md` · `doc/api.md` · `doc/admin.md` · `doc/user.md`
