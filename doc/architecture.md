# Architecture de MemberBase

MemberBase **v4.0.0** — application PHP 8.2 de gestion des membres pour ONG.
Licence AGPL-3.0-or-later.

> **Terminologie.** Depuis la v3.5.4, l'interface parle de **Segment** (au lieu de
> « groupe ») et de **Segment combiné** (au lieu de « métagroupe »). Ce sont des
> libellés uniquement : le code et l'API conservent les noms techniques d'origine
> — table `team`, table `metagroup`, endpoints `/api/groups`. Dans tout ce document,
> **« Segment » (UI) = entité `team` (technique)** et **« Segment combiné » (UI) =
> entité `metagroup`**.

---

## 1. Vue d'ensemble & principes

MemberBase est une application PHP procédurale, sans framework MVC, sans namespace,
sans Composer. Elle repose sur cinq principes structurants :

- **Point d'entrée unique** : `html/index.php` reçoit toutes les requêtes de
  l'interface web. Les fragments de vue et les handlers d'action sont inclus
  dynamiquement par deux routeurs (`routing/views.php`, `routing/actions.php`).
- **Pas d'ORM** : accès PDO paramétré, avec des classes de domaine en style
  *active-record*. La connexion `$pdo` est un global accédé via `global $pdo`.
- **htmx 2.0 pour la navigation** : `<body hx-boost="true">` transforme liens et
  formulaires en requêtes XHR ; la réponse (fragment HTML pur, jamais de JSON) est
  swappée dans `#main-content` sans recharger le layout.
- **Alpine.js 3 pour l'état local** : composants réactifs déclarés dans des fichiers
  JS externes (`html/js/member-general-form.js`) pour rester compatibles avec une
  CSP `self`.
- **API REST séparée** : le répertoire `html/api/` expose des endpoints JSON
  machine-to-machine, distincts de l'interface htmx mais partageant la même session.

Garde constante des listings : la colonne `users.status` (`1` = actif, `0` = archivé)
est filtrée partout via `WHERE users.status = 1`.

---

## 2. Flux d'une requête GET (navigation htmx)

```
Navigateur
  |  GET index.php?view=generalData&id=42   [HX-Request: true]
  v
html/index.php
  |-- define('APP_ENTRY', true); ob_start();
  |-- require auth.php → requireLogin(), requirePasswordChange()
  |-- include locales, bootstrap.php, classes/*
  |-- $isHtmx = !empty($_SERVER['HTTP_HX_REQUEST'])   → true
  |       include routing/actions.php   (traite ?action= si présent)
  |       include routing/views.php     (dispatche ?view=)
  |            └── include views/users_edit_form.php  → echo <div>…</div>
  |       ob_end_flush(); exit;
  v
Navigateur : htmx swappe innerHTML de #main-content, pushState (hx-push-url)
```

Quand `$isHtmx` est `false` (chargement direct/rafraîchissement), `index.php` émet le
document complet : `<!DOCTYPE html>`, `<head>` avec tous les scripts vendor, menu,
`#main-content`, toast, footer. Le contenu de `#main-content` est rendu par les mêmes
deux `include` de routing dans les deux chemins.

---

## 3. Flux d'une requête POST (action)

Pattern Post-Redirect-Get adapté à htmx.

```
Navigateur
  |  POST index.php  body: action=updateUser&id=42&…   [HX-Request: true]
  v
html/index.php → include routing/actions.php
  |-- $ACTION_MAP['updateUser'] = 'members'
  |-- require includes/actions/members.php
  |       vérifie le rôle (canWrite/isManager/isAdmin selon l'action)
  |       valide, $user->save(), auditLog(...)
  |       if ($isHtmx) { header('HX-Location: …?view=generalData&id=42'); exit; }
  |       header('Location: …'); exit;   // fallback non-htmx
  v
Navigateur : htmx capte HX-Location → nouveau GET → swap #main-content
             + toast #casaToast si le fragment contient #casa-save-ok
```

`HX-Location` (et non un `302 Location`) est utilisé pour toute réponse à une requête
htmx, afin que htmx effectue une navigation propre sans rechargement.

---

## 4. Couches architecturales

Le knowledge graph (`.understand-anything/knowledge-graph.json` : 192 nœuds, 342 arêtes)
identifie **13 couches**.

| Couche      | Rôle                                                        | Fichiers représentatifs                                   |
|-------------|------------------------------------------------------------|-----------------------------------------------------------|
| `entry`     | Points d'entrée & pages racine                             | `html/index.php`, `login.php`, `set-password.php`, `attestation_don.php`, `install.php` |
| `core-lib`  | PDO/settings, helpers date, audit log, constantes de filtres, champs d'import | `html/includes/lib/bootstrap.php`, `auth.php`, `import_fields.php` |
| `routing`   | Dispatch GET (`views.php`) et POST (`actions.php` + `$ACTION_MAP`) | `html/includes/routing/`                            |
| `views`     | Fragments PHP inclus par `views.php`                       | `html/includes/views/`, `includes/partials/`              |
| `concepts`  | Concepts transverses (dirty-guard, htmx, terminologie…)    | (transversal, pas de fichier unique)                      |
| `domain`    | Classes active-record                                      | `html/classes/` (User, Team, Compta, Metagroup, UserProperty) |
| `actions`   | Handlers POST procéduraux groupés par domaine             | `html/includes/actions/`                                  |
| `api`       | Endpoints REST JSON                                        | `html/api/`                                               |
| `tools`     | Scripts utilitaires CLI                                    | `html/tools/` (`import.php`, `fix_encoding.php`, `guest2010.php`) |
| `schema`    | DDL MariaDB idempotent (`CREATE TABLE IF NOT EXISTS`)      | `schema.sql`                                              |
| `infra`     | Docker, CI/CD, Makefile                                    | `docker-compose*.yml`, `Dockerfile`, `.github/workflows/` |
| `tests`     | Suite Playwright E2E, fixtures, reset DB                   | `tests/`                                                  |
| `docs`      | README, CHANGELOG, DESIGN, runbooks                        | `*.md`, `doc/`                                            |

> Le frontend (CSS/JS vendor) n'est pas une couche du graphe ; il est décrit en
> §9. Les libellés `auth`/`frontend` de versions antérieures de ce document ne
> correspondaient pas au graphe réel.

---

## 5. Schéma de base de données

Toutes les tables sont **InnoDB / utf8mb4**. Il n'y a **aucune clé étrangère
déclarée** : l'intégrité référentielle est assurée par le code (`SET foreign_key_checks = 0`
dans `schema.sql`). Les dates métier sont stockées en **timestamp Unix** (`int(16)`),
pas en `DATE` SQL ; conversions via `formatedDateToTimeStamp()` / `timeStampToformatedDate()`.

| Table             | Rôle                                                                                     | Clé / séquence         |
|-------------------|------------------------------------------------------------------------------------------|------------------------|
| `users`           | Membres : identité, coordonnées (dont **`email_alt`**), `sexe` (na/f/m/hf), `status` (1/0), dates Unix | `id` AUTO_INCREMENT    |
| `team`            | Segments : `name`, `hidden`                                                               | `id` AUTO_INCREMENT    |
| `user_properties` | EAV : appartenance segment (`parameter='team_<id>'`, `value='true'`) et notes de suivi   | pas de PK, `id` legacy |
| `metagroup`       | Segments combinés / catégories : `name`, `teamid`, `is_filter`, `sort_order`             | `id` via `maxval`      |
| `compta_type`     | Types d'écriture : `label`, `color`, `sort_order`, `is_cotisation`, `is_excluded_from_donation`, `is_institutional` | `id` AUTO_INCREMENT |
| `compta`          | Écritures : `user_id`, `date` (Unix), `libele`, `sum` (**decimal(10,2)**, CHF), `quittance`, `type_id`, `wants_attestation`, **`notified_at`** (dernier envoi du récapitulatif email, `NULL` = non notifiée), **`cotisation_year`** (année de cotisation si différente de l'année de paiement) | `id` AUTO_INCREMENT |
| `maxval`          | Compteur de séquence manuel (clé/valeur)                                                  | PK `parameter`         |
| `app_settings`    | Configuration organisation (clé/valeur : `org_name`, `membre_team`, `archive_id`, `org_ide`, `org_purpose`, `org_tax_status`, `smtp_*`, etc. — `value` en `TEXT` depuis la migration 0004 pour les champs multi-lignes) | PK `key`               |
| `app_users`       | Comptes applicatifs : `password_hash` (bcrypt), `role` enum, **`locale`** (langue d'interface, défaut `fr`), `force_password_change`, `is_active`, `last_login`, `reset_token`, `token_expires_at`, `email` | `id` AUTO_INCREMENT |
| `audit_log`       | Journal : `app_user_id`, `username`, `action`, `detail`, `subject_user_id`, `created_at`  | `id` AUTO_INCREMENT    |
| `email_templates` | Templates éditables (clé `tpl_*`) : `subject`, `body_text`, `body_html`, `updated_at`     | PK `key`                |
| `email_log`       | Historique des envois : `user_id`, `tpl_key`, `to_email`, `subject`, `status` (sent/error), `error_msg`, `body_text`, `body_html`, `created_at` | `id` AUTO_INCREMENT |
| `schema_migrations` | Suivi des migrations appliquées : `version`, `applied_at`, `checksum` (SHA-256, détection de dérive) | PK `version`          |

### Relations

```
app_users ── session ──> index.php ── actions/* ── auditLog() ──> audit_log

users (id)
  ├── user_properties (user_id)
  │        parameter = 'team_<N>'  → appartenance au segment team(id=N)
  │        parameter = 'suivi_*'   → notes de suivi (UserProperty)
  └── compta (user_id) ── type_id ──> compta_type
                                        (is_cotisation / is_excluded_from_donation / is_institutional)

team (id) ──> metagroup (teamid)   [1 ligne header (teamid NULL) + N lignes membres]
```

### Notes sur le modèle

- **L'appartenance à un segment n'a pas de table de jointure** : elle vit dans
  `user_properties` (`parameter = 'team_<teamId>'`, `value = 'true'`).
- `metagroup.id` est **partagé** entre la ligne header (`teamid IS NULL`, portant
  `name`) et les lignes membres (`teamid = N`), d'où l'usage de `maxval` plutôt que
  d'`AUTO_INCREMENT`.
- `user_properties.id` est une colonne héritée non fiable (nombreuses lignes à `0`) ;
  l'identité d'une propriété repose sur `user_id` + `parameter`. Le commentaire de
  `bootstrap.php` mentionne ~83k lignes concernées.

---

## 6. Classes de domaine

Fichiers dans `html/classes/`, style *active-record*, sans namespace, `global $pdo`.

### `User` (`user_class.php`) — 54 méthodes

Classe centrale. Regroupe :
- **Chargement** : `lookupUser(int $id)`, `lookupUserByEmail(string $email)`,
  `hydrateFromRow()` (privé).
- **Getters/setters** pour chaque colonne de `users` (dont `getEmailAlt`/`setEmailAlt`,
  `setBirthDay` qui parse une date d/m/Y).
- **Segments** : `getProperty()`, `isMemberOfTeam()`, `addMembership()`, `removeMembership()`.
- **Persistance** : `save()` (retourne l'id, insert ou update selon présence d'id),
  `remove()`.
- **Requêtes comptables par année** (utilisées par les vues donateurs) :

```php
public function isCotisationPayed(int $year): int  // 1re écriture cotisation de l'année, ou -1
public function hasPayed(int $year): int           // tout paiement non-cotisation
public function hasDonated(int $year): int         // don pur (exclut is_excluded_from_donation)
public function hasAnyEntry(int $year): int        // n'importe quelle écriture
public function hasComptaEntries(int $year, int $number): bool
public function hasComptaEntry(): bool
```

### `Team` (`team_class.php`)

`lookupTeam()`, `save()`, `remove()`, `isUsed()`, et gestion de l'appartenance aux
segments combinés : `isMemberOfMetagroup()`, `addMetagroupMembership()`,
`removeMetagroupMembership()`.

### `Compta` (`compta_class.php`)

`lookupCompta()`, `save()`, `remove()`, getters/setters (date, `libele`, `sum`,
`quittance`, `type_id`, `wants_attestation`, `notified_at`, `cotisation_year`).

`setCotisationYear()` valide la valeur côté serveur : rejette (met `null`) toute
année hors de la plage `[année courante - 50, année courante + 1]`, et coerce
les chaînes numériques en entier. Testé dans `tests/unit/ComptaYearTest.php`.

### `Metagroup` (`metagroup_class.php`)

`lookupMetagroup()`, `save()`, `remove()`, `isUsed()`. `id` alloué via `maxval`.

### `UserProperty` (`property_class.php`)

Note de suivi ou propriété générique. `lookupUserProperty()`, `save()`, `remove()`,
getters/setters (`parameter`, `date`, `value`).

---

## 7. Authentification & rôles

### Comptes (`app_users`, distincte de `users`)

`auth.php` gère session, login (`authLogin`), logout (`authLogout`) et gardes. Le login
vérifie `is_active = 1`, `password_verify()` contre le hash bcrypt, appelle
`session_regenerate_id(true)`, alimente `$_SESSION['app_user_*']` et met `last_login = NOW()`.
Le cookie de session est `HttpOnly`, `SameSite=Lax`, et `Secure` si HTTPS détecté.

### Les 4 rôles (enum `app_users.role`)

Prédicats réels de `auth.php` — chacun autorise les rôles supérieurs :

| Rôle       | Prédicat vrai pour                          | Droits                                                        |
|------------|---------------------------------------------|--------------------------------------------------------------|
| `readonly` | `canRead()`, `isLoggedIn()`                 | Lecture seule : vues et GET API, aucune écriture             |
| `user`     | `canWrite()`, `canRead()`                   | + écriture des membres, comptabilité, suivi                  |
| `manager`  | `isManager()`, `canWrite()`, `canRead()`    | + segments, segments combinés, réglages, **import CSV**      |
| `admin`    | `isAdmin()` (+ tous les précédents)         | + comptes applicatifs, suppression définitive, anonymisation |

```php
function isLoggedIn(): bool  // session app_user_id présent
function isAdmin(): bool     // role === 'admin'
function isManager(): bool   // role ∈ {admin, manager}
function canWrite(): bool    // role ∈ {admin, manager, user}
function canRead(): bool     // role ∈ {admin, manager, user, readonly}
```

### Gardes

- `requireLogin()` : redirige vers `login.php` si non connecté.
- `requirePasswordChange()` : si `force_password_change`, bloque toute vue/action sauf
  `changePassword` et `logout` (redirige vers `?view=changePassword`).
- `routing/views.php` déclare une **table de routes** (`$UA_VIEW_ROUTES`) associant
  chaque vue à son fichier et sa garde : `addUser`/`removeCompta`/`deleteComptaConfirm`/
  `removeSuivi`/`removeSuiviConfirm`→`canWrite`, `importStep1/2/3`/`mergeUsers`→`isManager`,
  `deleteUser`/`deleteUserConfirm`/`anonymizeUser`→`isAdmin`. Une garde refusée renvoie
  un bloc `alert-danger` (« Accès refusé ») ; une vue absente de la table renvoie
  « Vue introuvable ». Ajouter une route force donc une décision de garde explicite.
- La force brute est déléguée à Fail2Ban (logs Apache), pas au code PHP.

---

## 8. API REST (`html/api/`)

### Structure et routage

`html/api/.htaccess` réécrit les URL REST vers les scripts (ex. `members/42/groups` →
`members.php?id=42&sub=groups`). Endpoints :

| Fichier            | Ressource            | Méthodes                                   |
|--------------------|----------------------|--------------------------------------------|
| `members.php`      | `/api/members`       | GET (liste + `{id}` + `{id}/groups`), POST, PUT/PATCH, DELETE |
| `compta.php`       | `/api/compta`        | GET, POST, PUT/PATCH, DELETE               |
| `compta-types.php` | `/api/compta-types`  | GET uniquement (sinon 405)                 |
| `groups.php`       | `/api/groups`        | GET, POST, PUT/PATCH, DELETE, membres      |
| `suivi.php`        | `/api/suivi`         | GET, POST, PUT/PATCH, DELETE               |

### Middleware `_bootstrap.php`

Chaque endpoint commence par `require_once '_bootstrap.php'`, qui pose l'en-tête JSON,
charge `bootstrap.php` + `auth.php`, refuse `401` si `!isLoggedIn()`, et fournit
`apiError(int, string): never`. **La session PHP est partagée avec l'interface web** :
pas de JWT ni de clé API.

### Gardes de rôle (défense en profondeur)

Au-delà du `401`, chaque handler contrôle le rôle :

- **Lectures** (GET) : `canRead()` sinon `403`.
- **Écritures membres/compta/suivi** (POST/PUT/PATCH/DELETE) : `canWrite()` sinon `403`.
- **Écritures segments** (`groups.php` create/update/delete/add-member/remove-member) :
  `isManager()` sinon `403`.
- **Suppression définitive d'un membre** (`DELETE /api/members/{id}?dispose=delete`) :
  `isAdmin()`. Par défaut (`dispose=deactivate`) le membre est seulement archivé
  (`status=0`).

### `members.php` en détail

Dispatch par `match (true)` sur `REQUEST_METHOD` + présence de `id`/`sub` :

| Méthode      | URL                          | Handler            |
|--------------|------------------------------|--------------------|
| GET          | /api/members                 | `handleList()`     |
| GET          | /api/members/{id}            | `handleGet()`      |
| GET          | /api/members/{id}/groups     | `handleGetGroups()`|
| POST         | /api/members                 | `handleCreate()` (201, `lastName` requis) |
| PUT / PATCH  | /api/members/{id}            | `handleUpdate()`   |
| DELETE       | /api/members/{id}            | `handleDelete()` (204) |
| (autre)      | —                            | `apiError(405)`    |

`handleList()` supporte `?search=`, `?team=`, `?metagroup=`, `?page=`, `?limit=`
(max 2000), `?types=`. `handleUpdate()` charge l'avant, applique le patch, recharge
l'après, calcule un diff lisible et l'écrit dans `audit_log` (`updateUser`).

### Filtres virtuels (`MemberFilter`)

Un `?team=` **négatif** déclenche des requêtes métier dédiées, résolues par la classe
partagée `MemberFilter` (`html/classes/member_filter_class.php`) — seule source de
vérité consommée à la fois par la liste des membres (`users_list.php`) et par l'API
(`api/members.php`). `MemberFilter::resolveIds()` retourne la map `id => true` des
membres correspondants ; `MemberFilter::isVirtual()` teste si un ID de segment est
un filtre virtuel. Constantes définies dans `bootstrap.php` :

| Constante                       | Valeur | Sémantique                                                    |
|---------------------------------|--------|--------------------------------------------------------------|
| `FILTER_ALL_EXCEPT_ARCHIVES`    | -3     | Tous les membres actifs                                       |
| `FILTER_UNPAID_COTI_CURRENT`    | -4     | Membres du segment `membre_team` sans cotisation cette année  |
| `FILTER_UNPAID_COTI_3Y`         | -3333  | Ont déjà cotisé mais pas depuis 3 ans                        |
| `FILTER_NO_ACTIVITY_10Y`        | -5555  | Aucune écriture compta depuis 10 ans                        |
| `FILTER_NON_INSTIT_LAST_YEAR`   | -6666  | Paiement non-institutionnel l'an passé                       |

---

## 9. Assistant d'import CSV / TSV

Wizard web en 3 étapes réservé aux **Manager/Admin** (`isManager()` gardé côté vue
*et* côté action).

- **Actions** (`includes/actions/import.php`) : `importUpload` → `importApply` →
  `importResolveDuplicates`, mappées vers le handler `import` dans `$ACTION_MAP`.
- **Vues** : `includes/views/import_step{1,2,3}.php`.
- **Source unique des champs importables** : `includes/lib/import_fields.php`
  (`importFieldLabels()`, `importAllowedFields()`, plus la normalisation de civilité
  `importNormalizeSexe()` → `na`/`f`/`m`/`hf` et `importFieldValue()`). Champs :
  lastName, firstName, society, sexe, title, email, emailAlt, tel, telProf, portable,
  fax, address, npa, web, birthDay, comment.

**Étape 1 (`importUpload`)** : upload, conversion Latin-1 → UTF-8 si nécessaire,
suppression du BOM, détection du délimiteur (`\t`/`;`/`,`) sur la 1re ligne. Limites :
**5 Mo** et **5 000 lignes** (troncature signalée à l'étape 2). Les lignes parsées sont
stockées dans `$_SESSION['_import_*']`.

**Étape 2 (`importApply`)** : mapping colonne → champ ; au moins une colonne doit viser
un champ membre. Détection de doublons par **maps en mémoire préchargées en une seule
requête** (email, puis prénom+nom) — pas de SELECT par ligne. Création des contacts +
rattachement au segment cible (`existing`/`new`/`auto` ; segment auto nommé
`Import JJ.MM.AAAA HH:MM`), le tout dans une **transaction**. Les doublons existants
rejoignent aussi le segment. **Les lignes parsées sont libérées de la session** dès la
fin de l'étape (`unset`, anti-bloat).

**Étape 3 (`importResolveDuplicates`)** : résolution par ligne — `ignore`, `fill`
(compléter les champs vides), ou écrasement. Chaque création/mise à jour/ajout au
segment est journalisée (`auditLog`).

---

## 10. Frontend

### Bibliothèques (auto-hébergées, sauf TipTap)

| Bibliothèque                 | Version | Usage                                     |
|------------------------------|---------|-------------------------------------------|
| Bootstrap                    | 5.3     | Grille, composants, utilitaires           |
| htmx                         | 2.0.4   | Navigation SPA-like, swaps partiels       |
| Alpine.js                    | 3.x     | Composants réactifs (formulaire membre)   |
| DataTables + Bootstrap 5     | 1.13.x  | Tableaux triables/filtrables/exportables  |
| jQuery                       | 3.7.1   | Requis par DataTables & datetimepicker    |
| Moment.js + datetimepicker   | —       | Saisie de dates d/m/Y                      |
| Chart.js                     | —       | Graphiques (vue résumé)                    |
| pdfmake + jszip              | —       | Exports DataTables (PDF/Excel)            |
| Font Awesome                 | —       | Icônes                                     |
| TipTap 2 (via `esm.sh`)      | 2.x     | Éditeur rich-text du champ commentaire     |
| Inter                        | —       | Police auto-hébergée                       |

TipTap est le **seul** module chargé depuis un CDN (`esm.sh`) ; tout le reste est dans
`html/js/vendor/` et `html/css/vendor/`.

### htmx — configuration

```html
<body hx-boost="true" hx-target="#main-content" hx-swap="innerHTML" hx-push-url="true">
<meta name="htmx-config" content='{"scrollIntoViewOnBoost": false, "defaultSwapStyle": "innerHTML"}'>
```

Le JavaScript applicatif vit dans `html/js/app.js` (guard dirty-form, init des
plugins jQuery, toasts) et `html/js/tiptap-editor.js` (éditeur riche, module ES),
inclus par `index.php` avec cache-busting `filemtime`. Il n'y a plus de `<script>`
inline dans `index.php`.

Après chaque `htmx:afterSwap`, `casaInit()` (dans `app.js`) réinitialise datepickers
et `datahref`, nettoie les backdrops de modale résiduels et affiche le toast
`#casaToast` si le fragment contient `#casa-save-ok` ou `#casa-membership-toast`.
Les messages localisés du toast sont lus depuis les attributs `data-msg-*` posés
par `index.php`. Avant `htmx:beforeHistorySave`, tous les DataTables sont détruits
(`.destroy()`) pour éviter un conflit de colonnes à la restauration.

### Guard de formulaire non sauvegardé

Dans `html/js/app.js` (fonction `markDirty`) : un flag `dirty` passe à `true`
sur tout `change`/`input` d'un `INPUT`/`SELECT`/`TEXTAREA` non exclu. Il intercepte
`htmx:beforeRequest` pour confirmer avant une navigation GET (les POST — sauvegardes —
passent), et `beforeunload` pour la navigation hors-htmx. `htmx:afterSwap` remet `dirty`
et `window.__dirtyOverride` à `false`.

**Exclusions réelles** : classe `.mg-team-cb`, ids `#includeAttestation` et
`#team-filter-input`, tout ancêtre `[data-no-dirty]`, `.dt-search`/`.dataTables_filter`,
`.modal`, `#bulk-form`. Convention projet : setter `window.__dirtyOverride = true` avant
tout `window.location = …` inline, et poser `data-no-dirty` sur les selects/inputs de
navigation ou de filtre.

### Alpine — `memberGeneralForm` (`js/member-general-form.js`)

Chargé avant Alpine, initialisé via `x-data="memberGeneralForm()"`. Données passées par
attributs `data-*` (pas de `fetch` initial, compatible CSP `self`). États : `editing`,
`saving`, `saved`, `error`. `save()` n'envoie que les champs modifiés
(`draft[k] !== data[k]`) via `PATCH /api/members/{id}`, d'où un diff serveur trivial.

---

## 11. Génération de documents (attestations PDF)

Les attestations fiscales de dons sont générées **côté serveur avec `pdftk`** :

- `attestation_don.php` — une attestation pour un membre / une année. Construit un **FDF**
  (Form Data Format), encode chaque valeur en **UTF-16 BE hex** (accents corrects dans
  Adobe Reader), puis exécute `pdftk … fill_form … output … flatten` via `exec()`.
  Réponse `Content-Type: application/pdf` en pièce jointe.
- `attestation_bulk.php` — attestations de tous les donateurs qualifiés d'une année :
  un PDF par membre (même mécanisme FDF/pdftk), puis fusion en un seul fichier via
  `pdftk … cat …`.

Le gabarit AcroForm est `html/assets/attestation.pdf`. Ces deux points d'entrée
appellent `requireLogin()` mais ne sont **pas** des vues htmx (téléchargements directs).

> Il n'existe pas de génération « quittance Word / MHTML » dans le code : `quittance`
> est un simple champ texte de la table `compta`.

---

## 12. Tests & CI

### Playwright (`tests/`)

Suite E2E Node.js (specs `*.spec.ts` couvrant auth, membres, compta, groupes,
metagroups, suivi, rôles, import, réglages, API, fusion, etc.). Un `global-setup.ts`
produit un `storageState` pré-authentifié réutilisé par les specs.

Specs d'invariants issus des refactors #56–#59 : `filter-parity` (parité vue/API des
filtres virtuels), `route-guards` (matrice rôles × routes, régressions des failles
corrigées), `dirty-guard` (guard formulaire de `js/app.js`), `mobile-roles` (gardes du
menu mobile au viewport téléphone).

### Base de test

`docker-compose.test.yml` surcharge **uniquement** `DB_NAME: members_test` pour le
service `php` (même image, même port 8080, même MariaDB que la stack de dev — pas de
port 3307 ni de base « memberbase_test »). `tests/fixtures/reset-db.sh` réinitialise la
base (DDL + `tests/fixtures/seed.sql`).

### Workflow `.github/workflows/e2e.yml`

1. `docker compose -f docker-compose.yml -f docker-compose.test.yml up -d --build`.
2. Attend `http://localhost:8080/login.php` (polling curl, 30 tentatives × 3 s).
3. Node 24, `npm ci`, `npx playwright install chromium --with-deps`.
4. `chmod` sur `conf/`, puis `bash tests/fixtures/reset-db.sh`.
5. `npx playwright test --retries=0`.
6. En cas d'échec : upload du rapport `playwright-report/` (rétention 7 jours).

---

## 13. Conventions de code

### Vues (`includes/views/`)
- Fragments PHP procéduraux, accès aux variables d'`index.php` (`$pdo`, `$appSettings`,
  `$GLOBAL`, `$charset`, `$isHtmx`). Sortie utilisateur échappée
  (`htmlspecialchars` / `htmlentities`). Pas de redirection dans une vue.

### Handlers d'actions (`includes/actions/`)
- Scripts procéduraux inclus par `actions.php` via `$ACTION_MAP`. Vérifient le rôle en
  tête. Appellent `auditLog()` pour toute opération sensible. Terminent par
  `header('HX-Location: …')` (htmx) ou `header('Location: …')` puis `exit`.

### Accès base de données (`bootstrap.php`)
- PDO paramétré partout, `PDO::ATTR_EMULATE_PREPARES => false`,
  `PDO::FETCH_OBJ` par défaut, charset `utf8mb4`. Config DB via `conf/db.php`
  (installation classique) ou variables d'environnement `DB_*` (Docker).

### Séquences
- `AUTO_INCREMENT` natif pour `users`, `team`, `compta`, `compta_type`, `app_users`,
  `audit_log`. `maxval` (`getMaxVal()` / `updateAndGetMaxVal()`) uniquement pour
  `metagroup.id` (id partagé header/membres) et l'`id` legacy de `user_properties`.

### Audit log
- `auditLog(PDO $pdo, string $action, string $detail = '', ?int $subjectUserId = null)`
  insère qui/quoi/quand dans `audit_log` depuis `$_SESSION`. Appel **manuel** dans chaque
  handler modifiant des données — aucun hook automatique.

### Internationalisation
- Bundles de ressources PHP par langue dans `html/locales/` : `resources_fr.php`
  (base complète, source de vérité de toutes les clés) et `resources_en.php` /
  `resources_de.php` / `resources_es.php` (surcharges). `mbLoadLocale()`
  (`html/includes/lib/locale.php`) charge d'abord le FR puis, si différent,
  écrase avec la langue demandée — toute clé absente d'une traduction retombe
  automatiquement sur le français. Toutes les vues restent en `$GLOBAL['clé']`.
- Locale par utilisateur : colonne `app_users.locale` (défaut `fr`, migration
  `0003_app_users_locale`), copiée dans `$_SESSION['app_user_locale']` au
  login. Changement via l'action `changeLocale` (tous rôles), carte « Langue »
  sur la page Mot de passe — formulaire `hx-boost="false"` pour forcer un
  rechargement complet (rafraîchit `<html lang>`, dynamique depuis `$GLOBAL['currentLocale']`).
- Pages non authentifiées (`login.php`, `install.php`, `set-password.php`) et
  attestations PDF : toujours FR, chargé directement.
- Chaînes de données stables (marqueur `Anonymisé`, seeds `compta_type`) :
  volontairement non localisées.

### Git
- Commits signés `pvollenweider <pvollenweider@jahia.com>` (auteur *et* committer),
  sans ligne `Co-Authored-By` (voir `CLAUDE.md`).

---

## 14. Emails et notifications

### `html/includes/lib/mailer.php` — client SMTP pur PHP

Pas de dépendance externe (pas de PHPMailer/Symfony Mailer). Fonctions principales :

```php
mbSmtpSend(array $cfg, string $to, string $subject, string $bodyText, string $bodyHtml = ''): array
// Client bas niveau : plain / STARTTLS / SSL-TLS, AUTH LOGIN/PLAIN ou sans auth.
// Retourne ['ok' => bool, 'error' => string, 'debug' => string] (jamais d'exception).

mbSendMail(PDO $pdo, string $to, string $subject, string $bodyHtml, string $bodyText = ''): bool
// Helper haut niveau : lit la config SMTP depuis $appSettings, envoie, journalise
// dans email_log via _mbLogEmail(). Échec silencieux — ne casse jamais l'action appelante.

mbGetTemplate(PDO $pdo, string $key): object       // DB → repli sur mbDefaultTemplates()
mbRenderTemplate(string $tpl, array $vars): string  // remplace {{placeholder}}
mbSendTemplate(PDO $pdo, string $to, string $key, array $vars, ?int $userId = null)
// Charge le template, rend sujet/corps, envoie, journalise (avec user_id pour le lier
// à l'historique du membre).

mbBuildSalutation(string $firstname, string $lastname, string $society): array
// Gère le cas société-seule (pas de prénom/nom) : greeting, display_name, etc.

mbSmtpEncryptPassword() / mbSmtpDecryptPassword() / mbSmtpGetOrCreateEncKey()
// Le mot de passe SMTP est chiffré au repos dans app_settings avec une clé
// générée automatiquement par installation.
```

Mot de passe SMTP et secrets ne transitent jamais en clair dans les logs (`mbSmtpSend` ne journalise pas les identifiants dans `debug`).

### Handlers d'action

| Fichier | Rôle |
|---|---|
| `includes/actions/settings.php` | `saveSmtp`, `sendTestEmail`, `saveEmailTemplate`, `purgeEmailLog`, `resendEmail`, `zefixLookup` |
| `includes/actions/compta_recap.php` | `sendComptaRecap`, `sendComptaRecapOne`, `previewComptaRecap` (JSON, rendu HTML réel du template pour la modale), `markAllComptaNotified` |
| `includes/actions/cotisation_reminder.php` | `sendCotisationReminders`, `sendCotisationReminderOne` |

`compta_recap.php` factorise le chargement des entrées et la construction des
variables de template dans des fonctions privées (`_recapLoadEntries()`,
`_recapBuildVars()`, `_recapSinceLine()`) partagées entre l'envoi réel et
l'aperçu JSON — garantit que la prévisualisation correspond exactement à
l'email effectivement envoyé.

### Anti-doublon des rappels

`cotisation_reminder.php` interroge `email_log` (`tpl_key = 'tpl_cotisation_reminder'`,
filtré par année) avant d'envoyer, pour ne pas relancer deux fois le même
membre la même année sans forçage explicite.

### Zefix (registre du commerce suisse)

`zefixLookup` (action `settings.php`) interroge l'API REST publique de Zefix
en deux temps (l'ancien endpoint `GET /firm/{uid}` est décommissionné) :
1. `POST https://www.zefix.ch/ZefixREST/api/v1/firm/search.json` (recherche exacte par IDE) → `ehraid`
2. `GET https://www.zefix.ch/ZefixREST/api/v1/firm/{ehraid}.json` → nom, adresse, but statutaire

Préremplit `org_name`, `org_address`, `org_npa`, `org_city`, `org_country`,
`org_purpose` depuis le formulaire Réglages → Général. Le champ `org_tax_status`
(statut d'exonération fiscale) reste en saisie manuelle — aucun registre fédéral
n'est interrogé automatiquement pour cette donnée.
