# Guide utilisateur — MemberBase

Application de gestion des membres, cotisations et dons de MemberBase.

---

## Connexion

1. Ouvrir `https://votre-domaine/`
2. Saisir le nom d'utilisateur et le mot de passe fournis par l'administrateur
3. Si c'est la première connexion ou si l'admin a réinitialisé le mot de passe, l'application affiche le mot de passe temporaire et oblige à le changer immédiatement

Pour changer son mot de passe à tout moment : cliquer sur son nom en haut à droite → **Mot de passe**.

---

## Liste des membres

La page d'accueil affiche la liste des membres.

### Filtrer par groupe

- Le menu déroulant **groupe** en haut de la liste permet de filtrer par groupe ou méta-groupe
- Groupes spéciaux disponibles :
  - *Tous sauf archives* — tous les membres actifs
  - *Cotisation non payée* — membres sans cotisation à jour
  - *Rien ces 10 dernières années* — membres inactifs
  - *Donateurs non-instit ayant versé l'année passée*

### Rechercher un membre

- Le champ **Chercher** dans la barre de navigation effectue une recherche sur prénom, nom, société, email
- La barre de recherche de la table DataTables filtre en temps réel dans la vue actuelle

### Exporter la liste

Sous la liste, les boutons **Copier / Excel / PDF / Imprimer** exportent les membres affichés.  
Le bouton **Colonnes** permet de choisir les colonnes à afficher/masquer avant l'export.

---

## Fiche membre

Cliquer sur une ligne de la liste ouvre la fiche membre, avec plusieurs onglets :

### Données générales

Contient toutes les informations personnelles : civilité, prénom, nom, société, adresse, coordonnées, date de naissance, compétences.

**Modifier :** changer les valeurs et cliquer **Mettre à jour**.

**Appartenance aux groupes :** en bas de la fiche, cocher/décocher les groupes. La sauvegarde est automatique à chaque coche.

### Compta

Liste toutes les entrées comptables du membre.

**Ajouter une entrée :**
1. Remplir le formulaire en haut : type, date (format JJ.MM.AAAA), libellé, montant CHF
2. Cocher *Souhaite une attestation* si le donateur a demandé un reçu fiscal
3. Cliquer **Enregistrer**

**Modifier une entrée :** cliquer l'icône crayon sur la ligne.

**Supprimer une entrée :** cliquer l'icône corbeille → confirmer dans la page de confirmation.

**Filtrer par année :** le sélecteur d'année en haut de la liste filtre les entrées affichées.

**Attestation de don PDF :**
1. Cliquer le bouton **Attestation** (icône document) à côté du sélecteur d'année
2. Choisir l'année dans le dropdown
3. Le PDF est généré et téléchargé directement

### Suivi

Notes de contact et suivi individuel.

**Ajouter une note :**
1. Remplir la date et le texte
2. Cliquer **Enregistrer**

**Modifier / Supprimer :** icônes crayon et corbeille sur chaque ligne.

---

## Rapports

Accès via le menu **Rapports** dans la barre de navigation.

### Journal compta

Vue de toutes les dernières entrées comptables, tous membres confondus.

- Filtrer par **type** (cotisation, don, etc.) avec les boutons-filtres
- Filtrer par **année** avec le sélecteur
- Export DataTables disponible

### Journal suivi

Vue des dernières notes de suivi, tous membres confondus.

- Filtrer par **année**

### Contributions (résumé donateurs)

Vue agrégée des dons par donateur pour une année donnée.

**Utilisation :**
1. Sélectionner l'**année** en haut
2. Ajuster le **seuil minimum CHF** (1 / 100 / 200 / 500 / 1000 CHF) pour masquer les petits dons
3. Cocher *Avec les types exclus* pour inclure les types marqués "exclu des dons"
4. Cocher *Attestation demandée* pour n'afficher que les donateurs ayant coché la case attestation

**KPIs affichés :**
- Total CHF de l'année, delta vs même période N-1
- Progression vs total de l'année complète N-1
- Nombre de donateurs fidèles, nouveaux, perdus — tous cliquables pour voir la liste détaillée

**Attestations en masse :**
Quand une année spécifique est sélectionnée, le bouton **Toutes les attestations 20XX** génère un PDF unique contenant toutes les attestations pour l'année (uniquement les donateurs ayant coché *Souhaite une attestation*).

### Donateurs fidèles

Donateurs ayant contribué en N et N-1. Affiche les montants des deux années pour comparaison.

### Nouveaux donateurs

Donateurs ayant fait un premier don en N (aucun don en N-1).

### Donateurs perdus

Donateurs de N-1 absents en N.
- Bouton **Créer un groupe de relance** pour exporter cette liste dans un groupe existant ou nouveau.

---

## Groupes

Accès via **Groupes** dans la barre de navigation.

### Onglet Groupes

Liste de tous les groupes actifs avec le nombre de membres.

**Créer un groupe :**
1. Saisir le nom dans le champ en bas
2. Cliquer **Créer**

**Modifier un groupe :** cliquer le nom du groupe.

Depuis la page d'édition d'un groupe, on peut :
- Renommer le groupe
- Masquer le groupe (il n'apparaît plus dans les listes de filtrage)
- Changer la catégorie du groupe
- **Voir la liste** des membres filtrée sur ce groupe (lien en haut à droite)
- **Importer des membres** depuis un autre groupe (copie ponctuelle)
- **Importer les cotisants d'une année** — le sélecteur d'année indique le nombre de nouveaux membres
- **Importer les donateurs d'une année** — avec seuil minimum CHF
- **Transférer et dissoudre** — déplacer les membres vers un autre groupe et supprimer celui-ci
- **Supprimer** le groupe (si vide)

**Créer un groupe depuis plusieurs groupes :**
1. Cocher plusieurs groupes dans la liste
2. Cliquer **Créer un métagroupe** — crée un filtre de groupes regroupant la sélection

### Onglet Catégories

Permet d'organiser les groupes en sections visuelles (les catégories n'apparaissent pas dans la navigation, elles servent uniquement à structurer la liste des groupes).

- Réordonner les catégories par glisser-déposer
- Créer / renommer / supprimer des catégories

### Onglet Filtres

Gestion des **filtres de groupes** (métagroupes de type filtre). Un filtre regroupe dynamiquement plusieurs groupes : sélectionner ce filtre dans la liste membres affiche l'union de tous ses groupes membres.

**Créer un filtre :**
1. Cliquer **Créer un filtre**
2. Nommer le filtre
3. Cocher les groupes à inclure

Depuis la page d'édition d'un filtre, le lien **Voir la liste filtrée →** ouvre la liste membres pré-filtrée.

---

## Déconnexion

Cliquer sur son nom en haut à droite → **Déconnexion**.
