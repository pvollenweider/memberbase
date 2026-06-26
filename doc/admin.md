# Guide administrateur — Casa Alianza Membres

Ce guide couvre les tâches réservées aux comptes de rôle **admin** : gestion des utilisateurs de l'application, configuration, déploiement et maintenance serveur.

---

## Comptes utilisateurs de l'application

Accès : **Groupes** → icône engrenage → **Utilisateurs** (ou `?view=manageAppUsers`).

### Créer un compte

1. Cliquer **Nouvel utilisateur**
2. Saisir : nom d'affichage, identifiant (login), mot de passe temporaire, rôle (`admin` ou `user`)
3. Cliquer **Créer**
4. Communiquer le mot de passe temporaire à l'utilisateur — il devra le changer à la première connexion

### Réinitialiser un mot de passe

1. Dans la liste des utilisateurs, cliquer **Réinitialiser** à côté du compte concerné
2. Un mot de passe temporaire est affiché une seule fois à l'écran — le noter et le transmettre à l'utilisateur
3. L'utilisateur sera forcé de changer ce mot de passe à sa prochaine connexion

> Le mot de passe temporaire n'apparaît jamais dans les logs Apache (transmis via session, pas via URL).

### Supprimer un compte

Dans la liste des utilisateurs, cliquer **Supprimer** → confirmer. L'opération est irréversible.

### Rôles

| Rôle | Accès |
|------|-------|
| `user` | Toutes les fonctions métier (membres, compta, groupes, rapports) |
| `admin` | Idem + gestion des comptes utilisateurs |

---

## Réglages de l'application

Accès : icône engrenage dans la barre de navigation → **Réglages** (ou `?view=settings`).

La page de réglages est organisée en sections accessibles via une barre latérale (desktop) ou un sélecteur déroulant (mobile).

### Général

| Réglage | Description |
|---------|-------------|
| **Groupe par défaut** | Groupe affiché à l'ouverture de la liste membres |
| **Groupe membres de référence** | Groupe utilisé comme référence pour les filtres cotisation |
| **Groupe archives** | Groupe exclu des vues par défaut (filtre "tous sauf archives") |

Modifier les valeurs et cliquer **Enregistrer**.

### Groupes

Liste de tous les groupes actifs avec deux actions par ligne :

- **Crayon** — renommage rapide inline, sans rechargement de page
- **Engrenage** — ouvre la page de réglages complète du groupe (catégorie, visibilité, membres)

Pour créer un groupe, utiliser le formulaire en haut de l'onglet. La section "Importer les membres d'autres groupes" propose les groupes source triés par catégorie.

---

## Types de compta

Accès : icône engrenage → **Réglages** → section **Types de compta** (barre latérale).

Les types de compta définissent les catégories d'entrées (cotisation, don ponctuel, don récurrent, etc.).

### Créer un type

1. Remplir : libellé, classe de couleur Bootstrap (ex. `success`, `info`, `warning`, `danger`, `primary`)
2. Cocher les flags selon le besoin :
   - **Cotisation** — ce type est pris en compte dans les filtres "cotisation non payée" et l'import par cotisants
   - **Exclu des dons** — ce type n'apparaît pas dans la vue Contributions ni dans les attestations
3. Cliquer **Ajouter**

### Modifier un type

Cliquer l'icône crayon sur la ligne → modifier → **Enregistrer**.

### Archiver un type

Cocher **Archivé** sur un type existant. Le type disparaît du formulaire de saisie mais reste visible sur les entrées historiques. À utiliser plutôt que supprimer si le type a des entrées existantes.

### Supprimer un type

Disponible uniquement si le type n'est utilisé par aucune entrée compta. Sinon, archiver.

### Réordonner

Glisser-déposer les lignes pour changer l'ordre d'affichage dans les formulaires de saisie.

---

## Catégories de groupes

Accès : **Groupes** → onglet **Catégories**.

Les catégories organisent visuellement les groupes dans les listes (elles n'apparaissent pas dans le menu de filtrage).

### Créer une catégorie

1. Saisir le nom dans le champ en bas
2. Cliquer **Créer**

### Réordonner

Glisser-déposer les catégories dans la liste.

### Assigner un groupe à une catégorie

1. Aller sur **Groupes** → cliquer l'icône engrenage du groupe
2. Sélectionner la catégorie dans le champ **Catégorie**
3. Cliquer **Mettre à jour**

---

## Filtres de groupes (métagroupes)

Accès : **Groupes** → onglet **Filtres**.

Un filtre regroupe plusieurs groupes : sélectionner ce filtre dans la liste membres affiche l'union de tous ses groupes membres.

### Créer un filtre depuis zéro

1. Saisir un nom dans le champ en bas de l'onglet Filtres
2. Cliquer **Créer un filtre**
3. Cliquer le nom du filtre créé pour ouvrir sa page d'édition
4. Cocher les groupes à inclure — sauvegarde automatique à chaque coche

> Un bouton **Annuler** apparaît dans la notification après chaque modification (fenêtre de 4 secondes).

### Créer un filtre depuis une sélection de groupes

1. Dans l'onglet **Groupes**, cocher plusieurs groupes
2. Cliquer **Créer un métagroupe**
3. Nommer le métagroupe créé depuis sa page d'édition

---

## Déploiement

L'application se déploie par **rsync** (pas de git pull sur le serveur).

```bash
rsync -avz --delete html/ user@membres.casa-alianza.ch:/var/www/membres.casa-alianza.ch/html/
```

Adapter le chemin de destination selon la configuration Apache du serveur.

### Prérequis serveur

- PHP 8+ avec extensions PDO, PDO_MySQL, mbstring, gd
- MariaDB (ou MySQL 8+)
- `pdftk-java` pour la génération d'attestations PDF : `apt install pdftk-java`
- Apache avec mod_rewrite

### Première installation

1. Créer la base de données et importer le schéma SQL
2. Configurer `html/includes/declarations.inc` avec les paramètres de connexion DB
3. Créer le compte admin initial — voir `migration_app_users.sql`
4. Se connecter avec `admin` / `ChangeMe123!` et changer le mot de passe immédiatement

---

## Fail2Ban

Le serveur est configuré avec Fail2Ban pour bloquer les attaques par force brute sur le formulaire de login.

### Règle en place

- **5 tentatives échouées** en 5 minutes → bannissement de l'IP pendant **24 heures**
- Un login échoué retourne HTTP 200 (login échoué affiche le formulaire) ; un succès retourne HTTP 302 (redirection) — Fail2Ban distingue les deux

### Vérifier l'état

```bash
fail2ban-client status casa-login
```

Affiche les IPs actuellement bannies et les compteurs.

### Débannir une IP manuellement

```bash
fail2ban-client set casa-login unbanip <IP>
```

### IPs en whitelist (jamais bannies)

La whitelist est configurée dans `/etc/fail2ban/jail.local` section `[DEFAULT]` sous `ignoreip`. Elle s'applique à tous les jails (SSH, Apache, Casa Login).

Pour ajouter une IP ou un CIDR :

1. Éditer `/etc/fail2ban/jail.local`
2. Ajouter l'IP à la ligne `ignoreip = ...` (séparées par des espaces)
3. Redémarrer Fail2Ban : `systemctl restart fail2ban`
4. Vérifier : `fail2ban-client -d | grep ignoreip`

### Vérifier qu'une IP est bien whitelistée

```bash
fail2ban-client -d | grep ignoreip
```

Toutes les jails doivent afficher la même liste d'IPs.

---

## Sécurité — points importants

- **Mots de passe** stockés en bcrypt (`password_hash` / `password_verify`), jamais en clair
- **Cookie de session** avec `secure=true` (HTTPS uniquement) et `httponly=true`
- **Mot de passe temporaire** transmis via session flash, jamais en paramètre URL (pas de trace dans les logs Apache)
- **Tous les endpoints** nécessitent une authentification — `requireLogin()` en tête de `index.php`, `attestation_don.php` et `attestation_bulk.php`
- **HTTPS** obligatoire — le cookie session ne fonctionne pas en HTTP

### Procédure en cas de compromission d'un compte

1. Se connecter avec un compte admin
2. Aller dans **Utilisateurs** → **Réinitialiser** le mot de passe du compte compromis
3. Communiquer le nouveau mot de passe temporaire par un canal sécurisé
4. L'utilisateur change le mot de passe à la connexion suivante

---

## Sauvegarde de la base de données

La base n'est pas sauvegardée automatiquement par l'application. Mettre en place une sauvegarde régulière côté serveur :

```bash
mysqldump -u <user> -p <dbname> | gzip > backup_$(date +%Y%m%d).sql.gz
```

À planifier via cron ou le système de backup du serveur.
