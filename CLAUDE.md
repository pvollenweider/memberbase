# membres.casa-alianza.ch — notes développement

## Stack

PHP 8.2 / MariaDB / Bootstrap 5.3 / htmx 2.0.4 / Alpine.js  
Docker local : `localhost:8080`

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

`index.php` contient un guard dirty-form global qui :
1. écoute `change`/`input` sur tous les `SELECT`, `INPUT`, `TEXTAREA` non exclus → set `dirty = true`
2. intercepte `beforeunload` et `htmx:beforeRequest` si `dirty && !__dirtyOverride` → affiche le popup

Exclusions existantes (dans `markDirty`) :
- `[data-no-dirty]` sur l'élément ou un ancêtre
- `.mg-team-cb`, `#includeAttestation`, `.dt-search`, `.modal`, `#bulk-form`

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

## Colonne `users.status`

`TINYINT(1) NOT NULL DEFAULT 1` — 1 = actif, 0 = inactif.  
**Tous les listings doivent filtrer `WHERE ... AND users.status = 1`** (ou `u.status = 1` selon l'alias).  
Voir `MIGRATION_PROD.md` pour les requêtes SQL à appliquer en prod.
