# Guide utilisateur — MemberBase

Guide pratique pour la gestion quotidienne des membres, groupes, comptabilité et suivi.

---

## Sommaire

1. [Connexion et navigation](#1-connexion-et-navigation)
2. [Liste des membres](#2-liste-des-membres)
3. [Ajouter un membre](#3-ajouter-un-membre)
4. [Fiche membre](#4-fiche-membre)
5. [Comptabilité (onglet Compta)](#5-comptabilité-onglet-compta)
6. [Suivi (onglet Suivi)](#6-suivi-onglet-suivi)
7. [Groupes](#7-groupes)
8. [Vues d'activité — Rapports](#8-vues-dactivité--rapports)
9. [Attestations de dons](#9-attestations-de-dons)
10. [Réglages](#10-réglages)
11. [Rôles utilisateurs](#11-rôles-utilisateurs)
12. [Changer son mot de passe](#12-changer-son-mot-de-passe)
13. [Déconnexion](#13-déconnexion)

---

## 1. Connexion et navigation

### Se connecter

1. Ouvrir l'adresse de l'application dans le navigateur.
2. Saisir le nom d'utilisateur et le mot de passe fournis par l'administrateur.
3. Cliquer **Connexion**.

Si c'est la première connexion ou si l'administrateur a réinitialisé le mot de passe, l'application demande immédiatement d'en choisir un nouveau.

### Structure de la page

L'interface est divisée en deux zones :

- **Barre de navigation en haut** : champ de recherche rapide, liens vers les sections principales, accès au profil utilisateur (nom en haut à droite).
- **Zone de contenu centrale** : change selon la section active.

Les sections accessibles depuis la navigation sont :

| Lien | Contenu |
|---|---|
| Logo / accueil | Liste des membres |
| Rapports | Journaux compta et suivi, contributions, donateurs |
| Groupes | Gestion des groupes et filtres |
| Réglages (engrenage) | Configuration, types compta, comptes utilisateurs |

### Navigation sans rechargement de page

L'application utilise htmx : la plupart des actions (navigation entre membres, changement d'onglet, filtres) mettent à jour uniquement la zone de contenu sans recharger la page entière. L'URL dans la barre du navigateur se met quand même à jour, ce qui permet d'utiliser les boutons Précédent / Suivant du navigateur normalement.

### Utilisation sur mobile

Sur un écran étroit, certaines colonnes du tableau de membres sont masquées automatiquement (adresse, NPA, date de création). La navigation principale se replie. Toutes les fonctions restent accessibles, mais l'usage quotidien est prévu pour un écran de bureau.

---

## 2. Liste des membres

La liste des membres est la page principale. Elle affiche par défaut le groupe configuré comme groupe de référence (ex. : Membres 2026).

### Colonnes affichées

| Colonne | Description |
|---|---|
| Genre | Icône homme / femme / couple |
| Société | Nom de l'organisation si applicable |
| Nom | Nom de famille |
| Prénom | Prénom |
| Adresse | Rue et numéro |
| NPA / Localité | Code postal et ville |
| Email | Adresse e-mail cliquable |
| Création | Date d'ajout du profil |
| Types | Badges colorés indiquant les types de versements enregistrés |

Cliquer sur n'importe quelle ligne ouvre la fiche du membre.

### Filtrer par groupe ou filtre rapide

Le bouton en haut à gauche de la liste affiche le groupe actif. Cliquer dessus ouvre un menu déroulant avec :

- Un champ de recherche pour filtrer la liste des groupes par nom.
- Une section **Groupes de groupes** (métagroupes de type filtre) — affiche l'union de plusieurs groupes.
- Une section **Filtres rapides** :

| Filtre rapide | Membres affichés |
|---|---|
| Tout le monde | Tous les membres actifs (hors archivés) |
| Cotisation AAAA non payée | Membres sans cotisation enregistrée pour l'année en cours |
| Aucune cotisation ces 3 dernières années | Membres ayant déjà payé une cotisation, mais aucune depuis 3 ans |
| Aucun versement ces 10 dernières années | Membres actifs sans aucune entrée comptable depuis 10 ans |
| Donateur non institutionnel actif en AAAA-1 | Membres ayant fait au moins un versement non institutionnel l'année précédente |

- Une liste de tous les groupes, regroupés par catégorie. Le badge à droite de chaque groupe indique le nombre de membres.

Pour naviguer au clavier dans ce menu : taper dans le champ de filtre, puis utiliser les flèches haut/bas et Entrée pour aller sur un groupe.

### Rechercher un membre

- Le champ **Chercher** dans la barre de navigation en haut effectue une recherche globale sur prénom, nom, société, adresse, NPA, email et commentaires. La liste se met à jour sans recharger la page.
- La zone de recherche intégrée au tableau (DataTables) filtre en temps réel dans les résultats déjà affichés.

Les termes trouvés sont surlignés dans la liste.

### Trier les colonnes

Cliquer sur un en-tête de colonne pour trier. Un second clic inverse l'ordre. DataTables conserve le tri même après une recherche.

### Exporter et gérer les colonnes

La barre d'outils DataTables (au-dessus du tableau) propose :

| Bouton | Action |
|---|---|
| Copier | Copie le contenu du tableau dans le presse-papiers |
| Excel | Télécharge un fichier .xlsx |
| PDF | Génère un PDF de la liste affichée |
| Imprimer | Ouvre la boîte d'impression du navigateur |
| Colonnes | Affiche / masque des colonnes à la demande |

Seules les lignes visibles (après filtrage) sont exportées.

---

## 3. Ajouter un membre

1. Depuis la liste des membres, cliquer le bouton **Ajouter** (icône personne+) en haut à droite de la liste.
2. Remplir le formulaire :

| Champ | Description |
|---|---|
| Société | Nom de l'organisation (facultatif) |
| Genre | Homme / Femme / Monsieur et Madame / — |
| Titre | Titre honorifique (Dr, Prof., etc.) |
| Prénom | Prénom |
| Nom | Nom de famille |
| Adresse | Rue et numéro |
| NPA / Localité | Code postal et ville (format libre) |
| Email | Adresse e-mail principale |
| Privé | Téléphone privé |
| Prof. | Téléphone professionnel |
| Portable | Numéro de mobile |
| Fax | Numéro de fax |
| Web | URL du site web |
| Date naissance | Date de naissance |
| Compétences | Zone de texte libre (supporte le gras, l'italique, les listes) |

3. Cliquer **Ajouter** pour créer le profil.

Le nouveau membre apparaît dans la liste. Si un groupe était actif dans le filtre au moment de l'ajout, le membre est automatiquement rattaché à ce groupe.

---

## 4. Fiche membre

Cliquer sur une ligne de la liste ouvre la fiche membre. Une barre d'onglets en haut de la fiche donne accès aux différentes sections.

### Onglets disponibles

| Onglet | Contenu |
|---|---|
| Données | Données personnelles et groupes d'appartenance |
| Compta | Historique des versements |
| Suivi | Notes de contact |
| Historique | Journal des modifications (admins uniquement) |

### Activer / archiver un membre

Le commutateur **Actif / Archivé** en haut à droite de la fiche permet de basculer l'état du membre.

- **Actif** : le membre apparaît dans toutes les listes.
- **Archivé** : le membre est masqué de toutes les listes de filtrage. Il reste accessible par recherche et depuis son URL directe. Toutes ses données sont conservées. L'archivage peut être annulé à tout moment en réactivant le commutateur.

Un membre archivé qui n'a pas d'entrées comptables peut être **supprimé définitivement**. Si des entrées comptables existent, seule l'**anonymisation** est proposée : les données personnelles sont effacées mais l'historique financier est conservé.

### Données générales

La zone d'informations s'affiche en **mode lecture** par défaut. Les champs remplis sont visibles, les champs vides sont masqués.

**Modifier les données :**

1. Cliquer n'importe où dans la zone de données (un encadré apparaît au survol avec la mention « Modifier »).
2. Le formulaire d'édition s'ouvre à la place de l'affichage.
3. Modifier les champs souhaités.
4. Cliquer **Enregistrer** pour sauvegarder, ou **Annuler** pour revenir à l'affichage sans sauvegarder.

Les modifications sont envoyées immédiatement au serveur. Un message de confirmation apparaît brièvement en cas de succès.

Dans le mode lecture, un lien **Google Maps** s'affiche sous l'adresse pour l'ouvrir directement dans la cartographie.

### Appartenance aux groupes

La colonne de droite (sur desktop) affiche les groupes auxquels appartient le membre. Pour ajouter ou retirer un groupe, utiliser les contrôles affichés dans cette zone. Chaque modification est enregistrée immédiatement.

### Résumé financier

Sous les groupes, un encart affiche un résumé rapide des dons et versements :

- Montant de l'année en cours
- Montant de l'année précédente
- Total depuis le premier versement

Un encart séparé s'affiche pour les versements de type « autres versements » (non comptés comme dons).

---

## 5. Comptabilité (onglet Compta)

L'onglet **Compta** liste tous les versements enregistrés pour un membre.

### Ajouter une entrée

Le formulaire d'ajout est affiché en haut du tableau :

1. **Type** : sélectionner le type de versement dans la liste (cotisation, don, etc.).
2. **Date** : saisir la date au format JJ/MM/AAAA. La date du jour est pré-remplie.
3. **Libellé** : texte libre décrivant le versement.
4. **Somme** : montant en CHF (nombre, ex. : 50 ou 150.00).
5. **Commentaire** : champ libre (numéro de quittance, référence, note interne).
6. **Attestation** (icône PDF) : cocher si le donateur souhaite recevoir une attestation de don fiscale.
7. Cliquer **Ajouter**.

La ligne apparaît immédiatement dans le tableau.

### Modifier une entrée

Cliquer sur une ligne du tableau pour l'ouvrir en édition. Modifier les champs et sauvegarder.

### Supprimer une entrée

Depuis la page d'édition d'une entrée, cliquer **Supprimer cette écriture** et confirmer.

### Filtrer par année

Le bouton de sélection d'année (en haut à gauche de l'onglet Compta) filtre les entrées affichées. L'option **Toutes** affiche l'historique complet.

### Afficher uniquement les dons

Le commutateur **Dons uniquement** masque les entrées marquées comme non-don (cotisations, remboursements, ventes, etc.) pour ne voir que les versements comptant comme dons.

### Générer une attestation de don (individuelle)

1. Sélectionner une année dans le filtre d'année (le bouton n'est pas disponible si « Toutes » est sélectionné).
2. Cliquer le bouton **Attestation** (en haut à droite de l'onglet).
3. Choisir l'année dans le menu déroulant si vous souhaitez une autre année que celle affichée.
4. Le PDF s'ouvre dans un nouvel onglet et peut être téléchargé ou imprimé.

L'attestation ne reprend que les versements dont le type n'est pas marqué « exclu des dons » dans les réglages.

### Total

Le pied de tableau affiche le total des entrées affichées.

---

## 6. Suivi (onglet Suivi)

L'onglet **Suivi** permet d'enregistrer des notes de contact ou de suivi individuel pour un membre.

### Ajouter une note

1. Le formulaire d'ajout est affiché en haut du tableau.
2. **Date** : saisir la date au format JJ/MM/AAAA. La date du jour est pré-remplie.
3. **Commentaires** : saisir le texte de la note.
4. Cliquer **Ajouter**.

### Modifier une note

Cliquer sur une ligne du tableau pour l'ouvrir en édition. Modifier et enregistrer.

### Supprimer une note

Cliquer l'icône corbeille à droite de la ligne et confirmer.

Les notes sont affichées de la plus récente à la plus ancienne.

---

## 7. Groupes

Les groupes permettent de segmenter les membres en sous-ensembles (membres d'une année, comité, partenaires, etc.).

### Accéder à la gestion des groupes

Cliquer **Groupes** dans la barre de navigation, puis **Réglages** (ou via la roue crantée).

La gestion des groupes se trouve dans les onglets **Groupes**, **Catégories** et **Métagroupes** de la page Réglages.

### Créer un groupe

1. Dans l'onglet **Groupes**, saisir le nom du nouveau groupe dans le champ en bas de liste.
2. Cliquer **Ajouter**.

### Modifier un groupe

1. Cliquer le nom du groupe dans la liste.
2. La page d'édition permet de :
   - Renommer le groupe.
   - Masquer le groupe (il n'apparaît plus dans le menu de filtre de la liste des membres, mais les membres restent rattachés).
   - Changer la catégorie d'appartenance.
   - **Voir la liste** des membres de ce groupe.
   - **Importer des membres** depuis un autre groupe (copie ponctuelle des membres).
   - **Importer les cotisants d'une année** : ajoute automatiquement les membres ayant payé une cotisation pour l'année sélectionnée.
   - **Importer les donateurs d'une année** : avec seuil minimum CHF.
   - **Transférer et dissoudre** : déplace tous les membres vers un autre groupe, puis supprime le groupe.
   - **Supprimer** le groupe (uniquement si le groupe est vide).

### Groupes masqués

Un groupe masqué n'apparaît pas dans le menu de filtrage de la liste des membres. Il reste visible dans la gestion des groupes et dans les fiches membres. Utiliser cette option pour les groupes administratifs internes qui ne doivent pas encombrer le menu.

### Catégories de groupes

Les catégories servent à organiser visuellement les groupes dans le menu de filtrage (sections avec titre). Elles se gèrent dans l'onglet **Catégories** des réglages.

### Métagroupes (filtres)

Un métagroupe de type filtre regroupe plusieurs groupes. Sélectionner ce filtre dans la liste des membres affiche l'union de tous ses groupes membres.

**Créer un métagroupe :**

1. Dans l'onglet **Métagroupes** des réglages, cliquer **Créer un filtre**.
2. Nommer le filtre.
3. Cocher les groupes à inclure.
4. Enregistrer.

Le filtre apparaît ensuite dans le menu déroulant de la liste des membres, sous la section « Groupes de groupes ».

---

## 8. Vues d'activité — Rapports

Accès via le lien **Rapports** dans la barre de navigation. Cette section donne une vue transversale sur l'ensemble des membres.

### Journal compta

Toutes les dernières entrées comptables, tous membres confondus.

- Filtrer par **type** (cotisation, don, etc.) avec les boutons-filtres.
- Filtrer par **année**.
- Cliquer sur une ligne pour ouvrir directement la fiche compta du membre concerné.
- Export DataTables disponible (Copier / Excel / PDF / Imprimer).

### Journal suivi

Toutes les dernières notes de suivi, tous membres confondus.

- Filtrer par **année**.
- Cliquer sur une ligne pour ouvrir la fiche du membre.

### Contributions

Vue agrégée des versements par donateur pour une année donnée. Fournit des indicateurs clés pour suivre l'évolution des dons.

**Utiliser les filtres :**

1. **Année** : sélectionner l'année à analyser.
2. **Seuil minimum CHF** : masquer les donateurs en dessous d'un montant (boutons 1 / 100 / 200 / 500 / 1 000 CHF).
3. **Dons uniquement** : exclure les versements de types marqués « non-don ».
4. **Toutes entrées** : inclure tous les types de versements.
5. **Attestation demandée** : filtrer sur les donateurs ayant coché « Souhaite une attestation ».

**Indicateurs affichés :**

- Total CHF pour l'année sélectionnée, avec variation par rapport à l'année précédente.
- Nombre de donateurs **fidèles** (présents en N et N-1), **nouveaux** (présents en N uniquement), **perdus** (présents en N-1 uniquement) — chaque chiffre est cliquable pour afficher la liste détaillée.

### Donateurs fidèles

Donateurs ayant contribué à la fois en N et en N-1. Affiche les montants des deux années pour comparaison directe.

### Nouveaux donateurs

Donateurs ayant fait un premier versement en N (aucune trace en N-1).

### Donateurs perdus

Donateurs actifs en N-1 qui n'ont pas contribué en N.

Le bouton **Créer un groupe de relance** exporte cette liste dans un groupe existant ou nouveau, pour faciliter les actions de relance.

### Membres perdus

Membres ayant été actifs (cotisation ou don) en N-1 mais sans aucun versement en N. Différent des « donateurs perdus » : inclut les cotisants.

---

## 9. Attestations de dons

Une attestation de don est un document PDF officiel remis au donateur pour sa déclaration fiscale. Elle reprend les versements de l'année pour un membre donné (hors types exclus des dons).

### Attestation individuelle depuis la fiche membre

1. Ouvrir la fiche du membre.
2. Cliquer sur l'onglet **Compta**.
3. Sélectionner une année dans le filtre d'année.
4. Cliquer le bouton **Attestation** (en haut à droite).
5. Choisir l'année dans le menu déroulant.
6. Le PDF s'ouvre dans un nouvel onglet.

### Attestation individuelle depuis la vue Contributions

1. Aller dans **Rapports > Contributions**.
2. Sélectionner l'année.
3. Sur la ligne du donateur, cliquer l'icône d'attestation.
4. Le PDF se télécharge.

### Attestations en masse (toute l'année)

Cette fonction génère en une seule opération un PDF groupé contenant les attestations de tous les donateurs ayant coché **Souhaite une attestation** pour l'année sélectionnée.

1. Aller dans **Rapports > Contributions**.
2. Sélectionner l'année cible.
3. Cliquer **Toutes les attestations 20XX**.
4. Un PDF unique est généré et téléchargé, avec une attestation par donateur.

Seuls les membres dont la case **Souhaite une attestation** a été cochée sur au moins une entrée de l'année sont inclus.

---

## 10. Réglages

Accès via l'icône engrenage dans la barre de navigation. Les onglets visibles dépendent du rôle.

### Onglet Groupes

Gestion des groupes : création, renommage, masquage, import de membres, suppression. Accessible à tous les utilisateurs avec droits d'écriture.

### Onglet Catégories

Gestion des catégories de groupes. Permet d'organiser les groupes en sections dans le menu de filtrage. Accessible à tous les utilisateurs avec droits d'écriture.

### Onglet Métagroupes

Gestion des filtres de groupes. Voir section [Métagroupes](#métagroupes-filtres). Accessible à tous les utilisateurs avec droits d'écriture.

### Onglet Types compta (managers et admins)

Définit les types de versements disponibles dans le formulaire compta (cotisation, don, vente, etc.).

Pour chaque type :

- **Nom** : libellé affiché dans le formulaire et les listes.
- **Couleur** : couleur du badge dans la liste des membres et de la ligne dans l'onglet compta.
- **Est une cotisation** : si coché, ce type est pris en compte pour les filtres de cotisation.
- **Exclu des dons** : si coché, les versements de ce type ne sont pas comptabilisés dans les totaux de dons ni dans les attestations.
- **Institutionnel** : si coché, les versements de ce type sont exclus du filtre « Donateurs non institutionnels ».

### Onglet Réglages (admins uniquement)

Paramètres généraux de l'application :

| Paramètre | Description |
|---|---|
| Nom de l'organisation | Utilisé dans les en-têtes des documents générés |
| Adresse | Adresse de l'organisation |
| NPA / Ville / Pays | Coordonnées postales |
| Préfixe des groupes membres | Préfixe pour retrouver les groupes membres des années précédentes (ex. « Membre » pour « Membre 2025 », « Membre 2026 ») |
| Groupe affiché par défaut | Groupe sélectionné à l'ouverture de la liste. Mettre à jour chaque année |
| Groupe membres (année de référence) | Groupe utilisé pour les filtres cotisations et le tableau de bord. Mettre à jour chaque année |
| Groupe membres sans cotisation | Membres actifs exemptés de cotisation (bénévoles, comité). Exclus du filtre « Aucune cotisation ces 3 dernières années » |

**Important :** mettre à jour les deux paramètres de groupe membres au début de chaque nouvelle année.

### Onglet Utilisateurs (admins uniquement)

Gestion des comptes utilisateurs de l'application :

- Voir la liste des utilisateurs avec leur rôle, date de dernière connexion et statut.
- Créer un nouvel utilisateur : cliquer **Ajouter**, renseigner le nom d'utilisateur, le nom d'affichage, l'email et le rôle.
- Modifier un utilisateur existant : cliquer sur son nom.
- Réinitialiser le mot de passe : génère un mot de passe temporaire que l'utilisateur devra changer à sa prochaine connexion.
- Désactiver / réactiver un compte.

### Onglet Journal (admins uniquement)

Journal d'activité : trace toutes les actions effectuées dans l'application (créations, modifications, suppressions) avec la date, l'utilisateur et le détail.

### Onglet Intégrité (admins uniquement)

Outils de vérification et de correction des données :

- Détecter des incohérences dans la base de données.
- Corriger des problèmes signalés.

### Archivés (admins uniquement)

Lien direct vers la liste des membres archivés. Permet de consulter, réactiver, anonymiser ou supprimer des profils archivés.

---

## 11. Rôles utilisateurs

L'application distingue quatre niveaux d'accès :

| Fonctionnalité | Lecture seule | Utilisateur | Manager | Admin |
|---|:---:|:---:|:---:|:---:|
| Voir la liste des membres | Oui | Oui | Oui | Oui |
| Rechercher et filtrer | Oui | Oui | Oui | Oui |
| Voir la fiche d'un membre | Oui | Oui | Oui | Oui |
| Voir l'historique compta et suivi | Oui | Oui | Oui | Oui |
| Modifier un membre (données générales) | Non | Oui | Oui | Oui |
| Ajouter / modifier / supprimer des entrées compta | Non | Oui | Oui | Oui |
| Ajouter / modifier / supprimer des notes de suivi | Non | Oui | Oui | Oui |
| Ajouter un membre | Non | Oui | Oui | Oui |
| Archiver / réactiver un membre | Non | Oui | Oui | Oui |
| Gérer les groupes, catégories, métagroupes | Non | Oui | Oui | Oui |
| Gérer les types compta | Non | Non | Oui | Oui |
| Accéder aux réglages généraux | Non | Non | Non | Oui |
| Gérer les comptes utilisateurs | Non | Non | Non | Oui |
| Consulter le journal d'activité | Non | Non | Non | Oui |
| Supprimer / anonymiser des membres | Non | Non | Non | Oui |

Le rôle **Lecture seule** permet de consulter toutes les données sans rien modifier.

---

## 12. Changer son mot de passe

1. Cliquer sur son nom d'utilisateur en haut à droite de la page.
2. Cliquer **Mot de passe** dans le menu déroulant.
3. Saisir le mot de passe actuel.
4. Saisir le nouveau mot de passe, puis le confirmer.
5. Cliquer **Sauvegarder**.

Si le mot de passe a été réinitialisé par un administrateur, l'application demande automatiquement d'en définir un nouveau à la prochaine connexion.

---

## 13. Déconnexion

Cliquer sur son nom d'utilisateur en haut à droite, puis **Déconnexion**.
