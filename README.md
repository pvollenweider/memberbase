# MemberBase

[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4)](https://www.php.net)
[![Contributions welcome](https://img.shields.io/badge/contributions-welcome-brightgreen)](CONTRIBUTING.md)

Application web PHP de gestion des membres, groupes, cotisations et dons.

## Documentation

| | |
|---|---|
| [Guide utilisateur](doc/user.md) | Navigation, membres, compta, groupes, exports, rôles |
| [Guide administrateur](doc/admin.md) | Installation, déploiement, comptes, sécurité, sauvegarde |
| [Architecture](doc/architecture.md) | Flux de requête, couches, schéma DB, conventions de code |
| [API REST](doc/api.md) | Endpoints, paramètres, exemples curl, filtres virtuels |
| [Installation](#installation-fresh-install) | Wizard web, Docker, mise à jour |
| [CHANGELOG](CHANGELOG.md) | Historique des versions |
| [CONTRIBUTING](CONTRIBUTING.md) | Comment contribuer |

---

## Histoire

MemberBase est né au début des années 2000 dans le cadre de [Casa Alianza Suisse](https://www.casa-alianza.ch), une association suisse qui soutient des enfants et adolescents vulnérables en Amérique latine — victimes d'exploitation, de violence et d'exclusion sociale — en leur offrant des programmes de réhabilitation, de formation professionnelle et de réinsertion au Guatemala, Honduras et Mexique.

Comme dans beaucoup d'associations de l'époque, la gestion des membres et des donateurs se faisait dans des fichiers Excel. Ça a rapidement atteint ses limites. Lors d'un trajet vers la montagne un week-end, l'un des membres fondateurs a sorti son portable et commencé à coder les bases de ce qui allait devenir MemberBase: un système simple d'appartenance à des groupes et une base de données pour saisir des lignes de comptabilité. Ce noyau n'a jamais vraiment changé — il a juste grandi.

Avec le temps, l'application a été refactorisée pour devenir aussi générique que possible, utilisable par n'importe quelle association quelle que soit sa structure. C'est un projet open source, communautaire, construit pour un besoin réel — et livré tel quel, sans prétention. Il peut être utilisé directement ou adapté librement. Toute contribution est la bienvenue.

---

## Fonctionnalités

### Gestion des membres

- Liste paginée et filtrée des membres avec recherche textuelle
- Ajout, modification et suppression de membres
- Champs: société, civilité, prénom, nom, adresse, NPA/localité, email, **email alternatif** (`email_alt`, adresse historique non utilisée pour les envois), téléphone, portable, fax, web, date de naissance, compétences
- Appartenance à un ou plusieurs segments
- Suivi individuel (notes de contact)
- **Import de contacts CSV / TSV** — assistant en 3 étapes (upload → mapping des colonnes → doublons), réservé aux rôles Manager/Admin. Détection d'encodage et de délimiteur, normalisation de la civilité, détection des doublons (email ou nom+prénom), et ajout optionnel des contacts à un segment (existant, nouveau, ou `Import <date>` par défaut). Voir [doc/user.md](doc/user.md).

### Segments et segments combinés

> Terminologie : depuis la v3.5.4, l'interface parle de **Segment** (anciennement « groupe », entité technique `team`) et de **Segment combiné** (anciennement « métagroupe »).

- Création et gestion de segments (`team`) avec visibilité configurable (actif/masqué)
- Segments combinés (métagroupes): regrouper des segments en catégories pour filtrage
- Filtre de la liste membres par segment ou segment combiné
- Recherche incrémentale dans le dropdown de sélection de segment
- Filtre rapide par statut: tout le monde sauf archives, cotisation non payée, rien ces 10 dernières années, non-instit ayant versé l'année passée
- **Import automatique dans un segment** depuis la page d'édition d'un segment:
  - Importer les membres d'un autre segment (copie ponctuelle)
  - Importer les **donateurs d'une année** (seuil min CHF configurable: 1 / 100 / 200 / 500 / 1000)
  - Importer les **cotisants d'une année** (filtre par types marqués "cotisation")
  - Chaque sélecteur d'année affiche le nombre de nouveaux membres qui seraient ajoutés

### Compta

- Saisie et modification d'entrées comptables par membre (type, date, libellé, somme, quittance)
- Vue historique par membre avec filtre par année
- **Flag "souhaite une attestation de don"** par entrée: checkbox directement dans la liste, visible dans la vue résumé
- Types de compta configurables (UI d'administration): label, couleur Bootstrap, ordre
  - Flag **cotisation** (utilisé par les filtres de cotisation et l'import de membres)
  - Flag **exclu des dons** (exclu des vues résumé et attestations)
  - Archivage d'un type (masqué à la saisie, visible sur les lignes existantes)
- Coloration des lignes par type dans toutes les vues compta
- Génération de quittance de don (Word/MHTML téléchargeable)

### Vues d'activité

| Vue | Description |
|-----|-------------|
| **Compta** (`lastEntryCompta`) | Dernières entrées compta, filtrable par type et année, export DataTables |
| **Suivi** (`lastEntrySuivi`) | Dernières notes de suivi, filtrable par année |
| **Contributions** (`resume`) | Donateurs classés par total annuel, filtre min CHF (1 / 100 / 200 / 500 / 1000), filtre année, mode "toutes entrées", filtre "attestation demandée". KPIs: total CHF, delta même période N-1, progression vs total N-1, donateurs fidèles/nouveaux/perdus cliquables, répartition par type |
| **Donateurs fidèles** (`loyalDonors`) | Donateurs ayant contribué en N et N-1, avec comparaison des deux montants |
| **Nouveaux donateurs** (`newDonors`) | Primo-donateurs de l'année (pas de don en N-1) |
| **Donateurs perdus** (`lapsedDonors`) | Donateurs de N-1 absents en N, avec création de segment de relance |
| **Membres perdus** (`lapsedMembers`) | Membres de l'équipe N-1 non reconduits en N, avec création de segment de relance |

### Attestations de dons (PDF)

- Génération d'une attestation PDF individuelle depuis la vue compta d'un membre (bouton "Attestation" avec dropdown année)
- Génération individuelle depuis la liste gros donateurs (icône PDF par ligne)
- **Génération en masse**: un seul PDF contenant toutes les attestations de l'année affichée (bouton "Toutes les attestations 20XX")
- Template officiel AcroForm (Administration fiscale cantonale de Genève)
- Remplissage via `pdftk` côté serveur, encodage UTF-16 BE pour les caractères accentués
- Les données d'institution (nom, adresse, NPA) sont préconfigurées

### Emails et communications

- **Configuration SMTP** propre à l'installation (Réglages → Email) : client SMTP pur PHP sans dépendance externe, chiffrement (aucun/STARTTLS/SSL-TLS), authentification, envoi de test avec message d'erreur détaillé
- **Templates d'email configurables** (objet + corps texte + HTML, variables `{{placeholder}}`) : rappel de cotisation, récapitulatif comptable, attestation de don
- **Récapitulatifs comptables groupés** (`comptaRecap`) : un email par membre récapitulant ses entrées non notifiées, aperçu avant envoi (rendu HTML réel), envoi individuel ou en masse, filtre par année, mode étendu (renvoi des membres déjà notifiés)
- **Rappels de cotisation impayée** : envoi manuel depuis la vue Membres perdus, individuel ou en masse, anti-doublon par année
- **Journal des emails** (Réglages → Email → Journal) : historique paginé, statut envoyé/erreur, renvoi d'une entrée en erreur, purge
- **Année de cotisation** (`cotisation_year`) sur les entrées compta : distingue l'année de paiement de l'année de cotisation couverte (ex. cotisation N+1 payée en décembre N), reflétée dans les emails de récapitulatif
- **Vérification IDE via Zefix** : préremplissage automatique du nom/adresse/but statutaire de l'organisation depuis le registre du commerce suisse

### Export et impression

- Export DataTables: Copier, Excel, PDF, Imprimer sur toutes les vues tabulaires
- Sélecteur de colonnes (colvis) sur les vues principales
- Export XLS et étiquettes Word depuis la liste membres

### Réglages

Navigation par barre latérale (desktop) / sélecteur (mobile) avec sections :
- **Général** — groupe par défaut, groupe membres de référence, groupe archives
- **Groupes** — liste avec renommage rapide inline et accès aux réglages complets
- **Catégories** — réordonnement par glisser-déposer
- **Filtres** — métagroupes de filtrage, avec undo sur les modifications d'appartenance
- **Types de compta** — UI complète : ajout, édition inline, toggle flags, réordonnement
- **Email** — configuration SMTP, templates, journal des envois (voir [Emails et communications](#emails-et-communications))
- **Comptes utilisateurs** — gestion des comptes app (admin uniquement) : création, modification de rôle, réinitialisation de mot de passe, suppression
- **Intégrité** — détection des groupes masqués avec assignations actives
- **Santé** — export de base de données, application des migrations en attente, détection de dérive de schéma (sans accès SSH)

### Interface multilingue

Chaque utilisateur choisit sa langue d'interface — français (défaut), anglais, allemand
(orthographe suisse) ou espagnol — depuis la page *Mot de passe*. Le choix est enregistré
sur le compte (`app_users.locale`) et s'applique à toutes les sessions suivantes. Architecture
en bundles de ressources PHP (`html/locales/resources_{fr,en,de,es}.php`) avec repli
automatique sur le français pour toute clé non traduite — voir [doc/architecture.md](doc/architecture.md#internationalisation).

### API REST

Endpoints JSON disponibles sous `/api/` (authentification de session requise) :

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/api/members` | Liste des membres (filtrés par groupes, métagroupes, statut, recherche) |
| `POST` | `/api/members` | Créer un membre |
| `GET` | `/api/members/{id}` | Fiche membre complète |
| `PUT` / `PATCH` | `/api/members/{id}` | Modifier un membre (champs individuels, audit log diff) |
| `DELETE` | `/api/members/{id}` | Désactiver (`status=0`) ou supprimer (`?dispose=delete`, admin) |
| `GET` | `/api/members/{id}/groups` | Groupes du membre |
| `GET` | `/api/members/{id}?sub=compta` | Entrées comptables du membre |
| `GET` | `/api/groups` | Liste des groupes avec comptage membres |
| `POST` | `/api/groups` | Créer un groupe (manager) |
| `GET` | `/api/groups/{id}` | Détail d'un groupe |
| `PUT` | `/api/groups/{id}` | Renommer / basculer visibilité (manager) |
| `DELETE` | `/api/groups/{id}` | Supprimer un groupe vide (manager) |
| `GET` | `/api/groups/{id}/members` | Membres d'un groupe |
| `POST` | `/api/groups/{id}/members` | Ajouter un membre à un groupe (manager) |
| `DELETE` | `/api/groups/{id}/members` | Retirer un membre d'un groupe (manager) |
| `GET` | `/api/compta` | Entrées comptables d'un membre (filtres: `memberId`, `year`) |
| `POST` | `/api/compta` | Créer une écriture comptable |
| `GET` | `/api/compta/{id}` | Détail d'une écriture comptable |
| `PUT` | `/api/compta/{id}` | Modifier une écriture comptable |
| `DELETE` | `/api/compta/{id}` | Supprimer une écriture comptable |
| `GET` | `/api/compta-types` | Types de compta configurés |
| `GET` | `/api/suivi` | Notes de suivi d'un membre (filtre: `memberId`) |
| `POST` | `/api/suivi` | Créer une note de suivi |
| `GET` | `/api/suivi/{id}` | Détail d'une note de suivi |
| `PUT` | `/api/suivi/{id}` | Modifier une note de suivi |
| `DELETE` | `/api/suivi/{id}` | Supprimer une note de suivi |

Filtres sur `/api/members` : `search`, `team`, `metagroup`, `page`, `limit`, `types`.

Toutes les réponses sont en JSON UTF-8. Les erreurs retournent `{"error": "message"}` avec le code HTTP approprié.

---

## Stack technique

- **Backend**: PHP 8.2, PDO/MySQL (MariaDB)
- **Frontend**: Bootstrap 5.3.8, htmx 2.0.4, Alpine.js 3, DataTables 1.13, jQuery 3, Font Awesome 6, Chart.js, moment.js 2.30 — tous auto-hébergés (zéro CDN)
- **PDF**: pdftk (fill AcroForm) sur le serveur
- **Génération documents**: MHTML (quittances Word)

## Structure

```
conf/
└── db.php                      # Config DB (gitignorée, écrite par l'installeur)
html/
├── index.php                   # Point d'entrée unique
├── install.php                 # Installeur web (wizard 5 étapes)
├── attestation_don.php         # Génération PDF attestation individuelle
├── attestation_bulk.php        # Génération PDF attestation en masse
├── api/                        # API REST JSON
│   ├── .htaccess               # Rewrite vers index.php (FollowSymLinks)
│   ├── _bootstrap.php          # Auth de session + headers JSON
│   ├── members.php             # CRUD /api/members, GET|PUT|PATCH|DELETE /api/members/{id}
│   ├── groups.php              # CRUD /api/groups, membres /api/groups/{id}/members
│   ├── compta.php              # CRUD /api/compta, GET|PUT|DELETE /api/compta/{id}
│   ├── compta-types.php        # GET /api/compta-types
│   └── suivi.php               # CRUD /api/suivi, GET|PUT|DELETE /api/suivi/{id}
├── assets/
│   └── attestation.pdf         # Template AcroForm officiel
├── includes/
│   ├── lib/
│   │   ├── auth.php            # Session, login, rôles (readonly/user/manager/admin)
│   │   ├── bootstrap.php       # PDO, app settings, helpers
│   │   ├── mailer.php          # Client SMTP pur PHP, templates, journal
│   │   ├── locale.php          # Chargement des bundles de locale (mbLoadLocale)
│   │   └── migrations.php      # Runner de migrations (CLI + admin web)
│   ├── routing/
│   │   ├── views.php           # View router
│   │   └── actions.php         # POST action dispatcher
│   ├── views/                  # Page fragments, prefixed by domain
│   │   ├── users_list.php      # Member list + team filter dropdown
│   │   ├── users_general_data.php # Fiche membre (view/edit Alpine toggle)
│   │   ├── users_edit_form.php # Onglets compta, suivi, historique
│   │   ├── donors_summary.php  # Contributions KPIs + donor list
│   │   ├── settings_general.php# Settings (groupes, catégories, filtres, compta, comptes)
│   │   ├── settings_app_users.php # Gestion des comptes utilisateurs (admin)
│   │   └── ...
│   ├── partials/
│   │   ├── menu.php            # Nav sidebar
│   │   └── donor_table.php     # Shared donor table partial
│   └── actions/                # CRUD handlers (members, groups, compta, compta_recap, cotisation_reminder…)
├── classes/
│   ├── user_class.php          # Classe User (CRUD, cotisation, dons)
│   ├── team_class.php          # Classe Team (groupes)
│   ├── compta_class.php        # Classe Compta (écritures comptables)
│   ├── metagroup_class.php     # Classe Metagroup (catégories de groupes)
│   └── property_class.php      # Classe UserProperty (appartenance, suivi)
├── locales/
│   ├── resources_fr.php        # Libellés français (UTF-8, base complète)
│   └── resources_{en,de,es}.php # Surcharges EN/DE/ES avec repli sur le FR
├── migrations/
│   └── NNNN_description.sql    # Un fichier par changement de schéma, suivi dans schema_migrations
├── css/
│   ├── custom.css              # Styles MemberBase
│   ├── webfonts/               # Font Awesome 6 woff2/ttf
│   └── vendor/                 # Bootstrap, DataTables, Font Awesome CSS
├── js/
│   ├── member-general-form.js  # Alpine component: view/edit toggle fiche membre
│   └── vendor/                 # Bootstrap, DataTables, moment, Chart.js, htmx, Alpine.js
└── fonts/
    └── inter/                  # Inter woff2 (latin + latin-ext)
```

## Installation (fresh install)

### Prérequis

- PHP ≥ 8.1 avec extensions `pdo_mysql` et `mbstring`
- MariaDB / MySQL ≥ 10.5
- Apache avec `mod_rewrite`
- `pdftk-java` pour la génération d'attestations PDF (`apt install pdftk-java`)

### Via l'installeur web (recommandé)

1. Cloner le repo et pointer le `DocumentRoot` Apache sur `html/`
2. S'assurer que le dossier `conf/` (à la racine du repo, hors webroot) est accessible en écriture par le process Apache (`chmod 775 conf/ && chown www-data:www-data conf/`)
3. Naviguer sur `https://votre-domaine/install.php`
4. Suivre le wizard en 5 étapes :
   - **Étape 1** — vérification des prérequis PHP
   - **Étape 2** — connexion à la base de données (écrit `conf/db.php`)
   - **Étape 3** — création du schéma (tables idempotentes)
   - **Étape 4** — paramètres de l'organisation et groupes initiaux (crée automatiquement les groupes "Membre {année-1}" et "Membre {année}" dans une catégorie "Membres")
   - **Étape 5** — création du compte administrateur (bcrypt)
5. Supprimer ou protéger `install.php` après installation (l'accès est bloqué automatiquement si un admin actif existe déjà)

### Via Docker (développement)

```bash
cp docker-compose.yml docker-compose.override.yml  # optionnel
chmod 777 conf/   # le process PHP doit pouvoir écrire conf/db.php
docker compose up -d
```

Puis aller sur `http://localhost:8080/install.php` et utiliser `mariadb` comme host de DB (pas `localhost`).

La stack Docker inclut [Mailpit](https://github.com/axllent/mailpit) pour intercepter les emails en développement : SMTP sur `localhost:1025`, interface web sur `http://localhost:8025`.

### Mise à jour (instance existante)

```bash
git pull
# redémarrer Apache si nécessaire
systemctl reload apache2
```

Aucune migration manuelle requise pour les mises à jour mineures — le schéma utilise `CREATE TABLE IF NOT EXISTS`.

---

## Déploiement

L'application tourne sur Apache + PHP 8 avec MariaDB. `pdftk` doit être installé sur le serveur (`apt install pdftk-java`).

La configuration de la base de données est stockée dans `conf/db.php` (hors webroot, gitignorée). En environnement Docker/12-factor, les variables d'environnement `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` sont utilisées en fallback si `conf/db.php` est absent.

## Accès

`https://votre-domaine/`

## Sécurité

### Authentification et rôles

Gérée par PHP (table `app_users`, bcrypt). Le compte admin initial est créé via l'installeur web (`install.php`). Les comptes suivants sont gérés dans Réglages → Comptes utilisateurs (admin uniquement).

| Rôle | Lecture | Écriture | Suppression | Gestion des comptes |
|------|---------|----------|-------------|---------------------|
| `readonly` | ✓ | — | — | — |
| `user` | ✓ | ✓ | — | — |
| `manager` | ✓ | ✓ | ✓ | — |
| `admin` | ✓ | ✓ | ✓ | ✓ |

Tout utilisateur peut changer son propre mot de passe. L'admin peut réinitialiser les mots de passe des autres comptes.

### Fail2Ban

Jail configurée sur le serveur pour bannir les IPs après 5 tentatives de login échouées en 5 minutes (ban 24h).

**Filtre** `/etc/fail2ban/filter.d/memberbase-login.conf` :
```ini
[Definition]
failregex = ^<HOST> .* "POST /login\.php HTTP/1\.[01]" 200
ignoreregex =
```

**Jail** dans `/etc/fail2ban/jail.local` :
```ini
[memberbase-login]
enabled  = true
port     = http,https
filter   = memberbase-login
logpath  = /var/log/apache2/votre-domaine-access_log
maxretry = 5
findtime = 300
bantime  = 86400
```

Vérifier l'état : `fail2ban-client status memberbase-login`
