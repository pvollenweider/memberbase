<?php
/**
 * Generates a single donation attestation PDF for one member/year via pdftk.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

require_once __DIR__ . '/includes/auth.inc';
requireLogin();
ob_start();
include "includes/declarations.inc";
include "classes/user_class.inc";

$userid = isset($_GET['userid']) ? (int)$_GET['userid'] : 0;
$year   = isset($_GET['year'])   ? (int)$_GET['year']   : (int)date('Y');

if (!$userid) { http_response_code(400); die('Missing userid'); }

$user = new User();
$user->lookupUser($userid);

// Total donations for the year (excluding types flagged is_excluded_from_donation)
$from = mktime(0, 0, 0, 1, 0, $year);
$to   = mktime(0, 0, 0, 1, 1, $year + 1);
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(c.sum), 0) AS total
    FROM compta c
    WHERE c.user_id = ?
      AND c.date > ? AND c.date < ?
      AND c.type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation = 1)
");
$stmt->execute([$userid, $from, $to]);
$total = (float)$stmt->fetchObject()->total;
$totalFormatted = number_format($total, 2, '.', "'");

// Split "NPA Localité" stored as single field
$npaFull  = $user->getNpa() ?? '';
$npaParts = preg_split('/\s+/', trim($npaFull), 2);
$npa      = $npaParts[0] ?? '';
$localite = $npaParts[1] ?? '';

// AcroForm field values — institution info from app_settings
$fields = [
    'Nom de institution' => $appSettings['org_name']    ?? '',
    'Adresse'            => $appSettings['org_address']  ?? '',
    'NPA'                => $appSettings['org_npa']      ?? '',
    'Localite'           => $appSettings['org_city']     ?? '',
    'Nom'                => $user->getLastName() ?? '',
    'Prenom'             => $user->getFirstName() ?? '',
    'Adresse 2'          => $user->getAddress() ?? '',
    'NPA 2'              => $npa,
    'Localite 2'         => $localite,
    'annee1'             => (string)$year,
    'annee2'             => (string)$year,
    'Case à cocher2'     => 'Oui',   // Dons en espèces
    'Somme'              => $totalFormatted,
    'Lieu'               => $appSettings['org_city'] ?? '',
    'mois'               => date('m'),
    'date'               => date('Y'),
];

// Generate FDF (Form Data Format) for pdftk
// Field names: convert UTF-8 → Latin-1 to match PDF internal encoding
function fdf_escape_name(string $s): string {
    $latin1 = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $latin1);
}

// Field values: encode as UTF-16 BE hex string to support accented chars
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

$tmpFdf = tempnam(sys_get_temp_dir(), 'att_') . '.fdf';
$tmpPdf = tempnam(sys_get_temp_dir(), 'att_') . '.pdf';

file_put_contents($tmpFdf, fdf_generate($fields));

$template = __DIR__ . '/assets/attestation.pdf';
$cmd = sprintf(
    'pdftk %s fill_form %s output %s flatten 2>&1',
    escapeshellarg($template),
    escapeshellarg($tmpFdf),
    escapeshellarg($tmpPdf)
);
exec($cmd, $cmdOutput, $returnCode);

unlink($tmpFdf);

if ($returnCode !== 0 || !file_exists($tmpPdf) || filesize($tmpPdf) === 0) {
    http_response_code(500);
    echo '<pre>Erreur pdftk (code ' . $returnCode . "):\n" . htmlspecialchars(implode("\n", $cmdOutput)) . '</pre>';
    exit;
}

$filename = 'attestation_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $user->getLastName() . '_' . $year) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpPdf));
header('Cache-Control: private, no-cache');
readfile($tmpPdf);
unlink($tmpPdf);
exit;
