# MemberBase — Guide d'accueil développeur

Bienvenue. Ce guide fait entrer dans le code de **MemberBase**, une application PHP 8.2 de
gestion des membres, groupes, cotisations et dons pour associations à but non lucratif. Il
suit l'ossature du graphe de connaissance du projet (`.understand-anything/knowledge-graph.json`) :
ses couches d'architecture et son tour guidé.

---

## 1. Aperçu du projet

MemberBase est une application web auto-hébergée de gestion des **membres**, des **donateurs**
et de la **comptabilité associative**. Terminologie centrale : un **Segment** regroupe des
membres (table `segment`, appartenance via `contact_segment`) ; un **Segment combiné** regroupe
plusieurs segments (table `combined_segment`, union de membres ou catégorie de rangement).

| | |
|---|---|
| **Langages** | PHP, JavaScript, SQL |
| **Frameworks / libs** | Bootstrap 5.3, htmx 2.0.4, Alpine.js, DataTables, Playwright, PHPUnit |
| **Base de données** | MariaDB |
| **Infrastructure** | Docker (PHP/Apache + MariaDB + Mailpit + Adminer), CI GitHub Actions |

Architecture : **MVC en PHP pur**, sans framework applicatif ni ORM. Un front-controller unique
(`html/index.php`), des classes de domaine style *active-record* sur PDO, et du HTML rendu côté
serveur, enrichi par htmx (navigation sans rechargement) et Alpine.js (bascule lecture/édition
côté client).

---

## 2. Couches d'architecture

| Couche | Description | Fichiers clés |
|---|---|---|
| **API** | Endpoints REST JSON + les 2 tables de dispatch centrales | `html/api/{_bootstrap,contacts,segments,compta,suivi,compta-types}.php`, `html/includes/routing/{actions,views}.php` |
| **Contrôleurs d'action** | Handlers POST, dispatchés par nom depuis `$ACTION_MAP` | `html/includes/actions/*.php` (13 fichiers) |
| **Vues & présentation** | Templates PHP rendus côté serveur + partiels UI + pages racine | `html/includes/views/*.php` (~55 fichiers), `html/includes/partials/*.php`, `html/index.php`, `html/login.php`, `html/install.php` |
| **Domaine & services** | Classes active-record + bibliothèques métier partagées | `html/classes/*.php` (7 classes), `html/includes/lib/*.php` (16 fichiers) |
| **Données** | Schéma et son évolution | `schema.sql`, `html/migrations/*.sql` (40 fichiers numérotés) |
| **Frontend** | JS et CSS pilotant l'UI Bootstrap/htmx/Alpine | `html/js/*.js`, `html/css/*.css` |
| **Configuration & locales** | Config projet/runtime + 4 bundles de langue | `composer.json`, `package.json`, `.htaccess`, `html/locales/resources_{fr,en,de,es}.php` |
| **Infrastructure & CI/CD** | Conteneurs, pipelines, scripts de release/maintenance | `Dockerfile`, `docker-compose*.yml`, `.github/workflows/*.yml`, `Makefile`, `tools/*.sh`, `html/tools/*` |
| **Suite de tests** | E2E Playwright + unitaires PHPUnit | `tests/*.spec.ts`, `tests/unit/*Test.php`, `tests/fixtures/` |
| **Documentation** | Docs du projet | `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `DESIGN.md`, `PRODUCT.md`, `MIGRATION_PROD.md`, `CLAUDE.md`, `doc/*` |

---

## 3. Concepts clés

- **Routage htmx** — `index.php` reçoit toutes les requêtes web et distingue requête htmx
  (fragment `#main-content`) et chargement full-page. Les redirections après action utilisent
  `HX-Location` pour htmx, `Location` sinon (voir `CLAUDE.md`).
- **Alpine.js — mode lecture/édition inline** — les fiches basculent entre lecture et édition
  côté client sans rechargement, Alpine pilotant l'état local.
- **RBAC / rôles** — 4 rôles (`readonly`/`user`/`manager`/`admin`) dans `html/includes/lib/auth.php` :
  `authUser()`, gardes `canRead()`/`canWrite()`/`isManager()`/`isAdmin()`, plus `requireLogin()`
  et `requirePasswordChange()`. Sessions PHP + mots de passe bcrypt.
- **CSRF** — toute action listée dans `$ACTION_MAP` (`includes/routing/actions.php`) exige un
  jeton valide (`csrfCheck()`), sur toutes les méthodes HTTP. `app.js` gère la propagation
  automatiquement (en-tête htmx, champ caché des formulaires POST) — un `fetch()` inline est le
  seul cas demandant un ajout manuel de l'en-tête `X-CSRF-Token` (voir `CLAUDE.md`).
- **Filtres virtuels (`MemberFilter`)** — un `?segment=` négatif déclenche une requête métier
  dédiée (cotisation impayée depuis N ans, aucune activité depuis 10 ans, jamais rien versé…)
  résolue par `html/classes/member_filter_class.php`, seule source de vérité partagée par la
  liste des membres et l'API REST.
- **Active-record sans ORM** — les classes de domaine (`Contact`, `Segment`, `Compta`,
  `CombinedSegment`, `UserProperty`, `SuiviTask`) encapsulent leur accès via le singleton `db()`
  directement, sans couche de mapping.
- **Dirty-form guard** — garde globale (`js/app.js`) qui marque le formulaire « modifié » sur
  `change`/`input` et intercepte `beforeunload`/`htmx:beforeRequest`. Toujours poser
  `window.__dirtyOverride = true` avant une navigation JS et `data-no-dirty` sur les selects
  de navigation (voir `CLAUDE.md`).
- **Assistant d'import CSV/TSV** — wizard 3 étapes : `importUpload` → `importApply` →
  `importResolveDuplicates`. Source unique des champs importables dans
  `html/includes/lib/import_fields.php` ; détection de doublons par maps en mémoire ; création
  enveloppée dans une transaction.
- **Journal d'audit** — helper `auditLog()` dans `bootstrap.php`, chaque handler POST trace ses
  écritures dans `audit_log`.
- **Anti-brute-force en deux couches** — Fail2Ban (ban par IP, dépendant de l'infra) et un
  compteur applicatif (`login_rate_limit`, indépendant de l'infra) protègent `login.php`.
- **Fusion de membres** — transaction PDO atomique fusionnant deux fiches doublons (compta,
  suivi, appartenance aux segments).

---

## 4. Tour guidé

Suivez ces étapes dans l'ordre pour prendre le code en main.

1. **Point d'entrée** — `html/index.php` reçoit toutes les requêtes, impose l'authentification,
   charge la locale et les classes de domaine, puis distingue fragment htmx et page complète.
   Vue par défaut : le tableau de bord (`?view=dashboard`).
2. **Bibliothèque cœur** — `html/includes/lib/bootstrap.php` (PDO, constantes `FILTER_*`,
   `APP_VERSION`, `auditLog()`) et `html/includes/lib/auth.php` (session, rôles, CSRF).
3. **Routage** — `html/includes/routing/views.php` (`$UA_VIEW_ROUTES`, GET) et
   `html/includes/routing/actions.php` (`$ACTION_MAP`, POST + garde CSRF).
4. **Classes de domaine** — `Contact`, `Segment`, `Compta` (`html/classes/*.php`), style
   active-record sur PDO.
5. **Handlers d'action** — `html/includes/actions/contacts.php` illustre le patron commun :
   avant/après + `auditLog()`.
6. **Assistant d'import** — `includes/actions/import.php` + `includes/lib/import_fields.php`,
   exemple concret assemblant routage, domaine et actions.
7. **API REST** — `html/api/_bootstrap.php` (auth, CSRF JSON, rate limit) et
   `html/api/contacts.php` (CRUD complet).
8. **Frontend** — `html/js/app.js` : propagation du jeton CSRF, ré-init DataTables après swap
   htmx, dirty-form guard.
9. **Schéma** — `schema.sql`, snapshot complet utilisé par `install.php`.
10. **Localisation** — `html/locales/resources_fr.php` est le bundle de base et le jeu de clés
    canonique ; en/de/es ne surchargent qu'un sous-ensemble et retombent sur le français.
11. **Conteneurisation** — `Dockerfile` + `docker-compose.yml`.
12. **CI** — `.github/workflows/e2e.yml` boote la stack Docker de test et lance la suite
    Playwright.

---

## 5. Points de complexité à aborder prudemment

Ces fichiers portent la complexité la plus élevée du projet. Prévoyez du temps et relisez les
tests associés avant d'y toucher.

| Fichier | Pourquoi c'est délicat |
|---|---|
| `html/includes/views/users_list.php` | Liste membres : DataTables, filtres virtuels, sélection manuelle par checkbox, toolbar bulk-action |
| `html/includes/views/donors_summary.php` | Tableau de bord donateurs : agrégations, graphiques Chart.js, mode étendu/résumé |
| `html/includes/actions/segments.php` | Gestion de groupes : création/suppression/fusion, règles de cascade, actions bulk sur les filtres de nettoyage |
| `html/includes/views/settings_general.php` | Page Réglages à onglets : chaque panneau a sa propre garde de rôle |
| `html/classes/suivi_task_class.php` | Modèle de tâches + toute la génération automatique de tâches par règle métier |
| `html/includes/lib/mailer.php` | Client SMTP écrit de zéro (pas de PHPMailer) |
| `html/api/contacts.php` | CRUD membres + pagination ; filtres virtuels délégués à `MemberFilter` |

Tests dédiés à ces zones : `tests/filter-parity.spec.ts` (parité vue/API des filtres),
`tests/route-guards.spec.ts` (matrice rôles × routes), `tests/dirty-guard.spec.ts`
(guard formulaire), `tests/mobile-roles.spec.ts` (menu mobile), `tests/roles.spec.ts`
(gardes serveur par rôle, la plus grosse suite du projet).

---

## 6. Premiers pas

### Lancer en local (Docker)

```bash
make up            # docker compose up -d --build (PHP/Apache + MariaDB + Mailpit + Adminer)
make logs          # suivre les logs PHP
make shell         # shell dans le conteneur PHP
make db            # console MariaDB
make down          # arrêter
```

Application : `http://localhost:8080` — Adminer : `http://localhost:8082` — Mailpit (capture des
emails envoyés en dev) : `http://localhost:8025`.
Premier lancement : passer par `install.php` pour initialiser le schéma et le premier compte
admin.

Importer un dump SQL : `make import DUMP=chemin/vers/dump.sql`
Appliquer les migrations en attente : `make migrate` (voir l'état avec `make migrate-status`)

### Lancer les tests

```bash
make test              # npx playwright test (E2E)
make test-ui           # mode interactif --ui
make test-reset-db     # réinitialiser la base de test (tests/fixtures/reset-db.sh)
make test-unit         # PHPUnit — logique pure, sans DB
```

La suite E2E complète doit tourner avec `npx playwright test --workers=1` pour éviter les
interférences entre fichiers de test qui mutent la même base partagée (voir `CLAUDE.md`, section
« Pièges connus »). La CI GitHub Actions exécute la suite E2E, la suite PHPUnit, un test de
sauvegarde/restauration, et un test de convergence des migrations sur une base legacy.

---

## Références

`README.md` · `CHANGELOG.md` · `CONTRIBUTING.md` · `DESIGN.md` · `PRODUCT.md` ·
`MIGRATION_PROD.md` · `CLAUDE.md` · `doc/architecture.md` · `doc/api.md` · `doc/admin.md` ·
`doc/user.md`
