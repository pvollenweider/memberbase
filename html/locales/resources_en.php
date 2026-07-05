<?php
// English locale — loaded over the French base bundle (resources_fr.php).
// To add a new language, duplicate this file and translate the values.

// --- Quick-filter labels ---
$GLOBAL['allExceptArchives']       = "Everyone";
$GLOBAL['cotiUnpayed']             = "Membership fee " . date('Y') . " unpaid";
$GLOBAL['cotiUnpayedLast3Years']   = "No membership fee in the last 3 years";
$GLOBAL['nothingLast10Years']      = "No payment in the last 10 years";
$GLOBAL['nonInstitPayedSomethingLastYear'] = "Non-institutional donor active in " . (date('Y') - 1);

// --- Navigation ---
$GLOBAL['search']          = "Search";
$GLOBAL['logout']          = "Log out";
$GLOBAL['changePassword']  = "Password";
$GLOBAL['donationOverview']= "Donation overview";
$GLOBAL['administration']  = "Administration";

// --- Common actions ---
$GLOBAL['save']            = "Save";
$GLOBAL['saved']           = "Saved.";
$GLOBAL['groupModified']   = "Segment modified.";
$GLOBAL['confirmPassword'] = "Confirm password";
$GLOBAL['cancel']          = "Cancel";
$GLOBAL['close']           = "Close";
$GLOBAL['confirm']         = "Confirm";
$GLOBAL['archive']              = "Archive";
$GLOBAL['anonymize']            = "Anonymize";
$GLOBAL['edit']                 = "Edit";
$GLOBAL['confirmMerge']         = "Confirm merge";
$GLOBAL['confirmAnonymize']     = "Confirm anonymization";
$GLOBAL['wantsAttestationLabel']= "Wants a donation receipt";
$GLOBAL['deleteAll']            = "Delete all";
$GLOBAL['documentation']        = "Documentation";
$GLOBAL['deletePermanently']    = "Delete permanently";
$GLOBAL['deleteOrArchive']      = "Delete or archive this member";
$GLOBAL['deleteEntry']          = "Delete this entry";
$GLOBAL['deleteSuiviEntry']     = "Delete this follow-up entry";
$GLOBAL['archiveMember']        = "Archive this member";
$GLOBAL['anonymizeProfile']     = "Anonymize this profile";
$GLOBAL['editGroup']            = "Edit segment";
$GLOBAL['editCompta']           = "Edit accounting entry";
$GLOBAL['editMetagroup']        = "Edit";

// --- Dashboard / resume ---
$GLOBAL['donors']          = "Donors";
$GLOBAL['activeMembers']   = "Active members";
$GLOBAL['contributions']   = "Contributions";
$GLOBAL['loyalDonors']     = "Loyal";
$GLOBAL['newDonors']       = "New";
$GLOBAL['lapsedDonors']    = "Lapsed";
$GLOBAL['last12Months']    = "Last 12 months";
$GLOBAL['last24Months']    = "Last 24 months";
$GLOBAL['allEntries']      = "All entries";
$GLOBAL['wantsAttestation']= "Wants a receipt";
$GLOBAL['donationsOnly']   = "Donations only";
$GLOBAL['withoutType']     = "Without type";
$GLOBAL['historyByYear']   = "History by year";
$GLOBAL['distByType']      = "Breakdown by type";
$GLOBAL['nonDonation']     = "non-donation";

// --- Member form sections ---
$GLOBAL['contactInfo']     = "Contact details";
$GLOBAL['additionalInfo']  = "Additional info";
$GLOBAL['city']            = "City";
$GLOBAL['country']         = "Country";

// --- Settings ---
$GLOBAL['saveSettings']    = "Save";
$GLOBAL['adminOnly']       = "Access restricted to administrators.";

// --- UI labels ---
$GLOBAL['add']             = "<i class=\"fas fa-plus\"></i> Add";
$GLOBAL['addBtn']          = "Add";
$GLOBAL['addGroups']       = "Add segments";
$GLOBAL['addMetagroup']    = "Add a combined segment";
$GLOBAL['addTeam']         = "Add a segment";
$GLOBAL['addUser']         = "Add";
$GLOBAL['address']         = "Address";
$GLOBAL['all']             = "Everyone";
$GLOBAL['allTypes']        = "All";
$GLOBAL['allYear']         = "All";
$GLOBAL['birthDay']        = "Birth date";
$GLOBAL['comment']         = "Comments";
$GLOBAL['compet']          = "Skills";
$GLOBAL['compta']          = "Accounting";
$GLOBAL['coti']            = "<i class='fas fa-hand-holding-usd fa-fw s'></i> Membership fee";
$GLOBAL['creationDate']    = "Created";
$GLOBAL['date']            = "Date";
$GLOBAL['delete']          = "Delete";
$GLOBAL['donInst']         = "<i class='fas fa-university fa-fw s'></i> Public / Instit.";
$GLOBAL['email']           = "Email";
$GLOBAL['exportDoc']       = "Export to MS Word (Labels)";
$GLOBAL['exportQuittance'] = "Generate receipt";
$GLOBAL['exportXls']       = "Export to MS Excel (Sheet)";
$GLOBAL['f']               = "Female";
$GLOBAL['fax']             = "Fax";
$GLOBAL['firstName']       = "First name";
$GLOBAL['generalData']     = "Data";
$GLOBAL['groupName']       = "Combined segment name";
$GLOBAL['groups']          = "Segments";
$GLOBAL['hf']              = "Mr and Mrs";
$GLOBAL['lastEntry']       = "Reports";
$GLOBAL['lastEntryCompta'] = "Accounting log";
$GLOBAL['lastEntrySuivi']  = "Follow-up log";
$GLOBAL['lastModif']       = "Last modified";
$GLOBAL['lastName']        = "Name";
$GLOBAL['libele']          = "Label";
$GLOBAL['list']            = "Lists";
$GLOBAL['m']               = "Male";
$GLOBAL['manageMategroups']= "Combined segments management";
$GLOBAL['manageTeam']      = "Segments";
$GLOBAL['memberOf']        = "Segment membership";
$GLOBAL['na']              = "-";
$GLOBAL['name']            = "Name";
$GLOBAL['npa']             = "NPA / City";
$GLOBAL['portable']        = "Mobile";
$GLOBAL['quittance']       = "Comment";
$GLOBAL['sexe']            = "Gender";
$GLOBAL['society']         = "Company";
$GLOBAL['sort']            = "Click to sort the column";
$GLOBAL['suivi']           = "Follow-up";
$GLOBAL['sum']             = "Sum";
$GLOBAL['teamName']        = "Segment name";
$GLOBAL['tel']             = "Home";
$GLOBAL['telProf']         = "Work";
$GLOBAL['title']           = "Title";
$GLOBAL['total']           = "Total";
$GLOBAL['type']            = "Type";
$GLOBAL['update']          = "Update";
$GLOBAL['updateSuivi']     = "Update";
$GLOBAL['updateTeam']      = "Update";
$GLOBAL['updateUser']      = "Update";
$GLOBAL['web']             = "Web";

// --- Shared UI strings (used across several views) ---
$GLOBAL['accessDenied']      = "Access denied.";
$GLOBAL['create']            = "Create";
$GLOBAL['merge']             = "Merge";
$GLOBAL['import']            = "Import";
$GLOBAL['export']            = "Export";
$GLOBAL['copy']              = "Copy";
$GLOBAL['print']             = "Print";
$GLOBAL['excel']             = "Excel";
$GLOBAL['next']              = "Next";
$GLOBAL['back']              = "Back";
$GLOBAL['year']              = "Year";
$GLOBAL['status']            = "Status";
$GLOBAL['active']            = "Active";
$GLOBAL['inactive']          = "Inactive";
$GLOBAL['archivedOne']       = "Archived";
$GLOBAL['archived']          = "Archived";
$GLOBAL['user']              = "User";
$GLOBAL['username']          = "Username";
$GLOBAL['password']          = "Password";
$GLOBAL['madame']            = "Mrs";
$GLOBAL['monsieur']          = "Mr";
$GLOBAL['noName']            = "No name";
$GLOBAL['noCategoryLabel']   = "No category";
$GLOBAL['networkError']      = "Network error";
$GLOBAL['loading']           = "Loading…";
$GLOBAL['importContacts']    = "Import contacts";
$GLOBAL['filterPlaceholder'] = "Filter…";
$GLOBAL['emailAlt']          = "Alt. email";
$GLOBAL['emailAltLong']      = "Alt. e-mail";
$GLOBAL['npaCity']           = "NPA / City";
$GLOBAL['hiddenSegment']     = "Hidden segment";
$GLOBAL['memberManagement']  = "Member management";
$GLOBAL['yes']               = "Yes";
$GLOBAL['action']            = "Action";
$GLOBAL['detail']            = "Detail";
$GLOBAL['amount']            = "Amount";

// --- Settings navigation ---
$GLOBAL['settings']            = "Settings";
$GLOBAL['categories']          = "Categories";
$GLOBAL['combinedSegments']    = "Combined segments";
$GLOBAL['comptaTypes']         = "Accounting types";
$GLOBAL['users']               = "Users";
$GLOBAL['journal']             = "Log";
$GLOBAL['integrity']           = "Integrity";
$GLOBAL['health']              = "Health";
$GLOBAL['management']          = "Management";
$GLOBAL['settingsSectionsAria']= "Settings sections";

// --- DataTables language block ---
$GLOBAL['dtSearch']       = "Search:";
$GLOBAL['dtLengthMenu']   = "Show _MENU_ entries";
$GLOBAL['dtInfo']         = "Entries _START_ to _END_ of _TOTAL_";
$GLOBAL['dtInfoFiltered'] = "(filtered from _MAX_)";
$GLOBAL['dtPrevious']     = "Previous";
$GLOBAL['dtNext']         = "Next";
$GLOBAL['dtEmptyTable']   = "No entries.";

// --- Month abbreviations (index 1-12; index 0 unused) ---
$GLOBAL['monthsShort']    = ['', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
$GLOBAL['monthsShortCap'] = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// --- Added: core (routing, actions, libs) ---
// Staging locale keys — externalized strings from routing, actions, lib and partials.
// To be merged into resources_fr.php.
$GLOBAL['viewNotFound'] = "View not found.";
$GLOBAL['csrfRejected'] = "Request rejected (invalid CSRF token). Reload the page and try again.";
$GLOBAL['passwordTooShort'] = "The password must contain at least 8 characters.";
$GLOBAL['passwordMismatch'] = "The two passwords do not match.";
$GLOBAL['currentPasswordIncorrect'] = "Current password incorrect.";
$GLOBAL['invalidUsername'] = "Invalid username (letters, digits, ., -, _ only).";
$GLOBAL['usernameTaken'] = "This username is already taken.";
$GLOBAL['cannotDemoteLastAdmin'] = "Cannot demote the last administrator.";
$GLOBAL['cannotDeleteLastAdmin'] = "Cannot delete the last administrator.";
$GLOBAL['oneGroupHidden'] = "1 group hidden.";
$GLOBAL['groupsHidden'] = "%d groups hidden.";
$GLOBAL['oneGroupShown'] = "1 group shown.";
$GLOBAL['groupsShown'] = "%d groups shown.";
$GLOBAL['lapsedDonorsGroupName'] = "Donors to re-engage %d (%s)";
$GLOBAL['lapsedMembersGroupName'] = "Members to re-engage %d (%s)";
$GLOBAL['memberTeamsNotFound'] = "Could not find the member teams.";
$GLOBAL['noUsersToAdd'] = "No user to add.";
$GLOBAL['invalidData'] = "Invalid data";
$GLOBAL['groupNotFound'] = "Group not found";
$GLOBAL['importSegmentName'] = "Import %s";
$GLOBAL['anonymized'] = "Anonymized";
$GLOBAL['viewAllEntriesOf'] = "View all entries of %s";
$GLOBAL['lastNameFull'] = "Last name";
$GLOBAL['genderCivility'] = "Gender / title (Mr, Mrs…)";
$GLOBAL['landlinePhone'] = "Landline phone";
$GLOBAL['workPhone'] = "Work phone";
$GLOBAL['mobilePhone'] = "Mobile";
$GLOBAL['website'] = "Website";
$GLOBAL['birthDateWithFormat'] = "Birth date (DD/MM/YYYY)";
$GLOBAL['remarks'] = "Remarks";
$GLOBAL['migrationReadError'] = "Cannot read: %s";
$GLOBAL['migrationFailed'] = "FAILED";

// --- Added: compta / donors / import / suivi views ---
// Staging locale additions — new keys introduced while externalizing hardcoded
// strings from the view files (compta, donors, import wizard, suivi).
// To be merged into resources_fr.php.

// --- Shared form / chart labels ---
$GLOBAL['numericAmountHint'] = "Numeric amount (e.g. 50 or 12.50)";
$GLOBAL['allTypesFull'] = "All types";
$GLOBAL['monthlyVsCumulative'] = "Monthly vs cumulative";
$GLOBAL['annual'] = "Annual";
$GLOBAL['monthly'] = "Monthly";
$GLOBAL['cumulative'] = "Cumulative";
$GLOBAL['annualAmount'] = "Annual amount";
$GLOBAL['monthlyAmount'] = "Monthly amount";

// --- compta_list ---
$GLOBAL['addMembershipEntry'] = "Add membership";
$GLOBAL['hideNonDonationEntries'] = "Hide non-donation entries (sales, refunds…)";
$GLOBAL['attestation'] = "Receipt";
$GLOBAL['displayedYear'] = "(displayed year)";
$GLOBAL['removeTypeFilter'] = "Remove the type filter";
$GLOBAL['notCountedAsDonation'] = "Not counted as a donation";

// --- Donor / member cohort views ---
$GLOBAL['backToDonationOverview'] = "Back to the donation overview";
$GLOBAL['lapsedDonorsTitle'] = "Lapsed donors %d → %d";
$GLOBAL['createSegmentLapsedDonors'] = "Create segment “Donors to re-engage %d”";
$GLOBAL['createSegmentTitle'] = "Create the segment";
$GLOBAL['confirmCreateLapsedDonorsSegment'] = "Create the segment “Donors to re-engage %d” with <strong>%s</strong> person(s)?";
$GLOBAL['lapsedDonorsCount'] = "<strong>%s donor%s</strong> contributed in <strong>%d</strong> but not in <strong>%d</strong>.";
$GLOBAL['donYear'] = "Donation %d";
$GLOBAL['lastDonation'] = "Last donation";
$GLOBAL['firstDonation'] = "First donation";
$GLOBAL['loyalDonorsTitle'] = "Loyal donors %d";
$GLOBAL['loyalDonorsCount'] = "<strong>%s loyal donor%s</strong> — contributed in both <strong>%d</strong> and <strong>%d</strong>.";
$GLOBAL['newDonorsTitle'] = "New donors %d";
$GLOBAL['newDonorsCount'] = '<strong>%1$s new donor%3$s</strong> — contributed in <strong>%4$d</strong> with no donation in <strong>%5$d</strong>.';
$GLOBAL['lapsedMembersTitle'] = "Lapsed members %d → %d";
$GLOBAL['noMemberTeamFound'] = "No “Member %d” team found in the database.";
$GLOBAL['createSegmentLapsedMembers'] = "Create segment “Members to re-engage %d”";
$GLOBAL['confirmCreateLapsedMembersSegment'] = "Create the segment “Members to re-engage %d” with <strong>%s</strong> person(s)?";
$GLOBAL['lapsedMembersCount'] = "<strong>%s member%s</strong> were in “Member %d” but not in “Member %d”.";

// --- donors_summary ---
$GLOBAL['activeQuestion'] = "Active?";
$GLOBAL['minAmountChf'] = "Min. %s CHF";
$GLOBAL['vsJanMonth'] = "vs Jan–%s %d";
$GLOBAL['gapToTarget'] = "%s CHF still needed to reach %d (%s) — %s%% reached";
$GLOBAL['targetExceeded'] = "Total %d (%s) exceeded by +%s CHF (+%s%%)";
$GLOBAL['samePeriodCount'] = "Same period %d: %s";
$GLOBAL['alsoDonatedIn'] = "Also donated in %d";
$GLOBAL['loyalShort'] = "%s loyal";
$GLOBAL['firstContributionIn'] = "First contribution in %d";
$GLOBAL['donatedButNotIn'] = "Donated in %d but not in %d";
$GLOBAL['lapsedShort'] = "%s lapsed";
$GLOBAL['membersNotRenewed'] = "Members %d not renewed in %d";
$GLOBAL['minAmountLabel'] = "Minimum amount";
$GLOBAL['extendedMode'] = "Extended mode";
$GLOBAL['includeIfAttestationRequested'] = "Include if a receipt was requested";
$GLOBAL['attestationFilterExplanation'] = "Shows people who checked “wants a donation receipt” even if their total is below the selected minimum amount.";
$GLOBAL['attestationFilterAriaLabel'] = "Explanation of the receipts filter";
$GLOBAL['generateAllAttestationsPdf'] = "Generate all receipts in a single PDF";
$GLOBAL['attestationsYear'] = "Receipts %d";
$GLOBAL['extendedModeWarningIntro'] = "<strong>Extended mode</strong> — all accounting types are included";
$GLOBAL['extendedModeWarningExcluded'] = ", including those usually excluded from donations: %s";
$GLOBAL['extendedModeWarningOutro'] = ". Totals do not reflect donations only.";
$GLOBAL['statusTitleInstitutional'] = "%s / Institutional donation";
$GLOBAL['donations'] = "Donations";
$GLOBAL['others'] = "Others";
$GLOBAL['wantsAttestationShort'] = "Wants a receipt";
$GLOBAL['institutionalDonation'] = "Institutional donation";
$GLOBAL['attestationOfDonations'] = "Donation receipt %d";
$GLOBAL['attestationOfDonationsFor'] = "Donation receipt %d for %s";
$GLOBAL['generateAttestations'] = "Generate the receipts";
$GLOBAL['bulkAttestConfirmBody'] = "Generate a PDF with the donation receipts for %s person(s).";
$GLOBAL['bulkAttestDuration'] = "This operation can take several minutes depending on the number of entries.";
$GLOBAL['bulkAttestInProgress'] = "Generation in progress — the PDF will open in a new tab.";
$GLOBAL['progress'] = "Progress";
$GLOBAL['bulkAttestCanClose'] = "You can close this window. Generation continues in the open tab.";
$GLOBAL['generate'] = "Generate";

// --- Import wizard ---
$GLOBAL['importStep1Subtitle'] = "Step 1 of 3 — Select a CSV or TSV file.";
$GLOBAL['importErrUpload'] = "Error while uploading the file. Check that a file is selected.";
$GLOBAL['importErrTooBig'] = "File too large (maximum 5 MB).";
$GLOBAL['importErrEmpty'] = "The file appears to be empty or contains no valid data.";
$GLOBAL['importErrSession'] = "Session expired — please restart the import.";
$GLOBAL['csvFileLabel'] = "CSV / TSV file";
$GLOBAL['importHintFormats'] = "Accepted formats: CSV (comma or semicolon), TSV (tab).";
$GLOBAL['importHintEncoding'] = "UTF-8 or Latin-1 encoding. First row = column headers.";
$GLOBAL['importHintLimit'] = "Limit: 5,000 rows per import.";
$GLOBAL['importStep2Subtitle'] = "Step 2 of 3 — Map each file column to a member field.";
$GLOBAL['rowsDetected'] = "%s row%s detected.";
$GLOBAL['importTruncatedWarning'] = "The file exceeds the 5,000-row limit — only the first 5,000 will be imported.\n      The remaining rows are <strong>ignored</strong>. Split the file to import the rest.";
$GLOBAL['importErrNoMapping'] = "No column is mapped to a member field — select at least one field.";
$GLOBAL['fileColumn'] = "File column";
$GLOBAL['memberField'] = "Member field";
$GLOBAL['examples'] = "Examples";
$GLOBAL['ignoreField'] = "— ignore —";
$GLOBAL['addContactsToSegment'] = "Add the contacts to a segment";
$GLOBAL['createSegmentNamed'] = "Create a segment <strong>%s</strong>";
$GLOBAL['addToExistingSegment'] = "Add to an existing segment";
$GLOBAL['createNewSegment'] = "Create a new segment";
$GLOBAL['noCategoryOption'] = "— No category —";
$GLOBAL['doNotAddToSegment'] = "Do not add to a segment";
$GLOBAL['importStep3Subtitle'] = "Step 3 of 3 — Import results.";
$GLOBAL['contactsCreated'] = "<strong>%s</strong> contact%s created successfully.";
$GLOBAL['noNewContacts'] = "No new contact created.";
$GLOBAL['duplicatesDetectedCount'] = "<strong>%s</strong> duplicate%s detected — to be handled below.";
$GLOBAL['contactsAddedToSegment'] = "<strong>%s</strong> contact%s added to the segment";
$GLOBAL['viewMemberList'] = "View the member list";
$GLOBAL['duplicatesDetected'] = "Duplicates detected";
$GLOBAL['duplicateResolutionHint'] = "For each duplicate, choose the action to apply to the existing contact.";
$GLOBAL['duplicateOf'] = "duplicate of";
$GLOBAL['ignore'] = "Ignore";
$GLOBAL['fillEmptyFields'] = "Fill empty fields";
$GLOBAL['overwrite'] = "Overwrite";
$GLOBAL['applyChoices'] = "Apply choices";
$GLOBAL['finishWithoutApplying'] = "Finish without applying";

// --- Suivi ---
$GLOBAL['viewSuiviOf'] = "View follow-up of %s";
$GLOBAL['dtInfoEntries'] = "_START_–_END_ of _TOTAL_ entries";
$GLOBAL['deleteThisEntry'] = "Delete this entry";

// --- Added: settings views ---
// Staging locale keys — externalized strings from the settings views.
// To be merged into resources_fr.php.

// --- Shared across settings views ---
$GLOBAL['segmentCount'] = "%d segment%s";

// --- settings_group_edit ---
$GLOBAL['cotisantsImported'] = "Fee payers imported into the segment.";
$GLOBAL['donorsImported'] = "Donors imported into the segment.";
$GLOBAL['viewList'] = "View the list";
$GLOBAL['hideInInterfaces'] = "Hide in the interfaces";
$GLOBAL['category'] = "Category";
$GLOBAL['importMembersFromOtherTeams'] = "Import members from other segments";
$GLOBAL['oneTimeCopyImportWarning'] = "<strong>One-time copy</strong> — the import copies members as they are <em>now</em>. If the source segment changes later, this segment is not updated.";
$GLOBAL['dynamicFilterHint'] = "For a dynamic filter, use a combined segment in %s instead.";
$GLOBAL['importCotisantsOfYear'] = "Import the fee payers of a year";
$GLOBAL['oneTimeCopyCotisWarning'] = "<strong>One-time copy</strong> — members already in this segment are untouched. Only missing fee payers are added.";
$GLOBAL['typesTakenIntoAccount'] = "Types taken into account: %s.";
$GLOBAL['noCotisationTypeWarning'] = "No type marked “membership fee” — configure them in %s.";
$GLOBAL['importCotisantsBtn'] = "Import the fee payers";
$GLOBAL['importDonorsOfYear'] = "Import the donors of a year";
$GLOBAL['oneTimeCopyDonorsWarning'] = "<strong>One-time copy</strong> — members already in this segment are not touched. Only missing donors are added.";
$GLOBAL['allDonors'] = "All donors";
$GLOBAL['nonInstitutionals'] = "Non-institutional";
$GLOBAL['institutionals'] = "Institutional";
$GLOBAL['minChf'] = "Min CHF";
$GLOBAL['toImportCount'] = "+%d to import";
$GLOBAL['zeroToImport'] = "0 to import";
$GLOBAL['importDonorsBtn'] = "Import the donors";
$GLOBAL['reassignOrDissolve'] = "Reassign or dissolve…";
$GLOBAL['membersBelongToSegment'] = "%s member%s belong to this segment:";
$GLOBAL['transferMembersToOtherSegment'] = "Transfer the members to another segment";
$GLOBAL['chooseSegmentOption'] = "— choose the segment —";
$GLOBAL['transferAndDissolve'] = "Transfer and dissolve";
$GLOBAL['removeAllMembersAndDelete'] = "Remove all members and delete the segment";
$GLOBAL['membersWillBeRemoved'] = "The %d member%s will be removed from the segment but their records will remain intact.";
$GLOBAL['removeMembersAndDelete'] = "Remove the members and delete";
$GLOBAL['segmentHasNoMembers'] = "This segment has no members.";
$GLOBAL['reassignAndDeleteConfirm'] = "Reassign %d member%s and delete the segment “%s”?";
$GLOBAL['deleteSegmentAndRemoveMembersConfirm'] = "Delete the segment “%s” and remove its %d member%s?";
$GLOBAL['deleteSegmentConfirm'] = "Delete the segment “%s”?";

// --- settings_groups ---
$GLOBAL['noCategoryOptionLower'] = "— no category —";
$GLOBAL['importMembersFromOtherSegments'] = "Import members from other segments";
$GLOBAL['importCopyHint'] = "One-time copy — members are copied as they are now. For a dynamic filter, create a combined segment instead.";
$GLOBAL['hide'] = "Hide";
$GLOBAL['show'] = "Show";
$GLOBAL['createFilter'] = "Create a filter";
$GLOBAL['deselect'] = "Deselect";
$GLOBAL['selectAll'] = "Select all";
$GLOBAL['hiddenPlural'] = "Hidden";
$GLOBAL['renameSegmentAria'] = "Rename the segment “%s”";
$GLOBAL['saveEnterAria'] = "Save (Enter)";
$GLOBAL['cancelEscapeAria'] = "Cancel (Escape)";
$GLOBAL['renameNameAria'] = "Rename “%s”";
$GLOBAL['segmentSettingsAria'] = "Settings of the segment “%s”";
$GLOBAL['filterNameExample'] = "E.g. Active donors";
$GLOBAL['selectedCount'] = "%d selected";
$GLOBAL['segmentRenamedTo'] = "Segment renamed to “%s”.";
$GLOBAL['renameError'] = "Error while renaming";

// --- settings_general ---
$GLOBAL['settingsSectionAria'] = "Settings section";
$GLOBAL['organization'] = "Organization";
$GLOBAL['orgName'] = "Organization name";
$GLOBAL['npaShort'] = "NPA";
$GLOBAL['memberTeamPrefixLabel'] = "Member segments prefix";
$GLOBAL['memberTeamPrefixHelp'] = "Prefix used to find the member segments of previous years (e.g. “Member” for the segments “Member 2025”, “Member 2026”…).";
$GLOBAL['defaultTeamLabel'] = "Segment displayed by default";
$GLOBAL['defaultTeamHelp'] = "Segment selected when opening the member list. Choose the segment matching the current year's members (e.g. “Member 2026”). To be updated every year.";
$GLOBAL['maskedSuffix'] = "(hidden)";
$GLOBAL['membreTeamLabel'] = "Member segment (reference year)";
$GLOBAL['membreTeamHelp'] = "Member segment of the current year (e.g. “Member 2026”). Used for the membership fee filters and shown in the Contributions dashboard with a comparison to the previous year. To be updated every year.";
$GLOBAL['noCotiTeamLabel'] = "Segment of members without membership fee";
$GLOBAL['noCotiTeamHelp'] = "Members considered active without paying a membership fee (volunteers, committee…). Excluded from the “No membership fee in the last 3 years” filter. Leave empty if not applicable.";
$GLOBAL['noneOption'] = "— None —";
$GLOBAL['orgIde'] = "IDE Number";
$GLOBAL['orgIdeHelp'] = "Swiss business identification number (CHE-XXX.XXX.XXX). Will appear on donation attestations.";
$GLOBAL['orgPurpose'] = "Statutory purpose";
$GLOBAL['orgPurposeHelp'] = "Excerpt from the articles of association describing the organisation's purpose. Used in official documents.";
$GLOBAL['orgTaxStatus'] = "Tax exemption status";
$GLOBAL['orgTaxStatusHelp'] = "E.g. «Tax-exempt AFC-GE since 2018». Use the LINDAS button to retrieve automatically from the federal register, or enter manually.";
$GLOBAL['orgTaxStatusPlaceholder'] = "E.g. Tax-exempt AFC-GE since 2018";
$GLOBAL['zefixVerify'] = "Verify via Zefix";
$GLOBAL['zefixChecking'] = "Checking…";
$GLOBAL['zefixMissingIde'] = "Please enter an IDE number first.";
$GLOBAL['zefixInvalidIde'] = "Invalid IDE number (expected format: CHE-XXX.XXX.XXX).";
$GLOBAL['zefixNotFound'] = "IDE number not found in the Zefix register.";
$GLOBAL['zefixUnreachable'] = "Unable to reach Zefix. Check your connection.";
$GLOBAL['zefixNetworkError'] = "Network error while checking Zefix.";

// --- settings_filter_edit ---
$GLOBAL['combinedSegmentCreated'] = "Combined segment “%s” created.";
$GLOBAL['assignSegmentsBelowOr'] = "You can now assign segments below, or";
$GLOBAL['backToListLink'] = "go back to the list";
$GLOBAL['combinedSegmentsLower'] = "combined segments";
$GLOBAL['categoriesLower'] = "categories";
$GLOBAL['backToLabel'] = "Back to %s";
$GLOBAL['memberSegments'] = "Member segments";
$GLOBAL['viewFilteredList'] = "View the filtered list";
$GLOBAL['autoSaveOnCheck'] = "Saved automatically on each check.";
$GLOBAL['hiddenSegmentLower'] = "hidden segment";
$GLOBAL['segmentsInThisCategory'] = "Segments in this category";
$GLOBAL['noSegmentsInCategory'] = "No segment in this category.";
$GLOBAL['removeFromCategory'] = "Remove from the category";
$GLOBAL['removeName'] = "Remove %s";
$GLOBAL['addToCategory'] = "Add to the category";
$GLOBAL['addName'] = "Add %s";
$GLOBAL['moveToCategory'] = "Move to “%s”";
$GLOBAL['moveNameToCategory'] = "Move %s to %s";
$GLOBAL['undoLastAction'] = "Undo the last action";
$GLOBAL['saveError'] = "Error while saving";
$GLOBAL['actionUndone'] = "Action undone";
$GLOBAL['deleteMetagroupHelp'] = "Deletes the combined segment. The member segments are not affected.";
$GLOBAL['deleteNameConfirm'] = "Delete “%s”?";
$GLOBAL['memberSegmentsNotDeleted'] = "The member segments will not be deleted.";

// --- settings_filters ---
$GLOBAL['filtersHelp'] = "Group several segments into one dynamic filter — accessible from the navigation bar.";
$GLOBAL['noFilters'] = "No filters.";
$GLOBAL['filterNamePlaceholder'] = "Filter name";

// --- settings_integrity ---
$GLOBAL['integrityHelp'] = "Potential duplicates among members, and hidden segments still assigned.";
$GLOBAL['allClean'] = "All clean — no issue detected.";
$GLOBAL['membersSameName'] = "Members with the same name";
$GLOBAL['firstLastName'] = "First name / Last name";
$GLOBAL['records'] = "Profiles";
$GLOBAL['mergeEllipsis'] = "Merge…";
$GLOBAL['membersSameEmail'] = "Members with the same email";
$GLOBAL['hiddenSegmentsInCategory'] = "Hidden segments in a category";
$GLOBAL['editShort'] = "Edit";
$GLOBAL['hiddenSegmentsInCombined'] = "Hidden segments in a combined segment";
$GLOBAL['combinedSegmentSingular'] = "Combined segment";
$GLOBAL['hiddenSegmentsWithMembers'] = "Hidden segments with members";
$GLOBAL['members'] = "Members";
$GLOBAL['member'] = "Member";
$GLOBAL['membersNoNameTitle'] = "Members without last name or company";
$GLOBAL['membersNoNameHelp'] = "These members have neither a last name nor a company — they are hard to identify.";
$GLOBAL['invalidComptaDates'] = "Invalid accounting dates";
$GLOBAL['invalidComptaDatesHelp'] = "Entries with a date at 0 or in the future.";
$GLOBAL['zeroEmpty'] = "0 (empty)";
$GLOBAL['comptaEntriesWithoutType'] = "Accounting entries without a type";
$GLOBAL['comptaEntriesWithoutTypeHelp'] = "These entries have <code>type_id = NULL</code> — they do not appear in any breakdown by type.";
$GLOBAL['malformedEmails'] = "Malformed emails";
$GLOBAL['malformedAltEmails'] = "Malformed alt. emails";
$GLOBAL['invalidGenderTitle'] = "Gender outside allowed values";
$GLOBAL['expectedGenderValues'] = "Expected values: <code>na</code>, <code>hf</code>, <code>f</code>, <code>m</code>.";
$GLOBAL['valueLabel'] = "Value";
$GLOBAL['birthdayInFuture'] = "Birth date in the future";
$GLOBAL['birthDateLabel'] = "Birth date";

// --- settings_health ---
$GLOBAL['systemHealth'] = "System health";
$GLOBAL['migErrBackup'] = "Check “I have made a backup” before applying the migrations.";
$GLOBAL['migErrNoRecentExport'] = "You must <strong>export the database</strong> from this browser within the last 30 minutes before applying the migrations.";
$GLOBAL['migErrLocked'] = "A migration is already running — wait for it to finish before retrying.";
$GLOBAL['migErrGeneric'] = "Failed to apply the migrations — check the audit log and restore from your backup if needed.";
$GLOBAL['migrationsAppliedSuccess'] = "%d migration(s) applied successfully.";
$GLOBAL['migrationDriftLabel'] = "Migration drift:";
$GLOBAL['migrationDriftBody'] = "%d applied migration(s) whose file has changed since (%s). An already-applied migration file must never be modified — check the repository.";
$GLOBAL['warningLabel'] = "Warning:";
$GLOBAL['pendingMigrationsBody'] = "%d pending database migration(s) (%s). Apply them with <code>php html/tools/migrate.php</code>.";
$GLOBAL['systemOperational'] = "System operational — database up to date, no pending migration.";
$GLOBAL['application'] = "Application";
$GLOBAL['version'] = "Version";
$GLOBAL['commit'] = "Commit";
$GLOBAL['server'] = "Server";
$GLOBAL['database'] = "Database";
$GLOBAL['connection'] = "Connection";
$GLOBAL['databaseShort'] = "Database";
$GLOBAL['tables'] = "Tables";
$GLOBAL['migrations'] = "Migrations";
$GLOBAL['appliedLabel'] = "Applied";
$GLOBAL['tableMissing'] = "table missing";
$GLOBAL['pendingLabel'] = "Pending";
$GLOBAL['driftChecksumLabel'] = "Drift (checksum)";
$GLOBAL['lastLabel'] = "Last";
$GLOBAL['volumeActivity'] = "Volume &amp; activity";
$GLOBAL['comptaEntriesLabel'] = "Accounting entries";
$GLOBAL['appUsersShort'] = "App users";
$GLOBAL['lastAction'] = "Last action";
$GLOBAL['maintenance'] = "Maintenance";
$GLOBAL['exportDbSql'] = "Export the database (SQL)";
$GLOBAL['iHaveBackup'] = "I have made a backup";
$GLOBAL['exportFirstRequired'] = "Export the database above first (required).";
$GLOBAL['exportBeforeMigrating'] = "Export the database before migrating";
$GLOBAL['applyMigrationsCount'] = "Apply %d migration(s)";
$GLOBAL['exportHelpParagraph'] = "The export generates a downloadable SQL dump (restorable via phpMyAdmin or <code>make restore</code>) — useful
      on hosting <strong>without SSH access</strong>. Applying the migrations runs the pending DDL;
      <strong>export just before</strong> (DDL cannot be rolled back).";
$GLOBAL['healthEndpointHelp'] = "A lightweight check endpoint for external monitoring is available at <code>/health.php</code>
  (JSON <code>{\"status\":\"ok\"|\"degraded\"}</code>, without authentication or sensitive data).";

// --- settings_app_users ---
$GLOBAL['inviteLinkFor'] = "Invitation link for %s (valid 7 days):";
$GLOBAL['inviteLinkHelp'] = "Send this link to the user. They will set their own password.";
$GLOBAL['tempPasswordFor'] = "Temporary password for %s:";
$GLOBAL['tempPasswordHelp'] = "Give it to the user. They will have to change it at the next login.";
$GLOBAL['appUsersTitle'] = "Application users";
$GLOBAL['newUser'] = "New user";
$GLOBAL['role'] = "Role";
$GLOBAL['lastLogin'] = "Last login";
$GLOBAL['youBadge'] = "you";
$GLOBAL['invitePendingTooltip'] = "Invitation pending — link not yet used";
$GLOBAL['inviteBadge'] = "invitation";
$GLOBAL['mustChangePasswordTooltip'] = "Must change their password";
$GLOBAL['keyBadge'] = "key";
$GLOBAL['roleAdmin'] = "Admin";
$GLOBAL['roleManager'] = "Manager";
$GLOBAL['roleReadonly'] = "Read-only";
$GLOBAL['resetPasswordShort'] = "Reset password";
$GLOBAL['changeMyPassword'] = "Change my password";
$GLOBAL['usernamePatternHint'] = "Letters, digits, dot, dash, underscore";
$GLOBAL['displayName'] = "Display name";
$GLOBAL['viewRightsMatrix'] = "View the rights matrix";
$GLOBAL['rightLabel'] = "Right";
$GLOBAL['roleReadonlyWrapped'] = "Read-<br>only";
$GLOBAL['roleUserWrapped'] = "User";
$GLOBAL['rightViewData'] = "View members, accounting, follow-up";
$GLOBAL['rightEditData'] = "Create / edit members, accounting, follow-up";
$GLOBAL['rightImportContacts'] = "Import contacts (CSV/TSV)";
$GLOBAL['rightManageSettings'] = "Manage segments, categories, settings";
$GLOBAL['rightMergeArchive'] = "Merge / archive a member";
$GLOBAL['rightDeleteAnonymize'] = "Delete / anonymize a member";
$GLOBAL['rightManageAccounts'] = "Manage the application accounts";
$GLOBAL['yesLower'] = "yes";
$GLOBAL['noLower'] = "no";
$GLOBAL['tempPassword'] = "Temporary password";
$GLOBAL['generateRandomPassword'] = "Generate a random password";
$GLOBAL['tempPasswordDefaultHelp'] = "Leave empty to use <strong>changeme</strong> by default. The user will have to change it at the first login.";
$GLOBAL['editUserTitle'] = "Edit %s";
$GLOBAL['accountActive'] = "Account active";
$GLOBAL['saveButton'] = "Save";
$GLOBAL['resetPasswordTitle'] = "Reset the password";
$GLOBAL['resetPasswordConfirm'] = "Reset the password of “%s”?";
$GLOBAL['deleteUserTitle'] = "Delete the user";
$GLOBAL['deleteUserConfirm'] = "Delete the user “%s”? This action is irreversible.";

// --- settings_audit_log ---
$GLOBAL['activityLog'] = "Activity log";
$GLOBAL['cleanUp'] = "Clean up";
$GLOBAL['auditLogFlushed'] = "Log cleaned.";
$GLOBAL['keepLastLabel'] = "Keep the last";
$GLOBAL['days'] = "days";
$GLOBAL['allMasculineOption'] = "— all —";
$GLOBAL['allFeminineOption'] = "— all —";
$GLOBAL['reset'] = "Reset";
$GLOBAL['entriesTotalCount'] = "%d entries in total";
$GLOBAL['auditLogDisplayCap'] = "(2000 displayed)";
$GLOBAL['deleteAllAuditLogTitle'] = "Delete the entire log";
$GLOBAL['deleteAllAuditLogConfirm'] = "This action will delete <strong>all</strong> log entries. Continue?";

// --- settings_compta_types ---
$GLOBAL['colorBlue'] = "Blue";
$GLOBAL['colorGrey'] = "Grey";
$GLOBAL['colorGreen'] = "Green";
$GLOBAL['colorRed'] = "Red";
$GLOBAL['colorYellow'] = "Yellow";
$GLOBAL['colorCyan'] = "Cyan";
$GLOBAL['colorWhite'] = "White";
$GLOBAL['colorDark'] = "Dark";
$GLOBAL['colorOrange'] = "Orange";
$GLOBAL['colorTeal'] = "Teal";
$GLOBAL['colorPink'] = "Pink";
$GLOBAL['colorPurple'] = "Purple";
$GLOBAL['colorIndigo'] = "Indigo";
$GLOBAL['colorLime'] = "Lime";
$GLOBAL['comptaTypesTitle'] = "Accounting types";
$GLOBAL['newComptaType'] = "New type";
$GLOBAL['labelField'] = "Label";
$GLOBAL['color'] = "Color";
$GLOBAL['entriesColumn'] = "Entries";
$GLOBAL['cotiTooltip'] = "Counts as a membership fee";
$GLOBAL['cotiShort'] = "Fee";
$GLOBAL['exclDonTooltip'] = "Excluded from donations";
$GLOBAL['exclDonShort'] = "Excl. don.";
$GLOBAL['institTooltip'] = "Institutional payment";
$GLOBAL['institShort'] = "Instit.";
$GLOBAL['yesClickToDisable'] = "Yes — click to disable";
$GLOBAL['noClickToEnable'] = "No — click to enable";
$GLOBAL['deleteShort'] = "Del.";
$GLOBAL['orderLabel'] = "Order";
$GLOBAL['deleteComptaTypeTitle'] = "Delete this type";
$GLOBAL['deleteComptaTypeConfirm'] = "Delete this fee type? This action is irreversible.";

// --- settings_categories ---
$GLOBAL['categoriesHelp'] = "Organize segments into visual sections in the lists. A segment belongs to a single category.";
$GLOBAL['noCategories'] = "No categories.";
$GLOBAL['categoryNamePlaceholder'] = "Category name";

// --- Added: member views, install, login, attestations ---
// Staging locale keys — users views, standalone entry points (install, login,
// set-password) and attestation generators. To be merged into resources_fr.php.

// --- users_anonymize.php / users_merge.php (shared) ---
$GLOBAL['memberNotFound'] = "Member not found.";

// --- users_anonymize.php ---
$GLOBAL['anonymizeComptaCount'] = "This profile has <strong>%d accounting entries</strong>.";
$GLOBAL['anonymizeNoDeleteReason'] = "Permanent deletion is impossible for accounting traceability reasons.";
$GLOBAL['anonymizeExplanation'] = "Anonymization erases all personal data (last name, first name, address, email, phone…) while keeping the accounting history associated with this internal ID.";
$GLOBAL['anonymizeIrreversibleIntro'] = "<strong>This operation is irreversible.</strong> The following data will be erased:";
$GLOBAL['anonymizeErasedFieldsList'] = "last name, first name, company, address, NPA, email, phones, web, birth date, note.";

// --- users_edit_form.php ---
$GLOBAL['noNameId'] = "No name #%d";
$GLOBAL['memberSheet'] = "Profile";
$GLOBAL['history'] = "History";
$GLOBAL['historyShort'] = "Hist.";
$GLOBAL['archiveModalBody'] = "The profile will be removed from all lists.<br>Can be unarchived at any time.";
$GLOBAL['archivedBanner'] = "This profile is <strong>archived</strong> — it does not appear in any list.";
$GLOBAL['totalSince'] = "Total since %s";
$GLOBAL['otherPayments'] = "Other payments";
$GLOBAL['anonymizeTooltip'] = "This profile has accounting data — deletion is impossible. Anonymization erases the personal data while keeping the accounting history.";

// --- users_general_data.php ---
$GLOBAL['clickToEdit'] = "Click to edit";
$GLOBAL['googleMaps'] = "Google Maps";
$GLOBAL['createdAtLabel'] = "Created: %s";
$GLOBAL['modifiedAtLabel'] = "Modified: %s";
$GLOBAL['emailAltHint'] = "Historical / alternative address — not used for mailings";
$GLOBAL['ttFormatting'] = "Formatting";
$GLOBAL['ttBold'] = "Bold (Ctrl+B)";
$GLOBAL['ttBoldShort'] = "Bold";
$GLOBAL['ttItalic'] = "Italic (Ctrl+I)";
$GLOBAL['ttItalicShort'] = "Italic";
$GLOBAL['ttBulletList'] = "Bullet list";
$GLOBAL['ttOrderedList'] = "Numbered list";
$GLOBAL['ttUndo'] = "Undo (Ctrl+Z)";
$GLOBAL['ttRedo'] = "Redo (Ctrl+Shift+Z)";
$GLOBAL['ttRedoShort'] = "Redo";
$GLOBAL['saveBtn'] = "Save";

// --- users_history.php ---
$GLOBAL['changeHistory'] = "Change history";
$GLOBAL['changeHistoryHint'] = "All actions recorded for this member.";
$GLOBAL['noJournalEntriesForMember'] = "No log entry for this member.";

// --- users_inactive.php ---
$GLOBAL['archivedMembers'] = "Archived members";
$GLOBAL['archivedMembersHint'] = "Archived profiles. They are no longer visible in the lists.";
$GLOBAL['noArchivedMembers'] = "No archived member.";
$GLOBAL['idLabel'] = "ID";
$GLOBAL['unarchive'] = "Unarchive";
$GLOBAL['unarchiveConfirmTitle'] = "Unarchive this member?";
$GLOBAL['unarchiveModalBody'] = "The profile will reappear in all lists.";

// --- users_list.php ---
$GLOBAL['importDone'] = "Import finished.";
$GLOBAL['duplicatesUpdated'] = "<strong>%d</strong> duplicate%s updated.";
$GLOBAL['noCotiExclusion'] = " Members of the segment %s are excluded.";
$GLOBAL['filterDescCotiUnpaid3y'] = "Profiles that paid at least one membership fee in their history, but none in the last 3 years (%s–%s).";
$GLOBAL['filterDescNoActivity10y'] = "Active profiles with no accounting entry (membership fee, donation or other) since %s.";
$GLOBAL['filterDescNonInstitLastYear'] = "Profiles that made at least one non-institutional payment in %s — includes membership fees, donations and any other type not marked “Institutional” in the accounting types.";
$GLOBAL['filterDescCotiUnpaidCurrent'] = "Members whose %s membership fee has not been recorded yet.";
$GLOBAL['quickFilters'] = "Quick filters";
$GLOBAL['typesHeader'] = "Types";
$GLOBAL['comptaHistory'] = "Accounting history";
$GLOBAL['fh'] = "Mrs and Mr";
$GLOBAL['entriesCountShort'] = "%d entr.";
$GLOBAL['cotiCountShort'] = "%d fee";
$GLOBAL['lastActivityYear'] = "last: %s";
$GLOBAL['missedRevenue'] = "missed revenue of CHF %d for %s from unpaid membership fees...";
$GLOBAL['dtInfoProfiles'] = "_TOTAL_ profiles";
$GLOBAL['dtInfoFilteredMasc'] = "(filtered from _MAX_)";

// --- users_member_of.php ---
$GLOBAL['segmentNumber'] = "segment #%d";
$GLOBAL['membershipAdded'] = "Added: %s";
$GLOBAL['membershipRemoved'] = "Removed: %s";
$GLOBAL['noSegments'] = "No segments.";
$GLOBAL['removeFromSegment'] = "Remove from %s";
$GLOBAL['hiddenSegmentPrefix'] = "[Hidden segment] ";
$GLOBAL['hiddenSegments'] = "Hidden segments";
$GLOBAL['hideHiddenSegments'] = "Hide the hidden segments";

// --- users_merge.php ---
$GLOBAL['invalidMergeParams'] = "Invalid merge parameters.";
$GLOBAL['sexLabel'] = "Sex";
$GLOBAL['telShort'] = "Tel.";
$GLOBAL['telProfShort'] = "Work tel.";
$GLOBAL['birthShort'] = "Birth";
$GLOBAL['noteLabel'] = "Note";
$GLOBAL['memberMerge'] = "Member merge";
$GLOBAL['mergeTwoMembers'] = "Merge two member profiles";
$GLOBAL['mergeInstruction'] = "Click the value to keep for each differing field.";
$GLOBAL['allDataIdentical'] = "All data is identical";
$GLOBAL['divergentFieldsCount'] = "%d differing field%s";
$GLOBAL['mergeTableAria'] = "Member profiles comparison";
$GLOBAL['fieldLabel'] = "Field";
$GLOBAL['chooseValueA'] = "Choose value A for %s";
$GLOBAL['chooseValueB'] = "Choose value B for %s";
$GLOBAL['emptyValue'] = "empty";
$GLOBAL['keepBothNotes'] = "Keep both notes (survivor first)";
$GLOBAL['linkedDataAuto'] = "Linked data (merged automatically)";
$GLOBAL['profileA'] = "Profile A";
$GLOBAL['profileB'] = "Profile B";
$GLOBAL['comptaEntries'] = "Accounting entries";
$GLOBAL['suiviEntries'] = "Follow-up entries";
$GLOBAL['survivorProfile'] = "Surviving profile (keeps its ID)";
$GLOBAL['sourceProfileAfterMerge'] = "Source profile after the merge";
$GLOBAL['mergeDeleteWarning'] = "Irreversible — all data of the source profile will be erased.";
$GLOBAL['resolveAllFields'] = "Resolve all differing fields to continue.";
$GLOBAL['mergeConfirmIntro'] = "This operation is irreversible. Check the summary before confirming.";
$GLOBAL['survivorLabel'] = "Surviving profile:";
$GLOBAL['sourceDeletedLabel'] = "Source profile deleted:";
$GLOBAL['yesIrreversible'] = "yes (irreversible)";
$GLOBAL['noArchivedOnly'] = "no — archived only";
$GLOBAL['fieldsModifiedSummary'] = "%d field(s) modified according to your selection.";
$GLOBAL['mergeReattachInfo'] = "All accounting and follow-up entries of the source profile will be reattached to the surviving profile.";
$GLOBAL['mergeSegmentsInfo'] = "Segment memberships will be merged (automatic deduplication).";

// --- login.php ---
$GLOBAL['invalidRequest'] = "Invalid request. Please try again.";
$GLOBAL['badCredentials'] = "Incorrect username or password.";
$GLOBAL['loginTitle'] = "Sign in — %s";
$GLOBAL['signIn'] = "Sign in";

// --- set-password.php ---
$GLOBAL['invalidLink'] = "Invalid link.";
$GLOBAL['linkExpired'] = "This link is invalid or has expired. Ask the administrator for a new access.";
$GLOBAL['passwordsMismatch'] = "The two passwords do not match.";
$GLOBAL['setMyPassword'] = "Set my password";
$GLOBAL['passwordSetSuccess'] = "Password set. You can now <a href=\"login.php\">sign in</a>.";
$GLOBAL['backToLogin'] = "Back to login";
$GLOBAL['welcomeUser'] = "Welcome %s.";
$GLOBAL['choosePasswordActivate'] = "Choose a password to activate your account.";
$GLOBAL['minPasswordHint'] = "8 characters minimum.";
$GLOBAL['setPasswordBtn'] = "Set the password";

// --- install.php ---
$GLOBAL['installTitle'] = "Installation — MemberBase";
$GLOBAL['installWizardSubtitle'] = "Installation wizard";
$GLOBAL['stepPrereqs'] = "Prerequisites";
$GLOBAL['stepDatabase'] = "Database";
$GLOBAL['stepSchema'] = "Schema";
$GLOBAL['stepOrganisation'] = "Organization";
$GLOBAL['stepAdminAccount'] = "Admin account";
$GLOBAL['dbNameRequired'] = "Database name required.";
$GLOBAL['dbUserRequired'] = "User required.";
$GLOBAL['cannotWriteConf'] = "Cannot write <code>%s</code>. Check the permissions of the <code>conf/</code> directory.";
$GLOBAL['connectionFailed'] = "Connection failed: %s";
$GLOBAL['sqlError'] = "SQL error: %s";
$GLOBAL['orgNameRequired'] = "Organization name required.";
$GLOBAL['genericError'] = "Error: %s";
$GLOBAL['usernameRequired'] = "Username required.";
$GLOBAL['usernameInvalid'] = "Invalid username (2–50 chars, letters/digits/.-_).";
$GLOBAL['installPasswordTooShort'] = "Password too short (min. 8 characters).";
$GLOBAL['installPasswordsMismatch'] = "The passwords do not match.";
$GLOBAL['usernameTakenNamed'] = "The username “%s” is already taken.";
$GLOBAL['prereqPhpVersion'] = "PHP ≥ 8.1";
$GLOBAL['prereqPdoMysql'] = "PDO MySQL extension";
$GLOBAL['prereqMbstring'] = "mbstring extension";
$GLOBAL['prereqConfWritable'] = "Write access to conf/";
$GLOBAL['statusOk'] = "OK";
$GLOBAL['statusMissing'] = "Missing";
$GLOBAL['statusNotWritable'] = "Not writable";
$GLOBAL['statusDirMissing'] = "Directory missing";
$GLOBAL['prereqsServerTitle'] = "Server prerequisites";
$GLOBAL['fixPrereqsWarning'] = "Fix the prerequisites before continuing.";
$GLOBAL['continueBtn'] = "Continue";
$GLOBAL['dbConnectionTitle'] = "Database connection";
$GLOBAL['dbConnectionHint'] = "The settings will be saved in <code>conf/db.php</code> (outside the webroot).";
$GLOBAL['hostLabel'] = "Host";
$GLOBAL['portLabel'] = "Port";
$GLOBAL['dbNameLabel'] = "Database name";
$GLOBAL['testConnectionBtn'] = "Test the connection and continue";
$GLOBAL['schemaInitTitle'] = "Schema initialization";
$GLOBAL['schemaInitHint'] = "Creates the tables from <code>schema.sql</code>. Existing tables are not modified.";
$GLOBAL['tablesCreated'] = "Tables created:";
$GLOBAL['createTablesBtn'] = "Create the tables";
$GLOBAL['orgConfigTitle'] = "Organization configuration";
$GLOBAL['orgConfigHint'] = "This information appears in the application title and on the donation receipts. A member group for the current year will be created automatically.";
$GLOBAL['orgNameLabel'] = "Organization name";
$GLOBAL['orgNamePlaceholder'] = "My association";
$GLOBAL['npaLabel'] = "NPA";
$GLOBAL['memberPrefixLabel'] = "Member groups prefix";
$GLOBAL['memberPrefixHint'] = "Example: “Member” → groups named “Member 2024”, “Member 2025”…";
$GLOBAL['seedTypesTitle'] = "Membership fee / donation types created automatically";
$GLOBAL['seedTypesHint'] = "If the <code>compta_type</code> table is empty, these 4 types will be inserted. Editable later in Settings.";
$GLOBAL['seedCotisationDesc'] = "annual membership fee (is_cotisation=1, excluded from donations)";
$GLOBAL['seedDonDesc'] = "general donation";
$GLOBAL['seedEventDesc'] = "event income (excluded from donations)";
$GLOBAL['seedInstitDesc'] = "institutional donors (is_institutional=1)";
$GLOBAL['saveAndContinueBtn'] = "Save and continue";
$GLOBAL['adminAccountTitle'] = "Administrator account";
$GLOBAL['adminAccountHint'] = "First admin account — full access to the application.";
$GLOBAL['usernameFormatHint'] = "2–50 characters, letters/digits/.-_";
$GLOBAL['displayNameLabel'] = "Display name";
$GLOBAL['adminDisplayNamePlaceholder'] = "Administrator";
$GLOBAL['emailLong'] = "E-mail";
$GLOBAL['optionalSuffix'] = "(optional)";
$GLOBAL['minPasswordChars'] = "Minimum 8 characters.";
$GLOBAL['createAccountBtn'] = "Create the account and finish";
$GLOBAL['installDoneTitle'] = "Installation complete";
$GLOBAL['installDoneMessage'] = "Database initialized, organization configured, admin account created.";
$GLOBAL['deleteInstallHint'] = "Delete <code>install.php</code> once signed in.";
$GLOBAL['goToAppBtn'] = "Go to the application";

// --- attestation_don.php / attestation_bulk.php ---
$GLOBAL['pdftkError'] = "pdftk error (code %d):";
$GLOBAL['noDonorsFound'] = "No donor found for %d (min CHF %d)";
$GLOBAL['pdfGenerationError'] = "PDF generation error:";
$GLOBAL['pdftkMergeError'] = "pdftk merge error:";

// --- Added: delete confirms, change password, index banner ---
$GLOBAL['archiveKeepsHistoryHint'] = "— keeps the history, removed from all views";
$GLOBAL['irreversibleHint'] = "— irreversible";
$GLOBAL['actionIrreversible'] = "This action is irreversible.";
$GLOBAL['content'] = "Content";
$GLOBAL['forcePasswordChangeNotice'] = "Please set a new password before continuing.";
$GLOBAL['changePasswordTitle'] = "Change the password";
$GLOBAL['currentPassword'] = "Current password";
$GLOBAL['newPassword'] = "New password";
$GLOBAL['confirmationLabel'] = "Confirmation";
$GLOBAL['pendingDbMigrationsLabel'] = "pending database migration%s";
$GLOBAL['pendingMigrationsBannerBody'] = "Apply them from
            <a href=\"%2\$s?view=settings&amp;tab=health\">Settings → Health</a>
            (no SSH needed), or on the command line <code>php html/tools/migrate.php</code>,
            after backing up the database. Until this is done, some
            features may not work correctly.\n";
$GLOBAL['language']              = "Language";
$GLOBAL['interfaceLanguage']     = "Interface language";
$GLOBAL['interfaceLanguageHelp'] = "Applied to your account, across all your sessions.";

// SMTP settings
$GLOBAL['smtpSettings']        = "Email";
$GLOBAL['smtpServer']          = "SMTP Server";
$GLOBAL['smtpHost']            = "SMTP Host";
$GLOBAL['smtpPort']            = "Port";
$GLOBAL['smtpEncryption']      = "Encryption";
$GLOBAL['smtpEncNone']         = "None";
$GLOBAL['smtpAuth']            = "Authentication required";
$GLOBAL['smtpUser']            = "Username";
$GLOBAL['smtpPassword']        = "Password";
$GLOBAL['smtpPasswordSet']     = "Password saved";
$GLOBAL['smtpPasswordHelp']    = "Leave blank to keep the current password.";
$GLOBAL['smtpSender']          = "Sender";
$GLOBAL['smtpFromName']        = "Sender name";
$GLOBAL['smtpFromEmail']       = "Sender email";
$GLOBAL['smtpReplyTo']         = "Reply-To address";
$GLOBAL['smtpReplyToHelp']     = "Optional. If empty, replies go to the sender address.";
$GLOBAL['smtpTest']            = "Test configuration";
$GLOBAL['smtpTestTo']          = "Send a test email to";
$GLOBAL['smtpTestSend']        = "Send";
$GLOBAL['smtpTesting']         = "Sending…";
$GLOBAL['smtpTestOk']          = "Email sent successfully.";
$GLOBAL['smtpTestFail']        = "Send failed. Please check your configuration.";
$GLOBAL['smtpTestMissingTo']   = "Please enter a destination email address.";

// Email log journal
$GLOBAL['emailLog']              = "Send log";
$GLOBAL['emailLogDate']          = "Date";
$GLOBAL['emailLogTo']            = "Recipient";
$GLOBAL['emailLogSubject']       = "Subject";
$GLOBAL['emailLogStatus']        = "Status";
$GLOBAL['emailLogStatusSent']    = "Sent";
$GLOBAL['emailLogStatusError']   = "Error";
$GLOBAL['emailLogEmpty']         = "No emails sent yet.";
$GLOBAL['emailLogPurge']         = "Clear log";
$GLOBAL['emailLogPurgeConfirm']  = "Delete all email log entries?";
$GLOBAL['emailLogPurged']        = "Log cleared.";
$GLOBAL['emailLogResend']        = "Resend";
$GLOBAL['emailLogResending']     = "Resending…";
$GLOBAL['emailLogResendOk']      = "Email resent successfully.";
$GLOBAL['emailLogResendFail']    = "Resend failed.";

// Email templates
$GLOBAL['emailTemplates']              = "Email templates";
$GLOBAL['emailTemplatesSaved']         = "Template saved.";
$GLOBAL['emailTemplateSubject']        = "Subject";
$GLOBAL['emailTemplateBody']           = "Body";
$GLOBAL['emailTemplateHelp'] = "Available variables: {{firstname}}, {{lastname}}, {{email}}, {{org_name}}, {{contact_email}}, {{org_address}}, {{org_city}}, {{org_web}}";
$GLOBAL['emailTemplateWelcome']        = "Welcome email";
$GLOBAL['emailTemplateCotiReminder']   = "Membership reminder";
$GLOBAL['emailTemplateAttestationDon'] = "Donation certificate";
$GLOBAL['emailWelcomeEnabled']         = "Send a welcome email when a member is created";

// Welcome email manual send
$GLOBAL['sendWelcomeEmail']        = "Send welcome email";
$GLOBAL['sendWelcomeEmailSending'] = "Sending…";
$GLOBAL['sendWelcomeEmailOk']      = "Welcome email sent.";
$GLOBAL['sendWelcomeEmailFail']    = "Send failed.";
$GLOBAL['sendWelcomeEmailNoEmail'] = "This member has no email address.";
$GLOBAL['sendWelcomeEmailAlreadySent'] = "Welcome email already sent on %s";
