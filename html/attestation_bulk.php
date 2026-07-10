<?php
define('APP_ENTRY', true);
/**
 * Generates a bulk donation attestation PDF (all qualifying donors for a year) via pdftk.
 *
 * Individual PDFs are merged into a single download using pdftk's cat command.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

require_once __DIR__ . '/includes/lib/auth.php';
requireLogin();
ob_start();
set_time_limit(120);

include __DIR__ . "/includes/lib/bootstrap.php";
require_once __DIR__ . '/locales/resources_fr.php';
require_once __DIR__ . '/includes/lib/attestation.php';
include "classes/contact_class.php";

$year   = isset($_GET['year'])   ? (int)$_GET['year']   : (int)date('Y');
$minSum = isset($_GET['minSum']) ? (int)$_GET['minSum'] : 1;
$stamp  = !empty($_GET['stamp']);
if (!in_array($minSum, [1, 200, 500, 1000])) { $minSum = 1; }
if ($year < 2000 || $year > 2100) { http_response_code(400); die('Invalid year'); }

$from = mktime(0, 0, 0, 1, 0, $year);
$to   = mktime(0, 0, 0, 1, 1, $year + 1);

// Same query as resume.inc — one row per qualifying donor
$stmt = $pdo->prepare("
    SELECT u.id, u.firstname, u.lastname, u.npa, u.address,
           SUM(c.sum) AS total
    FROM contact u
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
    die(sprintf($GLOBAL['noDonorsFound'], $year, $minSum));
}

$tmpDir   = sys_get_temp_dir();
$tmpFiles = [];
$errors   = [];

foreach ($donors as $row) {
    $fields = mbBuildAttestationFields(
        $appSettings,
        $row->lastname  ?? '',
        $row->firstname ?? '',
        $row->npa       ?? '',
        $row->address   ?? '',
        (float)$row->total,
        $year
    );
    $pdf = mbGenerateAttestationPdf($fields);
    if ($pdf !== null) {
        $tmpPdf = tempnam($tmpDir, 'att_') . '.pdf';
        file_put_contents($tmpPdf, $pdf);
        $tmpFiles[] = $tmpPdf;
    } else {
        $errors[] = ($row->lastname ?? '?') . ': pdftk fill_form failed';
    }
}

if (!$tmpFiles) {
    http_response_code(500);
    echo '<pre>' . $GLOBAL['pdfGenerationError'] . "\n" . htmlspecialchars(implode("\n", $errors)) . '</pre>';
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
    echo '<pre>' . $GLOBAL['pdftkMergeError'] . "\n" . htmlspecialchars(implode("\n", $out)) . '</pre>';
    if (file_exists($mergedPdf)) { unlink($mergedPdf); }
    exit;
}

if ($stamp) {
    require_once __DIR__ . '/includes/lib/attestation_stamp.php';
    mbStampAttestation($mergedPdf);
}

$filename = 'attestations_dons_' . $year . '_minCHF' . $minSum . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($mergedPdf));
header('Cache-Control: private, no-cache');
readfile($mergedPdf);
unlink($mergedPdf);
