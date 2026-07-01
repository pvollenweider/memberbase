<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for CSV/TSV member import (3-step wizard).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: importUpload, importApply, importResolveDuplicates

if (!canWrite()) { http_response_code(403); exit; }

require_once __DIR__ . '/../lib/import_fields.php';

function importRedirect(string $url): never {
    if (!empty($_SERVER['HTTP_HX_REQUEST'])) { header('HX-Location: ' . $url); exit; }
    header('Location: ' . $url); exit;
}

// ── Step 1 → 2 : parse uploaded file ────────────────────────────────────────
if ($_REQUEST['action'] === 'importUpload') {
    $err = $_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        $msg = $err === UPLOAD_ERR_INI_SIZE ? 'toobig' : 'upload';
        importRedirect($_SERVER['PHP_SELF'] . '?view=importStep1&err=' . $msg);
    }

    $content = file_get_contents($_FILES['csv']['tmp_name']);

    // Convert Latin-1 → UTF-8 if needed
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }
    // Remove UTF-8 BOM
    $content = ltrim($content, "\xEF\xBB\xBF");

    // Detect delimiter on first line
    $firstLine = strtok($content, "\n");
    $counts = ["\t" => substr_count($firstLine, "\t"), ';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ',')];
    arsort($counts);
    $delimiter = key($counts);

    // Parse
    $tmp = tmpfile();
    fwrite($tmp, $content);
    rewind($tmp);

    $headers = null;
    $rows    = [];
    while (($row = fgetcsv($tmp, 0, $delimiter)) !== false) {
        if ($headers === null) {
            $headers = array_map('trim', $row);
            continue;
        }
        if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
        $row = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
        $rows[] = array_map('trim', $row);
        if (count($rows) >= 5000) break;
    }
    fclose($tmp);

    if (empty($headers) || empty($rows)) {
        importRedirect($_SERVER['PHP_SELF'] . '?view=importStep1&err=empty');
    }

    $_SESSION['_import_headers']   = $headers;
    $_SESSION['_import_rows']      = $rows;
    $_SESSION['_import_delimiter'] = $delimiter;

    importRedirect($_SERVER['PHP_SELF'] . '?view=importStep2');

// ── Step 2 → 3 : apply mapping, create new, detect duplicates ───────────────
} elseif ($_REQUEST['action'] === 'importApply') {
    $mapping = $_POST['mapping'] ?? [];
    $rows    = $_SESSION['_import_rows']    ?? [];
    $headers = $_SESSION['_import_headers'] ?? [];

    if (empty($rows)) {
        importRedirect($_SERVER['PHP_SELF'] . '?view=importStep1&err=session');
    }

    $allowed = importAllowedFields();

    $stmtByEmail = $pdo->prepare("
        SELECT id, firstName, lastName, society, email
        FROM users WHERE status=1 AND TRIM(email) != '' AND TRIM(LOWER(email))=TRIM(LOWER(?))
    ");
    $stmtByName = $pdo->prepare("
        SELECT id, firstName, lastName, society, email
        FROM users WHERE status=1
          AND TRIM(LOWER(firstName))=TRIM(LOWER(?))
          AND TRIM(LOWER(lastName))=TRIM(LOWER(?))
          AND TRIM(firstName) != '' AND TRIM(lastName) != ''
    ");

    $created    = 0;
    $duplicates = [];

    foreach ($rows as $rowIdx => $row) {
        $data = [];
        foreach ($mapping as $colIdx => $field) {
            if ($field === '' || !in_array($field, $allowed, true)) continue;
            $data[$field] = $row[(int)$colIdx] ?? '';
        }
        if (empty(array_filter($data, fn($v) => $v !== ''))) continue;

        $email     = trim($data['email'] ?? '');
        $firstName = trim($data['firstName'] ?? '');
        $lastName  = trim($data['lastName'] ?? '');

        // Duplicate detection: email first, then name
        $existing = null;
        if ($email !== '') {
            $stmtByEmail->execute([$email]);
            $existing = $stmtByEmail->fetch(PDO::FETCH_OBJ) ?: null;
        }
        if (!$existing && $firstName !== '' && $lastName !== '') {
            $stmtByName->execute([$firstName, $lastName]);
            $existing = $stmtByName->fetch(PDO::FETCH_OBJ) ?: null;
        }

        if ($existing) {
            $duplicates[] = [
                'rowIdx'       => $rowIdx,
                'data'         => $data,
                'existingId'   => (int)$existing->id,
                'existingName' => trim($existing->firstName . ' ' . $existing->lastName) ?: (string)$existing->society,
                'existingEmail'=> (string)$existing->email,
            ];
            continue;
        }

        $user            = new User();
        $user->firstName = unquote($firstName);
        $user->lastName  = unquote($lastName);
        $user->society   = unquote($data['society'] ?? '');
        $user->sexe      = 'na';
        $user->address   = unquote($data['address'] ?? '');
        $user->npa       = unquote($data['npa'] ?? '');
        $user->email     = unquote($email);
        $user->emailAlt  = unquote($data['emailAlt'] ?? '');
        $user->web       = unquote($data['web'] ?? '');
        $user->tel       = unquote($data['tel'] ?? '');
        $user->telProf   = unquote($data['telProf'] ?? '');
        $user->portable  = unquote($data['portable'] ?? '');
        $user->fax       = unquote($data['fax'] ?? '');
        $user->comment   = unquote($data['comment'] ?? '');

        $_bd = trim($data['birthDay'] ?? '');
        $user->birthDay  = $_bd !== '' ? (string)(int)formatedDateToTimeStamp($_bd) : '0';

        $newId = (int)$user->save();
        auditLog($pdo, 'importUser', 'import CSV | ' . trim("$firstName $lastName") . ' | email: ' . $email, $newId);
        $created++;
    }

    $_SESSION['_import_created']    = $created;
    $_SESSION['_import_duplicates'] = $duplicates;

    importRedirect($_SERVER['PHP_SELF'] . '?view=importStep3');

// ── Step 3 : resolve duplicates ──────────────────────────────────────────────
} elseif ($_REQUEST['action'] === 'importResolveDuplicates') {
    $choices    = $_POST['choice'] ?? [];
    $duplicates = $_SESSION['_import_duplicates'] ?? [];
    $allowed    = importAllowedFields();

    $resolved = 0;
    foreach ($duplicates as $i => $dup) {
        $choice = $choices[$i] ?? 'ignore';
        if ($choice === 'ignore') continue;

        $user = new User();
        $user->lookupUser($dup['existingId']);
        if (!$user->getId()) continue;

        foreach ($allowed as $field) {
            if (!isset($dup['data'][$field])) continue;
            $val = trim($dup['data'][$field]);
            if ($val === '') continue;
            if ($choice === 'fill' && trim((string)$user->$field) !== '') continue;
            if ($field === 'birthDay') {
                $user->$field = (string)(int)formatedDateToTimeStamp($val);
            } else {
                $user->$field = unquote($val);
            }
        }
        $user->save();
        auditLog($pdo, 'importUserUpdate', 'import CSV | #' . $dup['existingId'] . ' ' . $dup['existingName'] . ' | mode: ' . $choice, $dup['existingId']);
        $resolved++;
    }

    unset($_SESSION['_import_headers'], $_SESSION['_import_rows'], $_SESSION['_import_delimiter'],
          $_SESSION['_import_created'], $_SESSION['_import_duplicates']);

    importRedirect($_SERVER['PHP_SELF'] . '?import_done=1&import_resolved=' . $resolved);
}
