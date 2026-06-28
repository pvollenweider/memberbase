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
$GLOBAL['groupModified']   = "Groupe modifié.";
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
$GLOBAL['deletePermanently']    = "Supprimer définitivement";
$GLOBAL['deleteOrArchive']      = "Supprimer ou archiver ce membre";
$GLOBAL['deleteEntry']          = "Supprimer cette écriture";
$GLOBAL['deleteSuiviEntry']     = "Supprimer cette entrée de suivi";
$GLOBAL['archiveMember']        = "Archiver ce membre";
$GLOBAL['anonymizeProfile']     = "Anonymiser ce profil";
$GLOBAL['editGroup']            = "Modifier le groupe";
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
$GLOBAL['addGroups']       = "Ajouter des groupes";
$GLOBAL['addMetagroup']    = "Ajouter meta-groupe";
$GLOBAL['addTeam']         = "Ajouter un groupe";
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
$GLOBAL['groupName']       = "Nom du meta-groupe";
$GLOBAL['groups']          = "Groupes";
$GLOBAL['hf']              = "Monsieur et Madame";
$GLOBAL['lastEntry']       = "Rapports";
$GLOBAL['lastEntryCompta'] = "Journal compta";
$GLOBAL['lastEntrySuivi']  = "Journal suivi";
$GLOBAL['lastModif']       = "Dernière modification";
$GLOBAL['lastName']        = "Nom";
$GLOBAL['libele']          = "Libellé";
$GLOBAL['list']            = "Listes";
$GLOBAL['m']               = "Homme";
$GLOBAL['manageMategroups']= "Gestion des meta-groupes";
$GLOBAL['manageTeam']      = "Groupes";
$GLOBAL['memberOf']        = "Appartenance aux groupes";
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
$GLOBAL['teamName']        = "Nom du groupe";
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
?>
