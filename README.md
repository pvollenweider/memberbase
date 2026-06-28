# MemberBase

Application web PHP de gestion des membres, groupes, cotisations et dons.

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
- Champs: société, civilité, prénom, nom, adresse, NPA/localité, email, téléphone, portable, fax, web, date de naissance, compétences
- Appartenance à un ou plusieurs groupes
- Suivi individuel (notes de contact)

### Groupes et méta-groupes

- Création et gestion de groupes (teams) avec visibilité configurable (actif/masqué)
- Méta-groupes: regrouper des groupes en catégories pour filtrage
- Filtre de la liste membres par groupe ou méta-groupe
- Recherche incrémentale dans le dropdown de sélection de groupe
- Filtre rapide par statut: tout le monde sauf archives, cotisation non payée, rien ces 10 dernières années, non-instit ayant versé l'année passée
- **Import automatique dans un groupe** depuis la page d'édition d'un groupe:
  - Importer les membres d'un autre groupe (copie ponctuelle)
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
| **Donateurs perdus** (`lapsedDonors`) | Donateurs de N-1 absents en N, avec création de groupe de relance |
| **Membres perdus** (`lapsedMembers`) | Membres de l'équipe N-1 non reconduits en N, avec création de groupe de relance |

### Attestations de dons (PDF)

- Génération d'une attestation PDF individuelle depuis la vue compta d'un membre (bouton "Attestation" avec dropdown année)
- Génération individuelle depuis la liste gros donateurs (icône PDF par ligne)
- **Génération en masse**: un seul PDF contenant toutes les attestations de l'année affichée (bouton "Toutes les attestations 20XX")
- Template officiel AcroForm (Administration fiscale cantonale de Genève)
- Remplissage via `pdftk` côté serveur, encodage UTF-16 BE pour les caractères accentués
- Les données d'institution (nom, adresse, NPA) sont préconfigurées

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
- **Intégrité** — détection des groupes masqués avec assignations actives

---

## Stack technique

- **Backend**: PHP 8, PDO/MySQL (MariaDB)
- **Frontend**: Bootstrap 5.3.8, DataTables 1.13, jQuery 3, Font Awesome 6, Chart.js, moment.js 2.30 — tous auto-hébergés (zéro CDN)
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
├── quittancedon.php            # Génération quittance Word
├── assets/
│   └── attestation.pdf         # Template AcroForm officiel
├── includes/
│   ├── declarations.php        # Bootstrap PHP, PDO, types compta, app_settings
│   ├── manage_views.php        # Routeur de vues
│   ├── manage_actions.php      # Dispatcher actions POST
│   ├── view_users.php          # Liste membres
│   ├── add_user_form.php       # Formulaire ajout membre
│   ├── update_user_form.php    # Formulaire édition membre
│   ├── resume.php              # Vue contributions (KPIs + liste donateurs)
│   ├── lapsed_donors.php       # Vue donateurs perdus
│   ├── lapsed_members.php      # Vue membres perdus
│   ├── actions/                # Handlers CRUD (members, groups, compta…)
│   └── ...
├── classes/
│   ├── user_class.php          # Classe User (CRUD, cotisation, dons)
│   └── team_class.php          # Classe Team
├── locales/
│   └── resources_fr.php        # Libellés français (UTF-8)
├── css/
│   ├── custom.css              # Styles MemberBase
│   ├── webfonts/               # Font Awesome 6 woff2/ttf
│   └── vendor/                 # Bootstrap, DataTables, Font Awesome CSS
├── js/
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

### Authentification

Gérée par PHP (table `app_users`, bcrypt). Pas de htaccess. Le compte admin est créé via l'installeur web (`install.php`).

Rôles : `admin` (gestion des utilisateurs) et `user`. L'admin peut créer/supprimer des comptes et réinitialiser les mots de passe. Tout utilisateur peut changer son propre mot de passe.

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
