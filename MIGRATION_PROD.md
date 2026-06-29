# Migration production

Ce fichier liste les actions manuelles à effectuer sur le serveur de production
lors du déploiement de chaque branche ou version.

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

### 2. Activation de `mod_rewrite` Apache (si pas déjà actif)

Les endpoints API utilisent des URLs propres (`/api/members/42`) via `.htaccess`.
Vérifier que `mod_rewrite` est activé et que `AllowOverride All` est configuré
pour le répertoire `html/`.

```bash
a2enmod rewrite
systemctl reload apache2
```

Vérifier dans la config Apache (ou vhost) :

```apache
<Directory "/var/www/html">
    AllowOverride All
</Directory>
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
