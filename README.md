# Casa Alianza Suisse — Fichier des membres

Application web PHP de gestion des membres, groupes, cotisations et dons pour Casa Alianza Suisse.

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
| **Gros donateurs** (`resume`) | Donateurs classés par total annuel, filtre min CHF (1 / 100 / 200 / 500 / 1000), filtre année, mode "toutes entrées", filtre "attestation demandée" |

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

- Groupe affiché par défaut à l'ouverture
- Groupe membres de référence (pour filtres cotisations)
- Groupe archives (exclu des vues par défaut)
- Gestion des types de compta (UI complète: ajout, édition inline, toggle flags, suppression si non utilisé)

---

## Stack technique

- **Backend**: PHP 8, PDO/MySQL (MariaDB)
- **Frontend**: Bootstrap 5.3, DataTables 1.13, jQuery 3, Font Awesome 6, Chart.js
- **PDF**: pdftk (fill AcroForm) sur le serveur
- **Génération documents**: MHTML (quittances Word)

## Structure

```
html/
├── index.php                   # Point d'entrée unique
├── attestation_don.php         # Génération PDF attestation individuelle
├── attestation_bulk.php        # Génération PDF attestation en masse
├── quittancedon.php            # Génération quittance Word
├── assets/
│   └── attestation.pdf         # Template AcroForm officiel
├── includes/
│   ├── declarations.inc        # Bootstrap PHP, PDO, types compta, app_settings
│   ├── manage_views.inc        # Routeur de vues
│   ├── manage_actions.inc      # Actions POST (CRUD)
│   ├── view_users.inc          # Liste membres
│   ├── add_user_form.inc       # Formulaire ajout membre
│   ├── update_user_form.inc    # Formulaire édition membre
│   ├── compta_generic.inc      # Vue compta membre
│   ├── update_compta_form.inc  # Formulaire édition entrée compta
│   ├── lastEntryCompta.inc     # Vue activité compta
│   ├── lastEntrySuivi.inc      # Vue activité suivi
│   ├── resume.inc              # Vue gros donateurs
│   ├── manage_compta_types.inc # UI gestion types compta
│   ├── settings_form.inc       # Réglages application
│   └── ...
├── classes/
│   ├── user_class.inc          # Classe User (CRUD, cotisation, dons)
│   └── compta_class.inc        # Classe Compta
├── locales/
│   └── resources_fr.inc        # Libellés français (UTF-8)
└── css/
    └── custom.css              # Design system Casa Alianza
```

## Déploiement

L'application tourne sur Apache + PHP 8 avec MariaDB. `pdftk` doit être installé sur le serveur (`apt install pdftk-java`).

## Accès

`https://membres.casa-alianza.ch/`
