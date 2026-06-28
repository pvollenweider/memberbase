# Changelog

Tous les changements notables de ce projet sont documentés dans ce fichier.

## [3.5.0] — 2026-06-28

### Highlights

Premier release public sous le nom **MemberBase** — l'application est désormais générique, open source, et installable sans intervention manuelle.

### Nouveautés

- **Installeur web** (`install.php`) — wizard 5 étapes : prérequis, connexion DB, schéma, paramètres organisation, compte admin. Crée automatiquement les groupes "Membre {N-1}" et "Membre {N}" dans une catégorie "Membres"
- **Redirect automatique** vers `install.php` si la DB est inaccessible ou le schéma absent
- **Ajout au groupe depuis addUser** — checkbox "Ajouter au groupe «…»" pré-cochée quand on navigue depuis une vue de groupe
- **Mobile nav** — icônes de navigation (liste, compta, suivi, résumé) à gauche ; recherche, réglages, user à droite

### Rebrand & généralisation

- Renommé **MemberBase** (anciennement Casa Members)
- Suppression de toutes les références à l'instance d'origine dans le code, les templates et la documentation
- Config Apache spécifique à l'instance supprimée du repo
- `label.pl` supprimé

### Documentation & communauté

- README entièrement revu : guide d'installation, structure, badges
- Section "Histoire" — origine du projet
- `CONTRIBUTING.md`, templates GitHub (bug report, feature request, PR)
- Repo renommé `pvollenweider/memberbase` et passé en public

### Corrections

- `value='1'` → `value='true'` dans `user_properties` pour cohérence avec le reste de l'application
- Crash sur fresh install quand `default_team = 0` résolu

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [versionnement sémantique](https://semver.org/lang/fr/).

## [Non publié]

---

## [3.2.0] — 2026-06-28

Release majeure combinant un refactoring architectural complet, la généralisation de l'application pour toute association, une internationalisation complète, des améliorations UX et un nettoyage massif des dépendances (−237 000 lignes, dont la suppression de CKEditor).

### Refactoring architectural
- **Renommage .inc → .php** : tous les fichiers includes renommés en `.php` avec protection HTTP via `.htaccess`
- **Généralisation** : suppression de tout contenu hardcodé spécifique à Casa Alianza — l'application est désormais réutilisable pour toute association
- **AGPL-3.0** : ajout de la licence open-source
- **Paramètres d'organisation** : table `app_settings` pour stocker le nom de l'org, utilisé dynamiquement dans le titre de page
- **DataTables** : extraction des defaults dans `js/dt_defaults.js`, unification des tables donateurs (`_donor_table.php`) — DRY sur toutes les vues lapsed/resume

### Internationalisation (i18n)
- Centralisation de toutes les chaînes UI françaises hardcodées dans le fichier locale (`locales/resources_fr.php`)
- Couvre : labels de formulaires, messages toast, navigation, confirmations, messages d'erreur — tout passe par `$GLOBAL[...]`

### Ajouté
- **Donateurs à relancer** (`?view=lapsedDonors`) : lignes de tableau cliquables vers la compta complète du donateur
- **Membres à relancer** (`?view=lapsedMembers`) : même pattern de navigation
- **Modaux de confirmation** : remplacement de tous les `confirm()` JS natifs par des modaux Bootstrap accessibles (création de groupes, suppression journal, types compta, utilisateurs app, groupes, métagroupes)
- **Sidebar settings** : navigation contextuelle avec mode drill-down pour `updateTeam` / `updateMetagroup`

### UX & Mobile
- **Barre de recherche mobile** : icône loupe + barre expansible (remplace le hamburger)
- **iOS tap fixes** : `data-href` + délégation JS sur tbody pour les lignes cliquables (resume, compta, suivi)
- **Redirection après création de groupe** : `addTeam` / `addTeamWithImport` redirigent vers `updateTeam`
- **Garde `updateTeam`** : redirection si `id` manquant ou invalide

### Modifié
- **Font Awesome 5 → 6.7.2** : mise à jour de tous les noms d'icônes dans l'ensemble des fichiers PHP + auto-hébergement (`css/vendor/font-awesome.min.css` + `css/webfonts/`)
- **jQuery 3.3.1 → 3.7.1**
- **JS vendors** : tous les scripts tiers déplacés dans `js/vendor/`
- **Navbar** : brand/home redondant supprimé

### Corrigé
- **Backdrop Bootstrap + htmx** : nettoyage automatique du `.modal-backdrop` et des classes `modal-open` après chaque swap htmx
- **`hx-boost="false"`** sur tous les formulaires déclenchés depuis un modal
- **Parse errors** : backslashes parasites dans `resume.php` et `compta_generic.php` (`\$GLOBAL` → `$GLOBAL`)
- **FA6 webfonts** : chemin corrigé vers `css/webfonts/`

### Supprimé
- **CKEditor** : 554 fichiers / 6,1 Mo supprimés (remplacé par TipTap)
- **`conf/htpasswd`** : fichier sensible purgé de tout l'historique git
- **Fichiers morts** : `manage_teams.php`, `php7-mysql-shim.php`, `datahref.jquery.js`, `buttons.bootstrap4.min.js`, `moment-with-locales.min.js`, `popper.min.js`, `jquery_ckeditor.js`, `tools/normalize_comments.php`
- **`plugins/`** : dossiers `bootstrap/`, `font-awesome/`, `ckeditor/` supprimés

---

## [3.1.1] — 2026-06-27

### Ajouté
- **Mobile — navigation principale** : barre d'icônes cliquables en lieu et place du bouton hamburger
- **Mobile — onglets profil** : boutons toujours visibles (Fiche, Compta, Suivi, Historique) sans menu à déplier
- **Mobile — vues liste et fiche** : colonnes masquées sur petits écrans, clic sur ligne entière corrigé sur iOS
- **Resume** : icône don institutionnel (`fa-building`) dans la colonne Statut
- **Resume** : filtres "12 derniers mois" et "24 derniers mois" dans la dropdown années
- **Resume** : bouton ColVis (afficher/masquer colonnes) dans la barre DataTable
- **lastEntryCompta** : colonne Type affichée en badge coloré (avant Libellé)
- **lastEntryCompta** : dropdown filtre par type avec badges colorés
- **lastEntryCompta** : filtres "12 derniers mois" et "24 derniers mois" dans la dropdown années
- **Listing membres** : pastilles types compta cliquables → vue compta filtrée par type
- **Filtre -4** : exclure le groupe `member_no_coti_team` du filtre "cotisation non payée"

### Modifié
- **lastEntryCompta** : lignes sans colorisation de fond — couleur portée uniquement par le badge de type
- **Resume** : colonnes Sexe, Adresse, NPA masquées par défaut
- **Resume** : filtres 12/24 derniers mois avec séparateur avant la liste des années calendaires

### Corrigé
- **Mobile** : formulaire `updateCompta` — colonnes label/champ empilées correctement sur xs
- **Mobile** : clic sur lignes de tableau (iOS) — `data-href` + délégation JS sur compta, suivi, historique
- **Mobile** : cards statistiques du résumé en `flex-wrap` sur xs (don principal pleine largeur)
- **Mobile** : légende du pie chart visible sur mobile
- **Mobile** : contrôles attestations masqués sur xs
- **Resume** : légende du pie chart se triplait à chaque navigation htmx — `innerHTML = ''` avant re-render
- **Resume** : icône membre actif remplacée par `fa-id-card`
- **Listing** : rechargement `pageshow` (bfcache) pour éviter mismatch colonnes DataTables
- **Listing** : `z-index:2` sur les badges pour passer au-dessus du stretched-link

---

## [3.1.0] — 2026-06-27

### Ajouté
- **Archivage membres** : colonne `users.status` (1=actif, 0=inactif) ; vue "Membres masqués" ; actions Réactiver / Anonymiser / Supprimer selon contexte
- **Fusion de profils** (`?view=mergeUsers`) : sélection champ par champ, transfert compta + suivi + groupes, suppression du doublon (Alpine.js)
- **Filtre -6666** : donateurs non institutionnels actifs en année N-1 (`is_institutional=0`)
- **Filtre -5555** : "Aucun versement ces 10 dernières années" — colonne "Historique compta" dans le listing (N+1 → 1 requête)
- **Filtre -3333** refactorisé : membre ayant payé une cotisation mais pas depuis 3 ans, configurable via `member_no_coti_team`
- **Paramètre `member_no_coti_team`** : groupe exclu du filtre -3333 (bénévoles, comité), configurable dans les réglages
- **Mini-dashboard profil** : dons cette année / année précédente / total depuis YYYY ; bloc "Autres versements" séparé ; ligne "Ensemble des versements"
- **Toggle "Dons uniquement"** dans la vue compta d'un profil ; indicateur "non-don" par ligne
- **Pastilles** discrètes sur les onglets Compta et Suivi (count d'entrées)
- **Historique par membre** (`?view=userHistory`) : journal de toutes les actions pour un profil donné
- **Intégrité** : détection de doublons potentiels par nom et par email
- **Migration AUTO_INCREMENT** : `team`, `users`, `compta` — suppression de la table `maxval` pour ces entités
- **`compta_type.is_institutional`** : colonne pour distinguer dons institutionnels
- **`audit_log`** : colonne `subject_user_id` + paramètre optionnel dans `auditLog()`

### Modifié
- `auditLog()` : paramètre optionnel `$subjectUserId` — toutes les actions membre transmettent l'ID concerné
- Label -5555 : "Aucun versement ces 10 dernières années" (était "Aucun don")
- Label -6666 : "Donateur non institutionnel actif en YYYY" (dynamique)
- Descriptions explicatives sous la barre de filtres rapides pour tous les filtres spéciaux
- DataTable listing : "_TOTAL_ profils" (était "_TOTAL_ membres")
- Exceptions hardcodées supprimées des filtres -5555 et -3333
- `startswith()` supprimée de `declarations.inc` (inutilisée)
- Suppression des filtres morts -2 / -5 / -444 / -6 et de `tools/compta.php`

### Corrigé
- Alerte "modifications non sauvegardées" déclenchée par le toggle "Dons uniquement" (`data-no-dirty`)
- Filtres du journal d'activité : dropdowns utilisateur + action avec export CSV/Excel/Impression
- Sections Intégrité collapsées par défaut
- Box "Dons" affichait les entrées "Excl. don" dans le comptage
- Stretched-link non cliquable sur iOS dans les DataTables — gestionnaire JS touch ajouté

---

## [3.0.1] — 2026-06-04

### Ajouté
- **Renommage rapide** des groupes depuis l'onglet Groupes : crayon inline, sauvegarde sans rechargement de page
- **Bouton Annuler** dans le toast de modification d'appartenance métagroupe/catégorie (fenêtre de 4 s)
- **Import groupé par catégorie** dans le formulaire d'ajout de groupe
- **Badge** nombre de membres par groupe dans le dropdown de liste
- Groupes cachés avec membres actifs visibles dans l'outil Intégrité
- Ouverture de la vue compta avec toutes les années depuis le résumé
- Vue compta par défaut : toutes les années (au lieu de l'année courante)
- Pourcentage dans le tooltip du donut chart compta

### Modifié
- Page Réglages (`?view=settings`) : navigation horizontale → barre latérale verticale (desktop) / sélecteur (mobile), avec séparateur Administration
- Onglet Groupes : deux boutons par ligne — crayon (renommer) et engrenage (réglages complets)
- `?view=manageTeam` décommissionné, redirige vers les onglets réglages

### Corrigé
- Renommage de groupe : warning PHP `Undefined variable $oldName` polluait la réponse JSON
- Fuites mémoire : événements `datahref` namespacing, `DataTable.destroy()` avant ré-initialisation
- Double en-tête DataTable dans la liste membres (`<tbody>` manquant)
- Bouton retour renommé "Retour à l'aperçu des dons" sur les vues donateur/membre

---

## [3.0.0] — 2026-05-15

### Ajouté
- **htmx 2 + Alpine.js** : navigation SPA sans rechargement de page complet
- **Journal d'audit** : enregistrement de toutes les actions (ajout, modification, suppression) avec auteur et date
- Filtres utilisateur + action dans le journal ; export CSV/Excel/Impression
- **Multi-entrées Suivi** : plusieurs entrées de suivi par membre
- **Outil Intégrité** : sections collapsibles, signalement de problèmes de données
- **Lien d'invitation** par token pour la création de nouveaux utilisateurs
- Générateur de mot de passe aléatoire sur le formulaire de création utilisateur
- Environnement Docker (PHP 8.2 + MariaDB 11 + Adminer) + Makefile

### Modifié
- Mise à jour DataTables 1.13.7 → 2.2.2 + Buttons 3.1.2
- `strftime()` remplacé par tableau de noms de mois (déprécié PHP 8.2)
- Actions de gestion découpées en 7 fichiers handlers (`manage_actions/`)
- Navigation : Activité → Rapports, Compta → Journal compta, Suivi → Journal suivi

### Corrigé
- Suppression de log4php (dépendance obsolète)
- Clé de session `app_user_username` corrigée dans le journal d'audit
- `scrollIntoViewOnBoost` désactivé (htmx scroll intempestif)
- Formulaires : ne pas avertir sur POST intentionnel (dirty-check)

---

## [2.2.2] — 2026-01-20

### Ajouté
- Avertissement "modifications non sauvegardées" avant navigation hors du formulaire
- Lien vers liste filtrée depuis la page d'édition de groupe
- Badge nombre de membres inline après le nom du groupe
- Guides utilisateur et administrateur dans `doc/`

### Corrigé
- Déduplification du select catégorie (`GROUP BY id` sur la requête métagroupe)

---

## [2.2.1] — 2026-01-10

### Ajouté
- Onglets "Catégories" et "Filtres de groupes" séparés dans la gestion des équipes

### Modifié
- Onglet "Organisation" renommé "Catégories" dans `manageTeam`

### Supprimé
- Vues mortes : `resume2/3/4`, `dna`, `non-coti`, `manage_metagroups`, `lastEntryCompta2`
- `quittancedon.php` décommissionné — tous les liens de téléchargement quittance supprimés
- `xls.php` (export Excel non utilisé)
- `label.php` (générateur étiquettes Word non utilisé)

### Corrigé
- Création métagroupe : nom vide si `lookupMetagroup` ne filtrait pas `name IS NOT NULL`
- Validation des entrées : `comptaid` casté en int, `groupType` en liste blanche, `teamId` en int
- Séparateur de milliers sur les montants individuels dans `lastEntryCompta`
- `wants_attestation` en lecture seule dans la liste compta (n'était plus modifiable par toggle)
- `ob_start()` pour autoriser `header()` depuis `manage_actions.inc`
- Cookie de session sécurisé (flag `secure`)

### Sécurité
- Suppression des endpoints non authentifiés
- Correction XSS dans plusieurs vues
- Sanitisation du nom de fichier dans `Content-Disposition`

---

## [2.2.0] — 2025-12-15

### Ajouté
- **Authentification PHP** (sessions bcrypt) en remplacement du `.htaccess` / htpasswd

### Supprimé
- Authentification `.htaccess`

---

## [2.1.0] — 2025-11-20

### Ajouté
- **Dashboard KPIs** : indicateurs clés sur la page résumé
- Vues donateurs et contributions
- **Ordre par glisser-déposer** des types compta
- Déduplification des appartenances aux groupes
- Index base de données pour les requêtes fréquentes
- `wants_attestation` : case à cocher dans le résumé (cochée par défaut)
- Filtre `minCHF` dans la barre d'outils du résumé
- Types exclus listés explicitement dans la bannière mode "Tout afficher"
- Assets (JS/CSS) auto-hébergés (plus de CDN extérieur)

### Modifié
- Menu renommé : "Gros donateurs" → "Contributions"
- `membre_team` (réglage) remplace les références hardcodées à `team_141`

### Corrigé
- Anneau focus orange natif sur les cases à cocher d'attestation supprimé
- Colonne "actif" dans le résumé utilise `default_team` (pas `membre_team`)
- Classes `.hide` / `.hidden` définies en CSS ; `visually-hidden` pour DataTables

---

## [2.0.0] — 2025-10-01

Version initiale publique documentée.
