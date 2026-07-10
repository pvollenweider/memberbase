# Guide administrateur — MemberBase

Ce guide s'adresse à l'administrateur système qui gère le serveur, le déploiement Docker et les comptes utilisateurs de MemberBase (version 4.0.0). Il couvre l'installation, la configuration, la sécurité, l'API et la maintenance.

---

## Table des matières

1. [Prérequis](#1-prérequis)
2. [Installation](#2-installation)
3. [Configuration](#3-configuration)
4. [Déploiement Apache (production)](#4-déploiement-apache-production)
5. [Docker](#5-docker)
6. [Gestion des comptes utilisateurs](#6-gestion-des-comptes-utilisateurs)
7. [Sécurité](#7-sécurité)
8. [API REST](#8-api-rest)
9. [Sauvegarde](#9-sauvegarde)
10. [Intégrité des données](#10-intégrité-des-données)
11. [CI/CD](#11-cicd)
12. [Mise à jour du schéma](#12-mise-à-jour-du-schéma)
13. [Logs d'audit](#13-logs-daudit)
14. [Emails et communications](#14-emails-et-communications)

---

## 1. Prérequis

### Logiciels obligatoires

| Composant | Version minimale | Notes |
|-----------|-----------------|-------|
| PHP | 8.1 (8.2 recommandé) | Extensions `pdo_mysql` et `mbstring` requises |
| MariaDB | 10.5 | MySQL 8+ aussi compatible |
| Apache | 2.4 | Module `mod_rewrite` activé |
| pdftk-java | toute version stable | Génération des attestations PDF AcroForm |

### Installation des dépendances sur Debian/Ubuntu

```bash
# PHP et extensions
apt install php8.2 php8.2-mysql php8.2-mbstring

# pdftk-java (génération PDF)
apt install pdftk-java

# Activer mod_rewrite
a2enmod rewrite
systemctl reload apache2
```

### Vérification rapide

```bash
php -r "echo PHP_VERSION, ' pdo_mysql=', extension_loaded('pdo_mysql') ? 'OK' : 'MANQUANT', ' mbstring=', extension_loaded('mbstring') ? 'OK' : 'MANQUANT', PHP_EOL;"
pdftk --version
```

---

## 2. Installation

### 2.1 Via le wizard web (recommandé pour la production)

Le wizard `install.php` guide l'installation en 5 étapes. Il est la seule méthode garantissant un schéma cohérent et un premier compte admin valide.

**Préparation :**

```bash
# Cloner le dépôt
git clone <url-depot> /var/www/votre-domaine

# Pointer Apache sur html/ (voir section 4)
# S'assurer que conf/ est accessible en écriture par www-data
mkdir -p /var/www/votre-domaine/conf
chown www-data:www-data /var/www/votre-domaine/conf
chmod 750 /var/www/votre-domaine/conf
```

Puis naviguer sur `https://votre-domaine/install.php`.

**Étape 1 — Prérequis serveur**

L'installeur vérifie automatiquement :
- PHP >= 8.1
- Extension PDO MySQL
- Extension mbstring
- Répertoire `conf/` accessible en écriture

Tous les indicateurs doivent être verts avant de continuer.

**Étape 2 — Connexion à la base de données**

Saisir les paramètres de connexion. L'installeur teste la connexion et, si elle réussit, écrit `conf/db.php`. Les champs sont préremplis depuis les variables d'environnement si elles sont définies.

| Champ | Valeur exemple | Notes |
|-------|---------------|-------|
| Hôte | `localhost` | `mariadb` sous Docker |
| Port | `3306` | |
| Nom de la base | `members` | Base à créer au préalable |
| Utilisateur | `members` | |
| Mot de passe | `***` | |

**Étape 3 — Initialisation du schéma**

Crée les tables suivantes (toutes avec `CREATE TABLE IF NOT EXISTS` — idempotent) :

`contact` `segment` `contact_properties` `contact_segment` `metagroup` `compta` `compta_type` `maxval` `app_settings` `app_users` `audit_log` `email_templates` `email_log` `api_rate_limit` `schema_migrations`

Un clic suffit. Les tables existantes ne sont pas modifiées.

**Étape 4 — Configuration de l'organisation**

Saisir les informations de l'association (nom, adresse, NPA, ville, pays). Ces données apparaissent dans le titre de l'application et sur les attestations de dons PDF.

Le wizard crée automatiquement :
- Deux groupes membres : `{Préfixe} {année-1}` et `{Préfixe} {année}` (ex. `Membre 2024` et `Membre 2025`)
- Une catégorie `Membres` regroupant ces deux groupes
- Quatre types de compta de base si la table est vide :

| Type | Couleur | Flags |
|------|---------|-------|
| Cotisation | `bg-light` | `is_cotisation=1`, exclu des dons |
| Don | `bg-info-subtle` | |
| Evénementiel | `bg-primary-subtle` | exclu des dons |
| Institutionnel | `bg-warning-subtle` | `is_institutional=1` |

**Étape 5 — Compte administrateur**

Crée le premier compte `admin`. Règles de validation :
- Identifiant : 2 à 50 caractères, uniquement lettres/chiffres/`.`/`-`/`_`
- Mot de passe : 8 caractères minimum
- Le hash est généré en bcrypt via `PASSWORD_DEFAULT`
- Le flag `force_password_change` est mis à `0` pour ce compte initial

**Après installation :**

La page `install.php` est automatiquement bloquée dès qu'un compte admin actif existe en base (redirection vers `index.php`). Elle reste accessible sur les étapes 4 et 5 si la config DB existe mais sans admin. Supprimer ou protéger le fichier n'est pas obligatoire, mais reste une bonne pratique.

### 2.2 Via Docker (développement)

```bash
git clone <url-depot> memberbase
cd memberbase

# Rendre conf/ accessible en écriture par le process PHP dans le conteneur
chmod 777 conf/

# Démarrer la stack
docker compose up -d

# Ouvrir le wizard
open http://localhost:8080/install.php
# Utiliser "mariadb" comme hôte DB (pas "localhost")
```

Voir la section [5 — Docker](#5-docker) pour le détail de la stack.

### 2.3 Mise à jour d'une instance existante

```bash
git pull
systemctl reload apache2   # si des fichiers PHP ont changé
```

La plupart des mises à jour mineures ne demandent aucune migration. Certaines versions ajoutent toutefois une colonne : **consulter systématiquement [`MIGRATION_PROD.md`](../MIGRATION_PROD.md)**. Par exemple, la **v3.5.4** requiert l'ajout de la colonne `email_alt` :

```sql
ALTER TABLE users ADD COLUMN email_alt VARCHAR(255) NOT NULL DEFAULT '' AFTER email;
```

Voir aussi la section [12 — Mise à jour du schéma](#12-mise-à-jour-du-schéma) pour les cas exceptionnels.

---

## 3. Configuration

### 3.1 conf/db.php

Fichier écrit par le wizard à l'étape 2. Situé **hors du webroot** (`conf/` est au niveau de la racine du dépôt, pas dans `html/`). Ce fichier est gitignored.

Structure générée :

```php
<?php
// Generated by installer — 2025-01-15 14:32:00
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'members');
define('DB_USER', 'members');
define('DB_PASS', 'secret');
```

Ne pas modifier ce fichier manuellement sauf nécessité — utiliser plutôt les variables d'environnement en environnement Docker.

### 3.2 Variables d'environnement (Docker / 12-factor)

Si `conf/db.php` est absent, `html/includes/lib/bootstrap.php` utilise les variables d'environnement en fallback :

| Variable | Valeur par défaut | Description |
|----------|-------------------|-------------|
| `DB_HOST` | `localhost` | Hôte MariaDB |
| `DB_NAME` | `members` | Nom de la base |
| `DB_USER` | `members` | Utilisateur DB |
| `DB_PASS` | `members` | Mot de passe DB |

> **Note sur le port** : la connexion runtime (`bootstrap.php`) ne lit **pas** `DB_PORT` (ni la variable d'environnement, ni la constante définie dans `conf/db.php`). Elle se connecte toujours sur le port MySQL par défaut (3306). `DB_PORT` n'est utilisé que par le wizard `install.php` (test de connexion et pré-remplissage du champ). Prévoyez donc MariaDB sur 3306 pour l'exploitation.

### 3.3 Paramètres applicatifs (table app_settings)

Stockés en base, modifiables dans Réglages → Général. Clés principales :

| Clé | Description |
|-----|-------------|
| `org_name` | Nom de l'association (titre et attestations) |
| `org_address` | Adresse (attestations) |
| `org_npa` | Code postal (attestations) |
| `org_city` | Ville (attestations) |
| `org_country` | Pays (attestations) |
| `default_team` | ID du groupe affiché par défaut dans la liste membres |
| `membre_team` | ID du groupe de référence pour les filtres cotisation |
| `archive_id` | ID du groupe archives (exclu des vues par défaut) |
| `membre_team_prefix` | Préfixe des groupes membres annuels (ex. `Membre`) |
| `org_ide` | Numéro IDE suisse (CHE-XXX.XXX.XXX) — figure sur les attestations de dons. Bouton **Vérifier via Zefix** dans l'UI : interroge le registre du commerce suisse (`zefix.ch`, flux `search.json` → `firm/{ehraid}.json`) pour préremplir nom/adresse/but statutaire à partir du numéro IDE |
| `org_purpose` | But statutaire (extrait des statuts) — préremplissable via Zefix |
| `org_tax_status` | Statut d'exonération fiscale — saisie manuelle uniquement (aucun registre fédéral consulté automatiquement) |
| `org_iban` | IBAN de l'association — requis pour générer le bulletin de versement QR joint aux rappels de cotisation (voir [§14.4](#144-rappels-de-cotisation)) |
| `org_coti_amount_desc` | Description libre du montant de cotisation, affichée dans l'email de rappel et sur le bulletin QR (champ « Montant ») — repli sur une valeur par défaut si vide |
| `smtp_*` | Configuration SMTP — voir [§14.1](#141-configuration-smtp) |

Modification directe en SQL si nécessaire :

```sql
UPDATE app_settings SET value = 'Nouvelle valeur' WHERE `key` = 'org_name';
```

---

## 4. Déploiement Apache (production)

### Configuration VirtualHost

Le `DocumentRoot` doit pointer sur `html/`, pas sur la racine du dépôt. Le répertoire `conf/` doit rester hors du webroot.

```apache
<VirtualHost *:443>
    ServerName membres.votre-domaine.ch
    DocumentRoot /var/www/membres.votre-domaine.ch/html

    <Directory /var/www/membres.votre-domaine.ch/html>
        AllowOverride All
        Require all granted
    </Directory>

    # HTTPS — certificat Let's Encrypt ou autre
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/membres.votre-domaine.ch/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/membres.votre-domaine.ch/privkey.pem

    ErrorLog  /var/log/apache2/membres.votre-domaine.ch-error.log
    CustomLog /var/log/apache2/membres.votre-domaine.ch-access.log combined
</VirtualHost>
```

### Permissions

```bash
WEBROOT=/var/www/membres.votre-domaine.ch

# conf/ en écriture pour www-data (l'installeur doit pouvoir écrire conf/db.php)
chown www-data:www-data $WEBROOT/conf
chmod 750 $WEBROOT/conf

# html/ en lecture seule pour www-data (sauf assets si upload prévu)
chown -R www-data:www-data $WEBROOT/html
find $WEBROOT/html -type d -exec chmod 755 {} \;
find $WEBROOT/html -type f -exec chmod 644 {} \;
```

### Routes API (mod_rewrite)

Les endpoints REST utilisent des URLs propres (`/api/contacts/42`). `mod_rewrite` doit être actif (`a2enmod rewrite`) **et** les règles `RewriteRule` déclarées **dans le vhost**, pas via `.htaccess` (l'`AllowOverride` du dossier `api/` bloque les directives Rewrite en `.htaccess`). Le bloc `<Directory .../html/api>` complet avec toutes les `RewriteRule` figure dans [`MIGRATION_PROD.md`](../MIGRATION_PROD.md#configuration-apache--routes-api). Vérification :

```bash
# URL propre → doit retourner 401 (auth requise), pas 404
curl -s -o /dev/null -w "%{http_code}" https://membres.votre-domaine.ch/api/contacts/1
```

### pdftk

La génération d'attestations PDF appelle `pdftk` via `exec()`. Le binaire doit être accessible dans le PATH du process Apache :

```bash
which pdftk          # doit retourner un chemin
apt install pdftk-java
```

### HTTPS

Le cookie de session est émis avec `Secure=true` uniquement si la requête arrive sur HTTPS. En HTTP, la session ne fonctionne pas. HTTPS est donc obligatoire en production.

---

## 5. Docker

### 5.1 Stack de développement (docker-compose.yml)

```yaml
services:
  php:
    build: .                           # Dockerfile à la racine
    ports:
      - "8080:80"                      # App sur http://localhost:8080
    volumes:
      - ./html:/var/www/html           # Bind mount — modifications en direct
      - ./conf:/var/www/conf           # Conf DB partagée avec l'hôte
      - ./logs:/var/www/logs
    environment:
      DB_HOST: mariadb                 # Nom du service Docker, pas "localhost"
      DB_NAME: members
      DB_USER: members
      DB_PASS: members
    depends_on:
      mariadb:
        condition: service_healthy     # Attend que MariaDB réponde avant de démarrer

  mariadb:
    image: mariadb:11
    environment:
      MARIADB_ROOT_PASSWORD: root
      MARIADB_DATABASE: members
      MARIADB_USER: members
      MARIADB_PASSWORD: members
    volumes:
      - mariadb_data:/var/lib/mysql    # Volume nommé — données persistées
      - ./docker/initdb:/docker-entrypoint-initdb.d  # Scripts SQL d'init optionnels
    healthcheck:
      test: ["CMD", "mariadb-admin", "ping", "-h", "localhost", "-umembers", "-pmembers"]
      interval: 5s
      timeout: 5s
      retries: 10

  adminer:
    image: adminer
    ports:
      - "8082:8080"                    # Interface web DB sur http://localhost:8082

volumes:
  mariadb_data:
```

Points importants :
- Le bind mount `./html:/var/www/html` permet d'éditer les fichiers PHP sans reconstruire l'image.
- `conf/` est également monté — `conf/db.php` créé par le wizard est visible sur l'hôte.
- L'hôte MariaDB à saisir dans le wizard est `mariadb` (nom du service), pas `localhost`.

### 5.2 Stack de test (docker-compose.test.yml)

Override minimal utilisé par la CI :

```yaml
services:
  php:
    environment:
      DB_NAME: members_test            # Base séparée, n'écrase pas "members"
```

Le fichier `docker-compose.test.yml` ne contient qu'une seule surcharge : `DB_NAME: members_test` sur le service `php`. Il ne redéfinit ni les ports, ni le service MariaDB. Utilisé avec :

```bash
docker compose -f docker-compose.yml -f docker-compose.test.yml up -d --build
```

Note : aucun des deux fichiers compose n'expose le port MariaDB sur l'hôte (pas de mapping `3306`/`3307`). La base n'est accessible que depuis le réseau Docker interne, ou via Adminer sur `http://localhost:8082`. La base de test `members_test` cohabite avec `members` dans la même instance MariaDB, sans conflit.

### 5.3 Dockerfile

Image basée sur `php:8.2-apache`. Contenu réel du Dockerfile :

- Extensions PHP installées : `pdo`, `pdo_mysql` (via `docker-php-ext-install`).
- Paquet système `pdftk-java` installé (génération des attestations PDF).
- Modules Apache activés : `rewrite`, `headers`, `expires`, `deflate`, `brotli` (`a2enmod`).
- Vhost personnalisé copié depuis `docker/apache.conf` vers `000-default.conf`.
- Réglage PHP : `error_reporting = E_ALL & ~E_NOTICE` (fichier `conf.d/casa.ini`).
- Répertoires `/var/www/logs` et `/var/www/conf` créés et attribués à `www-data`.

### 5.4 Commandes courantes

```bash
# Démarrer la stack de dev
docker compose up -d

# Voir les logs PHP/Apache
docker compose logs -f php

# Accéder au shell PHP
docker compose exec php bash

# Redémarrer après modification du Dockerfile
docker compose up -d --build php

# Arrêter et supprimer les conteneurs (données MariaDB conservées dans le volume)
docker compose down

# Supprimer aussi les volumes (reset complet)
docker compose down -v
```

---

## 6. Gestion des comptes utilisateurs

### 6.1 Rôles et permissions

MemberBase définit quatre rôles, définis dans `html/includes/lib/auth.php` :

| Rôle | Lecture | Écriture | Suppression | Gestion des comptes |
|------|:-------:|:--------:|:-----------:|:-------------------:|
| `readonly` | Oui | Non | Non | Non |
| `user` | Oui | Oui | Non | Non |
| `manager` | Oui | Oui | Oui | Non |
| `admin` | Oui | Oui | Oui | Oui |

Fonctions PHP correspondantes (dans `auth.php`) : `isLoggedIn()`, `canRead()` (tous rôles), `canWrite()` (`user`/`manager`/`admin`), `isManager()` (`manager`/`admin`), `isAdmin()` (`admin` seul).

Tout utilisateur connecté peut changer son propre mot de passe (`?view=changePassword`). Seul un `admin` peut réinitialiser le mot de passe d'un autre compte ou le supprimer.

Sur cette même page, chaque utilisateur choisit aussi la **langue de l'interface** (français,
anglais, allemand, espagnol) — stockée dans `app_users.locale` (colonne ajoutée par la
migration `0003_app_users_locale`, défaut `fr`). Le choix est individuel : il n'y a pas de
réglage global de langue pour l'application.

### 6.2 Accès à l'interface de gestion

**Réglages → Comptes utilisateurs** (ou `?view=settings&section=app_users`)

L'onglet est invisible pour les rôles non-admin.

### 6.3 Créer un compte

1. Cliquer **Nouvel utilisateur**
2. Remplir :
   - **Identifiant** : 2 à 100 caractères, lettres/chiffres/`.`/`-`/`_`
   - **Nom affiché** : optionnel, repris de l'identifiant si vide
   - **Email** : optionnel
   - **Rôle** : `readonly` / `user` / `manager` / `admin`
   - **Mot de passe temporaire** : saisir ou générer aléatoirement via le bouton dé. Laisser vide pour utiliser `changeme` par défaut
3. Cliquer **Créer**

Le compte est créé avec `force_password_change=1`. L'utilisateur sera bloqué sur la page de changement de mot de passe à sa première connexion.

**Option alternative — lien d'invitation :** après création, l'interface peut générer un token valable 7 jours. Copier le lien affiché et l'envoyer à l'utilisateur. Il définira son propre mot de passe sans que vous n'ayez à communiquer un mot de passe temporaire.

### 6.4 Modifier un compte

Cliquer l'icône crayon sur la ligne du compte concerné. Champs modifiables : nom affiché, email, rôle, statut actif/inactif.

Un compte `is_active=0` ne peut plus se connecter mais n'est pas supprimé.

### 6.5 Réinitialiser un mot de passe

1. Cliquer l'icône clé sur la ligne du compte
2. Confirmer dans la boîte de dialogue
3. Un mot de passe temporaire aléatoire est affiché une seule fois — le noter et le transmettre à l'utilisateur
4. `force_password_change` est remis à `1` : l'utilisateur sera forcé de changer ce mot de passe à la connexion suivante

Le mot de passe temporaire transite via session flash (`$_SESSION['reset_pw_flash']`), jamais en paramètre URL. Il n'apparaît pas dans les logs Apache.

### 6.6 Supprimer un compte

Cliquer l'icône corbeille → confirmer. Action irréversible. Un admin ne peut pas supprimer son propre compte (le bouton n'est pas affiché sur la ligne `vous`).

### 6.7 Compte admin initial

Créé via `install.php` étape 5. C'est le seul compte pour lequel `force_password_change=0` est défini à la création. Pour tous les comptes créés ensuite via l'interface, ce flag est `1`.

---

## 7. Sécurité

### 7.1 Authentification

- Mots de passe hashés en **bcrypt** (`password_hash($password, PASSWORD_DEFAULT)` / `password_verify()`)
- Après login réussi, `session_regenerate_id(true)` est appelé pour prévenir la fixation de session
- Cookie de session : `httponly=true`, `samesite=Lax`, `secure=true` (HTTPS uniquement)
- Durée de session : `lifetime=0` (session navigateur — expire à la fermeture de l'onglet)

### 7.2 Fail2Ban

Jail configurée pour bannir les IPs après 5 tentatives de login échouées en 5 minutes (ban 24 heures).

Un login échoué retourne HTTP 200 (le formulaire est ré-affiché). Un login réussi retourne HTTP 302. Fail2Ban distingue les deux via ce code de retour.

**Filtre** — créer `/etc/fail2ban/filter.d/memberbase-login.conf` :

```ini
[Definition]
failregex = ^<HOST> .* "POST /login\.php HTTP/1\.[01]" 200
ignoreregex =
```

**Jail** — ajouter dans `/etc/fail2ban/jail.local` :

```ini
[memberbase-login]
enabled  = true
port     = http,https
filter   = memberbase-login
logpath  = /var/log/apache2/membres.votre-domaine.ch-access.log
maxretry = 5
findtime = 300
bantime  = 86400
```

Adapter `logpath` au nom réel du fichier de log Apache.

**Commandes de gestion :**

```bash
# Recharger la configuration après modification
systemctl reload fail2ban

# Vérifier l'état de la jail
fail2ban-client status memberbase-login

# Débannir une IP manuellement
fail2ban-client set memberbase-login unbanip <IP>

# Whitelister des IPs (jamais bannies) — dans jail.local section [DEFAULT]
# ignoreip = 127.0.0.1/8 ::1 192.168.1.0/24

# Vérifier la whitelist
fail2ban-client -d | grep ignoreip
```

### 7.3 Protection des répertoires sensibles

Les répertoires `html/includes/` et `html/api/` contiennent des `.htaccess` qui bloquent l'accès direct. Les fichiers PHP de ces dossiers ne doivent être inclus que par `index.php` (qui définit la constante `APP_ENTRY`). Tout accès direct déclenche `die('Direct access not permitted.')`.

### 7.4 Audit log

Voir la section [13 — Logs d'audit](#13-logs-daudit).

### 7.5 Procédure en cas de compromission de compte

1. Se connecter avec un autre compte admin
2. Réglages → Comptes → réinitialiser le mot de passe du compte compromis
3. Si le compte admin unique est compromis, réinitialiser le hash directement en base :

```sql
-- Générer un nouveau hash bcrypt en PHP :
-- php -r "echo password_hash('NouveauMotDePasse!', PASSWORD_DEFAULT);"
UPDATE app_users
SET password_hash = '$2y$10$...hash...', force_password_change = 1
WHERE username = 'admin';
```

---

## 8. API REST

### 8.1 Activation

L'API est toujours disponible. Aucun flag d'activation n'existe. Elle requiert une **authentification de session** active — il n'y a pas d'authentification par token ou clé API. L'utilisateur doit d'abord se connecter via `POST /login.php`, puis inclure le cookie de session dans les requêtes API.

Le bootstrap de l'API se trouve dans `html/api/_bootstrap.php` : il vérifie `isLoggedIn()` et émet les headers `Content-Type: application/json` et CORS appropriés.

### 8.2 Authentification par session pour les appels curl

```bash
# 1. Se connecter et récupérer le cookie de session
curl -c cookies.txt -b cookies.txt \
  -d "username=admin&password=VotreMotDePasse" \
  https://membres.votre-domaine.ch/login.php

# 2. Utiliser le cookie pour les appels API suivants
curl -b cookies.txt https://membres.votre-domaine.ch/api/contacts
```

### 8.3 Endpoints disponibles

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/contacts` | Liste des membres |
| `GET` | `/api/contacts/{id}` | Fiche membre complète |
| `PATCH` | `/api/contacts/{id}` | Modification partielle (génère un audit log) |
| `GET` | `/api/contacts/{id}/groups` | Segments du membre |
| `GET` | `/api/segments` | Liste des segments avec comptage membres |
| `GET` | `/api/segments/{id}` | Segment avec ses membres |
| `GET` | `/api/compta` | Entrées comptables |
| `GET` | `/api/compta-types` | Types de compta configurés |
| `GET` | `/api/suivi` | Notes de suivi |

### 8.4 Paramètres de filtrage

**`GET /api/contacts`**

| Paramètre | Type | Description |
|-----------|------|-------------|
| `group` | int | Filtrer par ID de groupe |
| `metagroup` | int | Filtrer par ID de métagroupe |
| `active` | bool | `1` = membres actifs uniquement |
| `search` | string | Recherche textuelle (nom, prénom, email) |
| `limit` | int | Nombre de résultats (pagination) |
| `offset` | int | Décalage (pagination) |

**`GET /api/compta`**

| Paramètre | Type | Description |
|-----------|------|-------------|
| `member` | int | Filtrer par ID membre |
| `type` | int | Filtrer par ID type compta |
| `year` | int | Filtrer par année |

**`GET /api/suivi`**

| Paramètre | Type | Description |
|-----------|------|-------------|
| `member` | int | Filtrer par ID membre |
| `year` | int | Filtrer par année |

### 8.5 Exemples curl

```bash
BASE=https://membres.votre-domaine.ch
COOKIES=cookies.txt

# Liste des membres du segment 3, actifs
curl -b $COOKIES "$BASE/api/contacts?group=3&active=1"

# Fiche complète du membre 42
curl -b $COOKIES "$BASE/api/contacts/42"

# Modifier le prénom du membre 42
curl -b $COOKIES -X PATCH \
  -H "Content-Type: application/json" \
  -d '{"firstname": "Jean-Pierre"}' \
  "$BASE/api/contacts/42"

# Entrées compta du membre 42 pour 2024
curl -b $COOKIES "$BASE/api/compta?member=42&year=2024"

# Liste des segments
curl -b $COOKIES "$BASE/api/segments"
```

### 8.6 Format des réponses

Toutes les réponses sont en JSON UTF-8. Les erreurs retournent un objet `{"error": "message descriptif"}` avec le code HTTP approprié :

| Code | Situation |
|------|-----------|
| 200 | Succès |
| 400 | Paramètre invalide ou corps JSON malformé |
| 401 | Non authentifié |
| 403 | Authentifié mais rôle insuffisant |
| 404 | Ressource introuvable |
| 405 | Méthode HTTP non supportée |

---

## 9. Sauvegarde

### Ce qu'il faut sauvegarder

| Élément | Emplacement | Priorité |
|---------|-------------|----------|
| Base de données | MariaDB | Critique |
| Config DB | `conf/db.php` | Haute |
| Template PDF | `html/assets/attestation.pdf` | Haute |
| Assets personnalisés | `html/assets/` | Selon usage |

Il n'y a pas de fichiers uploadés par les utilisateurs à gérer : MemberBase ne stocke pas de pièces jointes.

### Sauvegarde de la base de données

```bash
# Dump complet avec compression
mysqldump -h localhost -u members -p members \
  | gzip > /var/backups/memberbase/db_$(date +%Y%m%d_%H%M%S).sql.gz

# Planifier via cron (tous les jours à 3h)
# 0 3 * * * mysqldump -h localhost -u members -pmotdepasse members | gzip > /var/backups/memberbase/db_$(date +\%Y\%m\%d).sql.gz
```

### Restauration

```bash
gunzip < backup_20250115.sql.gz | mysql -u members -p members
```

### Sauvegarde de la configuration

```bash
cp /var/www/membres.votre-domaine.ch/conf/db.php /var/backups/memberbase/
```

---

## 10. Intégrité des données

### Accès

**Réglages → Intégrité** (onglet dans la barre latérale des réglages)

Accessible uniquement aux comptes `admin` et `manager`.

### Ce que l'outil vérifie

L'outil effectue cinq contrôles en lecture seule sur la base de données :

| Contrôle | Sévérité | Description |
|----------|----------|-------------|
| Membres avec même nom | Danger | Deux membres actifs avec le même prénom ET nom (insensible à la casse) |
| Membres avec même email | Danger | Deux membres actifs avec la même adresse email |
| Groupes masqués dans une catégorie | Avertissement | Un groupe `hidden=1` est encore assigné à une catégorie |
| Groupes masqués dans un métagroupe | Avertissement | Un groupe `hidden=1` est encore référencé dans un métagroupe de filtrage |
| Groupes masqués avec des membres | Avertissement | Un groupe `hidden=1` a encore des membres actifs assignés |

### Actions disponibles

- **Doublons de nom/email** : liens directs vers les fiches membres concernées. Pour deux doublons, un bouton **Fusionner** apparaît (vue `mergeUsers`).
- **Groupes masqués** : lien **Éditer** vers la page de configuration du métagroupe ou de la catégorie pour retirer l'assignation.

### Quand l'utiliser

- Après une importation de données en masse
- En cas de signalement d'incohérences par les utilisateurs
- Avant une exportation ou un bilan annuel
- Périodiquement, comme contrôle de routine (recommandé : mensuel)

---

## 11. CI/CD

### Pipeline GitHub Actions (`.github/workflows/e2e.yml`)

Le workflow (nom : « E2E Tests ») se déclenche sur chaque push vers `main` et sur chaque pull request.

**Étapes du pipeline :**

1. **Checkout** du code

2. **Démarrage de la stack Docker de test**
   ```bash
   docker compose -f docker-compose.yml -f docker-compose.test.yml up -d --build
   ```
   Utilise la base `members_test` (override via `docker-compose.test.yml`).

3. **Attente de disponibilité** : polling de `http://localhost:8080/login.php` toutes les 3 secondes, jusqu'à 30 tentatives (90 secondes maximum).

4. **Installation Node.js 24** (avec cache npm) et dépendances (`npm ci`).

5. **Installation Playwright** (navigateur Chromium uniquement + dépendances système).

6. **Correction des permissions `conf/`** : Docker crée ce répertoire avec `www-data` comme propriétaire ; le runner GitHub Actions (non-root) doit pouvoir y écrire pour que l'installeur fonctionne.

7. **Reset de la base de test** :
   ```bash
   bash tests/fixtures/reset-db.sh
   ```
   Ce script recharge le schéma et les fixtures dans `members_test`.

8. **Exécution des tests Playwright** sans retry (`--retries=0` pour obtenir des échecs francs).

9. **Artefact en cas d'échec** : le rapport Playwright (`playwright-report/`) est conservé 7 jours dans GitHub Actions pour investigation.

### Reproduire localement

```bash
# Démarrer la stack de test
docker compose -f docker-compose.yml -f docker-compose.test.yml up -d --build

# Attendre que l'app réponde
curl -sf http://localhost:8080/login.php

# Reset DB
bash tests/fixtures/reset-db.sh

# Lancer les tests
npx playwright test

# Voir le rapport HTML
npx playwright show-report
```

---

## 12. Mise à jour du schéma

### Principe général

Chaque changement de schéma est un fichier `html/migrations/NNNN_description.sql`
(sous `html/` pour être déployé avec l'application, protégé de l'accès HTTP par
`html/migrations/.htaccess`). Les migrations appliquées sont suivies dans la table
`schema_migrations` (nom + horodatage + checksum SHA-256).

Un `git pull` seul ne suffit plus pour les mises à jour qui ajoutent une
migration — il faut l'appliquer explicitement (deux méthodes ci-dessous).
Les instructions `CREATE TABLE` d'`install.php` restent idempotentes
(`CREATE TABLE IF NOT EXISTS`) et embarquent en plus le schéma courant complet :
une **installation fraîche** n'exécute jamais les migrations une par une, elle
crée directement le schéma à jour puis marque toutes les migrations comme
appliquées (« baseline »).

### Méthode 1 — CLI (`tools/migrate.php`)

```bash
# Voir l'état : appliquées / en attente / dérive
php html/tools/migrate.php --status

# Appliquer les migrations en attente
php html/tools/migrate.php
# (ou : make migrate)

# Marquer tout comme appliqué sans exécuter — réservé au fresh install,
# jamais sur une prod existante (géré automatiquement par install.php)
php html/tools/migrate.php --baseline
```

`--status` sort en code 2 et affiche `[!] DÉRIVE` si le contenu d'une migration
déjà appliquée a été modifié après coup (comparaison de checksum) — **ne jamais
éditer une migration déjà appliquée**, en créer une nouvelle à la place.

### Méthode 2 — Interface web (sans accès SSH)

**Réglages → Santé** (admin) permet, sans ligne de commande :
- **Exporter la base** (dump SQL pur, sans dépendre de `mysqldump` sur le serveur)
- **Appliquer les migrations en attente** (case « j'ai fait une sauvegarde »
  obligatoire avant de pouvoir valider)

Cette page affiche aussi le nombre de migrations en attente et signale toute
dérive de checksum détectée.

### Avant toute migration en production

1. `php html/tools/migrate.php --status` (ou Réglages → Santé) pour voir ce qui va s'appliquer
2. Dump de la base (`mysqldump`, ou export intégré via Réglages → Santé)
3. Tester sur un environnement de staging si la migration est structurante
4. Appliquer en production

⚠️ Le DDL MySQL est auto-committé (pas de rollback transactionnel) — la
sauvegarde de l'étape 2 est la seule protection en cas de problème.

`MIGRATION_PROD.md` à la racine du dépôt documente l'historique des migrations
manuelles d'avant ce système (versions ≤ 3.7.x) ; il n'est plus alimenté.

### Vérifier l'état du schéma

```sql
-- Lister les tables existantes
SHOW TABLES;

-- Vérifier la structure d'une table
DESCRIBE app_users;

-- État détaillé des migrations
SELECT * FROM schema_migrations ORDER BY id;
```

---

## 13. Logs d'audit

### Table audit_log

Structure :

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | int AUTO_INCREMENT | Identifiant de l'entrée |
| `created_at` | datetime | Horodatage (timezone Europe/Zurich) |
| `app_user_id` | int | ID du compte applicatif auteur |
| `username` | varchar(100) | Nom d'utilisateur au moment de l'action |
| `action` | varchar(100) | Code de l'action (ex. `PATCH /api/contacts/{id}`) |
| `detail` | text | Détail JSON (diff avant/après pour les PATCH) |
| `subject_user_id` | int unsigned | ID du membre concerné |

### Ce qui est tracé

La fonction `auditLog()` dans `bootstrap.php` est appelée par les endpoints API qui modifient des données :

- **`PATCH /api/contacts/{id}`** : enregistre un diff des champs modifiés (valeur avant / valeur après) au format JSON dans la colonne `detail`.

Les actions de gestion des comptes (création, suppression, réinitialisation de mot de passe) transitent par les actions POST de l'interface web et ne passent pas par l'API — elles ne génèrent pas d'entrée `audit_log` à ce stade.

### Consulter les logs

**Via l'interface** : Réglages → Audit Log (onglet visible pour les admins).

**Via SQL :**

```sql
-- 50 dernières entrées
SELECT created_at, username, action, detail, subject_user_id
FROM audit_log
ORDER BY created_at DESC
LIMIT 50;

-- Actions d'un utilisateur précis
SELECT * FROM audit_log
WHERE username = 'dupont'
ORDER BY created_at DESC;

-- Modifications d'un membre
SELECT * FROM audit_log
WHERE subject_user_id = 42
ORDER BY created_at DESC;
```

### Purge

La table `audit_log` n'est pas purgée automatiquement. Purge manuelle si nécessaire :

```sql
-- Supprimer les entrées de plus de 2 ans
DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

---

## 14. Emails et communications

Accès : **Réglages → Email** (`?view=settings&tab=email`, admin uniquement). Une seule page avec trois sections empilées : configuration SMTP, journal des envois, templates.

### 14.1 Configuration SMTP

Client SMTP pur PHP (`html/includes/lib/mailer.php`, sans dépendance externe) supportant connexion en clair, STARTTLS, SSL/TLS implicite, AUTH LOGIN/PLAIN ou sans authentification.

| Champ | Description |
|-------|-------------|
| Serveur / Port | Hôte et port SMTP |
| Chiffrement | Aucun / STARTTLS / SSL-TLS |
| Authentification | Case à cocher — si activée, expose utilisateur + mot de passe |
| Utilisateur / Mot de passe | Identifiants SMTP. Le mot de passe est **chiffré au repos** dans `app_settings` avec une clé générée automatiquement par installation (`mbSmtpGetOrCreateEncKey()`) |
| Nom / Email d'expéditeur | En-têtes `From` des emails sortants |
| Répondre à | En-tête `Reply-To` (optionnel). Si renseigné, propose aussi une case **BCC** (copie silencieuse à cette adresse, pas d'en-tête `Bcc:` visible) sur les envois de rappels de cotisation et d'attestations de dons, individuels comme en masse |

Un bouton **Envoi de test** permet de vérifier la configuration sans passer par une action métier (`sendTestEmail`) — affiche le message d'erreur SMTP brut en cas d'échec (utile pour diagnostiquer un souci d'auth ou de certificat SSL).

Si le SMTP n'est pas configuré ou que l'envoi échoue, les fonctionnalités email (rappels, récapitulatifs) échouent silencieusement côté utilisateur final (pas de blocage de l'action déclenchante) mais l'échec est visible dans le journal des emails.

### 14.2 Journal des emails (`email_log`)

Historique paginé de tous les envois (destinataire, sujet, statut envoyé/erreur, date). Actions :
- **Renvoyer** une entrée en erreur individuellement
- **Vider le journal** (purge complète)

Chaque envoi lié à un membre (rappel de cotisation, récapitulatif compta) apparaît aussi dans l'historique de ce membre (onglet Suivi / Historique) via une section dédiée.

### 14.3 Templates d'email

Trois templates éditables (objet + corps texte + corps HTML) stockés dans `email_templates`, avec repli sur un template intégré par défaut si absent :

| Clé | Usage |
|-----|-------|
| `tpl_cotisation_reminder` | Rappel de cotisation impayée |
| `tpl_attestation_don` | Envoi de l'attestation de don. Placeholders spécifiques : `{{formal_greeting}}`/`{{formal_greeting_text}}` (salutation genrée depuis `contact.sexe`), `{{year}}`, `{{cotisation_note}}`/`{{cotisation_note_html}}` (mention affichée seulement si le membre a payé une cotisation cette année-là) |
| `tpl_payment_receipt` | Récapitulatif comptable groupé (compta recap) |

Interpolation par `{{placeholder}}` (pas `%s`/`sprintf`). Une modale « Variables disponibles » liste les placeholders utilisables par template (ex. `{{firstname}}`, `{{entries}}`, `{{total}}`, `{{org_name}}`).

### 14.4 Rappels de cotisation

Depuis la vue **Membres perdus** (`lapsedMembers`), envoi manuel — individuel ou en masse — d'un rappel aux membres ayant cotisé l'année précédente mais pas l'année en cours. Anti-doublon : un membre déjà relancé cette année (`email_log.tpl_key = 'tpl_cotisation_reminder'` + année) n'est pas re-sollicité tant que l'option de forçage n'est pas utilisée (bouton **Renvoyer**). L'envoi individuel (et le renvoi) passe par une modale d'**aperçu** (action `previewCotisationReminder`, sujet + rendu HTML réel du template) avant confirmation — pas de `window.confirm()` natif.

Chaque rappel embarque en pièce jointe un **bulletin de versement QR** suisse (`sprain/swiss-qr-bill`, cf. `html/includes/lib/qr_bill.php`), généré à partir de l'IBAN configuré (`app_settings.org_iban`) et de la description de montant configurable (`app_settings.org_coti_amount_desc`, avec repli sur une valeur par défaut si vide). Nécessite l'extension PHP **GD** côté serveur (cf. section Dépendances du `CLAUDE.md`).

### 14.5 Récapitulatifs comptables (compta recap)

Vue **`comptaRecap`** : envoi groupé d'un email par membre récapitulant ses entrées comptables non encore notifiées (`compta.notified_at IS NULL`), filtrable par année. Fonctionnalités :
- Aperçu avant envoi (modale par membre, rendu HTML réel du template)
- Envoi en masse ou membre par membre
- Mode étendu affichant aussi les membres déjà notifiés (renvoi possible avec forçage)
- Annotation automatique de l'année de cotisation dans l'email quand elle diffère de l'année de paiement (`compta.cotisation_year` — ex. cotisation 2027 payée en décembre 2026), validée côté serveur dans une plage raisonnable (année N-50 à N+1)

Après envoi, les entrées incluses sont marquées `notified_at = NOW()` et ne réapparaissent plus dans le lot suivant.

### 14.6 Envoi des attestations de dons par email

Handler `html/includes/actions/attestation_email.php`, lib `html/includes/lib/attestation.php`. Actions : `previewAttestation`, `sendAttestationOne` (fiche membre / ligne du résumé dons), `previewAttestationsBulkList`, `sendAttestationsBulk` (résumé dons).

- **Tampon/signature** (`html/includes/lib/attestation_stamp.php`) : overlay généré via FPDF et fusionné sur le PDF aplati via `pdftk stamp`. Images non commitées, déposées manuellement par l'admin système dans `conf/attestation_stamp.png` et `conf/attestation_signature.png` (hors `html/`, comme `conf/db.php` — absentes = pas de tampon, aucune erreur). Toujours appliqué sur les PDF envoyés par email ; opt-in (`?stamp=1`) sur le téléchargement direct (`attestation_don.php`/`attestation_bulk.php`).
- **Déjà envoyé cette année** : `mbGetAlreadySentAttestationIds()` matche `email_log.tpl_key='tpl_attestation_don'` sur l'**année dans le sujet** (pas `YEAR(created_at)`, car une attestation peut être envoyée l'année suivant celle qu'elle couvre). L'envoi en masse liste ces personnes séparément (`previewAttestationsBulkList`) ; seules celles explicitement cochées (`force_ids`, liste d'ids séparés par des virgules) sont resendues, les autres comptent dans `already` (distinct de `skipped` = pas d'email / échec pdftk).
- **Avertissement hors-saison** : si le mois courant n'est pas janvier, une case de confirmation est requise côté client avant l'envoi (individuel et en masse) — aucune vérification serveur, purement UX.
- **Régénération depuis le journal** : `attestation_don.php?emailid=N` relit `email_log` (user_id, sujet pour l'année, `created_at` pour la date « Lieu / Date » du PDF), régénère et stampe le PDF avec la date d'envoi d'origine plutôt que la date du jour. Lien affiché dans `email_detail.php` pour toute entrée `tpl_attestation_don`.
- **BCC** : voir §14.1.

## Réglages de l'application

### Général

Accès : **Réglages** → section **Général**

| Réglage | Description |
|---------|-------------|
| Segment par défaut | Segment affiché à l'ouverture de la liste membres |
| Segment membres de référence | Référence pour les filtres cotisation non payée |
| Segment archives | Exclu des vues "tous sauf archives" |
| IBAN / Description du montant | Voir [§3.3](#33-paramètres-applicatifs-table-app_settings) — utilisés pour le bulletin QR des rappels de cotisation |

### Types de compta

Accès : **Réglages** → section **Types de compta**

Les types définissent les catégories d'entrées financières. Flags disponibles :

| Flag | Effet |
|------|-------|
| `is_cotisation` | Pris en compte dans les filtres "cotisation non payée" et l'import de cotisants |
| `is_excluded_from_donation` | Exclu de la vue Contributions et des attestations de dons |
| `is_institutional` | Donateur institutionnel (filtres spécifiques) |
| Archivé | Masqué à la saisie mais visible sur les entrées historiques |

---

## Référence rapide

```bash
# Vérifier l'état de Fail2Ban
fail2ban-client status memberbase-login

# Recharger Apache sans coupure
systemctl reload apache2

# Dump base de données
mysqldump -u members -p members | gzip > backup.sql.gz

# Logs Apache en temps réel
tail -f /var/log/apache2/membres.votre-domaine.ch-access.log

# Stack Docker — état des services
docker compose ps

# Stack Docker — logs en temps réel
docker compose logs -f
```
