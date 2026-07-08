<?php
// French locale — display strings for the member management application.
// To add a new language, duplicate this file and translate the values.

// --- Quick-filter labels ---
$GLOBAL['allExceptArchives']       = "Tout le monde";
$GLOBAL['cotiUnpayed']             = "Cotisation " . date('Y') . " non payée";
$GLOBAL['cotiUnpayedLast3Years']   = "Aucune cotisation ces 3 dernières années";
$GLOBAL['nothingLast10Years']      = "Aucun versement ces 10 dernières années";
$GLOBAL['nonInstitPayedSomethingLastYear'] = "Donateur non institutionnel actif en " . (date('Y') - 1);

// --- Navigation ---
$GLOBAL['search']          = "Chercher";
$GLOBAL['logout']          = "Déconnexion";
$GLOBAL['changePassword']  = "Mot de passe";
$GLOBAL['donationOverview']= "Aperçu des dons";
$GLOBAL['administration']  = "Administration";

// --- Common actions ---
$GLOBAL['save']            = "Sauvegarder";
$GLOBAL['saved']           = "Enregistré.";
$GLOBAL['groupModified']   = "Segment modifié.";
$GLOBAL['confirmPassword'] = "Confirmer le mot de passe";
$GLOBAL['cancel']          = "Annuler";
$GLOBAL['close']           = "Fermer";
$GLOBAL['confirm']         = "Confirmer";
$GLOBAL['archive']              = "Archiver";
$GLOBAL['anonymize']            = "Anonymiser";
$GLOBAL['edit']                 = "Modifier";
$GLOBAL['confirmMerge']         = "Confirmer la fusion";
$GLOBAL['confirmAnonymize']     = "Confirmer l'anonymisation";
$GLOBAL['wantsAttestationLabel']= "Souhaite une attestation de don";
$GLOBAL['deleteAll']            = "Tout supprimer";
$GLOBAL['documentation']        = "Documentation";
$GLOBAL['deletePermanently']    = "Supprimer définitivement";
$GLOBAL['deleteOrArchive']      = "Supprimer ou archiver ce membre";
$GLOBAL['deleteEntry']          = "Supprimer cette écriture";
$GLOBAL['deleteSuiviEntry']     = "Supprimer cette entrée de suivi";
$GLOBAL['archiveMember']        = "Archiver ce membre";
$GLOBAL['anonymizeProfile']     = "Anonymiser ce profil";
$GLOBAL['editGroup']            = "Modifier le segment";
$GLOBAL['editCompta']           = "Modifier entrée compta";
$GLOBAL['editMetagroup']        = "Modifier";

// --- Dashboard / resume ---
$GLOBAL['donors']          = "Donateurs";
$GLOBAL['activeMembers']   = "Membres actifs";
$GLOBAL['contributions']   = "Contributions";
$GLOBAL['loyalDonors']     = "Fidèles";
$GLOBAL['newDonors']       = "Nouveaux";
$GLOBAL['lapsedDonors']    = "Perdus";
$GLOBAL['last12Months']    = "12 derniers mois";
$GLOBAL['last24Months']    = "24 derniers mois";
$GLOBAL['allEntries']      = "Toutes entrées";
$GLOBAL['wantsAttestation']= "Souhaite une attestation";
$GLOBAL['donationsOnly']   = "Dons uniquement";
$GLOBAL['withoutType']     = "Sans type";
$GLOBAL['historyByYear']   = "Historique par année";
$GLOBAL['distByType']      = "Répartition par type";
$GLOBAL['nonDonation']     = "non-don";

// --- Member form sections ---
$GLOBAL['contactInfo']     = "Coordonnées";
$GLOBAL['additionalInfo']  = "Infos complémentaires";
$GLOBAL['city']            = "Ville";
$GLOBAL['country']         = "Pays";

// --- Settings ---
$GLOBAL['saveSettings']    = "Sauvegarder";
$GLOBAL['adminOnly']       = "Accès réservé aux administrateurs.";

// --- UI labels ---
$GLOBAL['add']             = "<i class=\"fas fa-plus\"></i> Ajouter";
$GLOBAL['addBtn']          = "Ajouter";
$GLOBAL['addGroups']       = "Ajouter des segments";
$GLOBAL['addMetagroup']    = "Ajouter un segment combiné";
$GLOBAL['addTeam']         = "Ajouter un segment";
$GLOBAL['addUser']         = "Ajouter";
$GLOBAL['address']         = "Adresse";
$GLOBAL['all']             = "Tout le monde";
$GLOBAL['allTypes']        = "Tous";
$GLOBAL['allYear']         = "Toutes";
$GLOBAL['birthDay']        = "Date naissance";
$GLOBAL['comment']         = "Commentaires";
$GLOBAL['compet']          = "Compétences";
$GLOBAL['compta']          = "Compta";
$GLOBAL['coti']            = "<i class='fas fa-hand-holding-usd fa-fw s'></i> Cotisation";
$GLOBAL['creationDate']    = "Création";
$GLOBAL['date']            = "Date";
$GLOBAL['delete']          = "Supprimer";
$GLOBAL['donInst']         = "<i class='fas fa-university fa-fw s'></i> Public / Instit.";
$GLOBAL['email']           = "Email";
$GLOBAL['exportDoc']       = "Exporter dans MS Word (Etiquettes)";
$GLOBAL['exportQuittance'] = "Générer quittance";
$GLOBAL['exportXls']       = "Exporter dans MS Excel (Feuille)";
$GLOBAL['f']               = "Femme";
$GLOBAL['fax']             = "Fax";
$GLOBAL['firstName']       = "Prénom";
$GLOBAL['generalData']     = "Données";
$GLOBAL['groupName']       = "Nom du segment combiné";
$GLOBAL['groups']          = "Segments";
$GLOBAL['hf']              = "Monsieur et Madame";
$GLOBAL['lastEntry']       = "Rapports";
$GLOBAL['lastEntryCompta'] = "Journal compta";
$GLOBAL['lastEntrySuivi']  = "Journal suivi";
$GLOBAL['lastModif']       = "Dernière modification";
$GLOBAL['lastName']        = "Nom";
$GLOBAL['libele']          = "Libellé";
$GLOBAL['list']            = "Listes";
$GLOBAL['m']               = "Homme";
$GLOBAL['manageMategroups']= "Gestion des segments combinés";
$GLOBAL['manageTeam']      = "Segments";
$GLOBAL['memberOf']        = "Appartenance aux segments";
$GLOBAL['na']              = "-";
$GLOBAL['name']            = "Nom";
$GLOBAL['npa']             = "NPA / Localité";
$GLOBAL['portable']        = "Portable";
$GLOBAL['quittance']       = "Commentaire";
$GLOBAL['sexe']            = "Genre";
$GLOBAL['society']         = "Société";
$GLOBAL['sort']            = "Cliquer pour trier la colonne";
$GLOBAL['suivi']           = "Suivi";
$GLOBAL['sum']             = "Somme";
$GLOBAL['teamName']        = "Nom du segment";
$GLOBAL['tel']             = "Privé";
$GLOBAL['telProf']         = "Prof.";
$GLOBAL['title']           = "Titre";
$GLOBAL['total']           = "Total";
$GLOBAL['type']            = "Type";
$GLOBAL['update']          = "Mettre à jour";
$GLOBAL['updateSuivi']     = "Mise à jour";
$GLOBAL['updateTeam']      = "Mise à jour";
$GLOBAL['updateUser']      = "Mise à jour";
$GLOBAL['web']             = "Web";

// --- Shared UI strings (used across several views) ---
$GLOBAL['accessDenied']      = "Accès refusé.";
$GLOBAL['create']            = "Créer";
$GLOBAL['merge']             = "Fusionner";
$GLOBAL['import']            = "Importer";
$GLOBAL['export']            = "Exporter";
$GLOBAL['copy']              = "Copier";
$GLOBAL['print']             = "Imprimer";
$GLOBAL['excel']             = "Excel";
$GLOBAL['next']              = "Suivant";
$GLOBAL['back']              = "Retour";
$GLOBAL['year']              = "Année";
$GLOBAL['status']            = "Statut";
$GLOBAL['active']            = "Actif";
$GLOBAL['inactive']          = "Inactif";
$GLOBAL['archivedOne']       = "Archivé";
$GLOBAL['archived']          = "Archivés";
$GLOBAL['user']              = "Utilisateur";
$GLOBAL['username']          = "Identifiant";
$GLOBAL['password']          = "Mot de passe";
$GLOBAL['madame']            = "Madame";
$GLOBAL['monsieur']          = "Monsieur";
$GLOBAL['noName']            = "Sans nom";
$GLOBAL['noCategoryLabel']   = "Sans catégorie";
$GLOBAL['networkError']      = "Erreur réseau";
$GLOBAL['loading']           = "Chargement…";
$GLOBAL['importContacts']    = "Importer des contacts";
$GLOBAL['filterPlaceholder'] = "Filtrer…";
$GLOBAL['emailAlt']          = "Email alt.";
$GLOBAL['emailAltLong']      = "E-mail alt.";
$GLOBAL['npaCity']           = "NPA / Ville";
$GLOBAL['hiddenSegment']     = "Segment masqué";
$GLOBAL['memberManagement']  = "Gestion des membres";
$GLOBAL['yes']               = "Oui";
$GLOBAL['action']            = "Action";
$GLOBAL['detail']            = "Détail";
$GLOBAL['amount']            = "Montant";

// --- Settings navigation ---
$GLOBAL['settings']            = "Réglages";
$GLOBAL['categories']          = "Catégories";
$GLOBAL['combinedSegments']    = "Segments combinés";
$GLOBAL['comptaTypes']         = "Types compta";
$GLOBAL['users']               = "Utilisateurs";
$GLOBAL['journal']             = "Journal";
$GLOBAL['integrity']           = "Intégrité";
$GLOBAL['health']              = "Santé";
$GLOBAL['management']          = "Gestion";
$GLOBAL['settingsSectionsAria']= "Sections des réglages";

// --- DataTables language block ---
$GLOBAL['dtSearch']       = "Rechercher :";
$GLOBAL['dtLengthMenu']   = "Afficher _MENU_ entrées";
$GLOBAL['dtInfo']         = "Entrées _START_ à _END_ sur _TOTAL_";
$GLOBAL['dtInfoFiltered'] = "(filtrées sur _MAX_)";
$GLOBAL['dtPrevious']     = "Précédent";
$GLOBAL['dtNext']         = "Suivant";
$GLOBAL['dtEmptyTable']   = "Aucune entrée.";

// --- Month abbreviations (index 1-12; index 0 unused) ---
$GLOBAL['monthsShort']    = ['', 'jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'aoû', 'sep', 'oct', 'nov', 'déc'];
$GLOBAL['monthsShortCap'] = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];

// --- Added: core (routing, actions, libs) ---
// Staging locale keys — externalized strings from routing, actions, lib and partials.
// To be merged into resources_fr.php.
$GLOBAL['viewNotFound'] = "Vue introuvable.";
$GLOBAL['csrfRejected'] = "Requête refusée (jeton CSRF invalide). Rechargez la page et réessayez.";
$GLOBAL['passwordTooShort'] = "Le mot de passe doit contenir au moins 8 caractères.";
$GLOBAL['passwordMismatch'] = "Les deux mots de passe ne correspondent pas.";
$GLOBAL['currentPasswordIncorrect'] = "Mot de passe actuel incorrect.";
$GLOBAL['invalidUsername'] = "Identifiant invalide (lettres, chiffres, ., -, _ uniquement).";
$GLOBAL['usernameTaken'] = "Cet identifiant est déjà utilisé.";
$GLOBAL['cannotDemoteLastAdmin'] = "Impossible de rétrograder le dernier administrateur.";
$GLOBAL['cannotDeleteLastAdmin'] = "Impossible de supprimer le dernier administrateur.";
$GLOBAL['oneGroupHidden'] = "1 groupe masqué.";
$GLOBAL['groupsHidden'] = "%d groupes masqués.";
$GLOBAL['oneGroupShown'] = "1 groupe affiché.";
$GLOBAL['groupsShown'] = "%d groupes affichés.";
$GLOBAL['lapsedDonorsGroupName'] = "Donateurs à relancer %d (%s)";
$GLOBAL['lapsedMembersGroupName'] = "Membres à relancer %d (%s)";
$GLOBAL['memberTeamsNotFound'] = "Impossible de trouver les équipes membres.";
$GLOBAL['noUsersToAdd'] = "Aucun utilisateur à ajouter.";
$GLOBAL['invalidData'] = "Données invalides";
$GLOBAL['groupNotFound'] = "Groupe introuvable";
$GLOBAL['importSegmentName'] = "Import %s";
$GLOBAL['anonymized'] = "Anonymisé";
$GLOBAL['viewAllEntriesOf'] = "Voir toutes les écritures de %s";
$GLOBAL['lastNameFull'] = "Nom de famille";
$GLOBAL['genderCivility'] = "Genre / civilité (Monsieur, Madame…)";
$GLOBAL['landlinePhone'] = "Téléphone fixe";
$GLOBAL['workPhone'] = "Tél. professionnel";
$GLOBAL['mobilePhone'] = "Mobile";
$GLOBAL['website'] = "Site web";
$GLOBAL['birthDateWithFormat'] = "Date de naissance (JJ/MM/AAAA)";
$GLOBAL['remarks'] = "Remarques";
$GLOBAL['migrationReadError'] = "Lecture impossible : %s";
$GLOBAL['migrationFailed'] = "ÉCHEC";

// --- Added: compta / donors / import / suivi views ---
// Staging locale additions — new keys introduced while externalizing hardcoded
// strings from the view files (compta, donors, import wizard, suivi).
// To be merged into resources_fr.php.

// --- Shared form / chart labels ---
$GLOBAL['numericAmountHint'] = "Montant numérique (ex: 50 ou 12.50)";
$GLOBAL['allTypesFull'] = "Tous les types";
$GLOBAL['monthlyVsCumulative'] = "Mensuel vs cumulé";
$GLOBAL['annual'] = "Annuel";
$GLOBAL['monthly'] = "Mensuel";
$GLOBAL['cumulative'] = "Cumulé";
$GLOBAL['annualAmount'] = "Montant annuel";
$GLOBAL['monthlyAmount'] = "Montant mensuel";

// --- compta_list ---
$GLOBAL['addMembershipEntry'] = "Add membership";
$GLOBAL['hideNonDonationEntries'] = "Masquer les entrées non-don (ventes, remboursements…)";
$GLOBAL['attestation'] = "Attestation";
$GLOBAL['displayedYear'] = "(année affichée)";
$GLOBAL['removeTypeFilter'] = "Supprimer le filtre type";
$GLOBAL['notCountedAsDonation'] = "Non compté comme don";
$GLOBAL['attestationExclNote']  = "Cette attestation ne contient que les dons. Les %d entrée(s) marquée(s) « Excl. don » (cotisations ou autres) n'y figurent pas.";

// --- Donor / member cohort views ---
$GLOBAL['backToDonationOverview'] = "Retour à l'aperçu des dons";
$GLOBAL['lapsedDonorsTitle'] = "Donateurs perdus %d → %d";
$GLOBAL['createSegmentLapsedDonors'] = "Créer segment «Donateurs à relancer %d»";
$GLOBAL['createSegmentTitle'] = "Créer le segment";
$GLOBAL['confirmCreateLapsedDonorsSegment'] = "Créer le segment «Donateurs à relancer %d» avec <strong>%s</strong> personne(s)?";
$GLOBAL['lapsedDonorsCount'] = "<strong>%s donateur%s</strong> ont contribué en <strong>%d</strong> mais pas en <strong>%d</strong>.";
$GLOBAL['donYear'] = "Don %d";
$GLOBAL['lastDonation'] = "Dernier don";
$GLOBAL['firstDonation'] = "Premier don";
$GLOBAL['loyalDonorsTitle'] = "Donateurs fidèles %d";
$GLOBAL['loyalDonorsCount'] = "<strong>%s donateur%s fidèles</strong> — ont contribué à la fois en <strong>%d</strong> et en <strong>%d</strong>.";
$GLOBAL['newDonorsTitle'] = "Nouveaux donateurs %d";
$GLOBAL['newDonorsCount'] = "<strong>%s nouveau%s donateur%s</strong> — ont contribué en <strong>%d</strong> sans donation en <strong>%d</strong>.";
$GLOBAL['lapsedMembersTitle'] = "Membres perdus %d → %d";
$GLOBAL['noMemberTeamFound']  = "Aucune équipe «Membre %d» trouvée en base.";
$GLOBAL['noComptaCotiType']   = "Aucun type de cotisation configuré.";
$GLOBAL['createSegmentLapsedMembers'] = "Créer segment «Membres à relancer %d»";
$GLOBAL['confirmCreateLapsedMembersSegment'] = "Créer le segment «Membres à relancer %d» avec <strong>%s</strong> personne(s)?";
$GLOBAL['lapsedMembersCount'] = "<strong>%s membre%s</strong> étaient dans «Membre %d» mais pas dans «Membre %d».";

// --- donors_summary ---
$GLOBAL['activeQuestion'] = "Actif?";
$GLOBAL['minAmountChf'] = "Min. %s CHF";
$GLOBAL['vsJanMonth'] = "vs jan–%s %d";
$GLOBAL['gapToTarget'] = "Il manque %s CHF pour atteindre %d (%s) — %s%% atteint";
$GLOBAL['targetExceeded'] = "Total %d (%s) dépassé de +%s CHF (+%s%%)";
$GLOBAL['samePeriodCount'] = "Même période %d&nbsp;: %s";
$GLOBAL['alsoDonatedIn'] = "Ont aussi donné en %d";
$GLOBAL['loyalShort'] = "%s fidèles";
$GLOBAL['firstContributionIn'] = "Première contribution en %d";
$GLOBAL['donatedButNotIn'] = "Ont donné en %d mais pas en %d";
$GLOBAL['lapsedShort'] = "%s perdus";
$GLOBAL['membersNotRenewed'] = "Membres %d non reconduits en %d";
$GLOBAL['minAmountLabel'] = "Montant minimum";
$GLOBAL['extendedMode'] = "Mode étendu";
$GLOBAL['includeIfAttestationRequested'] = "Inclure si attestation demandée";
$GLOBAL['attestationFilterExplanation'] = "Affiche les personnes ayant coché &laquo;souhaite une attestation de don&raquo; même si leur total est inférieur au montant minimum sélectionné.";
$GLOBAL['attestationFilterAriaLabel'] = "Explication du filtre attestations";
$GLOBAL['generateAllAttestationsPdf'] = "Générer toutes les attestations en un seul PDF";
$GLOBAL['attestationsYear'] = "Attestations %d";
$GLOBAL['extendedModeWarningIntro'] = "<strong>Mode étendu</strong> — tous les types comptables sont inclus";
$GLOBAL['extendedModeWarningExcluded'] = ", y compris ceux habituellement exclus des dons : %s";
$GLOBAL['extendedModeWarningOutro'] = ". Les totaux ne reflètent pas uniquement les dons.";
$GLOBAL['statusTitleInstitutional'] = "%s / Don institutionnel";
$GLOBAL['donations'] = "Dons";
$GLOBAL['others'] = "Autres";
$GLOBAL['wantsAttestationShort'] = "Souhaite attestation";
$GLOBAL['institutionalDonation'] = "Don institutionnel";
$GLOBAL['attestationOfDonations'] = "Attestation de dons %d";
$GLOBAL['attestationOfDonationsFor'] = "Attestation de dons %d pour %s";
$GLOBAL['generateAttestations'] = "Générer les attestations";
$GLOBAL['bulkAttestConfirmBody'] = "Générer un PDF avec les attestations de dons pour %s personne(s).";
$GLOBAL['bulkAttestDuration'] = "Cette opération peut prendre plusieurs minutes selon le nombre d'entrées.";
$GLOBAL['bulkAttestInProgress'] = "Génération en cours — le PDF s'ouvrira dans un nouvel onglet.";
$GLOBAL['progress'] = "Progression";
$GLOBAL['bulkAttestCanClose'] = "Vous pouvez fermer cette fenêtre. La génération continue dans l'onglet ouvert.";
$GLOBAL['generate'] = "Générer";

// --- Import wizard ---
$GLOBAL['importStep1Subtitle'] = "Étape 1 sur 3 — Sélectionnez un fichier CSV ou TSV.";
$GLOBAL['importErrUpload'] = "Erreur lors de l'envoi du fichier. Vérifiez que le fichier est bien sélectionné.";
$GLOBAL['importErrTooBig'] = "Fichier trop volumineux (maximum 5 MB).";
$GLOBAL['importErrEmpty'] = "Le fichier semble vide ou ne contient pas de données valides.";
$GLOBAL['importErrSession'] = "Session expirée — veuillez recommencer l'import.";
$GLOBAL['csvFileLabel'] = "Fichier CSV / TSV";
$GLOBAL['importHintFormats'] = "Formats acceptés : CSV (virgule ou point-virgule), TSV (tabulation).";
$GLOBAL['importHintEncoding'] = "Encodage UTF-8 ou Latin-1. Première ligne = en-têtes de colonnes.";
$GLOBAL['importHintLimit'] = "Limite : 5 000 lignes par import.";
$GLOBAL['importStep2Subtitle'] = "Étape 2 sur 3 — Associez chaque colonne du fichier à un champ membre.";
$GLOBAL['rowsDetected'] = "%s ligne%s détectée%s.";
$GLOBAL['importTruncatedWarning'] = "Le fichier dépasse la limite de 5 000 lignes — seules les 5 000 premières seront importées.\n      Les lignes suivantes sont <strong>ignorées</strong>. Découpez le fichier pour importer le reste.";
$GLOBAL['importErrNoMapping'] = "Aucune colonne n'est associée à un champ membre — sélectionnez au moins un champ.";
$GLOBAL['fileColumn'] = "Colonne fichier";
$GLOBAL['memberField'] = "Champ membre";
$GLOBAL['examples'] = "Exemples";
$GLOBAL['ignoreField'] = "— ignorer —";
$GLOBAL['addContactsToSegment'] = "Ajouter les contacts à un segment";
$GLOBAL['createSegmentNamed'] = "Créer un segment <strong>%s</strong>";
$GLOBAL['addToExistingSegment'] = "Ajouter à un segment existant";
$GLOBAL['createNewSegment'] = "Créer un nouveau segment";
$GLOBAL['noCategoryOption'] = "— Sans catégorie —";
$GLOBAL['doNotAddToSegment'] = "Ne pas ajouter à un segment";
$GLOBAL['importStep3Subtitle'] = "Étape 3 sur 3 — Résultats de l'import.";
$GLOBAL['contactsCreated'] = "<strong>%s</strong> contact%s créé%s avec succès.";
$GLOBAL['noNewContacts'] = "Aucun nouveau contact créé.";
$GLOBAL['duplicatesDetectedCount'] = "<strong>%s</strong> doublon%s détecté%s — à traiter ci-dessous.";
$GLOBAL['contactsAddedToSegment'] = "<strong>%s</strong> contact%s ajouté%s au segment";
$GLOBAL['viewMemberList'] = "Voir la liste des membres";
$GLOBAL['duplicatesDetected'] = "Doublons détectés";
$GLOBAL['duplicateResolutionHint'] = "Pour chaque doublon, choisissez l'action à effectuer sur le contact existant.";
$GLOBAL['duplicateOf'] = "doublon de";
$GLOBAL['ignore'] = "Ignorer";
$GLOBAL['fillEmptyFields'] = "Compléter les champs vides";
$GLOBAL['overwrite'] = "Écraser";
$GLOBAL['applyChoices'] = "Appliquer les choix";
$GLOBAL['finishWithoutApplying'] = "Terminer sans appliquer";

// --- Suivi ---
$GLOBAL['viewSuiviOf'] = "Voir suivi de %s";
$GLOBAL['dtInfoEntries'] = "_START_–_END_ sur _TOTAL_ entrées";
$GLOBAL['deleteThisEntry'] = "Supprimer cette entrée";

// --- Added: settings views ---
// Staging locale keys — externalized strings from the settings views.
// To be merged into resources_fr.php.

// --- Shared across settings views ---
$GLOBAL['segmentCount'] = "%d segment%s";

// --- settings_group_edit ---
$GLOBAL['cotisantsImported'] = "Cotisants importés dans le segment.";
$GLOBAL['donorsImported'] = "Donateurs importés dans le segment.";
$GLOBAL['viewList'] = "Voir la liste";
$GLOBAL['hideInInterfaces'] = "Masquer dans les interfaces";
$GLOBAL['category'] = "Catégorie";
$GLOBAL['importMembersFromOtherTeams'] = "Importer des membres d'autres segments";
$GLOBAL['oneTimeCopyImportWarning'] = "<strong>Copie ponctuelle</strong> — l'import copie les membres tels qu'ils sont <em>maintenant</em>. Si le segment source change plus tard, ce segment n'est pas mis à jour.";
$GLOBAL['dynamicFilterHint'] = "Pour un filtre dynamique, utilise plutôt un segment combiné dans %s.";
$GLOBAL['importCotisantsOfYear'] = "Importer les cotisants d'une année";
$GLOBAL['oneTimeCopyCotisWarning'] = "<strong>Copie ponctuelle</strong> — membres déjà dans ce segment non touchés. Seuls les cotisants absents sont ajoutés.";
$GLOBAL['typesTakenIntoAccount'] = "Types pris en compte: %s.";
$GLOBAL['noCotisationTypeWarning'] = "Aucun type marqué «cotisation» — configure-les dans %s.";
$GLOBAL['importCotisantsBtn'] = "Importer les cotisants";
$GLOBAL['importDonorsOfYear'] = "Importer les donateurs d'une année";
$GLOBAL['oneTimeCopyDonorsWarning'] = "<strong>Copie ponctuelle</strong> — les membres déjà dans ce segment ne sont pas touchés. Seuls les donateurs absents sont ajoutés.";
$GLOBAL['allDonors'] = "Tous les donateurs";
$GLOBAL['nonInstitutionals'] = "Non-institutionnels";
$GLOBAL['institutionals'] = "Institutionnels";
$GLOBAL['minChf'] = "Min CHF";
$GLOBAL['toImportCount'] = "+%d à importer";
$GLOBAL['zeroToImport'] = "0 à importer";
$GLOBAL['importDonorsBtn'] = "Importer les donateurs";
$GLOBAL['reassignOrDissolve'] = "Réaffecter ou dissoudre…";
$GLOBAL['membersBelongToSegment'] = "%s membre%s appartiennent à ce segment&nbsp;:";
$GLOBAL['transferMembersToOtherSegment'] = "Transférer les membres vers un autre segment";
$GLOBAL['chooseSegmentOption'] = "— choisir le segment —";
$GLOBAL['transferAndDissolve'] = "Transférer et dissoudre";
$GLOBAL['removeAllMembersAndDelete'] = "Retirer tous les membres et supprimer le segment";
$GLOBAL['membersWillBeRemoved'] = "Les %d membre%s seront retirés du segment mais leurs comptes resteront intacts.";
$GLOBAL['removeMembersAndDelete'] = "Retirer les membres et supprimer";
$GLOBAL['segmentHasNoMembers'] = "Ce segment n'a aucun membre.";
$GLOBAL['reassignAndDeleteConfirm'] = "Réaffecter %d membre%s et supprimer le segment «%s» ?";
$GLOBAL['deleteSegmentAndRemoveMembersConfirm'] = "Supprimer le segment «%s» et retirer ses %d membre%s ?";
$GLOBAL['deleteSegmentConfirm'] = "Supprimer le segment «%s» ?";

// --- settings_groups ---
$GLOBAL['noCategoryOptionLower'] = "— sans catégorie —";
$GLOBAL['importMembersFromOtherSegments'] = "Importer les membres d'autres segments";
$GLOBAL['importCopyHint'] = "Copie ponctuelle — les membres sont copiés tels qu'ils sont maintenant. Pour un filtre dynamique, crée plutôt un segment combiné.";
$GLOBAL['hide'] = "Masquer";
$GLOBAL['show'] = "Afficher";
$GLOBAL['createFilter'] = "Créer un filtre";
$GLOBAL['deselect'] = "Désélectionner";
$GLOBAL['selectAll'] = "Tout sélectionner";
$GLOBAL['hiddenPlural'] = "Masqués";
$GLOBAL['renameSegmentAria'] = "Renommer le segment «%s»";
$GLOBAL['saveEnterAria'] = "Enregistrer (Entrée)";
$GLOBAL['cancelEscapeAria'] = "Annuler (Échap)";
$GLOBAL['renameNameAria'] = "Renommer «%s»";
$GLOBAL['segmentSettingsAria'] = "Réglages du segment «%s»";
$GLOBAL['filterNameExample'] = "Ex: Donateurs actifs";
$GLOBAL['selectedCount'] = "%d sélectionné%s";
$GLOBAL['segmentRenamedTo'] = "Segment renommé en «%s».";
$GLOBAL['renameError'] = "Erreur lors du renommage";

// --- settings_general ---
$GLOBAL['settingsSectionAria'] = "Section des réglages";
$GLOBAL['organization'] = "Organisation";
$GLOBAL['orgName'] = "Nom de l'organisation";
$GLOBAL['npaShort'] = "NPA";
$GLOBAL['memberTeamPrefixLabel'] = "Préfixe des segments membres";
$GLOBAL['memberTeamPrefixHelp'] = "Préfixe utilisé pour retrouver les segments membres des années précédentes (ex: «Membre» pour les segments «Membre 2025», «Membre 2026»…).";
$GLOBAL['defaultTeamLabel'] = "Segment affiché par défaut";
$GLOBAL['defaultTeamHelp'] = "Segment sélectionné à l'ouverture de la liste des membres. Choisir le segment correspondant aux membres de l'année en cours (ex: «Membre 2026»). À mettre à jour chaque année.";
$GLOBAL['maskedSuffix'] = "(masqué)";
$GLOBAL['membreTeamLabel'] = "Segment membres (année de référence)";
$GLOBAL['membreTeamHelp'] = "Segment membres de l'année en cours (ex: «Membre 2026»). Utilisé pour les filtres cotisations et affiché dans le tableau de bord Contributions avec comparaison à l'année précédente. À mettre à jour chaque année.";
$GLOBAL['noCotiTeamLabel'] = "Segment membres sans cotisation";
$GLOBAL['noCotiTeamHelp'] = "Membres considérés comme actifs sans payer de cotisation (bénévoles, comité…). Exclus du filtre «Aucune cotisation ces 3 dernières années». Laisser vide si non applicable.";
$GLOBAL['noneOption'] = "— Aucun —";
$GLOBAL['orgIde'] = "Numéro IDE";
$GLOBAL['orgIdeHelp'] = "Numéro d'identification des entreprises suisses (CHE-XXX.XXX.XXX). Figurera sur les attestations de dons.";
$GLOBAL['orgPurpose'] = "But statutaire";
$GLOBAL['orgPurposeHelp'] = "Extrait des statuts décrivant le but de l'organisation. Utilisé dans les documents officiels.";
$GLOBAL['orgTaxStatus'] = "Statut d'exonération fiscale";
$GLOBAL['orgTaxStatusHelp'] = "Ex. : «Exonérée d'impôts AFC-GE depuis 2018». Utilisez le bouton LINDAS pour récupérer automatiquement depuis le registre fédéral, ou saisissez manuellement.";
$GLOBAL['orgTaxStatusPlaceholder'] = "Ex. : Exonérée AFC-GE depuis 2018";
$GLOBAL['zefixVerify'] = "Vérifier via Zefix";
$GLOBAL['zefixChecking'] = "Vérification…";
$GLOBAL['zefixMissingIde'] = "Saisissez d'abord un numéro IDE.";
$GLOBAL['zefixInvalidIde'] = "Numéro IDE invalide (format attendu : CHE-XXX.XXX.XXX).";
$GLOBAL['zefixNotFound'] = "Numéro IDE introuvable dans le registre Zefix.";
$GLOBAL['zefixUnreachable'] = "Impossible de contacter Zefix. Vérifiez votre connexion.";
$GLOBAL['zefixNetworkError'] = "Erreur réseau lors de la vérification Zefix.";

// --- settings_filter_edit ---
$GLOBAL['combinedSegmentCreated'] = "Segment combiné «%s» créé.";
$GLOBAL['assignSegmentsBelowOr'] = "Vous pouvez maintenant assigner des segments ci-dessous, ou";
$GLOBAL['backToListLink'] = "retourner à la liste";
$GLOBAL['combinedSegmentsLower'] = "segments combinés";
$GLOBAL['categoriesLower'] = "catégories";
$GLOBAL['backToLabel'] = "Retour aux %s";
$GLOBAL['memberSegments'] = "Segments membres";
$GLOBAL['viewFilteredList'] = "Voir la liste filtrée";
$GLOBAL['autoSaveOnCheck'] = "Sauvegarde automatique à chaque coche.";
$GLOBAL['hiddenSegmentLower'] = "segment masqué";
$GLOBAL['segmentsInThisCategory'] = "Segments dans cette catégorie";
$GLOBAL['noSegmentsInCategory'] = "Aucun segment dans cette catégorie.";
$GLOBAL['removeFromCategory'] = "Retirer de la catégorie";
$GLOBAL['removeName'] = "Retirer %s";
$GLOBAL['addToCategory'] = "Ajouter à la catégorie";
$GLOBAL['addName'] = "Ajouter %s";
$GLOBAL['moveToCategory'] = "Déplacer dans «%s»";
$GLOBAL['moveNameToCategory'] = "Déplacer %s dans %s";
$GLOBAL['undoLastAction'] = "Annuler la dernière action";
$GLOBAL['saveError'] = "Erreur lors de la sauvegarde";
$GLOBAL['actionUndone'] = "Action annulée";
$GLOBAL['deleteMetagroupHelp'] = "Supprime le segment combiné. Les segments membres ne sont pas affectés.";
$GLOBAL['deleteNameConfirm'] = "Supprimer «%s» ?";
$GLOBAL['memberSegmentsNotDeleted'] = "Les segments membres ne seront pas supprimés.";

// --- settings_filters ---
$GLOBAL['filtersHelp'] = "Regroupent plusieurs segments en un filtre dynamique — accessible depuis la barre de navigation.";
$GLOBAL['noFilters'] = "Aucun filtre.";
$GLOBAL['filterNamePlaceholder'] = "Nom du filtre";

// --- settings_integrity ---
$GLOBAL['integrityHelp'] = "Doublons potentiels dans les membres et segments masqués encore assignés.";
$GLOBAL['allClean'] = "Tout est clean — aucun problème détecté.";
$GLOBAL['membersSameName'] = "Membres avec même nom";
$GLOBAL['firstLastName'] = "Prénom / Nom";
$GLOBAL['records'] = "Fiches";
$GLOBAL['mergeEllipsis'] = "Fusionner…";
$GLOBAL['membersSameEmail'] = "Membres avec même email";
$GLOBAL['hiddenSegmentsInCategory'] = "Segments masqués dans une catégorie";
$GLOBAL['editShort'] = "Éditer";
$GLOBAL['hiddenSegmentsInCombined'] = "Segments masqués dans un segment combiné";
$GLOBAL['combinedSegmentSingular'] = "Segment combiné";
$GLOBAL['hiddenSegmentsWithMembers'] = "Segments masqués avec des membres";
$GLOBAL['members'] = "Membres";
$GLOBAL['member'] = "Membre";
$GLOBAL['membersNoNameTitle'] = "Membres sans nom de famille ni société";
$GLOBAL['membersNoNameHelp'] = "Ces membres n'ont ni nom de famille ni société — ils sont difficilement identifiables.";
$GLOBAL['invalidComptaDates'] = "Dates compta invalides";
$GLOBAL['invalidComptaDatesHelp'] = "Entrées avec date à 0 ou dans le futur.";
$GLOBAL['zeroEmpty'] = "0 (vide)";
$GLOBAL['comptaEntriesWithoutType'] = "Entrées compta sans type";
$GLOBAL['comptaEntriesWithoutTypeHelp'] = "Ces entrées ont <code>type_id = NULL</code> — elles n'apparaissent dans aucune ventilation par type.";
$GLOBAL['malformedEmails'] = "Emails mal formatés";
$GLOBAL['malformedAltEmails'] = "Emails alt. mal formatés";
$GLOBAL['invalidGenderTitle'] = "Genre hors valeurs autorisées";
$GLOBAL['expectedGenderValues'] = "Valeurs attendues : <code>na</code>, <code>hf</code>, <code>f</code>, <code>m</code>.";
$GLOBAL['valueLabel'] = "Valeur";
$GLOBAL['birthdayInFuture'] = "Date de naissance dans le futur";
$GLOBAL['birthDateLabel'] = "Date de naissance";

// --- settings_health ---
$GLOBAL['systemHealth'] = "Santé du système";
$GLOBAL['migErrBackup'] = "Cochez « J'ai fait une sauvegarde » avant d'appliquer les migrations.";
$GLOBAL['migErrNoRecentExport'] = "Vous devez <strong>exporter la base</strong> depuis ce navigateur dans les 30 dernières minutes avant d'appliquer les migrations.";
$GLOBAL['migErrLocked'] = "Une migration est déjà en cours — attendez qu'elle se termine avant de relancer.";
$GLOBAL['migErrGeneric'] = "Échec lors de l'application des migrations — consultez le journal d'audit et restaurez depuis votre sauvegarde si besoin.";
$GLOBAL['migrationsAppliedSuccess'] = "%d migration(s) appliquée(s) avec succès.";
$GLOBAL['migrationDriftLabel'] = "Dérive de migration :";
$GLOBAL['migrationDriftBody'] = "%d migration(s) appliquée(s) dont le fichier a changé depuis (%s). Un fichier de migration déjà appliqué ne doit jamais être modifié — vérifiez le dépôt.";
$GLOBAL['warningLabel'] = "Attention :";
$GLOBAL['pendingMigrationsBody'] = "%d migration(s) de base de données en attente (%s). Appliquez-les avec <code>php html/tools/migrate.php</code>.";
$GLOBAL['systemOperational'] = "Système opérationnel — base à jour, aucune migration en attente.";
$GLOBAL['application'] = "Application";
$GLOBAL['version'] = "Version";
$GLOBAL['commit'] = "Commit";
$GLOBAL['server'] = "Serveur";
$GLOBAL['database'] = "Base de données";
$GLOBAL['connection'] = "Connexion";
$GLOBAL['databaseShort'] = "Base";
$GLOBAL['tables'] = "Tables";
$GLOBAL['migrations'] = "Migrations";
$GLOBAL['appliedLabel'] = "Appliquées";
$GLOBAL['tableMissing'] = "table absente";
$GLOBAL['pendingLabel'] = "En attente";
$GLOBAL['driftChecksumLabel'] = "Dérive (checksum)";
$GLOBAL['lastLabel'] = "Dernière";
$GLOBAL['volumeActivity'] = "Volumétrie &amp; activité";
$GLOBAL['comptaEntriesLabel'] = "Écritures compta";
$GLOBAL['appUsersShort'] = "Utilisateurs app";
$GLOBAL['lastAction'] = "Dernière action";
$GLOBAL['maintenance'] = "Maintenance";
$GLOBAL['exportDbSql'] = "Exporter la base (SQL)";
$GLOBAL['iHaveBackup'] = "J'ai fait une sauvegarde";
$GLOBAL['exportFirstRequired'] = "Exportez d'abord la base ci-dessus (requis).";
$GLOBAL['exportBeforeMigrating'] = "Exportez la base avant de migrer";
$GLOBAL['applyMigrationsCount'] = "Appliquer %d migration(s)";
$GLOBAL['exportHelpParagraph'] = "L'export génère un dump SQL téléchargeable (restaurable via phpMyAdmin ou <code>make restore</code>) — utile
      sur un hébergement <strong>sans accès SSH</strong>. L'application des migrations exécute le DDL en attente ;
      <strong>exportez juste avant</strong> (le DDL n'est pas annulable).";
$GLOBAL['healthEndpointHelp'] = "Un point de contrôle léger pour du monitoring externe est disponible en <code>/health.php</code>
  (JSON <code>{\"status\":\"ok\"|\"degraded\"}</code>, sans authentification ni donnée sensible).";

// --- settings_app_users ---
$GLOBAL['inviteLinkFor'] = "Lien d'invitation pour %s (valable 7 jours) :";
$GLOBAL['inviteLinkHelp'] = "Envoyez ce lien à l'utilisateur. Il définira lui-même son mot de passe.";
$GLOBAL['tempPasswordFor'] = "Mot de passe temporaire pour %s :";
$GLOBAL['tempPasswordHelp'] = "Communiquez-le à l'utilisateur. Il devra le changer à la prochaine connexion.";
$GLOBAL['appUsersTitle'] = "Utilisateurs de l'application";
$GLOBAL['newUser'] = "Nouvel utilisateur";
$GLOBAL['role'] = "Rôle";
$GLOBAL['lastLogin'] = "Dernier login";
$GLOBAL['youBadge'] = "vous";
$GLOBAL['invitePendingTooltip'] = "Invitation en attente — lien non encore utilisé";
$GLOBAL['inviteBadge'] = "invitation";
$GLOBAL['mustChangePasswordTooltip'] = "Doit changer son mot de passe";
$GLOBAL['keyBadge'] = "clé";
$GLOBAL['roleAdmin'] = "Admin";
$GLOBAL['roleManager'] = "Manager";
$GLOBAL['roleReadonly'] = "Lecture seule";
$GLOBAL['resetPasswordShort'] = "Réinitialiser mot de passe";
$GLOBAL['changeMyPassword'] = "Changer mon mot de passe";
$GLOBAL['usernamePatternHint'] = "Lettres, chiffres, point, tiret, underscore";
$GLOBAL['displayName'] = "Nom affiché";
$GLOBAL['viewRightsMatrix'] = "Voir la matrice des droits";
$GLOBAL['rightLabel'] = "Droit";
$GLOBAL['roleReadonlyWrapped'] = "Lecture<br>seule";
$GLOBAL['roleUserWrapped'] = "Utilisa-<br>teur";
$GLOBAL['rightViewData'] = "Consulter membres, compta, suivi";
$GLOBAL['rightEditData'] = "Créer / modifier membres, compta, suivi";
$GLOBAL['rightImportContacts'] = "Importer des contacts (CSV/TSV)";
$GLOBAL['rightManageSettings'] = "Gérer segments, catégories, paramètres";
$GLOBAL['rightMergeArchive'] = "Fusionner / archiver un membre";
$GLOBAL['rightDeleteAnonymize'] = "Supprimer / anonymiser un membre";
$GLOBAL['rightManageAccounts'] = "Gérer les comptes applicatifs";
$GLOBAL['yesLower'] = "oui";
$GLOBAL['noLower'] = "non";
$GLOBAL['tempPassword'] = "Mot de passe temporaire";
$GLOBAL['generateRandomPassword'] = "Générer un mot de passe aléatoire";
$GLOBAL['tempPasswordDefaultHelp'] = "Laisser vide pour utiliser <strong>changeme</strong> par défaut. L'utilisateur devra le changer à la première connexion.";
$GLOBAL['editUserTitle'] = "Modifier %s";
$GLOBAL['accountActive'] = "Compte actif";
$GLOBAL['saveButton'] = "Enregistrer";
$GLOBAL['resetPasswordTitle'] = "Réinitialiser le mot de passe";
$GLOBAL['resetPasswordConfirm'] = "Réinitialiser le mot de passe de «%s»?";
$GLOBAL['deleteUserTitle'] = "Supprimer l'utilisateur";
$GLOBAL['deleteUserConfirm'] = "Supprimer l'utilisateur «%s»? Cette action est irréversible.";

// --- settings_audit_log ---
$GLOBAL['activityLog'] = "Journal d'activité";
$GLOBAL['cleanUp'] = "Nettoyer";
$GLOBAL['auditLogFlushed'] = "Journal nettoyé.";
$GLOBAL['keepLastLabel'] = "Garder les derniers";
$GLOBAL['days'] = "jours";
$GLOBAL['allMasculineOption'] = "— tous —";
$GLOBAL['allFeminineOption'] = "— toutes —";
$GLOBAL['reset'] = "Réinitialiser";
$GLOBAL['entriesTotalCount'] = "%d entrée%s au total";
$GLOBAL['auditLogDisplayCap'] = "(2000 affichées)";
$GLOBAL['deleteAllAuditLogTitle'] = "Supprimer tout le journal";
$GLOBAL['deleteAllAuditLogConfirm'] = "Cette action supprimera <strong>toutes</strong> les entrées du journal. Continuer?";

// --- settings_compta_types ---
$GLOBAL['colorBlue'] = "Bleu";
$GLOBAL['colorGrey'] = "Gris";
$GLOBAL['colorGreen'] = "Vert";
$GLOBAL['colorRed'] = "Rouge";
$GLOBAL['colorYellow'] = "Jaune";
$GLOBAL['colorCyan'] = "Cyan";
$GLOBAL['colorWhite'] = "Blanc";
$GLOBAL['colorDark'] = "Sombre";
$GLOBAL['colorOrange'] = "Orange";
$GLOBAL['colorTeal'] = "Sarcelle";
$GLOBAL['colorPink'] = "Rose";
$GLOBAL['colorPurple'] = "Violet";
$GLOBAL['colorIndigo'] = "Indigo";
$GLOBAL['colorLime'] = "Lime";
$GLOBAL['comptaTypesTitle'] = "Types de compta";
$GLOBAL['newComptaType'] = "Nouveau type";
$GLOBAL['labelField'] = "Label";
$GLOBAL['color'] = "Couleur";
$GLOBAL['entriesColumn'] = "Entrées";
$GLOBAL['cotiTooltip'] = "Compte comme cotisation";
$GLOBAL['cotiShort'] = "Coti";
$GLOBAL['exclDonTooltip'] = "Exclu des dons";
$GLOBAL['exclDonShort'] = "Excl. don";
$GLOBAL['institTooltip'] = "Versement institutionnel";
$GLOBAL['institShort'] = "Instit.";
$GLOBAL['yesClickToDisable'] = "Oui — cliquer pour désactiver";
$GLOBAL['noClickToEnable'] = "Non — cliquer pour activer";
$GLOBAL['deleteShort'] = "Suppr.";
$GLOBAL['orderLabel'] = "Ordre";
$GLOBAL['deleteComptaTypeTitle'] = "Supprimer ce type";
$GLOBAL['deleteComptaTypeConfirm'] = "Supprimer ce type de cotisation? Cette action est irréversible.";

// --- settings_categories ---
$GLOBAL['categoriesHelp'] = "Organisent les segments en sections visuelles dans les listes. Un segment appartient à une seule catégorie.";
$GLOBAL['noCategories'] = "Aucune catégorie.";
$GLOBAL['categoryNamePlaceholder'] = "Nom de la catégorie";

// --- Added: member views, install, login, attestations ---
// Staging locale keys — users views, standalone entry points (install, login,
// set-password) and attestation generators. To be merged into resources_fr.php.

// --- users_anonymize.php / users_merge.php (shared) ---
$GLOBAL['memberNotFound'] = "Membre introuvable.";

// --- users_anonymize.php ---
$GLOBAL['anonymizeComptaCount'] = "Ce profil possède <strong>%d écriture%s comptable%s</strong>.";
$GLOBAL['anonymizeNoDeleteReason'] = "La suppression définitive est impossible pour des raisons de traçabilité comptable.";
$GLOBAL['anonymizeExplanation'] = "L'anonymisation efface toutes les données personnelles (nom, prénom, adresse, email, téléphone…) tout en conservant l'historique comptable associé à cet identifiant interne.";
$GLOBAL['anonymizeIrreversibleIntro'] = "<strong>Cette opération est irréversible.</strong> Les données suivantes seront effacées&nbsp;:";
$GLOBAL['anonymizeErasedFieldsList'] = "nom, prénom, société, adresse, NPA, email, téléphones, web, date de naissance, note.";

// --- users_edit_form.php ---
$GLOBAL['noNameId'] = "Sans nom #%d";
$GLOBAL['memberSheet'] = "Fiche";
$GLOBAL['history'] = "Historique";
$GLOBAL['historyShort'] = "Hist.";
$GLOBAL['archiveModalBody'] = "Le profil sera retiré de toutes les listes.<br>Désarchivable à tout moment.";
$GLOBAL['archivedBanner'] = "Ce profil est <strong>archivé</strong> — il n'apparaît dans aucune liste.";
$GLOBAL['totalSince'] = "Total depuis %s";
$GLOBAL['otherPayments'] = "Autres versements";
$GLOBAL['anonymizeTooltip'] = "Ce profil a des données comptables — la suppression est impossible. L'anonymisation efface les données personnelles tout en conservant l'historique comptable.";

// --- users_general_data.php ---
$GLOBAL['clickToEdit'] = "Cliquer pour modifier";
$GLOBAL['googleMaps'] = "Google Maps";
$GLOBAL['createdAtLabel'] = "Créé : %s";
$GLOBAL['modifiedAtLabel'] = "Modifié : %s";
$GLOBAL['emailAltHint'] = "Adresse historique / alternative — non utilisée pour les envois";
$GLOBAL['ttFormatting'] = "Formatage";
$GLOBAL['ttBold'] = "Gras (Ctrl+B)";
$GLOBAL['ttBoldShort'] = "Gras";
$GLOBAL['ttItalic'] = "Italique (Ctrl+I)";
$GLOBAL['ttItalicShort'] = "Italique";
$GLOBAL['ttBulletList'] = "Liste à puces";
$GLOBAL['ttOrderedList'] = "Liste numérotée";
$GLOBAL['ttUndo'] = "Annuler (Ctrl+Z)";
$GLOBAL['ttRedo'] = "Rétablir (Ctrl+Shift+Z)";
$GLOBAL['ttRedoShort'] = "Rétablir";
$GLOBAL['saveBtn'] = "Enregistrer";

// --- users_history.php ---
$GLOBAL['changeHistory'] = "Historique des modifications";
$GLOBAL['changeHistoryHint'] = "Toutes les actions enregistrées pour ce membre.";
$GLOBAL['noJournalEntriesForMember'] = "Aucune entrée dans le journal pour ce membre.";

// --- users_inactive.php ---
$GLOBAL['archivedMembers'] = "Membres archivés";
$GLOBAL['archivedMembersHint'] = "Profils archivés. Ils ne sont plus visibles dans les listes.";
$GLOBAL['noArchivedMembers'] = "Aucun membre archivé.";
$GLOBAL['idLabel'] = "ID";
$GLOBAL['unarchive'] = "Désarchiver";
$GLOBAL['unarchiveConfirmTitle'] = "Désarchiver ce membre&nbsp;?";
$GLOBAL['unarchiveModalBody'] = "Le profil réapparaîtra dans toutes les listes.";

// --- users_list.php ---
$GLOBAL['importDone'] = "Import terminé.";
$GLOBAL['duplicatesUpdated'] = "<strong>%d</strong> doublon%s mis à jour.";
$GLOBAL['noCotiExclusion'] = " Les membres du segment %s sont exclus.";
$GLOBAL['filterDescCotiUnpaid3y'] = "Profils ayant payé au moins une cotisation dans leur historique, mais aucune lors des 3 dernières années (%s–%s).";
$GLOBAL['filterDescNoActivity10y'] = "Profils actifs sans aucune entrée comptable (cotisation, don ou autre) depuis %s.";
$GLOBAL['filterDescNonInstitLastYear'] = "Profils ayant effectué au moins un versement non institutionnel en %s — inclut cotisations, dons et tout autre type non marqué «&nbsp;Institutionnel&nbsp;» dans les types compta.";
$GLOBAL['filterDescCotiUnpaidCurrent'] = "Membres dont la cotisation %s n'a pas encore été enregistrée.";
$GLOBAL['quickFilters'] = "Filtres rapides";
$GLOBAL['typesHeader'] = "Types";
$GLOBAL['comptaHistory'] = "Historique compta";
$GLOBAL['fh'] = "Madame et Monsieur";
$GLOBAL['entriesCountShort'] = "%d entr.";
$GLOBAL['cotiCountShort'] = "%d coti";
$GLOBAL['lastActivityYear'] = "dernier: %s";
$GLOBAL['missedRevenue'] = "manque a gagner de CHF %d pour %s avec les cotis non pay&eacute;es...";
$GLOBAL['dtInfoProfiles'] = "_TOTAL_ profils";
$GLOBAL['dtInfoFilteredMasc'] = "(filtrés sur _MAX_)";

// --- users_member_of.php ---
$GLOBAL['segmentNumber'] = "segment #%d";
$GLOBAL['membershipAdded'] = "Ajouté : %s";
$GLOBAL['membershipRemoved'] = "Retiré : %s";
$GLOBAL['noSegments'] = "Aucun segment.";
$GLOBAL['removeFromSegment'] = "Retirer de %s";
$GLOBAL['hiddenSegmentPrefix'] = "[Segment masqué] ";
$GLOBAL['hiddenSegments'] = "Segments masqués";
$GLOBAL['hideHiddenSegments'] = "Masquer les segments cachés";

// --- users_merge.php ---
$GLOBAL['invalidMergeParams'] = "Paramètres de fusion invalides.";
$GLOBAL['sexLabel'] = "Sexe";
$GLOBAL['telShort'] = "Tél.";
$GLOBAL['telProfShort'] = "Tél. prof.";
$GLOBAL['birthShort'] = "Naissance";
$GLOBAL['noteLabel'] = "Note";
$GLOBAL['memberMerge'] = "Fusion membres";
$GLOBAL['mergeTwoMembers'] = "Fusionner deux fiches membres";
$GLOBAL['mergeInstruction'] = "Cliquez la valeur à conserver pour chaque champ divergent.";
$GLOBAL['allDataIdentical'] = "Toutes les données sont identiques";
$GLOBAL['divergentFieldsCount'] = "%d champ%s divergent%s";
$GLOBAL['mergeTableAria'] = "Comparaison des fiches membres";
$GLOBAL['fieldLabel'] = "Champ";
$GLOBAL['chooseValueA'] = "Choisir la valeur A pour %s";
$GLOBAL['chooseValueB'] = "Choisir la valeur B pour %s";
$GLOBAL['emptyValue'] = "vide";
$GLOBAL['keepBothNotes'] = "Garder les deux notes (survivant en premier)";
$GLOBAL['linkedDataAuto'] = "Données liées (fusionnées automatiquement)";
$GLOBAL['profileA'] = "Profil A";
$GLOBAL['profileB'] = "Profil B";
$GLOBAL['comptaEntries'] = "Entrées compta";
$GLOBAL['suiviEntries'] = "Entrées suivi";
$GLOBAL['survivorProfile'] = "Profil survivant (conserve son ID)";
$GLOBAL['sourceProfileAfterMerge'] = "Profil source après fusion";
$GLOBAL['mergeDeleteWarning'] = "Irréversible — toutes les données du profil source seront effacées.";
$GLOBAL['resolveAllFields'] = "Résolvez tous les champs divergents pour continuer.";
$GLOBAL['mergeConfirmIntro'] = "Cette opération est irréversible. Vérifiez le résumé avant de confirmer.";
$GLOBAL['survivorLabel'] = "Profil survivant :";
$GLOBAL['sourceDeletedLabel'] = "Profil source supprimé :";
$GLOBAL['yesIrreversible'] = "oui (irréversible)";
$GLOBAL['noArchivedOnly'] = "non — archivé uniquement";
$GLOBAL['fieldsModifiedSummary'] = "%d champ(s) modifié(s) selon votre sélection.";
$GLOBAL['mergeReattachInfo'] = "Toutes les entrées compta et suivi du profil source seront rattachées au profil survivant.";
$GLOBAL['mergeSegmentsInfo'] = "Les appartenances aux segments seront fusionnées (dédoublonnage automatique).";

// --- login.php ---
$GLOBAL['invalidRequest'] = "Requête invalide. Veuillez réessayer.";
$GLOBAL['badCredentials'] = "Identifiant ou mot de passe incorrect.";
$GLOBAL['loginTitle'] = "Connexion — %s";
$GLOBAL['signIn'] = "Se connecter";

// --- set-password.php ---
$GLOBAL['invalidLink'] = "Lien invalide.";
$GLOBAL['linkExpired'] = "Ce lien est invalide ou a expiré. Demandez un nouvel accès à l'administrateur.";
$GLOBAL['passwordsMismatch'] = "Les deux mots de passe ne correspondent pas.";
$GLOBAL['setMyPassword'] = "Définir mon mot de passe";
$GLOBAL['passwordSetSuccess'] = "Mot de passe défini. Vous pouvez maintenant <a href=\"login.php\">vous connecter</a>.";
$GLOBAL['backToLogin'] = "Retour au login";
$GLOBAL['welcomeUser'] = "Bienvenue %s.";
$GLOBAL['choosePasswordActivate'] = "Choisissez un mot de passe pour activer votre compte.";
$GLOBAL['minPasswordHint'] = "8 caractères minimum.";
$GLOBAL['setPasswordBtn'] = "Définir le mot de passe";

// --- install.php ---
$GLOBAL['installTitle'] = "Installation — MemberBase";
$GLOBAL['installWizardSubtitle'] = "Assistant d'installation";
$GLOBAL['stepPrereqs'] = "Prérequis";
$GLOBAL['stepDatabase'] = "Base de données";
$GLOBAL['stepSchema'] = "Schéma";
$GLOBAL['stepOrganisation'] = "Organisation";
$GLOBAL['stepAdminAccount'] = "Compte admin";
$GLOBAL['dbNameRequired'] = "Nom de la base de données requis.";
$GLOBAL['dbUserRequired'] = "Utilisateur requis.";
$GLOBAL['cannotWriteConf'] = "Impossible d'écrire <code>%s</code>. Vérifiez les permissions du répertoire <code>conf/</code>.";
$GLOBAL['connectionFailed'] = "Connexion échouée : %s";
$GLOBAL['sqlError'] = "Erreur SQL : %s";
$GLOBAL['orgNameRequired'] = "Nom de l'organisation requis.";
$GLOBAL['genericError'] = "Erreur : %s";
$GLOBAL['usernameRequired'] = "Identifiant requis.";
$GLOBAL['usernameInvalid'] = "Identifiant invalide (2–50 car., lettres/chiffres/.-_).";
$GLOBAL['installPasswordTooShort'] = "Mot de passe trop court (min. 8 caractères).";
$GLOBAL['installPasswordsMismatch'] = "Les mots de passe ne correspondent pas.";
$GLOBAL['usernameTakenNamed'] = "L'identifiant «%s» est déjà utilisé.";
$GLOBAL['prereqPhpVersion'] = "PHP ≥ 8.1";
$GLOBAL['prereqPdoMysql'] = "Extension PDO MySQL";
$GLOBAL['prereqMbstring'] = "Extension mbstring";
$GLOBAL['prereqConfWritable'] = "Écriture dans conf/";
$GLOBAL['statusOk'] = "OK";
$GLOBAL['statusMissing'] = "Manquante";
$GLOBAL['statusNotWritable'] = "Non accessible en écriture";
$GLOBAL['statusDirMissing'] = "Répertoire absent";
$GLOBAL['prereqsServerTitle'] = "Prérequis serveur";
$GLOBAL['fixPrereqsWarning'] = "Corrigez les prérequis avant de continuer.";
$GLOBAL['continueBtn'] = "Continuer";
$GLOBAL['dbConnectionTitle'] = "Connexion à la base de données";
$GLOBAL['dbConnectionHint'] = "Les paramètres seront enregistrés dans <code>conf/db.php</code> (hors webroot).";
$GLOBAL['hostLabel'] = "Hôte";
$GLOBAL['portLabel'] = "Port";
$GLOBAL['dbNameLabel'] = "Nom de la base";
$GLOBAL['testConnectionBtn'] = "Tester la connexion et continuer";
$GLOBAL['schemaInitTitle'] = "Initialisation du schéma";
$GLOBAL['schemaInitHint'] = "Création des tables depuis <code>schema.sql</code>. Les tables existantes ne sont pas modifiées.";
$GLOBAL['tablesCreated'] = "Tables créées :";
$GLOBAL['createTablesBtn'] = "Créer les tables";
$GLOBAL['orgConfigTitle'] = "Configuration de l'organisation";
$GLOBAL['orgConfigHint'] = "Ces informations apparaissent dans le titre de l'application et sur les attestations de dons. Un groupe membres pour l'année en cours sera créé automatiquement.";
$GLOBAL['orgNameLabel'] = "Nom de l'organisation";
$GLOBAL['orgNamePlaceholder'] = "Mon association";
$GLOBAL['npaLabel'] = "NPA";
$GLOBAL['memberPrefixLabel'] = "Préfixe des groupes membres";
$GLOBAL['memberPrefixHint'] = "Exemple : «Membre» → groupes nommés «Membre 2024», «Membre 2025»…";
$GLOBAL['seedTypesTitle'] = "Types de cotisation / don créés automatiquement";
$GLOBAL['seedTypesHint'] = "Si la table <code>compta_type</code> est vide, ces 4 types seront insérés. Modifiables ensuite dans Réglages.";
$GLOBAL['seedCotisationDesc'] = "cotisation annuelle (is_cotisation=1, exclue des dons)";
$GLOBAL['seedDonDesc'] = "don général";
$GLOBAL['seedEventDesc'] = "recettes événements (exclues des dons)";
$GLOBAL['seedInstitDesc'] = "donateurs institutionnels (is_institutional=1)";
$GLOBAL['saveAndContinueBtn'] = "Enregistrer et continuer";
$GLOBAL['adminAccountTitle'] = "Compte administrateur";
$GLOBAL['adminAccountHint'] = "Premier compte admin — accès complet à l'application.";
$GLOBAL['usernameFormatHint'] = "2–50 caractères, lettres/chiffres/.-_";
$GLOBAL['displayNameLabel'] = "Nom affiché";
$GLOBAL['adminDisplayNamePlaceholder'] = "Administrateur";
$GLOBAL['emailLong'] = "E-mail";
$GLOBAL['optionalSuffix'] = "(optionnel)";
$GLOBAL['minPasswordChars'] = "Minimum 8 caractères.";
$GLOBAL['createAccountBtn'] = "Créer le compte et terminer";
$GLOBAL['installDoneTitle'] = "Installation terminée";
$GLOBAL['installDoneMessage'] = "Base de données initialisée, organisation configurée, compte admin créé.";
$GLOBAL['deleteInstallHint'] = "Supprimez <code>install.php</code> une fois connecté.";
$GLOBAL['goToAppBtn'] = "Accéder à l'application";

// --- attestation_don.php / attestation_bulk.php ---
$GLOBAL['pdftkError'] = "Erreur pdftk (code %d):";
$GLOBAL['noDonorsFound'] = "Aucun donateur trouvé pour %d (min CHF %d)";
$GLOBAL['pdfGenerationError'] = "Erreur génération PDFs:";
$GLOBAL['pdftkMergeError'] = "Erreur merge pdftk:";

// --- Added: delete confirms, change password, index banner ---
$GLOBAL['archiveKeepsHistoryHint'] = "— conserve l'historique, retiré de toutes les vues";
$GLOBAL['irreversibleHint'] = "— irréversible";
$GLOBAL['actionIrreversible'] = "Cette action est irréversible.";
$GLOBAL['content'] = "Contenu";
$GLOBAL['forcePasswordChangeNotice'] = "Veuillez définir un nouveau mot de passe avant de continuer.";
$GLOBAL['changePasswordTitle'] = "Changer le mot de passe";
$GLOBAL['currentPassword'] = "Mot de passe actuel";
$GLOBAL['newPassword'] = "Nouveau mot de passe";
$GLOBAL['confirmationLabel'] = "Confirmation";
$GLOBAL['pendingDbMigrationsLabel'] = "migration%s de base de données en attente";
$GLOBAL['pendingMigrationsBannerBody'] = "Appliquez-la%s depuis
            <a href=\"%s?view=settings&amp;tab=health\">Réglages → Santé</a>
            (sans SSH), ou en ligne de commande <code>php html/tools/migrate.php</code>,
            après avoir sauvegardé la base. Tant que ce n'est pas fait, certaines
            fonctionnalités peuvent ne pas marcher correctement.\n";
$GLOBAL['language']              = "Langue";
$GLOBAL['interfaceLanguage']     = "Langue de l'interface";
$GLOBAL['interfaceLanguageHelp'] = "Appliquée à votre compte, sur toutes vos sessions.";

// SMTP settings
$GLOBAL['smtpSettings']        = "Email";
$GLOBAL['smtpServer']          = "Serveur SMTP";
$GLOBAL['smtpHost']            = "Hôte SMTP";
$GLOBAL['smtpPort']            = "Port";
$GLOBAL['smtpEncryption']      = "Chiffrement";
$GLOBAL['smtpEncNone']         = "Aucun";
$GLOBAL['smtpAuth']            = "Authentification requise";
$GLOBAL['smtpUser']            = "Nom d'utilisateur";
$GLOBAL['smtpPassword']        = "Mot de passe";
$GLOBAL['smtpPasswordSet']     = "Mot de passe enregistré";
$GLOBAL['smtpPasswordHelp']    = "Laissez vide pour conserver le mot de passe actuel.";
$GLOBAL['smtpSender']          = "Expéditeur";
$GLOBAL['smtpFromName']        = "Nom de l'expéditeur";
$GLOBAL['smtpFromEmail']       = "Adresse de l'expéditeur";
$GLOBAL['smtpReplyTo']         = "Adresse de réponse (Reply-To)";
$GLOBAL['smtpReplyToHelp']     = "Optionnel. Si vide, les réponses vont à l'adresse de l'expéditeur.";
$GLOBAL['smtpTest']            = "Tester la configuration";
$GLOBAL['smtpTestTo']          = "Envoyer un email de test à";
$GLOBAL['smtpTestSend']        = "Envoyer";
$GLOBAL['smtpTesting']         = "Envoi en cours…";
$GLOBAL['smtpTestOk']          = "Email envoyé avec succès.";
$GLOBAL['smtpTestFail']        = "Échec de l'envoi. Vérifiez la configuration.";
$GLOBAL['smtpDebugToggle']     = "Afficher le journal de connexion SMTP";
$GLOBAL['smtpTestMissingTo']   = "Veuillez saisir une adresse email de destination.";

// Email log journal
$GLOBAL['emailLog']              = "Journal des envois";
$GLOBAL['emailLogDate']          = "Date";
$GLOBAL['emailLogTo']            = "Destinataire";
$GLOBAL['emailLogSubject']       = "Sujet";
$GLOBAL['emailLogStatus']        = "Statut";
$GLOBAL['emailLogStatusSent']    = "Envoyé";
$GLOBAL['emailLogStatusError']   = "Erreur";
$GLOBAL['emailLogEmpty']         = "Aucun email envoyé pour l'instant.";
$GLOBAL['emailLogPurge']         = "Vider le journal";
$GLOBAL['emailLogPurgeConfirm']  = "Supprimer tous les entrées du journal des emails ?";
$GLOBAL['emailLogPurged']        = "Journal vidé.";
$GLOBAL['emailLogResend']        = "Renvoyer";
$GLOBAL['emailLogResending']     = "Renvoi en cours…";
$GLOBAL['emailLogResendOk']      = "Email renvoyé avec succès.";
$GLOBAL['emailLogResendFail']    = "Échec du renvoi.";

// Email detail view
$GLOBAL['emailSent']             = "Email envoyé";
$GLOBAL['viewEmail']             = "Voir l'email";
$GLOBAL['emailTo']               = "Destinataire";
$GLOBAL['emailStatus']           = "Statut";
$GLOBAL['emailStatusSent']       = "Envoyé";
$GLOBAL['emailStatusError']      = "Erreur";
$GLOBAL['emailViewPlaintext']    = "Voir la version texte brut";

// Email templates
$GLOBAL['emailTemplates']              = "Modèles d'email";
$GLOBAL['emailTemplatesSaved']         = "Modèle enregistré.";
$GLOBAL['emailTemplateSubject']        = "Sujet";
$GLOBAL['emailTemplateBody']           = "Corps du message";
$GLOBAL['emailTemplateBodyText']       = "Texte brut";
$GLOBAL['emailTemplateBodyHtml']       = "HTML";
$GLOBAL['emailTemplateHelp']           = "Variables disponibles : {{greeting}}, {{greeting_text}}, {{display_name}}, {{firstname}}, {{lastname}}, {{society}}, {{email}}, {{org_name}}, {{contact_email}}, {{org_address}}, {{org_city}}, {{org_web}}";
$GLOBAL['emailTemplateHtmlHelp']       = "Template HTML de l'email. Laisser vide pour utiliser la version texte seule. Variables identiques à l'onglet texte.";
$GLOBAL['emailTemplateVarsHelp']       = "Variables disponibles";
$GLOBAL['emailTemplateWelcome']        = "Email de bienvenue";
$GLOBAL['emailTemplateCotiReminder']   = "Rappel de cotisation";
$GLOBAL['emailTemplateAttestationDon'] = "Attestation de don";
$GLOBAL['emailWelcomeEnabled']         = "Envoyer un email de bienvenue lors de la création d'un membre";

// Welcome email manual send
$GLOBAL['sendWelcomeEmail']        = "Envoyer email de bienvenue";
$GLOBAL['sendWelcomeEmailSending'] = "Envoi en cours…";
$GLOBAL['sendWelcomeEmailOk']      = "Email de bienvenue envoyé.";
$GLOBAL['sendWelcomeEmailFail']    = "Échec de l'envoi.";
$GLOBAL['sendWelcomeEmailNoEmail'] = "Ce membre n'a pas d'adresse email.";
$GLOBAL['sendWelcomeEmailAlreadySent'] = "Email de bienvenue déjà envoyé le %s";

// Bulk welcome email mark
$GLOBAL['welcomeEmailBulkTitle']      = "Marquage en masse — email de bienvenue";
$GLOBAL['welcomeEmailBulkDesc']       = "%d membre(s) actif(s) n'ont pas encore le marqueur « email de bienvenue envoyé ». Marquez-les tous comme traités pour éviter d'envoyer un email de bienvenue à des membres existants.";
$GLOBAL['welcomeEmailBulkConfirm']    = "Je comprends que ces membres ne recevront jamais l'email de bienvenue automatique";
$GLOBAL['welcomeEmailBulkBtn']        = "Marquer tous comme traités";
$GLOBAL['welcomeEmailBulkOk']         = "%d membre(s) marqué(s) comme traités.";
$GLOBAL['welcomeEmailBulkErrConfirm'] = "Veuillez cocher la case de confirmation.";

// Compta recap batch email
$GLOBAL['comptaRecapTitle']          = "Emails";
$GLOBAL['comptaRecapPageTitle']      = "Récapitulatifs comptables par email";
$GLOBAL['comptaRecapPendingMembers'] = "membres en attente";
$GLOBAL['comptaRecapPendingEntries'] = "entrées non notifiées";
$GLOBAL['comptaRecapLastBatch']      = "dernier envoi";
$GLOBAL['comptaRecapSendBtn']        = "Envoyer les récapitulatifs (%d membres)";
$GLOBAL['comptaRecapNoPending']      = "Aucune entrée en attente — tous les membres ont été notifiés.";
$GLOBAL['comptaRecapNoEntries']      = "Aucune entrée comptable pour cette année.";
$GLOBAL['comptaRecapNoEntriesForce'] = "Aucune entrée comptable trouvée pour cette année, même en mode forcé.";
$GLOBAL['comptaRecapHelp']           = "Un email par membre est envoyé, regroupant toutes les entrées non encore notifiées. Les entrées des membres sans adresse email sont marquées comme traitées sans envoi.";
$GLOBAL['comptaRecapSentOk']         = "%d membre(s) notifié(s) avec succès.";
$GLOBAL['comptaRecapSkipped']        = "%d membre(s) sans email ignoré(s).";
$GLOBAL['comptaRecapSinceLastBatch'] = "depuis votre dernier récapitulatif du %s";
$GLOBAL['comptaRecapSinceYear']      = "en %d";
$GLOBAL['comptaRecapSinceFirst']     = "depuis votre adhésion";
$GLOBAL['comptaRecapPreviewHint']    = "Cliquez sur une ligne pour prévisualiser l'email avant envoi.";
$GLOBAL['comptaRecapModalTitle']     = "Prévisualisation de l'email";
$GLOBAL['comptaRecapSendLater']      = "Envoyer plus tard";
$GLOBAL['comptaRecapSendOne']        = "Envoyer";
$GLOBAL['comptaRecapNoEmail']        = "sans email";
$GLOBAL['comptaRecapExtended']       = "Mode étendu (afficher aussi les envoyés)";
$GLOBAL['comptaRecapAlreadySent']    = "Déjà notifiés (%d membres)";
$GLOBAL['comptaRecapSentOn']         = "Notifié le %s";
$GLOBAL['comptaRecapNoEmailSection'] = "Membres sans adresse email (%d)";
$GLOBAL['comptaRecapForceAll']       = "Forcer l'envoi (toutes les entrées de l'année, même déjà envoyées)";
$GLOBAL['comptaRecapScopeNew']       = "Nouvelles entrées";
$GLOBAL['comptaRecapScopeAll']       = "Toutes les entrées";
$GLOBAL['comptaRecapSendUserBtn']    = "Envoyer un récapitulatif";

// Bulk compta notified mark (Settings → Santé)
$GLOBAL['comptaBulkTitle']           = "Marquage en masse — récapitulatifs comptables";
$GLOBAL['comptaBulkDesc']            = "%d entrée(s) compta existante(s) ne sont pas encore marquées comme notifiées. Marquez-les pour éviter d'envoyer un récapitulatif historique aux membres.";
$GLOBAL['comptaBulkConfirm']         = "Je comprends que ces entrées ne seront pas incluses dans le prochain envoi";
$GLOBAL['comptaBulkBtn']             = "Marquer toutes comme traitées";
$GLOBAL['comptaBulkOk']              = "%d entrée(s) marquée(s) comme traitées.";
$GLOBAL['comptaBulkErrConfirm']      = "Veuillez cocher la case de confirmation.";
// Payment receipt email (confirmation on compta entry add)
$GLOBAL['emailTemplatePaymentReceipt'] = "Confirmation de réception de paiement";
$GLOBAL['sendReceiptLabel']            = "Envoyer une confirmation au membre";
$GLOBAL['sendReceiptNoEmail']          = "Pas d'adresse e-mail enregistrée";
// Zero-sum compta entries toggle
$GLOBAL['showZeroEntries'] = "%d versement(s) à CHF 0.00 masqué(s) — afficher";
$GLOBAL['hideZeroEntries'] = "%d versement(s) à CHF 0.00 affiché(s) — masquer";
// Cotisation year field
$GLOBAL['cotisationYearLabel'] = "Année de cotisation";
