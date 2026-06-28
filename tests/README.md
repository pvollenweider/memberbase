# Tests E2E — membres.casa-alianza.ch

Tests d'intégration Playwright pour l'application de gestion des membres.

## Prérequis

- PHP 8.2 + MariaDB en cours d'exécution (voir `docker-compose.yml` ou installation locale)
- Node.js ≥ 18
- `npm install` effectué à la racine du projet

```bash
npm install
npx playwright install chromium
```

## Lancer les tests

```bash
# Tous les tests (réinitialise la base de données de test puis lance le tout)
npx playwright test

# Un fichier spécifique
npx playwright test tests/members.spec.ts

# Avec sortie lisible (pas de JSON)
npx playwright test --reporter=dot

# Mode débogage interactif
npx playwright test --debug
```

Le setup global (`tests/global-setup.ts`) s'exécute automatiquement avant chaque session :
1. Supprime `conf/db.php` s'il existe (évite d'écraser la base de prod)
2. Recrée la base `members_test` depuis `tests/fixtures/seed.sql`
3. Effectue un login admin et sauvegarde l'état d'authentification dans `tests/.auth/admin.json`

## Configuration

`playwright.config.ts` à la racine du projet :

| Paramètre | Valeur |
|---|---|
| `baseURL` | `http://localhost:8080` |
| `storageState` | `tests/.auth/admin.json` |
| `retries` | 1 |
| `fullyParallel` | false (tests séquentiels par défaut) |

La variable d'environnement `DB_NAME=members_test` est positionnée par le script de reset (`tests/fixtures/reset-db.sh`) pour isoler les tests de la base de production.

## Fichiers de test

| Fichier | Ce qui est couvert |
|---|---|
| `auth.spec.ts` | Login, logout, accès refusé sans session |
| `members.spec.ts` | Liste, recherche, ajout, modification, suppression d'un membre |
| `compta.spec.ts` | Voir, ajouter, modifier, supprimer une écriture comptable |
| `groups.spec.ts` | Voir, créer, renommer, supprimer un groupe (équipe) |
| `settings.spec.ts` | Navigation onglets réglages, modification d'un paramètre |
| `suivi.spec.ts` | Voir, ajouter, modifier, supprimer une note de suivi |
| `inactive-members.spec.ts` | Désactiver, lister les membres archivés, réactiver |
| `app-users.spec.ts` | Lister, créer, supprimer un utilisateur applicatif |
| `change-password.spec.ts` | Changement de mot de passe et restauration |
| `compta-types.spec.ts` | Ajouter et supprimer un type de compta |
| `metagroups.spec.ts` | Créer, renommer, supprimer un filtre (métagroupe) |
| `merge-users.spec.ts` | Fusionner deux fiches membres |
| `views.spec.ts` | Journal d'audit, historique, rapports donateurs, intégrité |
| `resume.spec.ts` | Vue résumé des dons |

## Données de test

`tests/fixtures/seed.sql` contient :
- 1 utilisateur admin (`testadmin` / `TestPassword1!`)
- 2 membres Alice Dupont (id=1) et Bob Martin (id=2)
- 2 groupes (id=1 et id=2)
- 1 écriture comptable (comptaid=1)

Les tests qui créent des données les nettoient eux-mêmes. La base est de toute façon réinitialisée au début de chaque session.

## Conventions

- Les blocs `test.describe.serial` sont utilisés quand les tests d'un groupe dépendent les uns des autres (ex. créer avant de supprimer).
- Les soumissions de formulaires htmx-boostées qui posent des problèmes de course sont précédées de `page.evaluate(() => document.body.removeAttribute('hx-boost'))` pour forcer un POST classique.
- `Promise.all([page.waitForNavigation(), ...click()])` est utilisé pour les navigations pleine page (non-htmx).
