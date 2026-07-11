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

**Sans accès SSH (cas le plus fréquent)** : l'admin peut tout faire depuis
**Réglages → Santé** — bouton **« Exporter la base (SQL) »** (dump téléchargeable,
sans `mysqldump`) puis **« Appliquer les migrations »** (case « j'ai fait une
sauvegarde » obligatoire). Le lien apparaît aussi dans le bandeau d'alerte.

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
- `0013_user_team_join_table.sql` — remplace le stockage EAV de l'appartenance
  aux équipes (`user_properties`, clé `team_N`) par une table de jointure
  `user_team` ; backfill automatique depuis les lignes EAV existantes, qui sont
  ensuite supprimées.
- `0014_rename_team_to_segment.sql` — **breaking (v5.0.0)** : renomme la table
  `team` → `segment`, `user_team` → `user_segment` (colonne `team_id` →
  `segment_id`), et `metagroup.teamid` → `metagroup.segmentid`.
- `0015_rename_users_to_contact.sql` — **breaking (v5.0.0)** : renomme la table
  `users` → `contact`, `user_segment` → `contact_segment`, `user_properties` →
  `contact_properties`. Combinée à `0014`, toute requête SQL ou intégration
  externe référençant `users`/`team`/`user_team` doit être mise à jour.
- `0016_settings_org_iban.sql` — nouveau réglage `org_iban` (bulletin de
  versement QR pour les rappels de cotisation).
- `0017_settings_coti_amount_desc.sql` — nouveau réglage
  `org_coti_amount_desc` (texte du champ « Montant » sur le rappel/bulletin QR).
- `0022_metagroup_member_table.sql` / `0024_rename_metagroup_to_combined_segment.sql`
  — **breaking (v5.1.0)** : `metagroup` devient une vraie table `AUTO_INCREMENT`
  avec jointure `metagroup_member`, puis l'ensemble est renommé
  `combined_segment`/`combined_segment_member`. Classe PHP `Metagroup` →
  `CombinedSegment`.
- `0023_foreign_keys.sql` — contraintes FK réelles sur `contact_segment`,
  `contact_properties`, `compta`, `combined_segment_member`, `audit_log`,
  `email_log` (fin de `foreign_key_checks=0`). Nettoie d'abord les lignes
  orphelines (⚠️ suppression de données — vérifier **Réglages → Intégrité**
  et sauvegarder avant de migrer en prod).
- `0025_rename_team_settings_to_segment.sql` — **breaking (v5.1.0)** : clés
  `app_settings` `default_team`/`membre_team`/`member_no_coti_team`/
  `membre_team_prefix` → `*_segment`/`*_segment_prefix` ; préfixe
  `contact_properties.parameter` `team_<id>` → `segment_<id>`.
- `0026`-`0030` — **breaking (v5.1.0)**, issue #143 : `contact.modificationDate`,
  `contact.creationDate`, `contact_properties.date`, `compta.date` (`int(16)` →
  `DATETIME`) et `contact.birthday` (`int(16)` → `DATE`). `0` n'est plus la
  sentinelle « non renseigné » — c'est désormais `NULL`.

  > ⚠️ **Prérequis obligatoire avant `0028`/`0029`/`0030`** : les tables de
  > fuseaux horaires nommés de MariaDB doivent être chargées, sinon le
  > backfill des dates échoue silencieusement (toutes les valeurs deviennent
  > `NULL` — pas une date fausse, mais une perte réelle si l'ancienne colonne
  > `int` a déjà été supprimée par la migration). Vérifier **avant** de
  > lancer ces migrations :
  > ```sql
  > SELECT CONVERT_TZ(NOW(), @@session.time_zone, 'Europe/Zurich');
  > ```
  > Si le résultat est `NULL`, charger les tables d'abord :
  > ```bash
  > mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql
  > ```
  > puis revérifier que la requête ci-dessus renvoie une vraie date avant de
  > relancer les migrations. En cas de backfill déjà parti en `NULL` : ne pas
  > restaurer la sauvegarde complète (perd tout ce qui a été écrit depuis) —
  > restaurer la sauvegarde dans une **base séparée** et recopier uniquement
  > les colonnes affectées par `id`, une fois les tables de fuseaux chargées.

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
enforcement. Étapes pour durcir ensuite : remplacer `'unsafe-inline'` par des
nonces, puis renommer l'en-tête en `Content-Security-Policy`.

Vérification : `curl -I https://votre-domaine/ | grep -i -E 'x-frame|x-content|referrer|strict-transport|content-security'`.

---

## Configuration Apache — routes API

Les endpoints API utilisent des URLs propres (`/api/contacts/42`).
`mod_rewrite` doit être actif et les règles déclarées **dans le vhost** (pas via `.htaccess`,
car `AllowOverride AuthConfig` bloque les directives Rewrite dans `.htaccess`).

> ⚠️ **v5.0.0** : les routes `/api/members` et `/api/groups` sont renommées en
> `/api/contacts` et `/api/segments` (aucune rétrocompatibilité). Remplacer le
> bloc `<Directory>` ci-dessous dans le vhost de prod en même temps que le
> déploiement du code.

```bash
a2enmod rewrite
systemctl reload apache2
```

Ajouter ce bloc dans le vhost HTTPS, à l'intérieur du `<VirtualHost *:443>` (identique à `docker/apache.conf`) :

```apache
<Directory "/var/www/vhosts/votre-domaine/html/api">
    Options FollowSymLinks
    AllowOverride None
    Require all granted

    RewriteEngine On
    RewriteRule ^contacts/([0-9]+)/groups/?$    contacts.php?id=$1&sub=groups [QSA,L]
    RewriteRule ^contacts/([0-9]+)/?$           contacts.php?id=$1            [QSA,L]
    RewriteRule ^contacts/?$                    contacts.php                  [QSA,L]
    RewriteRule ^compta/([0-9]+)/?$             compta.php?id=$1              [QSA,L]
    RewriteRule ^compta/?$                      compta.php                    [QSA,L]
    RewriteRule ^compta-types/?$                compta-types.php              [QSA,L]
    RewriteRule ^suivi/([0-9]+)/?$              suivi.php?id=$1               [QSA,L]
    RewriteRule ^suivi/?$                       suivi.php                     [QSA,L]
    RewriteRule ^segments/([0-9]+)/members/?$   segments.php?id=$1&sub=members [QSA,L]
    RewriteRule ^segments/([0-9]+)/?$           segments.php?id=$1            [QSA,L]
    RewriteRule ^segments/?$                    segments.php                  [QSA,L]
</Directory>
```

Vérification post-déploiement :

```bash
# Auth required → 401, not 500
curl -s -o /dev/null -w "%{http_code}" https://votre-domaine/api/contacts.php

# mod_rewrite active → 401, not 404
curl -s -o /dev/null -w "%{http_code}" https://votre-domaine/api/contacts/1
```

---

## Attribution des rôles

Après déploiement sur une instance existante, ajuster les rôles des utilisateurs
si nécessaire via **Admin → Réglages → Utilisateurs**, ou via SQL :

```sql
-- Example: promote a user to manager
UPDATE app_users SET role = 'manager' WHERE username = 'prenom.nom';

-- Check current role distribution
SELECT role, COUNT(*) FROM app_users GROUP BY role;
```
