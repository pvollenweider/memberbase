<?php
require_once __DIR__ . '/includes/auth.inc';
requireLogin();
ob_start();
set_time_limit(120);

include "includes/declarations.inc";
include "classes/user_class.inc";

$year   = isset($_GET['year'])   ? (int)$_GET['year']   : (int)date('Y');
$minSum = isset($_GET['minSum']) ? (int)$_GET['minSum'] : 1;
if (!in_array($minSum, [1, 200, 500, 1000])) { $minSum = 1; }
if ($year < 2000 || $year > 2100) { http_response_code(400); die('Invalid year'); }

$from = mktime(0, 0, 0, 1, 0, $year);
$to   = mktime(0, 0, 0, 1, 1, $year + 1);

// Same query as resume.inc — one row per qualifying donor
$stmt = $pdo->prepare("
    SELECT u.id, u.firstname, u.lastname, u.npa, u.address,
           SUM(c.sum) AS total
    FROM users u
    JOIN compta c ON u.id = c.user_id
    WHERE c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)
      AND c.date > ? AND c.date < ?
    GROUP BY u.id, u.firstname, u.lastname, u.npa, u.address
    HAVING SUM(c.sum) >= ?
    ORDER BY u.lastname, u.firstname
");
$stmt->execute([$from, $to, $minSum]);
$donors = $stmt->fetchAll(PDO::FETCH_OBJ);

if (!$donors) {
    http_response_code(404);
    die('Aucun donateur trouvé pour ' . $year . ' (min CHF ' . $minSum . ')');
}

// ── FDF helpers (same as attestation_don.php) ──────────────────────────────
function fdf_escape_name(string $s): string {
    $latin1 = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin1);
}
function fdf_encode_value(string $s): string {
    $utf16be = mb_convert_encoding($s, 'UTF-16BE', 'UTF-8');
    return '<' . strtoupper(bin2hex("\xfe\xff" . $utf16be)) . '>';
}
function fdf_generate(array $fields): string {
    $fdf  = "%FDF-1.2\n%\xe2\xe3\xcf\xd3\n";
    $fdf .= "1 0 obj\n<< /FDF << /Fields [\n";
    foreach ($fields as $name => $value) {
        $fdf .= "<< /T (" . fdf_escape_name($name) . ") /V " . fdf_encode_value($value) . " >>\n";
    }
    $fdf .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    return $fdf;
}

$template  = __DIR__ . '/assets/attestation.pdf';
$tmpDir    = sys_get_temp_dir();
$tmpFiles  = [];
$errors    = [];

foreach ($donors as $row) {
    $npaFull  = $row->npa ?? '';
    $npaParts = preg_split('/\s+/', trim($npaFull), 2);
    $total    = number_format((float)$row->total, 2, '.', "'");

    $fields = [
        'Nom de institution' => 'Casa Alianza Suisse',
        'Adresse'            => 'Rue Cécile-Biéler-Butticaz 5',
        'NPA'                => '1207',
        'Localite'           => 'Genève',
        'Nom'                => $row->lastname  ?? '',
        'Prenom'             => $row->firstname ?? '',
        'Adresse 2'          => $row->address   ?? '',
        'NPA 2'              => $npaParts[0]    ?? '',
        'Localite 2'         => $npaParts[1]    ?? '',
        'annee1'             => (string)$year,
        'annee2'             => (string)$year,
        'Case à cocher2'     => 'Oui',
        'Somme'              => $total,
        'Lieu'               => 'Genève',
        'mois'               => date('m'),
        'date'               => date('Y'),
    ];

    $tmpFdf = tempnam($tmpDir, 'att_') . '.fdf';
    $tmpPdf = tempnam($tmpDir, 'att_') . '.pdf';
    file_put_contents($tmpFdf, fdf_generate($fields));

    $cmd = sprintf(
        'pdftk %s fill_form %s output %s flatten 2>&1',
        escapeshellarg($template),
        escapeshellarg($tmpFdf),
        escapeshellarg($tmpPdf)
    );
    exec($cmd, $out, $rc);
    unlink($tmpFdf);

    if ($rc === 0 && file_exists($tmpPdf) && filesize($tmpPdf) > 0) {
        $tmpFiles[] = $tmpPdf;
    } else {
        $errors[] = ($row->lastname ?? '?') . ': ' . implode(' ', $out);
        if (file_exists($tmpPdf)) { unlink($tmpPdf); }
    }
}

if (!$tmpFiles) {
    http_response_code(500);
    echo '<pre>Erreur génération PDFs:' . "\n" . htmlspecialchars(implode("\n", $errors)) . '</pre>';
    exit;
}

// Merge all individual PDFs into one
$mergedPdf = tempnam($tmpDir, 'att_bulk_') . '.pdf';
$cmd = 'pdftk ' . implode(' ', array_map('escapeshellarg', $tmpFiles))
     . ' cat output ' . escapeshellarg($mergedPdf) . ' 2>&1';
exec($cmd, $out, $rc);

foreach ($tmpFiles as $f) { unlink($f); }

if ($rc !== 0 || !file_exists($mergedPdf) || filesize($mergedPdf) === 0) {
    http_response_code(500);
    echo '<pre>Erreur merge pdftk:' . "\n" . htmlspecialchars(implode("\n", $out)) . '</pre>';
    if (file_exists($mergedPdf)) { unlink($mergedPdf); }
    exit;
}

$filename = 'attestations_dons_' . $year . '_minCHF' . $minSum . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($mergedPdf));
header('Cache-Control: private, no-cache');
readfile($mergedPdf);
unlink($mergedPdf);
exit;
