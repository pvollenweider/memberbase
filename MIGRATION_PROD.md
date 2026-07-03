# Migrations base de données

Depuis la v3.5.6, les changements de schéma passent par un **système de
migrations versionnées** — plus de SQL manuel en production.

## Workflow

Les migrations sont des fichiers `html/migrations/NNNN_description.sql`
(**sous `html/`** pour être déployés avec l'app — les instances qui ne
synchronisent que `html/` les emportent ; l'accès HTTP à ces `.sql` est refusé
par `html/migrations/.htaccess`), appliqués dans l'ordre du nom. L'état appliqué
est suivi dans la table `schema_migrations`.

```bash
# Déploiement type d'une instance existante
git pull
php html/tools/migrate.php --status   # voir ce qui est en attente
php html/tools/migrate.php             # appliquer les migrations en attente
# (avec Docker : make migrate)
```

- **Rejouable** : relancer le runner est un no-op si rien n'est en attente.
- **Détection de dérive** : `--status` compare le checksum (SHA-256) de chaque
  migration appliquée à son fichier actuel et signale `[!] DÉRIVE` si un fichier
  déjà appliqué a été modifié après coup (à ne jamais faire — créer une nouvelle
  migration à la place).
- **Alerte in-app** : tant qu'une migration est en attente, un bandeau
  d'avertissement s'affiche en haut des pages **pour les administrateurs**, avec
  la commande à lancer. Il disparaît une fois les migrations appliquées.
- **Fresh install** : le wizard `install.php` pose le schéma complet à jour puis
  « baseline » automatiquement toutes les migrations (elles ne sont pas rejouées).
- **Nouvelle migration** : ajouter un fichier `html/migrations/NNNN_xxx.sql`
  (numéro suivant), le committer, il sera appliqué au prochain `migrate.php`.

## ⚠️ Sauvegarde & recovery

MySQL/MariaDB **valide implicitement le DDL** (`CREATE`/`ALTER`) : une migration
DDL ne peut pas être annulée par un `ROLLBACK`. **Toujours faire une sauvegarde
avant de migrer en production**. Les migrations DML (données) sont, elles,
transactionnelles (rollback automatique sur erreur).

### Sauvegarder / restaurer

Des scripts déployés avec l'app (`html/tools/`, config lue depuis `conf/db.php`
ou l'env) — leur cycle complet est **testé en CI** (`.github/workflows/backup.yml` :
seed → dump → drop → restore → vérification) :

```bash
# Sauvegarde (juste avant une migration)
bash html/tools/backup.sh backup_avant_migration.sql

# Migration
php html/tools/migrate.php

# Recovery en cas d'échec DDL : restaurer depuis la sauvegarde
bash html/tools/restore.sh backup_avant_migration.sql
```

En local avec Docker : `make backup [FILE=dump.sql]` / `make restore FILE=dump.sql`.

## Historique

- `0001_email_alt.sql` — colonne `users.email_alt` (v3.5.4). Idempotent
  (`ADD COLUMN IF NOT EXISTS`), donc sans risque même si la colonne existe déjà
  (instances migrées à la main avant l'introduction du runner).
- `0002_compta_sum_decimal.sql` — `compta.sum` passe de `VARCHAR(64)` à
  `DECIMAL(10,2)` (fin des montants stockés en texte). La migration **nettoie
  d'abord** les valeurs : virgules → points, puis toute valeur non numérique ou
  vide → `0`, avant l'`ALTER`. ⚠️ Ce nettoyage écrase les valeurs non
  numériques : vérifier la page **Réglages → Intégrité** et corriger les
  montants douteux **avant** de migrer en prod (et sauvegarder, DDL non
  annulable). Sur une base déjà propre, la conversion est sans perte.

---

## En-têtes de sécurité HTTP (#70)

`docker/apache.conf` pose désormais un socle d'en-têtes de sécurité. **Le vhost
HTTPS de prod est géré à la main** — répliquer le même bloc dans le
`<VirtualHost *:443>` (dans un `<IfModule mod_headers.c>`) :

```apache
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Content-Security-Policy-Report-Only "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
```

La CSP est en **`Report-Only`** (ne bloque pas). Vérifier la console du
navigateur : tant qu'il reste des violations légitimes, ne pas passer en
enforcement. Étapes pour durcir ensuite : self-héberger TipTap (supprime la
dépendance `esm.sh`), remplacer `'unsafe-inline'` par des nonces, puis renommer
l'en-tête en `Content-Security-Policy`.

Vérification : `curl -I https://membres.casa-alianza.ch/ | grep -i -E 'x-frame|x-content|referrer|strict-transport|content-security'`.

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
