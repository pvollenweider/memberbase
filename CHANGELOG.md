# Changelog

Tous les changements notables de ce projet sont documentés dans ce fichier.

## [5.3.2] — 2026-07-20

Corrections, sans changement de schéma.

### Nouveautés

- **Rollover annuel du segment membre** : au 1er janvier (ou à la première
  exécution du cron qui suit), le segment « Membre AAAA » de l'année en cours
  est créé s'il n'existe pas, mis en liste par défaut, et pré-rempli avec les
  membres ayant déjà réglé leur cotisation en avance. « Membre AAAA-1 » devient
  la référence de cotisation en cours. La même logique se déclenche aussi à
  chaque paiement de cotisation (pas seulement en janvier) : payer une
  cotisation AAAA crée « Membre AAAA » au besoin et y ajoute le payeur.
- **Catégorisation automatique** : les segments « Membre AAAA » créés par le
  rollover ou par un paiement de cotisation sont désormais classés dans une
  catégorie portant le nom du préfixe (Réglages → « Préfixe des segments
  membres », par défaut « Membre »), créée au besoin.
- **Intégrité — cotisants désynchronisés** : nouveau contrôle qui repère les
  membres ayant payé une cotisation pour une année sans être dans le segment
  « Membre <année> » correspondant (import/rattrapage antérieur au hook, ou
  retrait manuel depuis) — limité aux 3 dernières années, avec bouton de
  correction en un clic.

### Corrections

- **Ajout de membre depuis un segment** : la case « Ajouter au segment «X» » du
  formulaire d'ajout de membre n'ajoutait en réalité jamais le membre au segment —
  l'écriture visait l'ancienne table `contact_properties` (EAV) au lieu de la table
  de jointure `contact_segment` utilisée partout ailleurs. Trouvé en écrivant le
  test e2e correspondant.
- **Intégrité — bouton « Appliquer » (règles d'auto-assignation)** : le
  formulaire de correction repostait `tab=groups` au lieu de `tab=integrity`,
  ce qui faisait atterrir l'admin sur l'onglet Groupes après le clic — la
  correction avait pourtant bien eu lieu, mais semblait « ne rien faire ».

### Interface

- **Cartes manquantes** : les pages `?view=addUser`, `?view=mergeUsers` et les
  formulaires d'édition compta/suivi/tâche (hors mode intégré en modale)
  n'étaient pas habillés de la carte standard (`card shadow-sm border-0`) —
  harmonisé avec le reste de l'application.
- **Intégrité — « Membres sans nom de famille ni société »** : ajout de la
  colonne email au tableau, pour identifier le contact sans avoir à ouvrir
  sa fiche.

### Outils

- **`make deploy`** (`tools/deploy.sh`) : synchronise `html/` vers le serveur
  de production par rsync, avec aperçu (dry-run) et confirmation avant tout
  envoi réel.

### Tests

- Comblé les trous de couverture e2e identifiés par un audit doc-vs-tests :
  téléchargement d'attestation restreint Manager+, rejet CSRF journalisé, portée
  du garde CSRF, gestion des mots de passe des comptes applicatifs, génération
  de tâches « attestations », suppression en masse des tâches terminées,
  rate-limit API, assistant d'import CSV (3 étapes), suppression/anonymisation
  en masse des membres archivés, auto-édition du profil et changement de langue,
  bulletin QR des rappels de cotisation, renvoi forcé, création de segment
  depuis les mouvements donateurs/membres, mode étendu des récapitulatifs,
  imports ponctuels sur la page segment, CRUD des catégories de segments,
  champs Réglages généraux, filtre « Dons uniquement », et présence/charset
  des bundles JS/CSS.

## [5.3.1] — 2026-07-17

Corrections de sécurité et de performance, sans changement de schéma.

### Sécurité

- **CSRF** : le jeton est désormais vérifié sur **toutes** les méthodes HTTP pour chaque
  action, et plus seulement en POST. Les handlers lisant leurs paramètres dans `$_REQUEST`,
  une requête GET forgée (image, lien) pouvait déclencher une mutation (suppression de
  segment, d'écriture comptable, suppression en masse…) sans jeton. Tous les déclencheurs
  légitimes portent déjà le jeton (formulaires POST, liens boostés htmx, `fetch`).
- **Attestations** : `attestation_don.php` et `attestation_bulk.php` sont restreints aux
  managers/admins. Un compte en lecture seule pouvait télécharger les données de dons
  nominatives de n'importe quel membre.
- **CSRF (précision)** : la vérification est scopée aux actions réellement mappées dans
  `$ACTION_MAP` — une valeur `action=` non mappée (ex. `action=search`, utilisée comme simple
  indicateur de vue) n'est plus gardée, pour ne pas bloquer des chargements de page en GET
  simple qui ne portent jamais de jeton.

### Performance

- **Édition de segment** : le panneau de comptage des imports par année, qui exécutait
  ~40 requêtes agrégées (10 ans × 4), est réduit à 4 requêtes groupées par année.
  Résultats identiques, vérifiés sur la base réelle.

### Mobile

- **Menu latéral** : le bouton replier/déplier utilisait un `addEventListener` direct, tué à
  chaque re-rendu OOB de la barre du haut par htmx — remplacé par une délégation d'événement
  au niveau du document, qui survit aux swaps.
- Ajout d'un fond semi-transparent (`.ca-sidebar-overlay`) derrière le menu latéral ouvert sur
  mobile ; cliquer dessus referme le menu.
- L'état replié/déplié du menu est désormais persisté en session PHP (action `sidebarState`)
  plutôt qu'en `localStorage` : la préférence desktop survit aux rechargements sans déteindre
  sur mobile (qui repart toujours fermé).
- Compaction de l'interface sous 768px : bandeau d'en-tête plus court, marges de conteneur et
  de carte réduites, tableaux forcés en mode responsive.

## [5.3.0] — 2026-07-17

Refonte graphique de la navigation — aucune rupture de compatibilité, 2 migrations (`0038`,
`0039`, en plus des `0032`-`0037` déjà présentes en 5.2.0).

### Nouveautés

- **Refonte de la navigation** : la barre horizontale (navbar + dropdowns) est remplacée par
  un menu latéral fixe et une barre du haut minimale (bouton replier/déplier, nom de
  l'organisation, recherche). Le groupe **Administration** est aplati par rôle plutôt que par
  thème technique — sous-groupes **Segments** (Managers) et **Application** (Admins). La barre
  d'onglets de « Membres & finances » (ajoutée en 5.2.0) devient mobile uniquement, redondante
  avec le menu latéral sur desktop.
- **Recherche instantanée** : le champ de recherche de la barre du haut affiche les résultats
  dès 3 caractères saisis, directement dans l'onglet Membres du hub, sans validation.
- **Tâches** — cycle de finition : état **« en pause »**, distinct d'ouvert/terminé ; trois
  nouvelles règles de génération automatique (doublons de fiches, segments masqués encore
  peuplés, attestations de dons à renvoyer) ; marquage terminé et suppression en masse ; le
  cron s'exécute réellement dans le conteneur de développement, digest email quotidien
  limité à un envoi par jour.
- **Type de contact** : le type de compta par défaut peut désormais être dérivé du type de
  contact du membre ; le type de contact est inclus dans l'écran de fusion de doublons.
- **Page Archivés** : chaque membre archivé affiche son éligibilité à la suppression
  définitive (aucune entrée comptable) ou seulement à l'anonymisation ; suppression et
  anonymisation en masse (sélection multiple), avec confirmation récapitulative et
  avertissement si la sélection mélange des profils éligibles et non éligibles.
- **Réglages** : les segments masqués sont séparés dans une carte repliée distincte ; la
  composition des segments combinés (segments qui les composent) est affichée dans leur
  liste ; le formulaire général est scindé en cartes thématiques (Organisation, Finances,
  Adhésion, Segments) ; les contrôles d'intégrité sont repliés par défaut et chargent leur
  détail au clic, au lieu de tous se recalculer au chargement de la page.
- **Mon compte** (menu du pied de la barre latérale) : nom affiché et e-mail modifiables au
  même endroit que le mot de passe et la langue.
- Raccourcis clavier <kbd>Option/Cmd</kbd>+<kbd>1</kbd>/<kbd>2</kbd>/<kbd>3</kbd> pour
  naviguer entre Membres & finances, Journaux et le tableau de bord ; focus automatique sur
  la recherche du tableau de bord et sur le montant du formulaire compta.

### Améliorations

- La fiche membre affiche désormais nom/prénom et le type de contact (badge avec icône) en
  mode lecture — auparavant visibles uniquement en mode édition.
- Icône du type de contact dans les résultats de la recherche rapide du tableau de bord.
- Contraste corrigé du commutateur Actif/Archivé sur la fiche membre (piste bleue sur fond
  bleu, illisible).
- Les 25+ fichiers JS/CSS vendor chargés séparément sont regroupés en 3 bundles committés
  (`npm run dist`), même logique que `html/vendor/` pour les dépendances PHP — un unique
  fichier CSS et deux fichiers JS remplacent la longue liste de `<link>`/`<script>`.

### Technique

- `build/dist.mjs` (esbuild) : concaténation + minification des bundles JS/CSS vendor,
  documentée dans `CLAUDE.md`. Le CSS n'est volontairement pas minifié (parseur plus strict
  que les navigateurs, risque sur du CSS legacy) — seule la concaténation compte pour réduire
  le nombre de requêtes.
- Refonte du routage htmx : le rendu out-of-band (OOB) de la barre latérale/barre du haut sur
  chaque requête boostée est désormais conditionné à l'en-tête `HX-Target: main-content`, pour
  ne plus fuiter dans les fragments chargés par les nombreux modaux de l'application (compta,
  suivi, emails, tâches) qui usurpent seulement `HX-Request` sans cibler `#main-content`.

### Corrections

- **Bug de longue date** : `bootstrap-datetimepicker.min.css` avait son tout premier octet
  manquant (commentaire non fermé) — invisible tant que chargé comme feuille de style isolée
  (récupération d'erreur CSS indépendante par fichier), mais qui avalait le CSS suivant une
  fois concaténé dans le nouveau bundle. Découvert et corrigé pendant ce cycle.
- Régression #165 : la page Réglages plantait (erreur 500) si la migration `0038` n'était pas
  encore appliquée.
- Régression critique : erreur 500 sur toute page si la migration `0039` (tâches en pause)
  n'était pas encore appliquée.
- L'écriture compta ajoutée ou éditée ne filtre plus la liste par son propre type ensuite.
- Position et contraste du jour sélectionné dans le sélecteur de date.
- 14 tests e2e pré-existants (sans rapport avec ce cycle) corrigés : gap réel dans la fiche
  membre (nom/type de contact absents du mode lecture), tests jamais mis à jour après des
  changements d'UX déjà en production (modals compta/suivi, lazy-load Intégrité), données de
  test datées devenues obsolètes.

## [5.2.0] — 2026-07-13

Cycle majoritairement orienté UI/navigation — aucune rupture de compatibilité, 6 migrations
(`0032`-`0037`, en plus de la `0031` déjà présente en 5.1.0).

### Nouveautés

- **Tableau de bord d'accueil** (`?view=dashboard`, nouvelle vue par défaut après connexion) :
  - Cartes KPI (Contributions, Donateurs, Membres) avec delta vs année précédente et vs même
    période l'an dernier (YTD), objectif restant à atteindre pour égaler l'année précédente.
  - Graphique superposé de l'évolution cumulée des recettes (année en cours vs année
    précédente, mois par mois).
  - Camembert de répartition des dons par type de compta.
  - Recherche rapide d'un membre → fiche Compta.
  - Liste des raccourcis contextuels (n'apparaissent que si pertinents) : cotisations non
    payées à relancer, notifications de versement en attente, attestations à envoyer
    (janvier), nouveaux/perdus donateurs et membres, membres de l'année passée, membres
    actuels, migrations en attente (admin).
  - Liste des dernières écritures comptables et des derniers contacts créés.
- **Hub « Membres & finances »** (`?view=peopleFinance`), remplace les anciens liens de menu
  Listes/Relances/Aperçu des dons :
  - Onglet **Segments** (ex-Liste) : filtre par segment, segment combiné, type de contact,
    ou filtres rapides (aucune cotisation depuis 3 ans, aucune activité depuis 10 ans, etc.).
  - Onglet **Notification de versement** (managers) : membres avec écritures comptables non
    notifiées, aperçu et envoi de l'email avant confirmation.
  - Onglet **Attestation** (ex-Dons & attestations) : résumé annuel des dons, envoi
    individuel ou en masse des attestations fiscales.
  - Onglets **Mouvements membres** et **Mouvements donateurs** : deux pastilles « Perdus » /
    « Nouveaux » par onglet, remplaçant les anciennes pages séparées Cotisations non
    renouvelées / Donateurs perdus / Nouveaux donateurs / Nouveaux membres. Visibles pour
    tous les rôles (lecture seule) ; création de segment et envoi de rappels restent
    réservés aux managers.
- **Hub « Journaux »** (`?view=journals`), remplace les anciens liens Compta/Suivi : onglets
  Compta et Suivi fusionnés en une seule destination de navigation. Le filtre de type/année
  du journal Compta n'affiche désormais que les options réellement présentes dans les
  données (facettes), au lieu de la liste complète fixe.
- **Type de contact** (donateur privé / institution / établissement financier / entreprise),
  nouvelle table `contact_type` (migration `0035`) :
  - Visible et modifiable sur la fiche membre.
  - Réglages → Types de contact : ajout de types personnalisés (icône Font Awesome, code
    interne éditable pour les types personnalisés — figé pour les 4 types intégrés),
    suppression si inutilisé.
  - Matrice type de contact × type de compta (Réglages → Types de contact) : restreint les
    types de compta proposés à la création d'une écriture selon le type de contact
    (migration `0036`).
  - Archivage des types de compta (masqués à la création de nouvelles écritures, historique
    conservé) (migration `0036`).
  - Icône Font Awesome par type de contact, affichée en badge sur la fiche membre et dans
    les listes de donateurs/membres (migration `0037`).
- **Outil admin « Forcer le type de contact d'un segment »** (Réglages → Santé) : action
  ponctuelle, hors navigation standard, pour appliquer en masse un type de contact à tous
  les membres actifs d'un segment donné.
- **Gestion de tâches** (`suivi_task`, issue #117) : titre, priorité (haute/normale/basse),
  échéance, ouverte/fermée — génération automatique de tâches de relance de cotisation
  impayée (#149), envoi de rappel et fermeture automatique depuis la tâche, tâches
  planifiées par cron + digest quotidien par email (#150).
- **Règles d'auto-assignation de segment** (#154) : « quand un membre est assigné au segment
  X, l'assigner aussi au segment Y » (usage unique, pas un moteur de règles générique) —
  contrôle d'intégrité dédié pour détecter les incohérences (#156).
- Colonnes affichées/masquées (DataTables colvis) sur les listes de membres/donateurs ;
  adresse et NPA masquées par défaut, société/nom/prénom/email visibles par défaut.

### Améliorations

- Icône du type de contact affichée dans les listes « Nouveaux donateurs » et assimilées.
- Libellé « Contacts non institutionnel ayant effectué au moins un versement %s » (filtre
  rapide) simplifié.
- Suppression du préfixe « CHF » devant les montants de la carte « Dernières écritures » du
  tableau de bord.
- Lignes sans adresse email cliquables (→ fiche Compta) dans l'onglet Notification de
  versement.
- Note d'attestation de dons : la mention « pour un total annuel de dons de CHF 300 ou
  plus » est omise quand le seuil est déjà atteint (redondant), conservée en dessous.
- Les liens « Derniers contacts créés » du tableau de bord pointent vers la vue Données
  plutôt que Compta.
- Suppression de la section « Suggestion de classement » (Réglages → Types de contact) —
  outil de suggestion automatique retiré (UI, action, fonctions internes, clés de locale).

### Corrections

- **Migration `0031`** (`compta.quittance` → `compta.comment`) utilisait `RENAME COLUMN …
  TO …`, syntaxe incompatible avec MariaDB < 10.5.2 — remplacée par `CHANGE COLUMN`
  (portable), cause du blocage de migration en production.
- Réglages → Santé plantait (erreur 500) tant que la migration `0035` n'était pas appliquée,
  à cause du nouvel outil « Forcer le type de contact d'un segment » qui interrogeait la
  table `contact_type` sans garde — masqué tant que la migration est en attente.
- Le bouton du filtre rapide par segment n'affichait pas le libellé du type de contact
  sélectionné (`?contactTypeId=`), retombait sur « Liste ».
- Les liens de segments/filtres rapides de l'onglet Segments (hub Membres & finances)
  sortaient du hub vers la page `?view=list` autonome au lieu de rester dans
  `?view=peopleFinance&tab=members`.
- Les onglets Mouvements membres/donateurs étaient masqués aux rôles non-manager alors
  qu'ils auraient dû rester visibles en lecture seule (les actions de création de segment
  et d'envoi de rappels, elles, étaient déjà protégées côté serveur — la garde manquait
  seulement côté affichage du bouton).
- Liens du tableau de bord « Notification de versement » et « Membres de l'année passée »
  ne pointaient pas vers le bon onglet/la bonne vue du hub.
- Filtre par type de contact (`?contactTypeId=`) intersecté silencieusement avec le segment
  par défaut de l'organisation (les institutions/entreprises n'y appartenant généralement
  pas, la liste apparaissait vide) — le filtre type de contact est désormais indépendant du
  segment par défaut.
- DataTables : conflit de réinitialisation quand deux tableaux « Perdus »/« Nouveaux »
  coexistent dans le DOM (pastilles) — chaque instance a désormais un identifiant unique.
- Grammaire du bandeau de migrations en attente (« Appliquez-las » → « Appliquez-les »).

## [5.1.0] — 2026-07-11

### ⚠️ Changements majeurs (breaking)

- **`metagroup` renommé en `combined_segment`** (table, `metagroup_member` → `combined_segment_member`, classe PHP `Metagroup` → `CombinedSegment` dans `combined_segment_class.php`). Toute intégration externe référençant l'ancien nom de table/classe doit être adaptée (migration `0024`).
- **Vocabulaire "Groupe"/"team" éliminé du code et de la base** au profit de "segment", partout où ça restait :
  - `app_settings` : `default_team`/`membre_team`/`member_no_coti_team`/`membre_team_prefix` → `default_segment`/`membre_segment`/`member_no_coti_segment`/`membre_segment_prefix` (migration `0025`).
  - Préfixe `contact_properties.parameter` `team_<id>` → `segment_<id>` (marqueur d'import, migration `0025`).
  - Paramètre de requête générique **`?team=` → `?segment=`** partout (vue liste, `/api/contacts`, formulaires de recherche, liens de navigation) — tout lien/favori/intégration externe utilisant `?team=` doit être mis à jour.
  - Paramètre **`?metagroup=` → `?combinedSegment=`** sur `/api/contacts` et les vues.
  - Clés de locale génériques renommées (`addGroups`→`addSegments`, `editGroup`→`editSegment`, etc.) dans les 4 langues (fr/en/es/de).
- **5 colonnes timestamp Unix `int(16)` migrées vers `DATE`/`DATETIME` natifs** (issue #143, migrations `0026`-`0030`) : `contact.modificationDate`, `contact.creationDate` (DATETIME), `contact.birthday` (DATE), `contact_properties.date`, `compta.date` (DATETIME). `0` n'est plus la sentinelle "non renseigné" — c'est désormais `NULL`. Toute requête SQL directe (debug, exports, scripts) comparant ces colonnes à un entier doit être adaptée ; `FROM_UNIXTIME()`/`UNIX_TIMESTAMP()` ne sont plus nécessaires pour les lire.
- **Contraintes de clé étrangère réelles** sur `contact_segment`, `contact_properties`, `compta`, `combined_segment_member`, `audit_log`, `email_log` (au lieu de `foreign_key_checks=0`) — une suppression en cascade ou une valeur orpheline qui passait silencieusement avant peut désormais être rejetée par la base (migration `0023`).

### Nouveautés

- **Marquage en masse des récapitulatifs comptables** (Réglages → Santé) : choix explicite d'une date de référence (au lieu de "maintenant" implicite) affichée aux membres comme point de départ dans l'email suivant.
- **Bandeau d'alerte** si l'application tourne sur `localhost` avec un SMTP réel configuré (pas Mailpit) — évite un envoi accidentel à de vrais membres depuis un poste de dev.
- Le rapport d'envoi des récapitulatifs comptables affiche désormais les échecs d'envoi (auparavant silencieusement ignorés), avec le détail dans le journal d'audit.

### Améliorations

- **Récapitulatifs comptables** : la ligne « depuis … » de chaque email est calculée par membre à partir de la première entrée réellement incluse dans son envoi, plutôt que sur une date de dernier lot globale et fragile.
- Emails trim automatiquement les espaces parasites avant envoi (cause d'échecs silencieux).
- Reformulation « Dont dons pouvant figurer sur l'attestation fiscale » → « Montant déductible fiscalement ».
- Toutes les actions `$pdo` global remplacées par le singleton `db()` dans `includes/actions/` et `includes/views/` (#145) — élimine une classe de bugs de portée de variable.

### Corrections

- Page Réglages accessible même sur une base pas encore migrée jusqu'à `0021`.
- Colonne « Libellé par défaut » ajoutée au tableau des types de compta ; fallback serveur du libellé cotisation utilise `default_libele`.
- `compta.php` utilise `Contact::getMemberName()` au lieu de dupliquer la jointure `CONCAT(firstName,' ',lastName)`.
- **Bug de décalage de fuseau horaire corrigé** sur les migrations `0028`-`0030` : `birthday`/`contact_properties.date`/`compta.date` sont désormais converties entièrement côté PHP (jamais via `FROM_UNIXTIME()`/`UNIX_TIMESTAMP()` SQL, qui utilisent le fuseau de *session* MySQL — différent du fuseau PHP forcé à `Europe/Zurich`).
- **Plantage PDO corrigé** dans les migrations `0026`-`0030` (`SQLSTATE[HY000]: 2014` / `1295`) : le mécanisme de garde conditionnelle (`PREPARE`/`EXECUTE` dynamique) utilisait `SELECT 1` comme no-op et `SIGNAL` comme abandon volontaire — ni l'un ni l'autre n'est fiable via `PDO::exec()` sur tous les serveurs MySQL/MariaDB. Remplacé par `DO 0` (aucun jeu de résultats) ; le garde `SIGNAL` est retiré au profit d'une dégradation silencieuse et sûre (`NULL` plutôt qu'une date fausse) en cas de tables de fuseaux horaires MariaDB non chargées.

## [5.0.1] — 2026-07-10

### Nouveautés

- **Envoi des attestations de dons par email** (individuel depuis la fiche membre / résumé
  dons, et en masse) : aperçu de l'email avant envoi, tampon/signature de l'organisation
  toujours inclus (contrairement au téléchargement direct, où c'est optionnel), avertissement
  si l'envoi a lieu hors janvier.
- **Tampon et signature** sur les attestations de dons (téléchargées ou envoyées par email) :
  overlay généré via FPDF, images déposées manuellement par l'administrateur système dans
  `conf/attestation_stamp.png` / `conf/attestation_signature.png` (non commitées).
- **Envoi en masse des attestations** : détection des personnes déjà notifiées cette
  année-là, avec choix explicite (case décochée par défaut) de forcer le renvoi ou d'ignorer.
- **Régénération d'une attestation déjà envoyée** depuis le détail de l'email (journal des
  emails), en conservant la date d'envoi d'origine plutôt que la date du jour.
- **Aperçu de l'email avant envoi** pour les rappels de cotisation individuels (et renvois),
  comme pour les récapitulatifs comptables et attestations.
- **Copie (BCC)** vers l'adresse de contact (`smtp_reply_to`) si configurée, sur les rappels
  de cotisation et attestations de dons, individuels comme en masse.
- Nouveau modèle par défaut pour `tpl_attestation_don` : salutation formelle genrée
  (`contact.sexe`), mention sur les cotisations affichée uniquement si pertinente pour
  l'année de l'attestation.

## [5.0.0] — 2026-07-09

### ⚠️ Changements majeurs (breaking)

Cette version renomme en profondeur le vocabulaire du domaine (tables, classes PHP, endpoints API REST). **Aucune rétrocompatibilité n'est assurée** — les migrations `0013` à `0015` doivent être appliquées, et toute intégration externe (scripts, exports, API REST) doit être mise à jour.

- **Table `users` → `contact`**, `user_properties` → `contact_properties` (migration `0015`). Classe PHP `User` → `Contact` (`html/classes/user_class.php` → `contact_class.php`).
- **Table `team` → `segment`**, `user_team` → `user_segment` (migration `0014`, colonne `team_id` → `segment_id`, `metagroup.teamid` → `metagroup.segmentid`). Classe PHP `Team` → `Segment` (`team_class.php` → `segment_class.php`).
- **API REST** : `/api/members` → `/api/contacts`, `/api/groups` → `/api/segments`. Réponses JSON et corps de requête inchangés à part le renommage des routes elles-mêmes.
- **Adhésion aux segments** : l'ancien stockage EAV (`user_properties`, clé `team_N`) est remplacé par une vraie table de jointure `contact_segment` (migration `0013`, backfill automatique depuis l'EAV existant).
- Toute vue, action ou script personnalisé référençant les anciens noms de table/classe/route doit être adapté avant la mise à jour.

### Nouveautés

- **Bulletin de versement QR suisse (QR-facture)** joint en PDF aux rappels de cotisation, généré via `sprain/swiss-qr-bill` à partir de l'IBAN de l'organisation (nouveau champ **Réglages → IBAN**).
- **Description du montant configurable** pour les rappels de cotisation (**Réglages → `org_coti_amount_desc`**) : affichée dans l'email de rappel et sur le bulletin QR (champ « Montant »), avec repli sur une valeur par défaut si laissée vide.
- **Rappel individuel — confirmation** : le bouton « Envoyer un rappel » de la vue Membres perdus affiche désormais une modale de confirmation nommant le membre (au lieu d'envoyer immédiatement), alignée sur le comportement de l'envoi groupé.
- **Renvoi forcé** : possibilité de renvoyer un rappel à un membre déjà relancé (garde anti-doublon contournable explicitement) depuis la vue Membres perdus.

### Technique

- **Singleton `db()`** remplace la variable globale `$pdo` dans les fonctions et méthodes de classe — signature stable pour tout code appelant la couche DB (#125).
- Dépendances runtime PHP (`sprain/swiss-qr-bill`, `fpdf/fpdf`) vendorisées dans `html/vendor/` (committées, pas de `composer install` requis en prod ; extension **GD** requise sur le serveur).

## [4.0.0] — 2026-07-08

### Nouveautés

- **Configuration SMTP** (Réglages → Email) : client SMTP pur PHP sans dépendance externe (plain / STARTTLS / SSL-TLS, AUTH LOGIN/PLAIN ou sans auth), mot de passe chiffré au repos, envoi de test avec message d'erreur SMTP détaillé.
- **Templates d'email configurables** (objet + corps texte/HTML, variables `{{placeholder}}`) pour le rappel de cotisation, le récapitulatif comptable et l'attestation de don, avec repli sur un template intégré par défaut.
- **Récapitulatifs comptables groupés** (`comptaRecap`) : un email par membre récapitulant ses entrées non notifiées, aperçu avant envoi (rendu HTML réel), envoi individuel ou en masse, filtre par année, mode étendu pour renvoyer aux membres déjà notifiés.
- **Rappels de cotisation impayée** : envoi manuel individuel ou en masse depuis la vue Membres perdus, avec garde anti-doublon par année.
- **Journal des emails** (Réglages → Email → Journal) : historique paginé, statut envoyé/erreur, renvoi d'une entrée en erreur, purge.
- **Année de cotisation** (`compta.cotisation_year`) : distingue l'année de paiement de l'année de cotisation couverte (ex. cotisation N+1 payée en décembre N) ; champ validé côté serveur, reflété dans les emails et exposé par l'API REST (`GET /api/members/{id}?sub=compta`).
- **Champs organisation** : numéro IDE suisse avec vérification automatique via Zefix (préremplissage nom/adresse/but statutaire), but statutaire, statut d'exonération fiscale.

### Corrections

- Lien PDF d'attestation non cliquable dans une ligne cliquable (`ca-row-link`) : le plugin `datahref` interceptait les clics sur les liens imbriqués.
- Lookup Zefix : bascule vers le flux d'API réel (`search.json` → `firm/{ehraid}.json`), l'ancien endpoint étant décommissionné.
- Erreur réseau sur les boutons Zefix/LINDAS causée par une requête `fetch()` sans en-tête `HX-Request`.
- `assert.sh` (test CI d'upgrade) n'attendait que 9 migrations appliquées au lieu de 12.
- `email_templates.body_html` manquant dans `schema.sql`/`install.php` — un fresh install ne créait pas cette colonne pourtant utilisée par les templates HTML.

### Technique

- Test de convergence CI depuis une base de données legacy étendu aux migrations 0010-0012 (`tests/upgrade/`).

## [3.8.0] — 2026-07-03

### Nouveautés

- **Interface multilingue (FR/EN/DE/ES)** : chaque utilisateur choisit sa langue d'interface (carte « Langue » sur la page Mot de passe), stockée sur son compte (`app_users.locale`, défaut français) et appliquée immédiatement. Traductions complètes en anglais, allemand (orthographe suisse) et espagnol ; toute clé manquante retombe automatiquement sur le français.
- **Externalisation complète des chaînes UI** : les ~650 dernières chaînes codées en dur (messages d'erreur, boutons, titres, aide contextuelle…) sont passées dans le fichier de locale, conformément à la règle « aucun label en dur ».

### Technique

- Nouvelle architecture de locale par bundles PHP (`html/locales/resources_{fr,en,de,es}.php`), chargés par `mbLoadLocale()` — la base française reste la source complète, les autres langues sont des surcharges.
- Migration `0003_app_users_locale` (colonne `locale` sur `app_users`).
- `tools/release.sh` / `tools/publish-site.sh` : processus de release allégé (bump/tag/GitHub release + patch du changelog sur le site, sans régénération complète).

## [3.7.0] — 2026-07-03

### Nouveautés

- **Lien vers la documentation en ligne** : le pied de page de l'application et celui de l'installateur (`install.php`) pointent désormais vers le site du projet et la documentation en ligne (https://pvollenweider.github.io/memberbase/) au lieu du dépôt GitHub brut. Nouveau libellé `documentation` dans la locale.

### Documentation

- Site de documentation public GitHub Pages (https://pvollenweider.github.io/memberbase/) : workflow de maintenance documenté dans `CLAUDE.md` (régénération du knowledge graph et du site à chaque release).
- Nettoyage de balises parasites en fin de `doc/user.md` et `doc/architecture.md`.

## [3.6.0] — 2026-07-03

### Nouveautés

- **Maintenance DB sans SSH** (#104) : l'admin peut depuis **Réglages → Santé** exporter la base (dump SQL téléchargeable, sans `mysqldump`) et appliquer les migrations en attente directement depuis le navigateur (case « j'ai fait une sauvegarde » obligatoire). Couvre 100 % des cas sans accès SSH.
- **Cache-busting des assets CSS/JS** (#105) : tous les fichiers statiques (vendor + custom) portent désormais `?v=APP_VERSION` dans leur URL. Les navigateurs chargent automatiquement les nouvelles versions après chaque mise à jour — sans vider le cache manuellement.

### Documentation

- `CONTRIBUTING.md` enrichi : stack complète (TipTap, PHPUnit), règles migrations, CSRF, dirty-form guard, tableau des jobs CI.
- `MIGRATION_PROD.md` consolidé : sections d'actions manuelles obsolètes supprimées, instructions de déploiement généralisées (plus de références à une instance spécifique).

## [3.5.6] — 2026-07-02

Release d'**industrialisation** (ticket-cadre #67) : passer de « ça marche » à « installable, migrable, sécurisable sans stress », sans réécriture du noyau.

### Sécurité

- **Protection CSRF sur toutes les actions POST** (#69) : jeton de session vérifié dans le routeur d'actions **avant** dispatch (POST sans jeton valide → `403`). Propagation automatique — en-tête `X-CSRF-Token` sur toutes les requêtes htmx et champ caché estampillé sur tout `<form method="post">` (couvre soumissions natives, uploads multipart et `form.submit()` programmatiques).
- **En-têtes de sécurité HTTP** (#70) : `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Strict-Transport-Security`, plus une **Content-Security-Policy en `Report-Only`** (à valider avant enforcement). À répliquer dans le vhost HTTPS de prod (cf. `MIGRATION_PROD.md`).
- **Cohérence des gardes API** (#71) : `GET /api/groups` et `GET /api/compta-types` vérifient désormais `canRead()`, comme les autres lectures.

### Nouveautés

- **Système de migrations de base de données versionnées** (#68) : fichiers `html/migrations/NNNN_*.sql` appliqués par `php html/tools/migrate.php` (ou `make migrate`), état suivi dans la table `schema_migrations`. `--status` / `--baseline`, baseline automatique à l'installation, bandeau d'alerte admin tant qu'une migration est en attente. Plus aucun SQL manuel en prod. Les migrations vivent **sous `html/`** (accès HTTP refusé par `.htaccess`) pour être emportées par les déploiements qui ne synchronisent que `html/`.
- **Page de santé / observabilité** (#74) : onglet **Réglages → Santé** (Admin) — version, PHP, serveur, état DB, migrations appliquées/en attente, volumétrie et dernière activité. Plus un endpoint `/health.php` non authentifié pour du monitoring externe (JSON `{"status":"ok"|"degraded"}`, sans donnée sensible).

### Modèle de données

- **`compta.sum` passe de `VARCHAR(64)` à `DECIMAL(10,2)`** (#72) : fin des montants financiers stockés en texte. La migration nettoie les valeurs (virgules → points, non numériques → 0) puis convertit la colonne. Suppression des `CAST`/`REGEXP` et du contrôle d'intégrité « montants non numériques » devenus sans objet.

### Tests

- **Suite unitaire PHPUnit** (#73) : logique métier pure (dates, `unquote`, normalisation de civilité d'import) dans `tests/unit/`, exécutée par un **job CI dédié** (PHP 8.2), en complément des tests E2E Playwright. Helpers purs extraits dans `html/includes/lib/pure.php`, sans effet de bord.

### Corrections

- **Runner de migrations** (#68) : un commentaire en tête de fichier faisait silencieusement sauter le statement suivant ; et le `commit()` postérieur à un DDL (auto-committé par MySQL) levait un `ÉCHEC` trompeur alors que la migration était bien appliquée. Corrigés.

### Conventions

- `CLAUDE.md` : commentaires de code en anglais, aucun label en dur (tout dans la locale), sections CSRF / migrations / tests.

## [3.5.5] — 2026-07-02

### Sécurité

- **Deux routes de mutation étaient accessibles sans vérification de rôle** (accès direct par URL) : `deleteUserConfirm` (désactivation d'un membre) exige désormais **Admin**, `removeSuiviConfirm` (suppression d'une note de suivi) exige désormais **Writer**. Tests de régression dédiés (`tests/route-guards.spec.ts`).

### Nouveautés

- **Matrice des droits** affichée à la création d'un compte applicatif : le rôle choisi montre précisément ce qu'il autorise (#49).

### Corrections

- **Installer (`install.php`)** : fatal error `strict_types declaration must be the very first statement` à chaque accès — le wizard était inutilisable en fresh install.
- **Installer** : la colonne `users.email_alt` manquait dans le schéma embarqué — toute fresh install produisait une base où la création de membre (dont l'import CSV) plantait (`Unknown column 'email_alt'`). Garde anti-dérive ajoutée : `tests/schema-drift.spec.ts` compare `schema.sql` au schéma de l'installer en CI.
- **Mobile** : l'identité du membre reste visible sur la fiche (#54) ; clic-ligne de l'aperçu des dons et liens « donateurs perdus » fonctionnels (#53).
- **Fusion de doublons** : la colonne B restait invisible quand une note contenait du HTML volumineux (#50).
- **Audit** : la suppression d'un membre journalise ses données non vides (#52).
- **Liste des membres** : suppression d'un produit cartésien silencieux (`,compta` sans condition de jointure) et d'une requête N+1 sur le filtre « cotisation impayée » — page plus rapide sur les grandes bases.

### Architecture (interne, sans changement fonctionnel)

- **Routeur de vues déclaratif** : table route → [fichier, garde] ; ajouter une vue force une décision de droits explicite (#56).
- **`MemberFilter`** : les 5 filtres virtuels (cotisations impayées, sans activité 10 ans, etc.) ont désormais une implémentation unique partagée entre la liste des membres et l'API REST — fin des divergences silencieuses vue/API (#57).
- **`index.php` allégé** (417 → 191 lignes) : tout le JavaScript inline extrait vers `js/app.js` et `js/tiptap-editor.js` (#58).
- **`users_list.php` sans SQL** (923 → 743 lignes) : requêtes déplacées vers les classes (`User::listWithFilters()`, `Compta::typesByUser()`, …) (#59).
- **Documentation refondue** (architecture, API, admin, utilisateur, onboarding) (#51, #66).

### Tests

- **+45 tests Playwright** : parité vue/API des filtres virtuels, matrice rôles × routes (avec régressions des failles corrigées), guard de formulaire non sauvegardé, menu mobile par rôle (viewport téléphone), dérive de schéma installer (#65).

## [3.5.4] — 2026-07-01

### Nouveautés

- **Terminologie « Segment »** — dans toute l'interface, « Groupe » devient **Segment** et « Métagroupe » devient **Segment combiné**. Changement de libellés uniquement : les colonnes de la base (`team`, `metagroup`), les noms de variables PHP et les paramètres de l'API restent inchangés.
- **Import de contacts CSV / TSV** — assistant en 3 étapes accessible depuis la liste des membres (bouton « Importer », réservé aux rôles **Manager** et **Admin**) :
  - **Étape 1 — Upload** : fichiers CSV ou TSV, détection automatique de l'encodage (UTF-8 / Latin-1) et du délimiteur (`,` `;` tabulation). Limites : 5 MB / 5 000 lignes (troncature signalée).
  - **Étape 2 — Mapping** : association de chaque colonne à un champ membre, avec auto-détection par nom d'en-tête et aperçu échantillonné sur les 25 premières lignes. La civilité texte (Monsieur / Madame / Madame et Monsieur) est normalisée vers l'enum `sexe` (`m` / `f` / `hf` / `na`).
  - **Étape 3 — Résultats & doublons** : rapport « N créés », puis résolution des doublons ligne par ligne (**ignorer** / **compléter les champs vides** / **écraser**). Détection des doublons par email **ou** prénom + nom.
  - **Ajout à un segment** : à l'étape 2, les contacts importés (nouveaux **et** doublons existants) peuvent rejoindre un segment existant, un nouveau segment (avec catégorie optionnelle), ou — par défaut — un segment `Import JJ.MM.AAAA HH:MM` créé automatiquement.
  - Audit log pour chaque création, mise à jour et ajout au segment. Création des contacts et du segment enveloppée dans une transaction.
- **Champ e-mail alternatif (`email_alt`)** — adresse historique / secondaire, non utilisée pour les envois, éditable dans la fiche membre et importable.
- **Tests d'intégrité étendus** (page *Réglages → Intégrité*) :
  - Seuls les blocs présentant une anomalie sont affichés (ouverts par défaut) ; message « Tout est clean » sinon.
  - Nouveaux contrôles de format : montants compta non numériques, dates compta invalides, entrées sans type, emails / emails alt. mal formatés, genre hors enum, dates de naissance dans le futur, membres sans nom de famille ni société.
  - Actions correctives directes sur les montants compta invalides : **Mettre à 0** ou **Supprimer l'écriture**.
- **Vue résumé — mode étendu** : colonnes séparées **Dons / Autres / Total compta**, surlignage des lignes de type exclu-don, bascule « Mode étendu ».
- **Fusion de membres** : option « Garder les deux notes » ; rendu correct du HTML (Tiptap) du champ Note dans la vue de fusion.
- **Pastilles de type compta cohérentes** dans la vue « dernière écriture » (dropdown + tableau).

### Corrections

- **Import entre segments cassé** — la requête d'import de membres depuis d'autres segments utilisait la colonne inexistante `userid` au lieu de `user_id` (levait une `PDOException`).
- **Fusion de membres non atomique** — `mergeUsers` déplaçait compta et adhésions puis supprimait la source sans transaction ; un échec à mi-chemin laissait des écritures orphelines. L'opération est désormais transactionnelle (rollback sur erreur).
- **Crash à la création partielle d'un membre** — `new User()` laissait `title`, `comment`, etc. à `null`, violant les contraintes `NOT NULL` lors d'un `save()` partiel (import). Les propriétés sont initialisées à `''` (`0` / `'na'` selon le type).
- **Dates invalides acceptées** — `formatedDateToTimeStamp` acceptait des dates hors bornes (`32/13/2025`, 29/02 non bissextile) en les faisant déborder ; elles sont désormais rejetées via `DateTime::getLastErrors()`.
- **Casts d'identifiants** — casts `(int)` systématiques sur les ids passés aux lookups et entités (actions compta, suivi, groups) pour éviter les échecs silencieux.
- **Case « Ajouter au segment »** décochée par défaut sur le formulaire d'ajout de membre.

### Sécurité

- **Import réservé aux Manager / Admin** — garde `isManager()` sur l'action d'import et les vues `importStep1/2/3` ; bouton masqué aux autres rôles.
- **Lectures API protégées** — nouveau rôle de lecture minimal `canRead()` (`admin` / `manager` / `user` / `readonly`) appliqué comme garde sur tous les endpoints `GET` (`members`, `compta`, `suivi`). Documentation de `DELETE /api/compta` alignée sur le rôle réellement exigé (`canWrite()`).

### Performances

- **Liste des membres** — suppression du `SELECT` complet exécuté par ligne (`lookupUser()`) : `creationDate` est désormais porté par la requête principale, éliminant une requête par membre affiché.
- **Détection de doublons à l'import** — membres existants préchargés en une seule requête (maps en mémoire) au lieu de deux `SELECT` par ligne.

### Migration depuis v3.5.3

Ajouter la colonne `email_alt` sur la base de production si elle n'existe pas encore :

```sql
ALTER TABLE users ADD COLUMN email_alt VARCHAR(255) NOT NULL DEFAULT '' AFTER email;
```

Aucune autre migration de schéma n'est requise.

## [3.5.3] — 2026-07-01

### Nouveautés

- **Contrôle d'accès par rôle (RBAC)** — masquage conditionnel des actions dans l'UI selon le rôle (`readonly` / `user` / `manager` / `admin`) :
  - Bouton "Ajouter un membre" masqué pour `readonly`
  - Icône paramètres masquée pour `readonly` et `user`
  - Click-to-edit et hint d'édition masqués pour `readonly`
  - Lignes d'ajout compta et suivi masquées pour `readonly`
  - Toggle archive/désarchiver masqué pour `readonly` et `user` (affiché en texte statique)
  - Pills de groupe : lien de retrait masqué pour `readonly` et `user`
  - Section gestion des groupes masquée pour `readonly` et `user`
  - Boutons supprimer/anonymiser réservés à `admin`
- **Enforcement serveur RBAC** — HTTP 403 pour les actions hors-rôle :
  - `mergeUsers`, `deactivateUser`, `reactivateUser` requièrent `isManager()`
  - `anonymizeUser`, `deleteOrDeactivateUser` requièrent `isAdmin()`
  - Vues `deleteUser`, `anonymizeUser`, `mergeUsers` protégées côté serveur
- **Import donateurs institutionnels** — le formulaire d'import donateurs propose désormais 3 options : tous / non-institutionnels / institutionnels ; badge dynamique affichant le nombre à importer en temps réel
- **Pastilles de type compta** — les badges dans la liste membres respectent désormais les couleurs définies dans les paramètres (`bg-X-subtle` avec texte adapté au contraste)
- **Validation du montant compta** — champ `sum` avec `pattern` HTML5 et `inputmode="decimal"` ; rejet 422 côté serveur si le montant n'est pas numérique

### Corrections

- `schema.sql` : ENUM `role` étendu à `('admin','manager','user','readonly')` — manquaient `manager` et `readonly`
- `bootstrap.php` : `is_institutional` manquait dans la query de chargement de `$comptaTypes` — causait 0 résultat pour l'import institutionnel
- Import donateurs : `SUM(c.sum)` protégé contre les valeurs non-numériques en base (MariaDB mode strict)

### Tests

- Nouvelle suite E2E `tests/roles.spec.ts` — 40+ tests couvrant la visibilité UI par rôle et l'enforcement HTTP 403 côté serveur
- Seed de test étendu : 4 comptes app_users (un par rôle) + membre archivé pour les tests de suppression
- Global setup Playwright : authentification et sauvegarde d'état pour les 4 rôles

### Migration depuis v3.5.2

Appliquer sur la base de données de production si la colonne `role` n'a pas encore les nouvelles valeurs :
```sql
ALTER TABLE app_users MODIFY COLUMN role ENUM('admin','manager','user','readonly') NOT NULL DEFAULT 'readonly';
```

---

## [3.5.2] — 2026-06-29

### Nouveautés

- **API REST complète** — endpoints JSON sous `/api/`, authentification par session, permissions par rôle :
  - `GET /api/members` — liste paginée avec recherche et filtres
  - `POST /api/members` — créer un membre
  - `GET /api/members/{id}` — fiche complète
  - `PUT` / `PATCH /api/members/{id}` — modification partielle, payload diff-only (audit log reflète les vraies valeurs avant/après)
  - `DELETE /api/members/{id}` — désactiver ou supprimer (`?dispose=delete`, admin)
  - `GET /api/members/{id}/groups` — groupes d'un membre avec catégorie
  - `GET /api/groups` — liste avec catégorie et nombre de membres
  - `POST /api/groups` — créer un groupe (manager)
  - `PUT /api/groups/{id}` — renommer / basculer visibilité (manager)
  - `DELETE /api/groups/{id}` — supprimer un groupe vide (manager)
  - `GET /api/groups/{id}/members` — membres d'un groupe
  - `POST /api/groups/{id}/members` — ajouter un membre à un groupe (manager)
  - `DELETE /api/groups/{id}/members` — retirer un membre d'un groupe (manager)
  - `GET /api/compta?memberId={id}` — entrées comptables d'un membre
  - `POST /api/compta` — créer une écriture comptable
  - `GET /api/compta/{id}` — détail d'une écriture
  - `PUT /api/compta/{id}` — modifier une écriture
  - `DELETE /api/compta/{id}` — supprimer une écriture
  - `GET /api/compta-types` — types comptables
  - `GET /api/suivi?memberId={id}` — notes de suivi d'un membre
  - `POST /api/suivi` — créer une note de suivi
  - `GET /api/suivi/{id}` — détail d'une note
  - `PUT /api/suivi/{id}` — modifier une note
  - `DELETE /api/suivi/{id}` — supprimer une note
- **Système de permissions à 4 niveaux** — `readonly`, `user`, `manager`, `admin` ; contrôle fin sur les actions CRUD et les réglages
- **Édition inline** sur la fiche membre (données générales) — bascule vue/édition sans rechargement de page, sauvegarde partielle via Alpine.js
- **Filtres virtuels enrichis** — colonne "Groupes de groupes" dans la liste, adhésion modifiable via API, édition des app-users depuis l'interface
- **Groupes par métagroupe dans la dropdown de filtre** — section "Groupes de groupes" au-dessus des catégories

### Corrections

- **Filtre de groupe** — l'entrée clavier n'avait aucun effet : Bootstrap `.d-flex { display: flex !important }` écrasait le `style="display:none"` posé par le filtre ; corrigé en utilisant une classe CSS `.team-filterable.team-hidden { display: none !important }` à plus haute spécificité
- Les séparateurs entre catégories restaient visibles quand toute la catégorie était filtrée
- La saisie dans l'input de filtre déclenchait la dialog "modifications non sauvegardées"
- Ajout du champ `wants_attestation` dans le formulaire d'ajout d'écriture comptable
- Données de profil masquées en vue desktop — recalcul du collapse Alpine réactivé au bon moment
- Liens "périmés" ne bypassaient pas htmx boost sur mobile
- Régression `setTimeout(caInitDT)` — cassait le bouton ColVis DataTables
- Race condition Alpine sur la fiche membre (données nulles au premier rendu)
- `unquote nullsafe`, `.htaccess FollowSymLinks`, `member-general-form.js` externalisé
- Diff d'audit utilise des chaînes typées — les valeurs avant/après sont lisibles
- API groupes inclut `categoryId` et `categoryName` dans la réponse

### Tests

- Suite Playwright API — 1 spec couvrant tous les endpoints REST (CRUD complet)
- CI : URL de recherche membres corrigée, le pipeline échoue correctement sur test flakeux

### Documentation

- `MIGRATION_PROD.md` — checklist de déploiement en production (Docker, k8s, variables d'environnement)
- Vhost Apache pour l'API + bloc `Directory` explicite dans `docker/apache.conf`

### Migration depuis v3.5.1

Aucun changement de schéma. Aucun changement de configuration. Si vous utilisez Docker/k8s, reconstruire l'image et redéployer. Les endpoints API requièrent `mod_rewrite` Apache (déjà configuré dans `docker/apache.conf`).

---

## [3.5.1] — 2026-06-28

### Refactoring interne

- **Restructuration des includes** — les fichiers de `html/includes/` sont organisés en sous-dossiers conventionnels :
  - `lib/` — bootstrap PHP (`bootstrap.php`, ex `declarations.php`) et authentification (`auth.php`)
  - `routing/` — routeur de vues (`views.php`, ex `manage_views.php`) et dispatcher d'actions (`actions.php`, ex `manage_actions.php`)
  - `views/` — fragments de page, nommés par domaine (`users_list.php`, `donors_summary.php`, `settings_general.php`, etc.)
  - `partials/` — composants réutilisables (`menu.php`, `donor_table.php`)
- Tous les fichiers renommés en anglais et en snake_case
- Tous les `include` convertis en chemins `__DIR__`-relatifs pour éviter les ambiguïtés CWD/Apache

Table des renommages (`html/includes/`) :

| Ancien nom | Nouveau chemin | Rôle |
|---|---|---|
| `declarations.php` | `lib/bootstrap.php` | PDO, app settings, helpers |
| `auth.php` | `lib/auth.php` | Session, login, requireLogin() |
| `manage_views.php` | `routing/views.php` | View router |
| `manage_actions.php` | `routing/actions.php` | POST action dispatcher |
| `view_users.php` | `views/users_list.php` | Member list |
| `update_user_form.php` | `views/users_edit_form.php` | Edit member |
| `resume.php` | `views/donors_summary.php` | Contributions KPIs |
| `settings_form.php` | `views/settings_general.php` | App settings |
| `menu.php` | `partials/menu.php` | Nav sidebar |
| `_donor_table.php` | `partials/donor_table.php` | Donor table partial |
| _(et 27 autres fichiers de vues)_ | `views/` | Fragments préfixés par domaine |

### Tests

- **Suite Playwright complète** — 55 tests E2E couvrant auth, membres, compta, suivi, groupes, filtres, types compta, fusion, anonymisation, historique, intégrité, réglages
- Pipeline CI GitHub Actions (`e2e.yml`) — reset DB, warm-up, run suite sur chaque push/PR
- En-têtes de licence AGPL-3.0 ajoutées sur tous les fichiers PHP modifiés

### Documentation

- `README.md` — arborescence `includes/` mise à jour avec la nouvelle structure
- `doc/admin.md` — référence de configuration DB corrigée (`conf/db.php` / variables d'environnement)

### Migration depuis v3.5.0

Aucun changement de schéma. Aucun changement de configuration. Si des scripts ou intégrations référencent des fichiers sous `html/includes/` par leurs anciens noms, mettre à jour ces chemins vers la nouvelle structure.

---

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

---

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [versionnement sémantique](https://semver.org/lang/fr/).
