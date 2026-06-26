# Procédure de mise à jour — feature/htmx-alpine → production

> À supprimer une fois déployé.

## 1. Migrations SQL (à exécuter en production avant le déploiement)

### Token d'invitation (v2.2.x)
```sql
ALTER TABLE app_users
  ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL,
  ADD COLUMN token_expires_at DATETIME DEFAULT NULL;
```

### Journal d'activité (audit_log)
```sql
CREATE TABLE audit_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  created_at  DATETIME NOT NULL DEFAULT NOW(),
  app_user_id INT DEFAULT NULL,
  username    VARCHAR(100) DEFAULT NULL,
  action      VARCHAR(100) NOT NULL,
  detail      TEXT DEFAULT NULL,
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 2. Fichiers supprimés du repo (nettoyer sur le serveur)

```bash
rm -rf html/log4php/
rm -f  html/config.xml
```

## 3. Déploiement fichiers

Synchroniser `html/` vers le serveur (rsync habituel) :

```bash
rsync -avz --delete html/ user@serveur:/var/www/html/
```

## 4. Vérifications post-déploiement

- [ ] Login fonctionne
- [ ] Pas d'erreurs PHP dans les logs Apache (`tail -f /var/log/apache2/error.log`)
- [ ] `?view=auditLog` accessible en admin — tableau vide au début, c'est normal
- [ ] Création d'un user via invitation → token envoyé → `?view=auditLog` montre `createAppUser`
- [ ] Modification d'un membre → `?view=auditLog` montre `updateUser`
