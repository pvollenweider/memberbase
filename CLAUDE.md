# votre-domaine — notes développement

## Git — identité des commits

**Tous les commits doivent être signés `pvollenweider <pvollenweider@jahia.com>`**, aussi bien en auteur qu'en committer. Ne jamais laisser `Claude <noreply@anthropic.com>` apparaître.

Le dépôt est configuré avec `git config user.name/user.email` pour cette identité. Toujours passer `--author="pvollenweider <pvollenweider@jahia.com>"` lors d'un `git commit`, et ne jamais ajouter de ligne `Co-Authored-By:` ou `Claude-Session:` dans les messages de commit.

```bash
# ✅ correct
git commit --author="pvollenweider <pvollenweider@jahia.com>" -m "message"

# ❌ ne jamais faire
git commit -m "message
Co-Authored-By: Claude <noreply@anthropic.com>"
```

## Stack

PHP 8.2 / MariaDB / Bootstrap 5.3 / htmx 2.0.4 / Alpine.js  
Docker local : `localhost:8080`

## Conventions de code

### Commentaires en anglais

**Tous les commentaires dans le code (PHP, JS, CSS, SQL) doivent être en anglais**, quelle que soit la langue de l'interface. Les messages de commit et la doc (`CLAUDE.md`, `MIGRATION_PROD.md`, etc.) restent en français.

```php
// ✅ correct — English comment
// Load DB config from conf/db.php if present.

// ❌ à éviter — commentaire en français dans le code
// Charge la config DB depuis conf/db.php si présent.
```

### Aucun label en dur — tout dans le fichier de locale

**Aucune chaîne visible par l'utilisateur ne doit être codée en dur** dans les vues/actions : titres, boutons, labels de formulaire, messages, en-têtes de colonnes, textes d'alerte, etc. Toujours passer par le fichier de locale `html/locales/resources_fr.php` (exposé via `$GLOBAL[...]`).

```php
// ✅ correct
<button><?= htmlspecialchars($GLOBAL['save'], ENT_QUOTES, $charset) ?></button>

// ❌ label en dur
<button>Enregistrer</button>
```

- Ajouter la clé dans `html/locales/resources_fr.php` puis la référencer via `$GLOBAL['<clé>']`.
- Nommer les clés en anglais, de façon descriptive (`save`, `groupModified`, `close`…).
- Vaut aussi pour les chaînes injectées côté JS (passer par des attributs `data-*` remplis depuis la locale, cf. le toast dans `index.php`).

## Règle : navigation JS et dirty-form guard

**Toujours setter `window.__dirtyOverride = true` avant tout `window.location = ...` dans le code inline.**

```js
// ✅ correct
window.__dirtyOverride = true;
window.location = url;

// ❌ déclenche le popup "modifications non enregistrées"
window.location = url;
```

**Toujours ajouter `data-no-dirty` sur tout `<select>` ou `<input>` qui ne fait pas partie d'un vrai formulaire de saisie** (ex : selects de navigation, filtres, selects de fusion de doublons).

```html
<!-- ✅ -->
<select data-no-dirty onchange="window.__dirtyOverride=true;window.location=this.value">

<!-- ❌ marque dirty et bloque la navigation -->
<select onchange="window.location=this.value">
```

### Pourquoi

`html/js/app.js` contient un guard dirty-form global qui :
1. écoute `change`/`input` sur tous les `SELECT`, `INPUT`, `TEXTAREA` non exclus → set `dirty = true`
2. intercepte `beforeunload` et `htmx:beforeRequest` si `dirty && !__dirtyOverride` → affiche le popup

Exclusions existantes (dans `markDirty`) :
- `[data-no-dirty]` sur l'élément ou un ancêtre
- `.mg-team-cb`, `#includeAttestation`, `#team-filter-input`, `.dt-search`, `.modal`, `#bulk-form`

Couverture : `tests/dirty-guard.spec.ts`.

## Protection CSRF (actions POST)

Toute action POST passant par `includes/routing/actions.php` est vérifiée contre un jeton CSRF de session (`csrfCheck()` dans `auth.php`) **avant** le dispatch : POST sans jeton valide → `403`. Le jeton est exposé une fois dans `<meta name="csrf-token">` (rendu par `index.php`).

La propagation est automatique — **rien à faire pour un formulaire ou un appel htmx classique** :
- `html/js/app.js` ajoute l'en-tête `X-CSRF-Token` à **toutes** les requêtes htmx (`htmx:configRequest`) ;
- il « estampille » aussi tout `<form method="post">` avec un champ caché `csrf` (au chargement + après chaque swap htmx), ce qui couvre les soumissions natives (`hx-boost="false"`, upload multipart) et les `form.submit()` programmatiques.

**Seul cas à traiter à la main** : un `fetch()` inline qui POST vers `index.php`. Ajouter l'en-tête :

```js
headers: { /* … */, 'X-CSRF-Token': window.casaCsrfToken ? window.casaCsrfToken() : '' }
```

Ne concerne pas l'API REST `/api/*` (auth session + rôle, JSON only). Couverture : `tests/roles.spec.ts` (describe « Server — CSRF guard »).

## Redirections après action (htmx)

Utiliser `HX-Location` (pas `Location`) pour les réponses à des requêtes htmx :

```php
if ($isHtmx) {
    header('HX-Location: ' . $_SERVER['PHP_SELF'] . '?view=...');
    exit;
}
header('Location: ' . $_SERVER['PHP_SELF'] . '?view=...');
exit;
```

## Import CSV (wizard 3 étapes)

- Actions : `importUpload` → `importApply` → `importResolveDuplicates` dans `includes/actions/import.php`
- Vues : `importStep1/2/3` → `includes/views/import_step{1,2,3}.php`
- **Source unique des champs importables** : `includes/lib/import_fields.php` (`importFieldLabels()` / `importAllowedFields()`). Ne jamais dupliquer cette liste.
- État du wizard en `$_SESSION['_import_*']` — les lignes parsées sont libérées dès la fin d'`importApply` (ne pas les faire survivre : session bloat)
- Limites : 5 MB / 5 000 lignes (troncature signalée à l'étape 2)
- Détection de doublons : maps en mémoire (email, nom+prénom) préchargées en une requête — ne pas revenir à des SELECT par ligne
- La création des contacts est enveloppée dans une transaction

## Tests

Deux suites complémentaires :

- **E2E Playwright** (`tests/*.spec.ts`) — parcours navigateur, DB de test + seed. `make test` / `npx playwright test`. CI : `.github/workflows/e2e.yml`.
- **Unitaires PHPUnit** (`tests/unit/*Test.php`) — logique métier **pure** (sans DB). `make test-unit` (après `composer install`). CI : `.github/workflows/unit.yml` (PHP 8.2).

Les fonctions pures testables vivent dans `html/includes/lib/pure.php` (dates, `unquote`) et `html/includes/lib/import_fields.php` (`importNormalizeSexe`, `importFieldValue`) — **sans effet de bord**, pour être incluables hors du contexte `APP_ENTRY`/PDO. Le bootstrap unitaire (`tests/unit/bootstrap.php`) définit `APP_ENTRY` et n'inclut que ces fichiers. Ne pas y introduire de dépendance à `$pdo` ou à une connexion DB.

## Migrations DB

Tout changement de schéma = un fichier `html/migrations/NNNN_description.sql` (**sous `html/`** pour être déployé avec l'app — les prods qui ne synchronisent que `html/` l'emportent ; accès HTTP refusé par `html/migrations/.htaccess`), appliqué par `php html/tools/migrate.php` (ou `make migrate`). État suivi dans la table `schema_migrations`. **Ne plus mettre de SQL manuel dans `MIGRATION_PROD.md`.**

- `migrate.php --status` : voir appliquées / en attente.
- `migrate.php --baseline` : marquer tout comme appliqué sans exécuter (fresh install — géré automatiquement par `install.php`).
- **Checksum / dérive** : `schema_migrations.checksum` stocke le SHA-256 de chaque migration appliquée. `migrate.php --status` signale `[!] DÉRIVE` (et sort en code 2) si un fichier déjà appliqué a été modifié ; la page **Réglages → Santé** l'affiche aussi. Corollaire : **ne jamais éditer une migration déjà appliquée** — en créer une nouvelle.
- Écrire les migrations idempotentes quand possible (`ADD COLUMN IF NOT EXISTS`).
- ⚠️ Le DDL MySQL est auto-committé (pas de rollback) : sauvegarde avant migration prod.
- Garde de cohérence : `install.php` embarque le schéma complet à jour ET baseline les migrations (cf. `tests/` garde anti-dérive `schema.sql`↔`install.php`).

## Colonne `users.status`

`TINYINT(1) NOT NULL DEFAULT 1` — 1 = actif, 0 = inactif.  
**Tous les listings doivent filtrer `WHERE ... AND users.status = 1`** (ou `u.status = 1` selon l'alias).  
Voir `MIGRATION_PROD.md` pour les requêtes SQL à appliquer en prod.
