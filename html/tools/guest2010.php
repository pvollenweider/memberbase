<?php 
$charset = "UTF-8";
header("Content-Type: text/html; charset=$charset");

include "resources_fr.inc";
include "declarations.inc";
include "user_class.inc";
include "team_class.inc";
include "compta_class.inc";
include "property_class.inc";
include "metagroup_class.inc";

$FileName = "2010.txt";
$FileHandle = fopen($FileName, "r");
$FileContent = fread($FileHandle, filesize($FileName));
fclose($FileHandle);

$lines = explode("\n", $FileContent);
?>
<table border="1">
    <tr>
        <td>societe</td>
        <td>nom</td>
        <td>prenom</td>
        <td>adresse</td>
        <td>npa</td>
        <td>info</td>
    </tr>
<?php 
foreach ($lines as $line) {
    $line = str_replace("'", "''", $line);
    $fields = explode("\t", $line);
    $nom = $fields[0];
    $prenom = $fields[1];
    $adresse = $fields[2];
    $npa = $fields[3] . " " . $fields[4];
    $info = $fields[5];
    $society = $fields[6];
    ?>
        <tr>
            <td><?=$society?></td>
            <td><?=$nom?></td>
            <td><?=$prenom?></td>
            <td><?=$adresse?></td>
            <td><?=$npa?></td>
            <td><?=$info?></td>
        </tr>
    <?php 
    /*
    $user->lookupUser($userid);
    $user->addMembership(55);
    */
}
?>
</table>
