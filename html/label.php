<?php 
include "declarations.inc";
include "user_class.inc";
$charset = "ISO-8859-1";
$filename="casa_" . date("Ymd",time()) . ".doc";
header("Content-disposition: filename=$filename");
header("Content-type: application/msword");
header("Pragma: no-cache");
header("Expires: 0");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
xmlns:w="urn:schemas-microsoft-com:office:word"
xmlns="http://www.w3.org/TR/REC-html40">

<head>
<meta http-equiv=Content-Type content="text/html; charset=<?=$charset?>">
<meta name=ProgId content=Word.Document>
<link rel=File-List href="">
<title>Etiquettes</title>
<!--[if gte mso 9]><xml>
 <w:WordDocument>
  <w:HyphenationZone>21</w:HyphenationZone>
 </w:WordDocument>
</xml><![endif]-->
<style>
<!--
 /* Style Definitions */
p
	{mso-style-parent:"";
	margin:0cm;
	margin-bottom:.0001pt;
	mso-pagination:widow-orphan;
	font-size:10.0pt;
	font-family:"Arial";
	mso-fareast-font-family:"Arial";
	margin-top:0cm;margin-right:20pt;margin-bottom:0cm;margin-left:20pt;margin-bottom:.0001pt;}
@page Section1
	{size:595.3pt 841.9pt;
	margin:0cm 0cm 0cm 0cm;
	mso-header-margin:36.0pt;
	mso-footer-margin:36.0pt;
	mso-page-numbers:1;
	mso-paper-source:4;}
div.Section1
	{page:Section1;}
-->
</style>
</head>
<body lang=FR style='tab-interval:35.4pt'>

<?php 
$orderSort = "ASC";
$orderSortInverse = "DESC";
$orderColumn = "lastname";

if (isset($_REQUEST['orderSort'])) {
    $orderSort = $_REQUEST['orderSort'];
    $orderSortInverse = $orderSort == "ASC" ? "DESC" : "ASC";
}
if (isset($_REQUEST['orderColumn'])) {
    $orderColumn = $_REQUEST['orderColumn'];
}


$searchString = "";
if (isset ($_REQUEST["searchString"])) {
    $searchString = $_REQUEST["searchString"];
}
$team = -1;
if (isset ($_REQUEST["team"])) {
    $team = $_REQUEST["team"];
}
$query = "SELECT DISTINCT users.id,users.firstname,users.sexe,users.lastname,users.society,users.address,users.npa FROM users";
if ($team == -4 || $team == -1234 || $team == -3333) {
    $query .= ",compta ";
}
if ($team != -1) {
    $query .= ",user_properties ";
}
$query .= " WHERE 1=1";

if (isset($searchString) && $searchString != "") {
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
    } else if ($team == -3) {
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
$col=0;
$row=0;
$cell=0;
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
    } else if ($team == -6) {
        $displayLine = false;

        if ($user->hasPayed(2006) == -1 ||
            $user->hasPayed(2007) == -1
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
        $address = mb_convert_encoding($row->address, "ISO-8859-1", "UTF-8");
        $npa = mb_convert_encoding($row->npa, "ISO-8859-1", "UTF-8");
        $sexe = $row->sexe;
    	if ($cell%24==0){
    		?><div class=Section1><table border=0 cellspacing=0 cellpadding=0 style='border-collapse:collapse;mso-padding-top-alt:0cm;mso-padding-bottom-alt:0cm'><?php 
    	}
    	if ($cell%3==0){
    		?><tr style='height:104.9pt'><?php 
     	}
     	?>
    	<td width=265 style='width:198.4pt;padding:0cm .75pt 0cm .75pt;height:104.9pt'>
    	<p><?php 
    	if ($society) {
    	    ?><b><?=$society?></b><br/><?php 
    	}
    	if ($sexe != "na") {
            if ($sexe == "f") {
                $sexe = "Madame";
            } else if ($sexe == "m") {
                $sexe = "Monsieur";
            } else if ($sexe == "hf") {
                $sexe = "Monsieur et Madame";
            }
            ?><?=$sexe?><br/><?php 
        }
    	?><?=$firstName?> <?=$lastName?></p>
    	<p><?=$address?></p>
    	<p><?=$npa?></p>
    	<p><![if !supportEmptyParas]>&nbsp;<![endif]><o:p></o:p></p>
    	<p><![if !supportEmptyParas]>&nbsp;<![endif]><o:p></o:p></p>	</td>
    	<?php 
    	$cell++;
    	if ($cell%3==0){
    		?></tr><?php 
    	}
    	if ($cell%24==0){
    		?></table></div><span style='font-size:12.0pt;font-family:"Times New Roman";mso-fareast-font-family:"Times New Roman";display:none;mso-hide:all;mso-ansi-language:FR;mso-fareast-language:FR;mso-bidi-language:AR-SA'><br clear=all style='page-break-before:always;mso-break-type:section-break'></span><?php 
    	}
    }
}
mysql_free_result($result);
?>
<div class=Section1>
<p><span style='display:none;mso-hide:all'><![if !supportEmptyParas]>&nbsp;<![endif]><o:p></o:p></span></p>
</div>

</body>
</html>
