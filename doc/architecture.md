# Architecture de MemberBase

MemberBase v3.5.3 — application PHP 8.2 de gestion des membres pour ONG.
Licence AGPL-3.0-or-later.

---

## 1. Vue d'ensemble

MemberBase est une application PHP classique sans framework MVC. Elle repose sur
quatre principes structurants :

- **Point d'entrée unique** : `html/index.php` reçoit toutes les requêtes de
  l'interface web. Les fichiers de vues et les handlers d'actions sont inclus
  dynamiquement par les routeurs.
- **Pas d'ORM ni de framework** : PDO paramétré en accès direct, classes de
  domaine en accès actif (active-record), pas de namespace, pas de Composer.
- **htmx 2.0 pour la navigation** : `<body hx-boost="true">` intercepte tous les
  clics de liens et les soumet via XHR. La réponse est swappée dans
  `#main-content` sans rechargement de page. Le layout complet (menu, scripts,
  footer) reste en place.
- **Alpine.js 3 pour les composants réactifs** : utilisé pour les zones de la
  page qui nécessitent un état local (mode view/edit du formulaire membre,
  par exemple). Les composants Alpine sont déclarés dans des fichiers JS externes
  (`html/js/member-general-form.js`) pour rester compatibles avec une CSP `self`.

La réponse à une requête htmx est un fragment HTML pur (pas de JSON, pas de
template engine dédié). Pour les accès API machine-to-machine, un sous-répertoire
`html/api/` expose des endpoints REST distincts.

---

## 2. Flux d'une requête GET (navigation htmx)

```
Navigateur
  |
  |-- GET index.php?view=generalData&id=42
  |   [HX-Request: true]
  |
  v
html/index.php
  |
  |-- requireLogin()          <- auth.php: vérifie $_SESSION['app_user_id']
  |-- requirePasswordChange() <- redirige si force_password_change=1
  |
  |-- $isHtmx = true         <- HTTP_HX_REQUEST présent
  |
  |-- include routing/actions.php  <- traite $_REQUEST['action'] si présent
  |-- include routing/views.php    <- dispatche $_REQUEST['view']
  |       |
  |       +-- include views/users_edit_form.php
  |               |
  |               +-- new User()->lookupUser(42)
  |               +-- echo fragment HTML (<div>...</div>)
  |
  ob_end_flush()
  exit
  |
  v
Navigateur
  htmx swap innerHTML de #main-content
  htmx pushState url (hx-push-url="true")
```

Quand `$isHtmx` est `false` (premier chargement), `index.php` émet le layout
complet : `<!DOCTYPE html>`, head avec tous les scripts, menu, `#main-content`,
footer. Le contenu de `#main-content` est rendu au même endroit dans les deux
chemins.

---

## 3. Flux d'une requête POST (soumission de formulaire)

Le pattern suivi est Post-Redirect-Get (PRG), adapté pour htmx.

```
Navigateur
  |
  |-- POST index.php
  |   body: action=updateUser&id=42&firstName=Ana...
  |   [HX-Request: true]
  |
  v
html/index.php
  |
  |-- requireLogin(), requirePasswordChange()
  |
  |-- include routing/actions.php
  |       |
  |       +-- $ACTION_MAP['updateUser'] => 'members'
  |       +-- require includes/actions/members.php
  |               |
  |               +-- valide les entrées
  |               +-- $user->save()
  |               +-- auditLog(...)
  |               +-- if ($isHtmx) {
  |                       header('HX-Location: index.php?view=generalData&id=42');
  |                       exit;
  |                   }
  |                   header('Location: ...'); exit;  // fallback non-htmx
  |
  v
Navigateur
  htmx intercepte HX-Location (pas une vraie redirection HTTP)
  htmx émet un nouveau GET vers index.php?view=generalData&id=42
  htmx swap #main-content avec le fragment reçu
  htmx affiche le toast #casa-save-ok (détecté dans htmx:afterSwap)
```

`HX-Location` est utilisé à la place de l'en-tête `Location` standard car htmx
intercepte les redirections 302 mais les traite différemment selon la version.
`HX-Location` déclenche une navigation htmx propre sans rechargement de page.

---

## 4. Couches architecturales

Le graphe de connaissance identifie 14 couches logiques :

| Couche       | Rôle                                                          | Fichiers représentatifs                       |
|--------------|---------------------------------------------------------------|-----------------------------------------------|
| `infra`      | Infrastructure Docker, CI/CD, Makefile                        | `docker-compose.yml`, `.github/workflows/`    |
| `entry`      | Point d'entrée unique de l'application web                    | `html/index.php`                              |
| `core-lib`   | PDO, helpers de date, audit log, constantes de filtres        | `html/includes/lib/bootstrap.php`             |
| `auth`       | Session PHP, bcrypt, gardes d'accès, 4 rôles                  | `html/includes/lib/auth.php`                  |
| `routing`    | Dispatch GET (views.php) et POST (actions.php)                | `html/includes/routing/`                      |
| `domain`     | Classes active-record : User, Team, Compta, Metagroup...      | `html/classes/`                               |
| `actions`    | Handlers POST procéduraux groupés par domaine                 | `html/includes/actions/`                      |
| `views`      | Fragments PHP inclus par views.php                            | `html/includes/views/`                        |
| `api`        | Endpoints REST JSON (machine-to-machine)                      | `html/api/`                                   |
| `tools`      | Scripts utilitaires CLI (import, fix encoding, guest)         | `html/tools/` (`fix_encoding.php`, etc.)      |
| `frontend`   | CSS, JS vendor et custom, composants Alpine                   | `html/css/`, `html/js/`                       |
| `schema`     | DDL MariaDB, idempotent (`CREATE TABLE IF NOT EXISTS`)        | `schema.sql`                                  |
| `tests`      | Suite Playwright E2E, fixtures, reset DB                      | `tests/`, `tests/reset-db.sh`                 |
| `docs`       | README, CHANGELOG, DESIGN, CONTRIBUTING, runbooks             | `*.md`                                        |

---

## 5. Schéma de base de données

### Tables et leur rôle

| Table            | Moteur  | Rôle                                                                       |
|------------------|---------|----------------------------------------------------------------------------|
| `users`          | InnoDB  | Membres : données personnelles, statut (1=actif, 0=archivé)               |
| `team`           | InnoDB  | Groupes : nom, visibilité (`hidden`)                                       |
| `user_properties`| InnoDB  | EAV multi-usage : appartenance groupe (`team_N`=true), notes de suivi      |
| `metagroup`      | InnoDB  | Métagroupes : regroupe plusieurs teams en catégories de filtres            |
| `compta_type`    | InnoDB  | Types de transaction : libellé, couleur, flags is_cotisation / is_excluded |
| `compta`         | InnoDB  | Écritures comptables : montant (CHF), date, quittance, lien type           |
| `maxval`         | InnoDB  | Séquences manuelles pour metagroup_id et user_properties.id (legacy)      |
| `app_settings`   | InnoDB  | Configuration organisation : clé/valeur (org_name, membre_team, etc.)     |
| `app_users`      | InnoDB  | Comptes applicatifs : bcrypt, rôle, force_password_change, last_login      |
| `audit_log`      | InnoDB  | Journal d'activité : qui, quoi, quand, sur quel membre                     |

### Relations principales (diagramme ASCII)

```
app_users ──── (session) ───────────────────────> index.php
    |                                               |
    v                                               v
audit_log <── auditLog() ──────────────────── actions/*

users (id)
  |
  +──────────────────────────> user_properties (user_id)
  |                              parameter = 'team_N'  --> team (id=N)
  |                              parameter = 'suivi_*' --> notes libres
  |
  +──────────────────────────> compta (user_id)
                                  |
                                  +----> compta_type (type_id)
                                           is_cotisation
                                           is_excluded_from_donation
                                           is_institutional

team (id)
  |
  +──> metagroup (teamid) [N lignes par métagroupe : 1 header + N members]
```

### Notes importantes sur le modèle

- L'appartenance d'un membre à un groupe n'est **pas** une table de jointure
  dédiée : elle est stockée dans `user_properties` avec
  `parameter = 'team_<teamId>'` et `value = 'true'`. Cela permet d'ajouter
  des métadonnées par appartenance (date d'entrée, etc.) sans changer le schéma.

- Les dates sont stockées en **timestamp Unix** (`int(16)`), pas en `DATE` SQL.
  Les fonctions `formatedDateToTimeStamp` et `timeStampToformatedDate` font la
  conversion dans `bootstrap.php`.

- Toutes les tables utilisent **InnoDB** (utf8mb4_unicode_ci). Il n'y a pas de
  clés étrangères déclarées au niveau SQL ; l'intégrité référentielle est
  assurée par le code applicatif.

- `maxval` est un compteur de séquence manuel, subsistant pour `metagroup_id`
  (dont l'`id` est partagé entre ligne header et lignes membres) et
  `user_properties.id` (colonne cassée à 0 pour 83k lignes, non corrigée).
  Les nouvelles tables utilisent `AUTO_INCREMENT`.

---

## 6. Classes de domaine

Toutes les classes se trouvent dans `html/classes/`. Elles suivent le pattern
**active-record** : chaque instance représente une ligne, et les méthodes
d'accès à la base sont des membres de la classe. La variable globale `$pdo`
est accédée via `global $pdo` dans chaque méthode (pas d'injection de
dépendances). Il n'y a pas de namespace.

### User (`user_class.php`) — 51 méthodes

Classe centrale. Couvre : chargement (`lookupUser`, `lookupUserByEmail`),
persistance (`save`, `remove`), getters/setters pour chaque colonne de `users`,
appartenance groupe (`isMemberOfTeam`, `addMembership`, `removeMembership`),
et requêtes comptables :

```php
// Retourne le timestamp de la première écriture cotisation de l'année,
// ou -1 si aucune.
public function isCotisationPayed(int $year): int

// Tout paiement non-cotisation dans l'année
public function hasPayed(int $year): int

// Don pur (exclut is_excluded_from_donation)
public function hasDonated(int $year): int

// N'importe quelle écriture comptable dans l'année
public function hasAnyEntry(int $year): int
```

Ces méthodes sont utilisées par les vues de rapport (donors_loyal, donors_new,
donors_lapsed) pour calculer les badges de statut par membre.

### Team (`team_class.php`)

Chargement d'un groupe (`lookupTeam`), persistance, liste des membres via
`user_properties`.

### Compta (`compta_class.php`)

Écriture comptable : chargement (`lookupCompta`), persistance (`save`, `remove`),
lecture des champs (date, libellé, montant, quittance, type).

### Metagroup (`metagroup_class.php`)

Métagroupe (filtre nommé regroupant plusieurs teams). Chargement et persistance.
L'`id` d'un métagroupe est partagé entre la ligne header (`teamid IS NULL`) et
les lignes membres (`teamid = N`), d'où la nécessité de `maxval` plutôt que
`AUTO_INCREMENT`.

### UserProperty (`property_class.php`)

Note de suivi (`parameter` commençant par `suivi_`) ou propriété générique
par membre. Chargement, persistance, suppression.

---

## 7. Système d'authentification

### Stockage des comptes

La table `app_users` est entièrement séparée de la table `users` (membres).
Les comptes applicatifs portent :
- `password_hash` : bcrypt via `password_hash()` / `password_verify()`
- `role` : énumération à 4 niveaux
- `force_password_change` : bloque toute navigation jusqu'au changement
- `is_active` : soft-disable sans suppression
- `last_login` : mis à jour à chaque connexion réussie
- `reset_token` / `token_expires_at` : réinitialisation de mot de passe

### Les 4 rôles

| Rôle       | Prédicat PHP   | Droits                                           |
|------------|----------------|--------------------------------------------------|
| `readonly` | `isLoggedIn()` | Lecture seule — toutes les vues, aucune écriture |
| `user`     | `canWrite()`   | Lecture + écriture des membres et comptabilité   |
| `manager`  | `isManager()`  | `user` + gestion des groupes et paramètres       |
| `admin`    | `isAdmin()`    | `manager` + gestion des comptes applicatifs      |

Les prédicats sont définis dans `auth.php` et appelés inline dans les routeurs
et les handlers. Les vues qui nécessitent `canWrite()` vérifient le rôle en tête
de handler et renvoient un bloc `alert-danger` en cas de refus.

### Cycle de session

```php
// auth.php — login
session_regenerate_id(true);          // évite la fixation de session
$_SESSION['app_user_id']     = ...;
$_SESSION['app_user_role']   = ...;
$_SESSION['force_password_change'] = ...;

// auth.php — guard (appelé en tête de index.php)
requireLogin();           // redirige vers login.php si pas de session
requirePasswordChange();  // redirige vers changePassword si flag actif
```

Le cookie de session est configuré `HttpOnly`, `SameSite=Lax`, `Secure` si
HTTPS détecté. La protection contre les attaques de force brute est déléguée
à **Fail2Ban** côté serveur (analyse des logs Apache), pas au code PHP.

---

## 8. API REST

### Structure

```
html/api/
  _bootstrap.php     middleware partagé (JSON headers + auth guard)
  members.php        CRUD membres
  compta.php         CRUD écritures comptables
  compta-types.php   liste des types
  groups.php         lecture des groupes d'un membre
  suivi.php          CRUD notes de suivi
```

Le fichier `html/api/.htaccess` configure Apache pour router
`/api/members/42` vers `members.php?id=42` (RewriteRule).

### Middleware `_bootstrap.php`

```php
define('APP_ENTRY', true);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/lib/bootstrap.php';
require_once __DIR__ . '/../includes/lib/auth.php';
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
```

Chaque endpoint commence par `require_once __DIR__ . '/_bootstrap.php'`. La
session PHP est donc partagée entre l'interface web et l'API (pas de token JWT
ni de clé API séparée).

### Endpoint `members.php`

Dispatche selon `REQUEST_METHOD` et présence de `$_GET['id']` via `match (true)` :

| Méthode          | URL                      | Handler          |
|------------------|--------------------------|------------------|
| GET              | /api/members             | `handleList()`   |
| GET              | /api/members/{id}        | `handleGet()`    |
| GET              | /api/members/{id}?sub=groups | `handleGetGroups()` |
| POST             | /api/members             | `handleCreate()` |
| PUT ou PATCH     | /api/members/{id}        | `handleUpdate()` |
| DELETE           | /api/members/{id}        | `handleDelete()` |

### Filtres virtuels (`handleVirtualFilter`)

Quand `GET /api/members?team=-N` reçoit un `teamId` négatif, il est dirigé vers
`handleVirtualFilter()` plutôt que vers une requête de jointure classique. Ces
filtres encodent des règles métier complexes :

| Constante                  | Valeur  | Sémantique                                              |
|----------------------------|---------|---------------------------------------------------------|
| `FILTER_ALL_EXCEPT_ARCHIVES` | -3    | Tous les membres actifs                                 |
| `FILTER_UNPAID_COTI_CURRENT` | -4    | Membres sans cotisation cette année                     |
| `FILTER_UNPAID_COTI_3Y`     | -3333  | Membres qui ont déjà cotisé mais pas depuis 3 ans       |
| `FILTER_NO_ACTIVITY_10Y`    | -5555  | Membres sans aucune écriture depuis 10 ans              |
| `FILTER_NON_INSTIT_LAST_YEAR`| -6666 | Membres avec un paiement non-institutionnel l'an passé  |

Ces constantes sont définies dans `bootstrap.php` et partagées entre l'API et
les vues PHP classiques.

### PATCH et audit log

Le handler `handleUpdate()` charge le membre avant modification, snapshote les
champs (`memberFieldsForDiff()`), applique le patch, sauvegarde, puis enregistre
le diff dans `audit_log` :

```php
$before = memberFieldsForDiff($user);
applyFields($user, $body);
$user->save();
$after  = memberFieldsForDiff($user);
$diff   = array_filter(...);  // champs modifiés seulement
auditLog($pdo, 'updateMember', json_encode($diff), $id);
```

Le composant Alpine `memberGeneralForm` envoie un PATCH avec seulement les
champs modifiés (comparaison `draft` vs `data`), ce qui rend le diff trivial
côté serveur.

---

## 9. Frontend

### Bibliothèques (toutes auto-hébergées, zéro CDN)

| Bibliothèque              | Version | Usage                                            |
|---------------------------|---------|--------------------------------------------------|
| Bootstrap                 | 5.3.8   | Grille, composants UI, utilitaires               |
| htmx                      | 2.0.4   | Navigation SPA-like, swaps partiels              |
| Alpine.js                 | 3.x     | Composants réactifs inline (formulaire membre)   |
| DataTables + Bootstrap 5  | 1.13.x  | Tableaux triables, filtrables, exportables       |
| jQuery                    | 3.7.1   | Requis par DataTables et datetimepicker          |
| Moment.js + bootstrap-datetimepicker | — | Saisie de dates au format d/m/Y      |
| Chart.js                  | —       | Graphiques de synthèse (vue résumé)              |
| Font Awesome              | —       | Icônes                                           |
| TipTap 2 (via esm.sh)     | 2.x     | Éditeur rich text pour le champ commentaire      |
| Inter                     | —       | Police typographique auto-hébergée               |

TipTap est le seul module chargé depuis un CDN externe (`esm.sh`) — tous les
autres sont dans `html/js/vendor/` et `html/css/vendor/`.

### htmx — configuration et conventions

```html
<body hx-boost="true" hx-target="#main-content" hx-swap="innerHTML" hx-push-url="true">
<meta name="htmx-config" content='{"scrollIntoViewOnBoost": false, "defaultSwapStyle": "innerHTML"}'>
```

- `hx-boost` sur `<body>` : tous les liens `<a>` et formulaires sans attribut
  htmx explicite deviennent des requêtes XHR automatiquement.
- `hx-target="#main-content"` : la réponse remplace le contenu du `<div
  id="main-content">` sans toucher au layout.
- `hx-push-url="true"` : l'URL du navigateur est mise à jour via `history.pushState`.

Gestion des redirections POST : les handlers PHP émettent `header('HX-Location: ...')`
(pas un 302 standard) pour que htmx effectue une navigation propre sans
rechargement de page.

### Guard de formulaire non sauvegardé

Un script inline dans `index.php` maintient un flag `dirty` mis à `true` lors de
tout `change` ou `input` sur un champ de formulaire. Il intercepte `htmx:beforeRequest`
pour demander confirmation si `dirty` est vrai et que la requête est un GET (navigation).
Les soumissions POST (verb=post) passent sans confirmation. `htmx:afterSwap` remet
`dirty` à `false`.

Les champs exclus du guard (attribut `data-no-dirty` sur le conteneur, ou classes
spécifiques comme `.mg-team-cb`, `.dt-search`) permettent aux cases de gestion
de groupe et aux filtres DataTables de ne pas déclencher l'avertissement.

### Alpine.js — composant `memberGeneralForm`

Fichier : `html/js/member-general-form.js`

Composant chargé avant Alpine (`defer`) en mode `x-data="memberGeneralForm()"`.
Les données initiales sont passées via des attributs `data-*` sur le noeud racine
(évite un `fetch` initial et reste compatible CSP `self`).

États : `editing` (toggle view/edit), `saving` (appel PATCH en cours),
`saved` (feedback après sauvegarde réussie), `error` (message d'erreur).

La méthode `save()` construit un objet `patch` contenant uniquement les champs
dont la valeur a changé (`draft[k] !== data[k]`), puis envoie un `PATCH` vers
`/api/members/{id}`. En cas de succès, elle met à jour `this.data` avec la
réponse du serveur et passe `editing = false`.

### DataTables — configuration partagée (`dt_defaults.js`)

```js
var CA_DT_DOM     = '<"d-flex ..."<"d-flex gap-2"B>f>rtip';
var CA_DT_BUTTONS = [{ extend: 'collection', ... }];  // Copier / Excel / PDF / Imprimer
var CA_DT_LANGUAGE = { info: '_TOTAL_ entrées', ... };
```

Ces constantes sont incluses dans le `<head>` et réutilisées par chaque vue qui
initialise un DataTable, ce qui garantit un comportement uniforme (langue FR,
boutons d'export, layout).

Avant que htmx sauvegarde le DOM dans l'historique (`htmx:beforeHistorySave`),
tous les DataTables sont détruits (`.destroy()`) pour éviter un conflit de
colonnes lors de la restauration.

### Spécificité CSS et priorité

Les règles Bootstrap utilisent `!important` sur les utilitaires (`d-flex`, etc.).
Les classes de visibilité applicatives (`team-hidden`) utilisent également
`!important` pour garantir qu'un groupe masqué ne réapparaisse pas lors d'un
recalcul de styles. Le fichier `html/css/custom.css` étend Bootstrap avec des
variables CSS (`--ca-danger`, `--ca-primary`) et des composants propres à
l'application.

---

## 10. Génération de documents

### Attestations PDF (pdftk)

Les attestations fiscales de dons utilisent `pdftk` pour remplir un formulaire
AcroForm PDF prédéfini. Les valeurs sont encodées en **UTF-16 BE** pour garantir
l'affichage des caractères accentués dans Adobe Reader. La commande est construite
et exécutée via `exec()` ou `shell_exec()` depuis un handler PHP.

### Quittances Word (MHTML)

Les quittances sont générées au format **MHTML** (MIME HTML), un format que Word
sait ouvrir directement. Le contenu est un template HTML avec les données du
membre et de l'écriture comptable injectées, emballé dans une enveloppe MHTML et
livré avec le Content-Type `application/vnd.ms-word`.

---

## 11. Tests

### Suite Playwright E2E

Les tests se trouvent dans `tests/` et utilisent **Playwright** (Node.js).
Chaque spec charge un `storageState` pré-authentifié (fichier JSON généré une
fois lors du setup) pour éviter de rejouer le login à chaque test.

### Base de données de test

Le fichier `docker-compose.test.yml` remplace le nom de base `members` par
`memberbase_test` et expose MariaDB sur le port 3307 pour éviter les collisions
avec la stack de développement. Le script `tests/reset-db.sh` réinitialise la
base à un état connu (import du DDL + fixtures) avant chaque run de la suite.

### CI GitHub Actions

Le workflow `.github/workflows/e2e.yml` :
1. Lance la stack Docker avec les deux fichiers compose (`base + test`).
2. Attend que `login.php` réponde (polling curl, 30 tentatives, 3 s d'intervalle).
3. Exécute `reset-db.sh`.
4. Lance `npx playwright test` avec `--retries=0`.
5. En cas d'échec, uploade le rapport HTML Playwright comme artifact (7 jours).

---

## 12. Conventions de code

### Vues (fragments PHP)

- Pas de classe, pas de namespace.
- Fichier inclus directement par `views.php`, a accès aux variables définies
  dans `index.php` (`$pdo`, `$appSettings`, `$GLOBAL`, `$charset`, `$isHtmx`).
- Toute sortie utilisateur passe par `htmlspecialchars()` ou `htmlentities()`.
- Pas de logique de redirection dans les vues.

### Handlers d'actions (`includes/actions/`)

- Scripts procéduraux inclus par `actions.php`.
- Vérifient le rôle en tête (`canWrite()`, `isManager()`, `isAdmin()`).
- Accèdent à `$_REQUEST`, `$_POST`, `$_GET`.
- Appellent `auditLog()` pour toute modification sensible (suppression,
  anonymisation, changement de mot de passe).
- Terminent par un `header('HX-Location: ...')` suivi de `exit` (chemin htmx)
  ou `header('Location: ...')` (fallback).

### Accès base de données

- PDO paramétré partout : aucune interpolation de variable dans les requêtes SQL.
- `PDO::ATTR_EMULATE_PREPARES => false` : les paramètres sont envoyés séparément
  côté base, ce qui prévient les injections même sur les variantes de charset.
- `PDO::FETCH_OBJ` par défaut : les résultats sont des objets stdClass.

### Audit log

La fonction `auditLog()` dans `bootstrap.php` insère une ligne dans `audit_log`
avec l'identifiant et le nom de l'utilisateur applicatif courant (`$_SESSION`),
le nom de l'action, un détail libre, et optionnellement l'`id` du membre cible.
Elle est appelée manuellement dans chaque handler qui modifie des données
sensibles ; il n'y a pas de hook automatique.

### Identifiants et séquences

- `users.id`, `team.id`, `compta.id`, `app_users.id` : `AUTO_INCREMENT` natif.
- `metagroup.id`, `user_properties.id` : `maxval` + `getMaxVal()`/`updateAndGetMaxVal()`.
  La colonne `user_properties.id` est en réalité non utilisée (valeur 0 pour la
  majorité des lignes) ; seul `user_id` + `parameter` identifie une propriété.

### Internationalisation

Un seul fichier de ressources : `html/locales/resources_fr.php`. L'application
est entièrement en français, sans infrastructure i18n.
