# Guide utilisateur — MemberBase

Guide pratique pour la gestion quotidienne des membres, segments, comptabilité, suivi et dons.

> **Terminologie (v3.5.4).** L'interface parle de **Segment** (auparavant « groupe ») et de
> **Segment combiné** (auparavant « métagroupe » ou « filtre »). Les anciennes captures ou
> instructions mentionnant « groupe » désignent la même notion.

---

## Sommaire

1. [Connexion et navigation](#1-connexion-et-navigation)
2. [Liste des membres](#2-liste-des-membres)
3. [Ajouter un membre](#3-ajouter-un-membre)
4. [Importer des contacts (CSV / TSV)](#4-importer-des-contacts-csv--tsv)
5. [Fiche membre](#5-fiche-membre)
6. [Comptabilité d'un membre (onglet Compta)](#6-comptabilité-dun-membre-onglet-compta)
7. [Suivi d'un membre (onglet Suivi)](#7-suivi-dun-membre-onglet-suivi)
8. [Tâches](#8-tâches)
9. [Journaux et aperçu des dons](#9-journaux-et-aperçu-des-dons)
10. [Attestations de dons](#10-attestations-de-dons)
11. [Segments](#11-segments)
12. [Réglages](#12-réglages)
13. [Rôles utilisateurs et matrice des droits](#13-rôles-utilisateurs-et-matrice-des-droits)
14. [Changer son mot de passe](#14-changer-son-mot-de-passe)
15. [Déconnexion](#15-déconnexion)
16. [Récapitulatifs comptables par email](#16-récapitulatifs-comptables-par-email)
17. [Rappels de cotisation impayée](#17-rappels-de-cotisation-impayée)

---

## 1. Connexion et navigation

### Se connecter

1. Ouvrir l'adresse de l'application dans le navigateur.
2. Saisir l'identifiant et le mot de passe fournis par l'administrateur.
3. Cliquer **Connexion**.

Lors de la première connexion, ou après une réinitialisation par un administrateur,
l'application demande immédiatement de choisir un nouveau mot de passe. Si vous avez reçu
un **lien d'invitation**, il vous permet de définir vous-même votre mot de passe.

### Changer la langue de l'interface

Depuis le menu *Nom d'utilisateur → Mot de passe*, une carte **Langue** permet de choisir
la langue de l'interface : français (par défaut), anglais, allemand ou espagnol. Le choix
est enregistré sur le compte et s'applique à toutes vos futures connexions, sur n'importe
quel poste.

### La barre de navigation

En haut de chaque page, la barre bleue contient :

| Élément | Contenu |
|---|---|
| **Liste** (icône liste) | Liste des membres — page d'accueil |
| **Compta** (icône pièces) | Journal comptable global (tous membres confondus) |
| **Suivi** (icône livre) | Journal des notes de suivi (tous membres confondus) |
| **Aperçu des dons** (icône camembert) | Tableau de bord des dons et contributions |
| **Engrenage** (à droite) | Réglages — visible uniquement pour les rôles Manager et Admin |
| **Nom d'utilisateur** (à droite) | Menu : *Mot de passe*, *Déconnexion* |
| **Chercher** | Recherche rapide globale |

Il n'y a plus de menu « Rapports » ni « Groupes » séparé : les journaux et l'aperçu des dons
sont directement dans la barre, et la gestion des segments se fait dans les **Réglages**.

Sur mobile, la barre se réduit à une rangée d'icônes ; la loupe ouvre un champ de recherche
et l'icône utilisateur regroupe le profil.

### Navigation sans rechargement (htmx)

La plupart des actions (changement de membre, d'onglet, de filtre) ne rechargent que la zone
centrale. L'URL du navigateur reste à jour : les boutons Précédent / Suivant fonctionnent
normalement.

> **Modifications non enregistrées.** Si vous quittez une page contenant des modifications non
> sauvegardées (édition en cours), un message d'avertissement s'affiche.

---

## 2. Liste des membres

La liste des membres est la page d'accueil. Elle affiche par défaut le segment configuré comme
segment de référence (par exemple « Membre 2026 »).

### Colonnes affichées

| Colonne | Description |
|---|---|
| Genre | Icône homme / femme / couple |
| Société | Nom de l'organisation si applicable |
| Nom | Nom de famille |
| Prénom | Prénom |
| Adresse | Rue et numéro |
| NPA | Code postal / localité |
| Email | Adresse e-mail cliquable |
| Création | Date d'ajout du profil |
| Types | Petits badges colorés (3 lettres) indiquant les types de versements enregistrés ; cliquer ouvre l'onglet Compta du membre |

Cliquer sur une ligne ouvre la fiche du membre. Certaines colonnes sont masquées par défaut
ou sur petit écran (genre, adresse, NPA, date de création) et peuvent être réaffichées via le
bouton **Colonnes**.

### Filtrer par segment

Le bouton en haut à gauche de la liste affiche le segment actif. Cliquer dessus ouvre un menu
déroulant contenant :

- Un champ **Filtrer…** pour retrouver un segment par son nom (navigation clavier : flèches +
  Entrée).
- Une section **Segments combinés** (union de plusieurs segments), si au moins un existe.
- Une section **Filtres rapides** :

| Filtre rapide | Membres affichés |
|---|---|
| Tout le monde | Tous les membres actifs (hors archivés) |
| Aucune cotisation ces 3 dernières années | Membres ayant déjà cotisé, mais pas lors des 3 dernières années (les membres du segment « sans cotisation » sont exclus) |
| Aucun versement ces 10 dernières années | Membres actifs sans aucune entrée comptable depuis 10 ans |
| Cotisation AAAA non payée | Membres sans cotisation enregistrée pour l'année en cours |
| Donateur non institutionnel actif en AAAA-1 | Membres ayant fait au moins un versement non institutionnel l'année précédente |

- La liste de tous les segments, regroupés par **catégorie**. Le badge à droite de chaque
  segment indique son nombre de membres.

Une phrase d'explication s'affiche sous le bouton lorsqu'un filtre rapide est actif.

### Rechercher un membre

- Le champ **Chercher** de la barre de navigation effectue une recherche globale sur prénom,
  nom, société, adresse, NPA, e-mail et commentaires. La liste se met à jour automatiquement
  dès 3 caractères saisis (raccourci clavier : `/` pour placer le curseur dans le champ).
- Les termes trouvés sont surlignés dans la liste.

### Trier, exporter, gérer les colonnes

- Cliquer un en-tête de colonne pour trier ; un second clic inverse l'ordre.
- La barre d'outils au-dessus du tableau propose : **Copier**, **Excel**, **PDF**,
  **Imprimer**, **Colonnes**. Seules les lignes visibles (après filtrage) sont exportées.

### Boutons d'action de la liste

| Bouton | Rôle requis | Action |
|---|---|---|
| **Importer** (icône import) | Manager / Admin | Assistant d'import de contacts (voir §4) |
| **Ajouter** (icône personne +) | Utilisateur et plus | Créer un nouveau membre (voir §3) |

---

## 3. Ajouter un membre

1. Depuis la liste, cliquer le bouton **Ajouter**.
2. Remplir le formulaire :

| Champ | Description |
|---|---|
| Société | Nom de l'organisation (facultatif) |
| Nom | Nom de famille |
| Prénom | Prénom |
| Sexe | Indéterminé / Monsieur et Madame / Madame / Monsieur |
| Titre | Titre honorifique (Dr, Prof.…) |
| Adresse | Rue et numéro |
| NPA | Code postal et localité |
| E-mail | Adresse e-mail principale |
| **E-mail alt.** | Adresse alternative / historique — *non utilisée pour les envois* |
| Web | Site web |
| Tél. prof. / Privé / Portable / Fax | Numéros de téléphone |
| Date de naissance | Format JJ/MM/AAAA |
| Compétences / remarques | Zone de texte libre |

3. Si vous avez ouvert le formulaire depuis un segment actif, une case
   **Ajouter au segment « … »** est proposée en bas.
4. Cliquer **Ajouter**.

Le nouveau membre s'ouvre directement sur sa fiche.

---

## 4. Importer des contacts (CSV / TSV)

L'import en masse est réservé aux rôles **Manager** et **Admin**. Cliquer le bouton
**Importer** dans la barre d'outils de la liste. L'assistant se déroule en 3 étapes.

### Étape 1 — Fichier

- Sélectionner un fichier **CSV** (séparateur virgule ou point-virgule) ou **TSV**
  (tabulation).
- Encodage **UTF-8** ou **Latin-1**. La **première ligne** doit contenir les en-têtes de
  colonnes.
- Limites : **5 MB** et **5 000 lignes**. Au-delà, un avertissement s'affiche et seules les
  5 000 premières lignes sont importées (découper le fichier pour importer le reste).

### Étape 2 — Correspondance des colonnes

- Pour chaque colonne du fichier, choisir le **champ membre** correspondant (Nom, Prénom,
  Société, Genre/civilité, Titre, E-mail, E-mail alt., téléphones, Adresse, NPA, Web,
  Naissance, Remarques) ou **— ignorer —**.
- Les colonnes courantes sont **pré-associées** automatiquement d'après leur en-tête. Une
  colonne d'**exemples** (échantillon des 25 premières lignes) aide à vérifier. La civilité en
  texte (« Monsieur », « Madame », « Madame et Monsieur ») est convertie automatiquement.
- **Ajouter les contacts à un segment** — quatre choix :
  - **Créer un segment `Import JJ.MM.AAAA HH:MM`** (option par défaut) ;
  - **Ajouter à un segment existant** ;
  - **Créer un nouveau segment** (nom + catégorie facultative) ;
  - **Ne pas ajouter à un segment**.
- Cliquer **Importer**.

### Étape 3 — Résultats et doublons

- Un bandeau indique le nombre de contacts **créés** et, le cas échéant, le nombre de contacts
  ajoutés au segment choisi.
- Les **doublons** (même e-mail, ou même prénom + nom qu'un membre existant) sont listés. Pour
  chacun, choisir :
  - **Ignorer** (ne rien modifier) ;
  - **Compléter les champs vides** (n'écrase pas l'existant) ;
  - **Écraser** (remplace les valeurs par celles du fichier).
- Cliquer **Appliquer les choix**, ou **Terminer sans appliquer**.

---

## 5. Fiche membre

Cliquer une ligne de la liste ouvre la fiche. Une barre d'onglets donne accès aux sections.

### Onglets

| Onglet | Contenu |
|---|---|
| Données générales | Coordonnées + appartenance aux segments + résumé financier |
| Compta | Historique des versements (voir §6) |
| Suivi | Notes de contact (voir §7) |
| Historique | Journal des modifications de la fiche — **admins uniquement** |

Le nombre d'entrées est affiché en petit à côté des onglets Compta et Suivi.

### Actif / Archivé

Un commutateur **Actif / Archivé** est affiché en haut à droite (modifiable par les **Managers**
et **Admins**).

- **Actif** : le membre apparaît dans toutes les listes.
- **Archivé** : le profil est retiré de toutes les listes de filtrage (une confirmation est
  demandée). Il reste accessible par recherche et par son URL directe, et toutes ses données
  sont conservées. Réversible à tout moment.

Pour un membre **archivé**, un administrateur peut :

- le **supprimer définitivement** s'il n'a **aucune** entrée comptable ;
- sinon, uniquement l'**anonymiser** : les données personnelles sont effacées, l'historique
  financier est conservé.

### Données générales (mode lecture / édition)

Par défaut, les données s'affichent en **lecture** ; les champs vides sont masqués. Un lien
**Google Maps** apparaît sous l'adresse.

Pour modifier (rôles Utilisateur et plus) : cliquer dans la zone de données (la mention
*Modifier* apparaît au survol). Le formulaire s'ouvre en place. Il inclut notamment le champ
**E-mail alt.** (adresse historique / alternative, non utilisée pour les envois) et un éditeur
de texte enrichi pour les *compétences / remarques* (gras, italique, listes). Cliquer
**Enregistrer** ou **Annuler**. La date de création et de dernière modification est indiquée en
bas.

### Appartenance aux segments

La colonne de droite liste les **Segments** du membre, regroupés par catégorie. Les segments
masqués portent une icône œil barré.

Pour ajouter ou retirer un segment (rôles **Manager** et **Admin**) : cliquer la croix d'une
pastille pour retirer, ou déplier **Ajouter un segment** pour en ajouter. Chaque changement est
enregistré immédiatement (avec possibilité d'annuler via le bandeau). Le bouton **Segments
masqués** affiche aussi les segments cachés.

### Résumé financier

Sous les segments, des encarts récapitulent (année en cours, année précédente, total depuis le
premier versement) :

- **Dons** : versements comptés comme dons ;
- **Autres versements** : versements de types exclus des dons (avec le détail des types) ;
- **Total** de tous les versements confondus.

Un avertissement *Cotisation AAAA non payée* s'affiche si le membre n'a pas cotisé cette année
(sauf s'il appartient au segment « membres sans cotisation »).

---

## 6. Comptabilité d'un membre (onglet Compta)

L'onglet **Compta** liste tous les versements du membre et propose des graphiques (répartition
par type et évolution mensuelle / cumulée).

### Ajouter une entrée (rôles Utilisateur et plus)

Le formulaire est en haut du tableau :

1. **Type** — sélectionner le type de versement (cotisation, don…).
2. **Date** — format JJ/MM/AAAA (date du jour pré-remplie).
3. **Année de cotisation** — n'apparaît que pour un type marqué « cotisation » ; utile quand le
   paiement d'une année tombe dans une autre (ex. cotisation 2027 payée en décembre 2026).
   Pré-rempli sur l'année de la date de paiement.
4. **Libellé** — description libre. Pré-rempli automatiquement si le type définit un
   **libellé par défaut** (Réglages → Types compta) ; pour un type cotisation, l'année
   sélectionnée est ajoutée (ex. « Cotisation 2026 ») et suit les changements d'année.
   Dès que vous modifiez le libellé à la main, il n'est plus écrasé.
5. **Somme** — montant en CHF (ex. `50` ou `12.50`).
6. **Commentaire** — texte libre (ex. référence de paiement).
7. **Attestation** (case à cocher) — cocher si le donateur souhaite une attestation fiscale.
8. Cliquer **Ajouter**.

### Modifier / supprimer

Cliquer une ligne pour l'ouvrir en édition. La suppression se fait depuis la page d'édition de
l'entrée.

### Filtres et outils

- **Année** : filtrer par année, ou **Toutes** pour l'historique complet.
- **Dons uniquement** : masquer les entrées non-don (ventes, remboursements…). Les entrées
  non comptées comme don portent la mention « non-don ».
- **Attestation** (menu) : générer une attestation de don pour l'année choisie (voir §9).
- Le pied de tableau affiche le **total** des entrées affichées.

---

## 7. Suivi d'un membre (onglet Suivi)

L'onglet **Suivi** enregistre des notes de contact individuelles.

1. **Date** (date du jour pré-remplie) et **Commentaires**.
2. Cliquer **Ajouter**.

Cliquer une ligne pour la modifier ; l'icône corbeille supprime la note. Les notes sont
affichées de la plus récente à la plus ancienne. L'ajout, la modification et la suppression
sont réservés aux rôles Utilisateur et plus.

---

## 8. Tâches

Distinctes des notes de suivi, les **tâches** ont un titre, une échéance optionnelle, une
priorité et un statut ouvert/fermé — pensées pour le suivi d'actions à faire plutôt que pour
l'historique de contact.

### Depuis la fiche d'un membre

L'onglet **Tâches** de la fiche membre liste les tâches liées à ce membre et propose un
formulaire d'ajout (titre, échéance, priorité, description). Un badge sur l'onglet indique le
nombre de tâches ouvertes ; il devient **rouge** dès qu'au moins une tâche est en retard
(échéance dépassée et non terminée).

Sur chaque ligne : coche pour marquer **terminée** (ou rouvrir une tâche déjà terminée), icône
crayon pour modifier, icône corbeille pour supprimer.

### Vue globale

Le lien **Tâches** de la barre de navigation ouvre la liste de **toutes les tâches ouvertes**,
tous membres confondus, triées par échéance puis priorité. Les tâches en retard sont mises en
évidence en rouge. Cliquer une ligne ouvre la fiche du membre concerné (ou la tâche elle-même
si elle n'est liée à aucun membre en particulier).

Un formulaire d'ajout y est aussi disponible pour créer une **tâche générale**, non rattachée
à un membre (ex. « renouveler l'assurance RC », « préparer l'AG »).

### Génération automatique (rôles Manager et Admin)

Le bouton **Générer les tâches de relance cotisation** crée une tâche par membre correspondant
au filtre « Cotisation impayée cette année » (même règle que dans la liste des membres), sans
créer de doublon si une tâche de relance est déjà ouverte pour ce membre. Relancer la génération
plus tard ne recrée donc que les tâches manquantes.

Si un membre a entre-temps payé sa cotisation par un autre biais (saisie directe dans l'onglet
Compta, par exemple), relancer la génération **ferme automatiquement** la tâche de relance
devenue inutile — inutile de la chercher pour la marquer terminée à la main.

### Envoyer le rappel directement depuis la tâche

Une tâche de relance cotisation affiche un bouton **Envoyer le rappel** qui ouvre le même
aperçu email (sujet + rendu, bulletin de versement QR en pièce jointe) que la vue **Membres
perdus** (voir §17). Confirmer l'envoi **ferme automatiquement la tâche** — pas besoin de la
marquer terminée séparément après l'envoi.

L'ajout, la modification, la fermeture et la suppression de tâches sont réservés aux rôles
Utilisateur et plus.

### Rappel automatique par email (équipe)

Si l'administrateur système a configuré une tâche planifiée (cron, voir le guide administrateur),
un e-mail récapitulatif des tâches en retard ou à échéance dans les 3 prochains jours est envoyé
automatiquement à l'adresse configurée dans Réglages → Email. Sans cron configuré, il faut
consulter la vue globale des tâches soi-même.

---

## 9. Journaux et aperçu des dons

Ces vues transversales sont accessibles depuis la barre de navigation.

### Compta — journal comptable global

Toutes les entrées comptables, tous membres confondus.

- Filtrer par **type** de versement et par **année** (avec options *12 derniers mois* /
  *24 derniers mois* / *Toutes les années*).
- Cliquer une ligne ouvre l'onglet Compta du membre concerné.
- Export DataTables (Copier / Excel / PDF / Imprimer) et graphiques (répartition par type,
  évolution mensuelle vs cumulée).

### Suivi — journal des notes global

Toutes les notes de suivi, tous membres confondus, avec les mêmes outils d'export.

### Aperçu des dons

Tableau de bord des contributions. En haut, des **cartes clés** :

- **Contributions AAAA** : total CHF de l'année, avec comparaison à l'année précédente (et,
  pour l'année en cours, comparaison à la même période l'an dernier + objectif à atteindre).
- **Donateurs** : nombre de donateurs, avec trois liens cliquables :
  - **fidèles** — ont donné en AAAA et AAAA-1 ;
  - **Nouveaux** — première contribution en AAAA ;
  - **perdus** — ont donné en AAAA-1 mais pas en AAAA.
- **Membres actifs** : effectif du segment de référence, avec comparaison et lien
  *membres perdus* (membres non reconduits).
- Un **camembert** de répartition par type de versement.

Filtres et options :

- **Montant minimum** : 1 / 100 / 200 / 500 / 1 000 CHF.
- **Année** : année précise, *12 / 24 derniers mois*, ou *toutes les années*.
- **Mode étendu** : inclut **tous** les types de versements (y compris ceux exclus des dons) —
  les totaux ne reflètent alors plus uniquement les dons ; une colonne *Autres* et un *Total*
  apparaissent.
- **Inclure si attestation demandée** : ajoute les personnes ayant coché « souhaite une
  attestation » même sous le montant minimum.
- **Attestations AAAA** : génère toutes les attestations de l'année en un seul PDF (voir §9).

Le tableau liste les donateurs avec leur statut (membre / don institutionnel), le montant des
dons et l'indicateur d'attestation. Cliquer une ligne ouvre la compta du membre ; l'icône PDF
génère son attestation.

#### Listes détaillées (donateurs fidèles / nouveaux / perdus, membres perdus)

Accessibles via les liens des cartes. La vue **Donateurs perdus** propose un bouton
**Créer segment « Donateurs à relancer AAAA »** qui rassemble ces personnes dans un nouveau
segment pour faciliter la relance.

---

## 10. Attestations de dons

Une attestation de don est un PDF officiel remis au donateur pour sa déclaration fiscale. Elle
reprend les versements de l'année (hors types marqués « exclu des dons »), avec en option le
tampon et la signature de l'organisation (images déposées sur le serveur par l'administrateur
système — pas de réglage dans l'interface, voir la documentation d'administration).

### Qui peut en bénéficier fiscalement

Une attestation peut être émise aussi bien à un particulier qu'à une entreprise membre : les deux
ont droit à une déduction fiscale sur leurs dons, sous condition que l'association bénéficiaire
soit reconnue d'utilité publique et exonérée d'impôt sur le bénéfice.

- **Personnes physiques** : déduction jusqu'à 20% du revenu net imposable (art. 33a LIFD au
  niveau fédéral ; art. 37 al. 1 LIPP pour Genève).
- **Personnes morales (entreprises)** : même droit à déduction, jusqu'à 20% du bénéfice net
  (art. 59 al. 1 lit. c LIFD ; art. 13 al. 1 lit. c LIPM pour Genève).
- **Cotisations exclues** : seuls les dons sont déductibles — les cotisations ne le sont pas
  (d'où l'importance de marquer les types d'écriture correspondants comme « exclu des dons »,
  voir [Réglages → Types compta](admin.md#types-de-compta) dans le guide administrateur).

Les bases légales cantonales varient hors Genève ; se référer à la législation applicable dans le
canton de l'organisation. Référence générale : Circulaire CSI du 18 janvier 2008 (modifiée
novembre 2023) sur la déductibilité des libéralités.

### Télécharger le PDF

- **Depuis la fiche d'un membre** : onglet **Compta**, choisir une année, menu **Attestation**.
  Chaque année propose une case à cocher **Inclure tampon/signature** (décochée par défaut).
- **Depuis l'aperçu des dons** : icône **PDF** sur la ligne du donateur (sans tampon/signature).
- **En masse (toute l'année)** : dans **Aperçu des dons**, menu **Attestations AAAA** →
  **Télécharger le PDF (toutes)** ou **... avec tampon/signature**. Un PDF unique regroupant
  toutes les attestations est généré (peut prendre plusieurs minutes, se poursuit dans l'onglet
  ouvert). Sont inclus tous les donateurs dont le total de versements de l'année dépasse le
  montant minimum sélectionné (mêmes critères que le tableau **Aperçu des dons**).

### Envoyer par email (rôles Manager et Admin)

- **Individuel** : bouton enveloppe à côté du PDF (fiche membre onglet Compta, ou ligne du
  donateur dans l'Aperçu des dons). Un **aperçu de l'email** (sujet + rendu tel qu'il sera reçu)
  s'affiche avant l'envoi. Le tampon/signature est **toujours inclus** dans les envois par email
  (contrairement au téléchargement, où c'est optionnel).
- **En masse** : menu **Attestations AAAA** → **Envoyer par email**. Les personnes ayant déjà
  reçu leur attestation cette année sont listées séparément avec une case à cocher (**décochée
  par défaut**) permettant de forcer un renvoi ; les autres sont ignorées automatiquement.
- **Copie (BCC)** : si un email de contact est configuré dans Réglages → Email, une case
  **Envoyer une copie à [adresse]** propose d'en recevoir une copie silencieuse, pour l'envoi
  individuel comme en masse.
- **Hors saison** : les attestations se font normalement en janvier. En dehors de cette période,
  une case de confirmation explicite est requise avant l'envoi.
- **Régénérer une attestation déjà envoyée** : depuis le détail d'un email envoyé (journal des
  emails, Réglages → Email), un bouton **Régénérer l'attestation (PDF)** reproduit le PDF avec
  la date d'envoi d'origine (pas la date du jour).

---

## 11. Segments

Les segments découpent les membres en sous-ensembles (membres d'une année, comité, partenaires,
donateurs à relancer…). Leur gestion se trouve dans les **Réglages** (icône engrenage,
réservée aux Managers et Admins), onglets **Segments**, **Catégories** et **Segments combinés**.

### Créer un segment

Dans l'onglet **Segments**, saisir le nom dans le champ prévu et valider.

### Modifier un segment

Cliquer le nom d'un segment ouvre sa page d'édition, qui permet de :

- **Renommer** le segment ;
- le **Masquer dans les interfaces** (il disparaît du menu de filtrage, mais les membres
  restent rattachés) ;
- lui attribuer une **catégorie** ;
- **Voir la liste** de ses membres ;
- **Importer des membres d'autres segments** (copie ponctuelle) ;
- **Importer les cotisants d'une année** (types marqués « cotisation ») ;
- **Importer les donateurs d'une année** (tous / non-institutionnels / institutionnels, avec
  montant minimum) ;
- **Réaffecter ou dissoudre** : transférer tous les membres vers un autre segment puis le
  supprimer, ou retirer tous les membres et supprimer le segment.

> Les imports de membres sont des **copies ponctuelles** : si le segment source évolue ensuite,
> ce segment n'est pas mis à jour. Pour un regroupement dynamique, utiliser un **segment
> combiné**.

### Catégories

L'onglet **Catégories** organise visuellement les segments en sections (titres) dans le menu de
filtrage et dans les fiches membres.

### Segments combinés

Un **segment combiné** regroupe plusieurs segments : le sélectionner dans la liste affiche
l'**union** de leurs membres. Depuis l'onglet **Segments combinés**, saisir un nom pour en créer
un, puis cliquer son nom pour choisir les segments à inclure. Il apparaît ensuite en tête du
menu de filtrage de la liste des membres.

---

## 12. Réglages

Accès via l'icône **engrenage** (Managers et Admins). La barre latérale liste les sections ;
les sections disponibles dépendent du rôle.

| Section | Rôle | Contenu |
|---|---|---|
| Segments | Manager / Admin | Gestion des segments (voir §10) |
| Catégories | Manager / Admin | Catégories de segments |
| Segments combinés | Manager / Admin | Filtres regroupant plusieurs segments |
| Types compta | Manager / Admin | Types de versements |
| Réglages | Admin | Paramètres généraux de l'organisation |
| Email | Admin | Configuration SMTP, templates d'email, journal des envois |
| Utilisateurs | Admin | Comptes de connexion à l'application |
| Journal | Admin | Journal d'activité |
| Intégrité | Admin | Vérification et correction des données |
| Santé | Admin | Export de la base, application des migrations en attente |
| Archivés | Admin | Liste des membres archivés |

### Types compta

Pour chaque type de versement :

- **Nom** et **Couleur** (badge dans la liste et la compta) ;
- **Libellé par défaut** : pré-remplit le champ Libellé du formulaire de saisie ; pour un
  type cotisation, l'année sélectionnée est concaténée (ex. « Cotisation 2026 ») ;
- **Est une cotisation** : pris en compte par les filtres de cotisation ;
- **Exclu des dons** : non comptabilisé dans les totaux de dons ni les attestations ;
- **Institutionnel** : exclu du filtre « donateurs non institutionnels ».

### Réglages (généraux, Admin)

| Paramètre | Description |
|---|---|
| Nom / Adresse / NPA / Ville / Pays de l'organisation | En-têtes des documents générés |
| Préfixe des segments membres | Préfixe pour retrouver les segments membres des années (ex. « Membre ») |
| Segment affiché par défaut | Segment ouvert à l'arrivée sur la liste — **à mettre à jour chaque année** |
| Segment membres (année de référence) | Utilisé pour les filtres cotisations et l'aperçu des dons — **à mettre à jour chaque année** |
| Segment membres sans cotisation | Membres actifs exemptés de cotisation (bénévoles, comité…) |
| Numéro IDE | Identifiant d'entreprise suisse — bouton **Vérifier via Zefix** pour préremplir nom/adresse/but statutaire automatiquement |
| But statutaire | Extrait des statuts, utilisé dans les documents officiels |
| Statut d'exonération fiscale | Saisie manuelle (ex. « Exonérée AFC-GE depuis 2018 ») |
| IBAN | Numéro IBAN de l'association, utilisé pour générer le bulletin de versement QR joint aux rappels de cotisation (voir §16) |
| Description du montant (rappels de cotisation) | Texte affiché dans l'email de rappel et sur le bulletin QR (champ « Montant »), ex. « min. CHF 50.- / pers. · CHF 80.- / famille » — laissé vide, une valeur par défaut est utilisée |

### Utilisateurs (Admin)

Gestion des comptes de connexion :

- Liste des utilisateurs (identifiant, nom, e-mail, rôle, statut, dernière connexion).
- **Nouvel utilisateur** : identifiant, nom affiché, e-mail, **rôle** (une **matrice des
  droits** est consultable via l'icône **?** à côté du champ Rôle — voir §12), et mot de passe
  temporaire (ou lien d'invitation à envoyer).
- **Modifier**, **Réinitialiser le mot de passe** (l'utilisateur devra le changer à la
  prochaine connexion), **Supprimer**, activer / désactiver un compte.

### Journal (Admin)

Trace les actions effectuées (création, modification, suppression) avec date, utilisateur et
détail.

### Journal des emails (Réglages → Email, Admin)

Sous l'onglet **Email**, un journal liste tous les emails envoyés par l'application (récapitulatifs
comptables, rappels de cotisation, tests SMTP…) avec date, destinataire, template utilisé et
statut (envoyé / erreur).

### Intégrité (Admin)

Détecte et aide à corriger les incohérences : membres en double (même nom ou même e-mail, avec
bouton **Fusionner**), segments masqués encore assignés à une catégorie / un segment combiné /
des membres, montants ou dates comptables invalides, entrées sans type, e-mails ou e-mails alt.
mal formatés, genre hors valeurs, date de naissance dans le futur, membres sans nom ni société.
Un message « Tout est clean » s'affiche si rien n'est détecté.

#### Fusionner deux fiches

Depuis Intégrité, cliquer **Fusionner** sur un doublon ouvre l'écran de fusion :

- Pour chaque **champ divergent**, cliquer la valeur à conserver (A ou B).
- Pour la note, une case **Garder les deux notes** (survivant en premier) permet de tout
  conserver.
- Choisir le **profil survivant** (garde son ID) et le sort du **profil source** : *archiver*
  ou *supprimer*.
- Les entrées compta et suivi et les appartenances aux segments sont fusionnées
  automatiquement (dédoublonnées). Confirmer via la fenêtre de résumé.

---

## 13. Rôles utilisateurs et matrice des droits

L'application distingue quatre rôles : **Lecture seule**, **Utilisateur**, **Manager** et
**Admin**. La matrice ci-dessous est consultable directement à la création d'un compte (icône
**?** à côté du champ Rôle dans **Réglages ▸ Utilisateurs**).

| Droit | Lecture seule | Utilisateur | Manager | Admin |
|---|:---:|:---:|:---:|:---:|
| Consulter membres, compta, suivi | ✓ | ✓ | ✓ | ✓ |
| Créer / modifier membres, compta, suivi | – | ✓ | ✓ | ✓ |
| Importer des contacts (CSV/TSV) | – | – | ✓ | ✓ |
| Gérer segments, catégories, paramètres | – | – | ✓ | ✓ |
| Fusionner / archiver un membre | – | – | ✓ | ✓ |
| Supprimer / anonymiser un membre | – | – | – | ✓ |
| Gérer les comptes applicatifs | – | – | – | ✓ |

La gestion de l'appartenance d'un membre aux segments et l'accès aux Réglages sont donc
réservés aux Managers et Admins ; un rôle **Utilisateur** peut créer et modifier des membres et
saisir compta et suivi, mais pas gérer les segments.

---

## 14. Changer son mot de passe

1. Cliquer son **nom d'utilisateur** en haut à droite.
2. Choisir **Mot de passe**.
3. Saisir le mot de passe actuel, puis le nouveau mot de passe et sa confirmation.
4. Enregistrer.

Si un administrateur a réinitialisé votre mot de passe, l'application impose ce changement à la
connexion suivante.

---

## 15. Déconnexion

Cliquer son **nom d'utilisateur** en haut à droite, puis **Déconnexion**.

---

## 16. Récapitulatifs comptables par email

Accès : menu **Emails** (Manager / Admin). Envoie à chaque membre un email récapitulant ses
entrées comptables pas encore notifiées (cotisations, dons…), regroupées en une seule fois
plutôt qu'une notification par entrée.

1. Choisir l'**année** à traiter.
2. La liste affiche les membres avec entrées en attente, séparés entre ceux **avec email**
   (envoi possible) et **sans email** (repliable, non envoyables).
3. Cliquer une ligne ouvre un **aperçu** de l'email tel qu'il sera reçu (rendu HTML réel du
   template configuré dans Réglages → Email).
4. **Envoyer les récapitulatifs** (bouton en haut) envoie à tous les membres avec email en une
   fois, ou **Envoyer** dans la modale d'aperçu pour un envoi individuel.
5. **Mode étendu** (case à cocher) affiche aussi les membres déjà notifiés cette année, avec
   possibilité de renvoyer (forçage).

Une fois envoyée, une entrée n'est plus reprise dans le lot suivant. Si l'entrée a une
**année de cotisation** différente de l'année de paiement (ex. cotisation 2027 payée en
décembre 2026), l'email le précise explicitement.

---

## 17. Rappels de cotisation impayée

Accès : vue **Membres perdus** (menu principal ou Réglages → sections liées aux membres).
Liste les membres ayant cotisé l'année précédente mais pas encore l'année en cours.

- **Envoyer un rappel** sur une ligne individuelle, ou en masse pour toute la liste. L'envoi
  individuel (et le renvoi) affiche d'abord un **aperçu de l'email** (sujet + rendu tel qu'il
  sera reçu) avant confirmation.
- Un membre déjà relancé cette année n'est pas resollicité automatiquement (anti-doublon) —
  le statut « Rappel envoyé le [date] » apparaît sur sa ligne. Un bouton **Renvoyer** reste
  disponible sur ces lignes pour forcer un second envoi (l'aperçu rappelle la date du premier
  envoi).
- Le contenu de l'email est celui configuré dans Réglages → Email → Templates
  (`tpl_cotisation_reminder`), avec en pièce jointe un **bulletin de versement QR** suisse
  (généré automatiquement à partir de l'IBAN de l'organisation, voir §11 — Réglages
  généraux) mentionnant la description de montant configurée et « Cotisation AAAA » comme
  message.
- **Copie (BCC)** : si un email de contact est configuré dans Réglages → Email, une case
  **Envoyer une copie à [adresse]** propose d'en recevoir une copie silencieuse, à l'envoi
  individuel comme en masse.
- La vue propose aussi un bouton **Créer segment « Membres à relancer AAAA »** pour extraire
  la liste dans un nouveau segment (même principe que pour les donateurs perdus, voir §8).
