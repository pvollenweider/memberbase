# Handoff — branche `claude/lapsed-donors-clickable-rows-nfkj9l`

> Fichier temporaire de passation de contexte. À supprimer après lecture.

---

## Stack

PHP 8.2 / MariaDB / Bootstrap 5.3 / htmx 2.0.4 / Alpine.js  
Docker local : `localhost:8080`

## Règles importantes (CLAUDE.md)

- Toujours `window.__dirtyOverride = true` avant tout `window.location = ...`
- Utiliser `HX-Location` (pas `Location`) pour les réponses htmx
- Tous les listings doivent filtrer `WHERE ... AND users.status = 1`
- Commentaires en **anglais** dans le code, locale en **français**
- Tous les commits au nom de **Philippe Vollenweider** `<polito@gmail.com>`

---

## Ce qui a été fait

### 1. Clickable rows — table des donateurs perdus
- Les lignes de toutes les tables de donateurs (lapsed, loyal, new) naviguent vers `?view=compta&userid=X` au clic (toutes les années, pas de filtre d'année)
- Implémentation via attribut `data-href` + handler jQuery délégué — le CSS `stretched-link` ne fonctionne **pas** sur les `<tr>`
- Fichier : `html/includes/_donor_table.php`

### 2. Composant table partagé
- `html/includes/_donor_table.php` — composant réutilisable pour les tables de donateurs
- `html/js/dt_defaults.js` — configuration DataTables centralisée (`CA_DT_DOM`, `CA_DT_BUTTONS`, `CA_DT_COLVIS`, `CA_DT_LANGUAGE`)

### 3. Architecture — renommage `.inc` → `.php`
Tous les fichiers `includes/*.inc` et `locales/*.inc` renommés en `.php` pour éviter qu'Apache les serve en texte brut.

### 4. Protection des répertoires
- `.htaccess` avec `Require all denied` dans `html/includes/`, `html/classes/`, `html/locales/`
- Blocs `<Directory>` ajoutés dans `conf/apache.conf` pour les deux VirtualHosts (port 80 et 443) — nécessaire car la prod a `AllowOverride AuthConfig` qui ignore les `.htaccess`

### 5. Licence AGPL-3.0
- Fichier `LICENSE` créé (par l'utilisateur sur GitHub, fusionné via `git merge origin/main`)
- En-têtes de copyright ajoutés sur tous les fichiers PHP du projet (pas les librairies vendor) :
  ```php
  /**
   * @copyright 2024 Philippe Vollenweider
   * @license   AGPL-3.0-or-later
   */
  ```
- Fichiers concernés : tous les `html/includes/*.php`, `html/classes/*.php`, `html/*.php`

### 6. Généralisation — suppression du hard-coding org-spécifique
Toutes les données Casa Alianza remplacées par des clés `app_settings` :

| Clé DB               | Usage                        |
|----------------------|------------------------------|
| `org_name`           | Nom de l'organisation        |
| `org_address`        | Adresse                      |
| `org_npa`            | NPA                          |
| `org_city`           | Ville                        |
| `org_country`        | Pays                         |
| `membre_team_prefix` | Préfixe du groupe membre (ex. "Membre") |

- Formulaire de settings (`settings_form.php`) étendu avec ces champs
- `actions/settings.php` gère la sauvegarde (`INSERT ... ON DUPLICATE KEY UPDATE`)
- `attestation_bulk.php` utilise `$appSettings['org_name']` etc.

**Migration SQL nécessaire en prod :**
```sql
INSERT INTO app_settings (`key`, `value`) VALUES
  ('org_name', ''), ('org_address', ''), ('org_npa', ''),
  ('org_city', ''), ('org_country', ''), ('membre_team_prefix', 'Membre')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
```

### 7. Constantes nommées — suppression des magic numbers
Dans `html/includes/declarations.php` :
```php
const FILTER_ALL_EXCEPT_ARCHIVES  = -3;
const FILTER_UNPAID_COTI_CURRENT  = -4;
const FILTER_UNPAID_COTI_3Y       = -3333;
const FILTER_NO_ACTIVITY_10Y      = -5555;
const FILTER_NON_INSTIT_LAST_YEAR = -6666;
```
Tous les `-3`, `-3333`, etc. dans `view_users.php` remplacés par ces constantes.

### 8. Centralisation i18n — `html/locales/resources_fr.php`
Toutes les chaînes françaises codées en dur extraites vers le fichier de locale (`$GLOBAL[...]`).

**Clés ajoutées (sélection) :**
```php
// Navigation
$GLOBAL['search'], $GLOBAL['logout'], $GLOBAL['changePassword']
$GLOBAL['donationOverview'], $GLOBAL['administration']

// Actions courantes
$GLOBAL['save'], $GLOBAL['saved'], $GLOBAL['cancel'], $GLOBAL['close']
$GLOBAL['confirm'], $GLOBAL['archive'], $GLOBAL['anonymize']
$GLOBAL['delete'], $GLOBAL['edit'], $GLOBAL['addBtn'], $GLOBAL['addGroups']

// Confirmations contextuelles
$GLOBAL['confirmMerge'], $GLOBAL['confirmAnonymize']
$GLOBAL['deleteAll'], $GLOBAL['deletePermanently'], $GLOBAL['deleteOrArchive']
$GLOBAL['deleteEntry'], $GLOBAL['deleteSuiviEntry']
$GLOBAL['archiveMember'], $GLOBAL['anonymizeProfile']
$GLOBAL['editGroup'], $GLOBAL['editCompta'], $GLOBAL['editMetagroup']

// Dashboard / resume
$GLOBAL['donors'], $GLOBAL['activeMembers'], $GLOBAL['contributions']
$GLOBAL['loyalDonors'], $GLOBAL['newDonors'], $GLOBAL['lapsedDonors']
$GLOBAL['last12Months'], $GLOBAL['last24Months'], $GLOBAL['allEntries']
$GLOBAL['wantsAttestation'], $GLOBAL['wantsAttestationLabel']
$GLOBAL['donationsOnly'], $GLOBAL['withoutType'], $GLOBAL['nonDonation']
$GLOBAL['historyByYear'], $GLOBAL['distByType']

// Formulaires
$GLOBAL['contactInfo'], $GLOBAL['additionalInfo']
$GLOBAL['city'], $GLOBAL['country'], $GLOBAL['confirmPassword']
$GLOBAL['groupModified']
```

**Fichiers modifiés pour utiliser ces clés :**
`menu.php`, `compta_generic.php`, `resume.php`, `manage_views.php`,
`update_user_form.php`, `update_compta_form.php`, `update_suivi_form.php`,
`anonymize_user.php`, `merge_users.php`, `update_metagroup_form.php`,
`update_team_form.php`, `manage_compta_types.php`, `manage_groups.php`,
`manage_filters.php`, `manage_categories.php`, `suivi.php`, `memberOf.php`,
`audit_log.php`, `manage_app_users.php`, `settings_form.php`,
`inactive_users.php`, `add_user_form.php`, `index.php`, `set-password.php`

### 9. Commentaires et docblocks
- Fonctions utilitaires dans `declarations.php` documentées avec PHPDoc
- Commentaires traduits en anglais dans les fichiers modifiés

### 10. Auteur des commits
Tous les commits rebased via `git rebase --exec 'git commit --amend --reset-author --no-edit'` pour avoir `author = Philippe Vollenweider <polito@gmail.com>`.

---

## Ce qui reste à faire (éventuel)

- Appliquer la migration SQL en prod (voir section 6)
- Continuer à localiser d'éventuels messages d'erreur/aide encore en dur
- Créer une PR vers `main` quand la branche est validée
