# Mise en production — checklist et migrations SQL

> Contexte : session de dev juin 2026.
> Toutes les modifs sont dans `html/`. Les migrations SQL sont à appliquer
> sur la base MariaDB de prod **dans l'ordre indiqué**.

---

## 1. Migrations SQL (obligatoires)

### 1a. AUTO_INCREMENT sur `team`, `users`, `compta`

```sql
ALTER TABLE team   MODIFY id INT        NOT NULL AUTO_INCREMENT;
ALTER TABLE users  MODIFY id INT(8)     NOT NULL AUTO_INCREMENT;
ALTER TABLE compta MODIFY id INT(8)     NOT NULL AUTO_INCREMENT;

-- Supprimer les entrées maxval devenues inutiles
DELETE FROM maxval WHERE parameter IN ('teamid','userid','comptaid');
```

> `metagroup` et `user_properties` restent avec maxval (voir issue #7 pour la suite).

---

### 1b. Ajouter la colonne `is_institutional` sur la table `compta_type`

```sql
ALTER TABLE compta_type
  ADD COLUMN is_institutional TINYINT(1) NOT NULL DEFAULT 0
  AFTER is_excluded_from_donation;
```

Puis marquer les types institutionnels (adapter les IDs selon prod) :

```sql
-- Vérifier d'abord les IDs :
SELECT id, label FROM compta_type ORDER BY sort_order;

-- Marquer "Public / Institutionnel" (id=5 en dev) :
UPDATE compta_type SET is_institutional = 1 WHERE label = 'Public / Institutionnel';
```

---

### 1b. Ajouter la colonne `status` sur la table `users`

```sql
ALTER TABLE users
  ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1
  AFTER modificationDate;
```

> Si la colonne existe déjà (erreur `Duplicate column name`), ignorer.

Vérification :

```sql
DESCRIBE users;
-- doit avoir : status | tinyint(1) | NO | | 1 |
```

---

### 1b. Migrer les membres du groupe Archive → inactifs

Le groupe Archive a l'ID **19** (`$appSettings['archive_id']`).  
Cette requête passe tous les membres de ce groupe en `status=0` :

```sql
UPDATE users
SET    status = 0
WHERE  status = 1
  AND  id IN (
    SELECT user_id
    FROM   user_properties
    WHERE  parameter = 'team_19'
  );
```

> **Décision à prendre** : veut-on également retirer ces membres du groupe
> Archive après les avoir désactivés ? Si oui, ajouter :
>
> ```sql
> DELETE FROM user_properties
> WHERE  parameter = 'team_19'
>   AND  user_id IN (
>     SELECT id FROM users WHERE status = 0
>   );
> ```
>
> À ne faire qu'une fois, et seulement si le groupe Archive ne doit plus
> servir de liste manuelle.

---

## 2. Fichiers PHP modifiés

| Fichier | Nature de la modif |
|---|---|
| `html/classes/user_class.inc` | Ajout `public int $status = 1;` + `status` dans `SELECT_COLS` + hydratation |
| `html/includes/update_user_form.inc` | Switch BS5 dans navbar, overlay inactif, boutons Supprimer/Anonymiser |
| `html/includes/manage_actions.inc` | Nouvelles actions : `mergeUsers`, `reactivateUser`, `deactivateUser`, `anonymizeUser`, `deleteOrDeactivateUser` |
| `html/includes/manage_views.inc` | Nouvelles vues : `mergeUsers`, `inactiveUsers`, `anonymizeUser`, `deleteUser` redesign |
| `html/includes/manage_integrity.inc` | Filtres `status=1`, tri NOM Prénom, boutons Fusionner, bannière membres inactifs |
| `html/includes/memberOf.inc` | Section "Ajouter un groupe" collapsée par défaut (`<details>`) |
| `html/includes/settings_form.inc` | Lien "Masqués" vers `?view=inactiveUsers` dans sidebar |
| `html/css/custom.css` | Styles `.ca-merge-*`, `.ca-inactive-*`, `.ca-integrity-*` |
| `html/includes/view_users.inc` | Filtre `AND users.status=1` |
| `html/includes/lastEntryCompta.inc` | Filtre `AND u.status=1` |
| `html/includes/resume.inc` | Filtre `AND u.status=1` |
| `html/includes/lastEntrySuivi.inc` | Filtre `AND users.status=1` |
| `html/includes/new_donors.inc` | Filtre `AND u.status=1` |
| `html/includes/lapsed_donors.inc` | Filtre `AND u.status=1` |
| `html/includes/loyal_donors.inc` | Filtre `AND u.status=1` |
| `html/includes/lapsed_members.inc` | Filtre `AND u.status=1` |
| `html/includes/update_team_form.inc` | Filtre `AND u.status=1` |

---

## 3. Nouveaux fichiers PHP

| Fichier | Rôle |
|---|---|
| `html/includes/merge_users.inc` | Vue de fusion de deux profils (Alpine.js) |
| `html/includes/inactive_users.inc` | Liste des profils inactifs + bouton Réactiver |
| `html/includes/anonymize_user.inc` | Page de confirmation d'anonymisation |
| `html/includes/actions/members.php` | Toutes les actions membres (étendu) |

---

## 4. Vérifications post-déploiement

```sql
-- Compter les membres actifs vs inactifs
SELECT status, COUNT(*) FROM users GROUP BY status;

-- Vérifier qu'aucun inactif ne remonte dans view_users
-- (tester manuellement dans l'app)

-- Compter les doublons de noms parmi actifs uniquement
SELECT TRIM(LOWER(CONCAT(firstName,' ',lastName))) AS nom, COUNT(*) AS n
FROM users
WHERE status = 1 AND (firstName != '' OR lastName != '')
GROUP BY nom
HAVING n > 1
ORDER BY n DESC;
```

---

## 5. Rollback minimal (si problème)

```sql
-- Remettre tous les membres en actif (rollback status)
UPDATE users SET status = 1 WHERE status = 0;

-- Supprimer la colonne (dernier recours)
ALTER TABLE users DROP COLUMN status;
```
