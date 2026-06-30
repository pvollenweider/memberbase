# memberbase — Guide d'intégration développeur

## Vue d'ensemble du projet

**memberbase** est une application web auto-hébergée de gestion des membres et des donateurs, conçue pour les petites associations.

| | |
|---|---|
| **Langages** | PHP 8.2, SQL, JavaScript, CSS |
| **Frameworks** | htmx 2.0.4, Alpine.js, Bootstrap 5.3, DataTables, Playwright |
| **Base de données** | MariaDB 11 |
| **Infrastructure** | Docker (Apache + PHP + MariaDB), CI GitHub Actions |

L'application suit un pattern **MVC classique en PHP pur** : pas de framework PHP, pas de ORM complexe — juste des classes, du PDO, et du HTML servi par Apache.

---

## Architecture en couches

### 1. Database / Schema
*Les 10 tables MariaDB qui structurent toutes les données.*

- `schema.sql` — schéma idempotent (CREATE TABLE IF NOT EXISTS), à appliquer en prod via `MIGRATION_PROD.md`
- Tables clés : `users`, `team`, `user_properties`, `compta`, `audit_log`, `app_users`

### 2. Domain Classes
*Les 5 classes PHP qui encapsulent la logique métier et l'accès aux données.*

| Classe | Fichier | Rôle |
|---|---|---|
| `User` | `html/classes/user_class.php` | Membre — CRUD complet, adhésions, cotisations |
| `Team` | `html/classes/team_class.php` | Groupe — gestion des membres et des catégories |
| `Compta` | `html/classes/compta_class.php` | Écriture comptable — saisie et consultation |
| `Metagroup` | `html/classes/metagroup_class.php` | Filtre de navigation — groupe de groupes |
| `UserProperty` | `html/classes/property_class.php` | Attribut membre / note de suivi |

### 3. Library / Bootstrap
*Infrastructure partagée incluse par chaque page PHP.*

- `html/includes/lib/bootstrap.php` — connexion PDO, fonctions utilitaires globales (`auditLog`, `getMaxVal`), chargement des types compta et des settings
- `html/includes/lib/auth.php` — session, rôles (`isAdmin`, `isManager`, `canWrite`), guards (`requireLogin`)
- `html/locales/resources_fr.php` — toutes les chaînes UI en français

### 4. REST API
*Endpoints JSON sous `/api/`, consommés par le frontend Alpine.js et les intégrations externes.*

- `html/api/_bootstrap.php` — guard d'auth commun, header JSON, helper `apiError()`
- `html/api/members.php` — CRUD membres + pagination + filtres virtuels
- `html/api/groups.php` — CRUD groupes + gestion des adhésions
- `html/api/compta.php` — CRUD écritures comptables
- `html/api/suivi.php` — CRUD notes de suivi
- `html/api/compta-types.php` — lecture seule des types comptables

### 5. Routing
*Dispatch des requêtes — deux fichiers seulement.*

- `html/index.php` — **front-controller unique** : bootstrap, htmx vs full-page, dirty-form guard
- `html/includes/routing/actions.php` — route les POST vers le bon handler
- `html/includes/routing/views.php` — route les GET vers le bon template

### 6. Action Handlers
*Handlers POST qui valident, orchestrent les classes, et écrivent dans `audit_log`.*

```
html/includes/actions/
  auth.php       — connexion, mot de passe, gestion app_users
  members.php    — add, update, merge, anonymize, deactivate
  compta.php     — add, update, toggle attestation
  groups.php     — CRUD groupes, imports, bulk hide/show
  metagroups.php — CRUD filtres et catégories
  settings.php   — settings app, types compta
  suivi.php      — add, update notes de suivi
```

### 7. View Templates
*Templates PHP inclus dans le layout principal.*

```
html/includes/views/
  users_*        — liste membres, fiche, ajout, fusion, anonymisation…
  donors_*       — tableau de bord donateurs, nouveaux, fidèles, perdus
  compta_*       — liste comptable, formulaire, onglet par membre
  suivi_*        — liste globale, formulaire, édition
  settings_*     — groupes, filtres, types compta, app users, intégrité, audit
  auth_*         — changement de mot de passe
```

### 8. Partials & Standalone
- `html/includes/partials/menu.php` — barre de navigation principale
- `html/includes/partials/donor_table.php` — composant DataTables réutilisable pour tous les rapports donateurs
- `html/login.php`, `html/set-password.php` — pages auth hors session
- `html/install.php` — assistant d'installation en 5 étapes
- `html/attestation_bulk.php`, `html/attestation_don.php` — génération PDF via pdftk

### 9. Frontend Assets
- `html/js/member-general-form.js` — composant Alpine.js pour l'édition inline du profil membre (PUT `/api/members/{id}`)
- `html/js/dt_defaults.js` — configuration DataTables partagée (DOM, boutons export, langue FR)
- `html/css/custom.css` — design system Bootstrap 5 avec variables CSS (`--ca-primary`, etc.)

### 10. Infrastructure & CI
- `docker-compose.yml` — stack locale : PHP/Apache :8080 + MariaDB + Adminer :8082
- `Makefile` — commandes dev : `make up`, `make db`, `make test`
- `.github/workflows/e2e.yml` — CI : stack Docker de test + reset DB + Playwright

---

## Concepts clés à connaître

### Pattern htmx / dirty-form guard
Toutes les navigations JS doivent setter `window.__dirtyOverride = true` avant `window.location = ...`, sinon le guard dans `index.php` déclenche un popup "modifications non enregistrées". Les selects de navigation portent `data-no-dirty`.

### HX-Location vs Location
Pour les redirections après action htmx, utiliser `HX-Location` (pas `Location`) — voir `CLAUDE.md`.

### Rôles utilisateurs
4 rôles dans `app_users.role` : `admin` > `manager` > `user` > `readonly`. Les guards sont dans `html/includes/lib/auth.php`.

### users.status
`TINYINT(1)` — 1 = actif, 0 = inactif. **Tous les listings filtrent `AND users.status = 1`** sans exception.

### Séquences manuelles
`user_properties` et `metagroup` utilisent une table `maxval` comme auto-increment manuel (via `updateAndGetMaxVal()`), pas d'AUTO_INCREMENT SQL.

### Audit log
Chaque mutation d'importance appelle `auditLog()` — toutes les actions handlers en font usage.

---

## Tour guidé (ordre de lecture recommandé)

**Étape 1 — Entry point & flux de requête**
Lire `html/index.php`. C'est le seul front-controller. Il bootstrappe auth + classes, détermine si c'est une requête htmx ou full-page, puis délègue aux routers.

**Étape 2 — Schéma de base de données**
Lire `schema.sql`. Les 10 tables sont définies ici. Comprendre `users`, `team`, `user_properties`, `compta`, `audit_log` suffit pour 80% du code.

**Étape 3 — Classes domaine**
Lire `html/classes/user_class.php` (la plus importante). Puis `team_class.php`. Les autres classes suivent le même pattern.

**Étape 4 — Bootstrap & auth**
Lire `html/includes/lib/bootstrap.php` puis `html/includes/lib/auth.php`. Ces deux fichiers sont inclus en premier par toutes les pages.

**Étape 5 — API REST**
Lire `html/api/_bootstrap.php` puis `html/api/members.php`. La structure est identique pour tous les endpoints.

**Étape 6 — Vues principales**
Lire `html/includes/views/users_list.php` (liste membres) et `html/includes/views/users_general_data.php` (édition inline Alpine.js).

**Étape 7 — Module donateurs & compta**
Lire `html/includes/views/donors_summary.php` et `html/includes/views/compta_last_entry.php`.

**Étape 8 — Administration**
Lire `html/includes/views/settings_general.php` et `html/includes/actions/settings.php`.

---

## Fichiers les plus complexes (aborder avec soin)

| Complexité | Fichier | Pourquoi |
|---|---|---|
| 9/10 | `html/api/members.php` | Pagination, search, 6+ filtres virtuels, CRUD complet |
| 9/10 | `html/includes/actions/groups.php` | Import cotisants/donateurs, bulk hide/show, fusion de groupes |
| 9/10 | `html/includes/views/users_edit_form.php` | Onglets, mini-dashboard stats, multiples requêtes SQL |
| 9/10 | `doc/architecture.md` | Documentation de référence — lire en premier |
| 8/10 | `html/index.php` | Front-controller + dirty-form guard + gestion htmx |
| 8/10 | `html/includes/actions/members.php` | Fusion, anonymisation, désactivation, audit diff |
| 8/10 | `html/install.php` | Wizard 5 étapes, connexion DB, seed données |

---

## Démarrage rapide

```bash
# 1. Lancer la stack
make up          # http://localhost:8080

# 2. Accéder à la DB (optionnel)
make db          # Adminer sur http://localhost:8082

# 3. Lancer les tests E2E
make test

# 4. Voir les logs
make logs
```

L'assistant d'installation (`http://localhost:8080/install.php`) crée les tables et le premier compte admin.
