<?php 
$charset = "ISO-8859-1";
//header("Content-Type: text/html; charset=$charset");

include "includes/declarations.inc";
include "locales/resources_fr.inc";
include "classes/user_class.inc";
$filename="casa_" . date("Ymd",time()) . ".xls";
header("Content-disposition: filename=$filename");
header("Content-type: application/ms-excel;");
header("Pragma: no-cache");
header("Expires: 0");

print html_entity_decode($GLOBAL['sexe']) . "\t";
print html_entity_decode($GLOBAL['society']) . "\t";
print html_entity_decode($GLOBAL['lastName']) . "\t";
print html_entity_decode($GLOBAL['firstName']) . "\t";
print html_entity_decode($GLOBAL['address']) . "\t";
print html_entity_decode($GLOBAL['npa']) . "\t";
print html_entity_decode($GLOBAL['tel']) . "\t";
print html_entity_decode($GLOBAL['telProf']) . "\t";
print html_entity_decode($GLOBAL['portable']) . "\t";
print html_entity_decode($GLOBAL['fax']) . "\t";
print html_entity_decode($GLOBAL['email']) . "\t";
print html_entity_decode($GLOBAL['web']) . "\t";
print html_entity_decode($GLOBAL['birthDay']) . "\t";
print html_entity_decode($GLOBAL['creationDate']) . "\t";
print html_entity_decode($GLOBAL['lastModif']) . "\t";
print html_entity_decode($GLOBAL['groups']) . "\t";
print html_entity_decode($GLOBAL['coti']) . " " . date("Y") . "\t";
print "id\n";

# get all groups
$query = "SELECT id,name FROM team ORDER BY name";
$result = mysql_query($query) or die ("Query failed: " . mysql_error());
$teamsId = array();
$teamsName = array();
while ($row = mysql_fetch_object($result)) {
    $id = $row->id;
    $name = $row->name;
    array_push($teamsId,$id);
    array_push($teamsName,$name);
}
mysql_free_result($result);

$searchString = "";
if (isset ($_REQUEST["searchString"])) {
    $searchString = $_REQUEST["searchString"];
}
$team = -1;
if (isset ($_REQUEST["team"])) {
    $team = $_REQUEST["team"];
}
$year=date("Y");
if (isset($_REQUEST['year'])) {
    $year = $_REQUEST['year'];
}

$query = "SELECT DISTINCT ".
             "users.id,".
             "users.firstname,".
             "users.lastname,".
             "users.society,".
             "users.sexe,".
             "users.address,".
             "users.npa,".
             "users.tel,".
             "users.telprof,".
             "users.portable,".
             "users.fax,".
             "users.email,".
             "users.web,".
             "users.birthday,".
             "users.creationDate,".
             "users.modificationDate ".
         "FROM users";
if ($team == -4 || $team == -1234 || $team == -3333) {
    $query .= ",compta ";
}
if ($team != -1) {
    $query .= ",user_properties ";
}
$query .= " WHERE 1=1";
if (isset($searchString)) {
    $query .= " AND (users.firstname LIKE '%" . $searchString . "%'";
    $query .= " OR users.lastname LIKE '%" . $searchString . "%'";
    $query .= " OR users.society LIKE '%" . $searchString . "%'";
    $query .= " OR users.npa LIKE '%" . $searchString . "%'";
    $query .= " OR users.email LIKE '%" . $searchString . "%'";
    $query .= " OR users.comment LIKE '%" . $searchString . "%'";
    $query .= " OR users.address LIKE '%" . $searchString . "%')";
}
if ($team != -1) {

    if ($team == -2) {
        $query .= "AND users.id=user_properties.user_id AND NOT (";
        $query .= "    user_properties.parameter='team_13'";
        $query .= "    OR user_properties.parameter='team_10'";
        $query .= "    OR user_properties.parameter='team_20'";
        $query .= " )";
    } else if ($team == -3 || $team == -5555) {
        $query .= "AND users.id=user_properties.user_id AND NOT (";
        $query .= "    user_properties.parameter='team_19'";
        $query .= " )";
    } else if ($team == -4 || $team == -1234 || $team == -3333) {
        # search member
        $query .= " AND users.id=user_properties.user_id ";
        $query .= "AND (user_properties.parameter='team_5' OR ";
        $query .= "user_properties.parameter='team_6') ";
    } else if ($team == -5) {
        $logger->debug("team is -5 -> Mailing noel");
        $query .= "AND users.id=user_properties.user_id AND (";
        $query .= "    user_properties.parameter='team_6'";
        $query .= "    OR user_properties.parameter='team_5'";
        $query .= "    OR user_properties.parameter='team_9'";
        $query .= "    OR user_properties.parameter='team_7')";
        $query .= "    AND NOT user_properties.parameter='team_19'";
    } else if ($team == -6) {
        $logger->debug("team is -6 -> physiquePasMembreDonEn060708");
        $query .= "AND users.id=user_properties.user_id ";
        $query .= "    AND user_properties.parameter='team_7'";
    } else {
        $query .= " AND users.id=user_properties.user_id AND user_properties.parameter='team_$team'";
    }
}
$query .= " ORDER BY lastname,firstname";

$result = mysql_query($query) or die ("Query failed: " . mysql_error());

while ($row = mysql_fetch_object($result)) {
    $id = $row->id;
    $displayLine = true;
    $user = new User();
    $user->lookupUser($id);
    if ($team == -1234) {
        $displayLine = false;
        if ($user->isCotisationPayed(2004) != -1 ||
            $user->isCotisationPayed(2005) != -1 ||
            $user->isCotisationPayed(2006) != -1 ||
            $user->isCotisationPayed(2007) != -1 ||
            $user->isCotisationPayed(2008) != -1 ||
            $user->isCotisationPayed(2009) != -1
            ) {
            $displayLine = true;
        }
        $displayLine = !$displayLine;
        if ($user->isMemberOfTeam(19)) {
            $displayLine = false;
        }
    } else if ($team == -3333) {
        $displayLine = false;
        if ($user->isCotisationPayed($year-1) == -1 &&
            $user->isCotisationPayed($year-2)== -1 &&
            $user->isCotisationPayed($year-3)== -1) {
                $displayLine = true;
        } else {
            $displayLine = false;
        }
        if ($user->isMemberOfTeam(19)) {
            $displayLine = false;
        }
     } else if ($team == -5555) {
        $displayLine = true;
        if ($user->hasComptaEntries($year,10)) {
                $displayLine = false;
        } else {
            $displayLine = true;
        }
        if ($user->isMemberOfTeam(19) || $user->isMemberOfTeam(6)|| $user->isMemberOfTeam(47)|| $user->isMemberOfTeam(2) || $user->isMemberOfTeam(31)) {
            $displayLine = false;
        }
   } else if ($team == -6) {
        $displayLine = false;

        if ($user->hasPayed(2011) == -1
            ) {
            $displayLine = true;
        }
        if ($user->isMemberOfTeam(6) || $user->isMemberOfTeam(5)) {
            $displayLine = true;
        }
        $displayLine = !$displayLine;
        if ($user->isMemberOfTeam(19)) {
            $displayLine = false;
        }
    } else if ($team == -4) {
        if ($user->isCotisationPayed($year) > -1) {
            $displayLine = false;
        }
        if ($user->isMemberOfTeam(19)) {
            $displayLine = false;
        }
    } else if ($team == 19) {
        $displayLine = true;
    } else {
        if ($user->isMemberOfTeam(19)) {
            $displayLine = false;
        }
    }

    if ($displayLine && ! $user->isMemberOfTeam(19)) {
        $firstName = mb_convert_encoding($row->firstname, "ISO-8859-1", "UTF-8");
        $lastName = mb_convert_encoding($row->lastname, "ISO-8859-1", "UTF-8");
        $society = mb_convert_encoding($row->society, "ISO-8859-1", "UTF-8");
        $sexe = $row->sexe;
        if ($sexe == "na") { $sexe = "-"; }
        else if ($sexe == "f") { $sexe = "Madame"; }
        else if ($sexe == "m") { $sexe = "Monsieur"; }
        else if ($sexe == "hf") { $sexe = "Monsieur et Madame"; }
        $address = mb_convert_encoding($row->address, "ISO-8859-1", "UTF-8");
        $npa = mb_convert_encoding($row->npa, "ISO-8859-1", "UTF-8");
        $tel = $row->tel;
        $telprof = $row->telprof;
        $portable = $row->portable;
        $fax = $row->fax;
        $email = $row->email;
        $web = $row->web;
        $birthday = timeStampToformatedDate($row->birthday);
        $creationDate = timeStampToformatedDate($row->creationDate);
        $modificationDate = timeStampToformatedDate($row->modificationDate);
        $groups = "";
        $j = 0;
        for($i=0;$i<count($teamsId);$i++) {
            if ($user->isMemberOfTeam($teamsId[$i])) {
                $j++;
                if ($j > 1) {
                    $groups .= ", ";
                }
                $groups .= mb_convert_encoding($teamsName[$i], "ISO-8859-1", "UTF-8");
            }
        }
        $coti = $user->isCotisationPayed($year);
        if ($coti < 0) {
            $coti = "";
        } else {
            $coti = timeStampToformatedDate($coti);
        }
        print "$sexe\t$society\t$lastName\t$firstName\t$address\t$npa\t$tel\t$telprof\t$portable\t$fax\t$email\t$web\t$birthday\t$creationDate\t$modificationDate\t$groups\t$coti\t$id\n";
    }
}
mysql_free_result($result);
?>
