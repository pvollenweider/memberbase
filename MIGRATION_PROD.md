# Migrations base de données

Depuis la v3.5.6, les changements de schéma passent par un **système de
migrations versionnées** — plus de SQL manuel en production.

## Workflow

Les migrations sont des fichiers `migrations/NNNN_description.sql` (à la racine
du dépôt, hors webroot), appliqués dans l'ordre du nom. L'état appliqué est
suivi dans la table `schema_migrations`.

```bash
# Déploiement type d'une instance existante
git pull
php html/tools/migrate.php --status   # voir ce qui est en attente
php html/tools/migrate.php             # appliquer les migrations en attente
# (avec Docker : make migrate)
```

- **Rejouable** : relancer le runner est un no-op si rien n'est en attente.
- **Fresh install** : le wizard `install.php` pose le schéma complet à jour puis
  « baseline » automatiquement toutes les migrations (elles ne sont pas rejouées).
- **Nouvelle migration** : ajouter un fichier `migrations/NNNN_xxx.sql` (numéro
  suivant), le committer, il sera appliqué au prochain `migrate.php`.

## ⚠️ Sauvegarde & recovery

MySQL/MariaDB **valide implicitement le DDL** (`CREATE`/`ALTER`) : une migration
DDL ne peut pas être annulée par un `ROLLBACK`. **Toujours faire une sauvegarde
avant de migrer en production** (`make db` / `mysqldump`). En cas d'échec en
cours de DDL, la recovery = restauration depuis la sauvegarde. Les migrations
DML (données) sont, elles, transactionnelles (rollback automatique sur erreur).

## Historique

- `0001_email_alt.sql` — colonne `users.email_alt` (v3.5.4). Idempotent
  (`ADD COLUMN IF NOT EXISTS`), donc sans risque même si la colonne existe déjà
  (instances migrées à la main avant l'introduction du runner).

---

## Actions manuelles historiques (avant le système de migrations)

> Conservé pour référence. Sur une instance déjà à jour, rien à faire.

---

## `feature/api-members` — API JSON + 4 rôles

### 1. Mise à jour du schéma — rôles utilisateurs

La colonne `app_users.role` doit accepter deux nouvelles valeurs (`manager`, `readonly`).

```sql
ALTER TABLE app_users
  MODIFY COLUMN role ENUM('admin','manager','user','readonly')
  NOT NULL DEFAULT 'user';
```

> Aucune donnée existante n'est affectée : `admin` et `user` restent valides.

---

### 2. Configuration Apache — routes API

Les endpoints API utilisent des URLs propres (`/api/members/42`).
`mod_rewrite` doit être actif et les règles déclarées **dans le vhost** (pas via `.htaccess`,
car `AllowOverride AuthConfig` bloque les directives Rewrite dans `.htaccess`).

```bash
a2enmod rewrite
systemctl reload apache2
```

Ajouter ce bloc dans le vhost HTTPS (`/etc/apache2/sites-available/membres.casa-alianza.ch.conf`),
à l'intérieur du `<VirtualHost *:443>` :

```apache
<Directory "/var/www/vhosts/membres.casa-alianza.ch/html/api">
    Options FollowSymLinks
    AllowOverride None
    Require all granted

    RewriteEngine On
    RewriteRule ^members/([0-9]+)/groups/?$     members.php?id=$1&sub=groups  [QSA,L]
    RewriteRule ^members/([0-9]+)/?$            members.php?id=$1             [QSA,L]
    RewriteRule ^members/?$                     members.php                   [QSA,L]
    RewriteRule ^compta/([0-9]+)/?$             compta.php?id=$1              [QSA,L]
    RewriteRule ^compta/?$                      compta.php                    [QSA,L]
    RewriteRule ^compta-types/?$                compta-types.php              [QSA,L]
    RewriteRule ^suivi/([0-9]+)/?$              suivi.php?id=$1               [QSA,L]
    RewriteRule ^suivi/?$                       suivi.php                     [QSA,L]
    RewriteRule ^groups/([0-9]+)/members/?$     groups.php?id=$1&sub=members  [QSA,L]
    RewriteRule ^groups/([0-9]+)/?$             groups.php?id=$1              [QSA,L]
    RewriteRule ^groups/?$                      groups.php                    [QSA,L]
</Directory>
```

Puis recharger Apache :

```bash
apachectl configtest && systemctl reload apache2
```

---

### 3. Déploiement des fichiers

S'assurer que les nouveaux fichiers sont présents après `git pull` :

```
html/api/_bootstrap.php
html/api/.htaccess
html/api/members.php
html/api/compta.php
```

---

### 4. Vérification post-déploiement

```bash
# L'API répond (authentification requise → doit retourner 401, pas 500)
curl -s -o /dev/null -w "%{http_code}" https://votre-domaine/api/members.php
# → 401

# mod_rewrite actif (URL propre → doit retourner 401, pas 404)
curl -s -o /dev/null -w "%{http_code}" https://votre-domaine/api/members/1
# → 401
```

---

### 5. Attribution des rôles

Après déploiement, ajuster les rôles des utilisateurs existants si nécessaire
via l'interface Admin → Paramètres → Utilisateurs, ou via SQL :

```sql
-- Exemple : passer un utilisateur en manager
UPDATE app_users SET role = 'manager' WHERE username = 'prenom.nom';

-- Vérifier la répartition actuelle
SELECT role, COUNT(*) FROM app_users GROUP BY role;
```

---

## Versions précédentes

Aucune migration requise pour les versions antérieures à `feature/api-members`.
