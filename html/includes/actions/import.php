<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Action handler for CSV/TSV member import (3-step wizard).
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
// actions: importUpload, importApply, importResolveDuplicates

if (!isManager()) { http_response_code(403); exit; }

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

    if (($_FILES['csv']['size'] ?? 0) > 5 * 1024 * 1024) {
        importRedirect($_SERVER['PHP_SELF'] . '?view=importStep1&err=toobig');
    }

    $content = file_get_contents($_FILES['csv']['tmp_name']);

    // Convert Latin-1 → UTF-8 if needed
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }
    // Remove UTF-8 BOM
    $content = ltrim($content, "\xEF\xBB\xBF");

    // Detect delimiter on first line
    $_nl = strpos($content, "\n");
    $firstLine = $_nl === false ? $content : substr($content, 0, $_nl);
    $counts = ["\t" => substr_count($firstLine, "\t"), ';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ',')];
    arsort($counts);
    $delimiter = key($counts);

    // Parse
    $tmp = tmpfile();
    fwrite($tmp, $content);
    rewind($tmp);

    $headers   = null;
    $rows      = [];
    $truncated = false;
    while (($row = fgetcsv($tmp, 0, $delimiter)) !== false) {
        if ($headers === null) {
            $headers = array_map('trim', $row);
            continue;
        }
        if (count(array_filter($row, fn($v) => trim($v) !== '')) === 0) continue;
        if (count($rows) >= 5000) { $truncated = true; break; }
        $row = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
        $rows[] = array_map('trim', $row);
    }
    fclose($tmp);

    if (empty($headers) || empty($rows)) {
        importRedirect($_SERVER['PHP_SELF'] . '?view=importStep1&err=empty');
    }

    $_SESSION['_import_headers']   = $headers;
    $_SESSION['_import_rows']      = $rows;
    $_SESSION['_import_delimiter'] = $delimiter;
    $_SESSION['_import_truncated'] = $truncated;

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

    // At least one column must be mapped to a member field
    if (empty(array_intersect(array_values($mapping), $allowed))) {
        importRedirect($_SERVER['PHP_SELF'] . '?view=importStep2&err=nomap');
    }

    // Preload existing members once — O(1) in-memory lookups instead of 2 queries per row
    $byEmail = [];
    $byName  = [];
    $stmtAll = $pdo->query("
        SELECT id, firstName, lastName, society, email
        FROM users WHERE status=1
    ");
    while ($u = $stmtAll->fetch(PDO::FETCH_OBJ)) {
        $e = mb_strtolower(trim((string)$u->email));
        if ($e !== '' && !isset($byEmail[$e])) { $byEmail[$e] = $u; }
        $fn = mb_strtolower(trim((string)$u->firstName));
        $ln = mb_strtolower(trim((string)$u->lastName));
        if ($fn !== '' && $ln !== '' && !isset($byName["$fn|$ln"])) { $byName["$fn|$ln"] = $u; }
    }

    $created    = 0;
    $duplicates = [];

    $pdo->beginTransaction();

    // Resolve the target segment (team) the imported contacts should join.
    $segMode     = $_POST['segment_mode'] ?? 'auto';
    $segTeamId   = 0;
    $segTeamName = '';
    if ($segMode === 'existing') {
        $segTeamId = (int)($_POST['segment_existing_id'] ?? 0);
        $_chk = $pdo->prepare("SELECT name FROM team WHERE id=?");
        $_chk->execute([$segTeamId]);
        $segTeamName = (string)($_chk->fetchColumn() ?: '');
        if ($segTeamName === '') { $segTeamId = 0; } // stale id → skip
    } elseif ($segMode === 'new' || $segMode === 'auto') {
        $segTeamName = $segMode === 'new' ? trim((string)($_POST['segment_new_name'] ?? '')) : '';
        if ($segTeamName === '') { $segTeamName = sprintf($GLOBAL['importSegmentName'], date('d.m.Y H:i')); }
        $team = new Team();
        $team->name = $segTeamName;
        $team->setHidden(0);
        $team->save();
        $segTeamId = (int)$team->id;
        // Attach to a category (metagroup) when one was chosen for a brand-new segment
        $segCatId = $segMode === 'new' ? (int)($_POST['segment_new_category'] ?? 0) : 0;
        if ($segTeamId > 0 && $segCatId > 0) {
            $team->addMetagroupMembership($segCatId);
        }
    }
    $segStmt = ($segTeamId > 0)
        ? $pdo->prepare("INSERT IGNORE INTO user_properties (user_id, parameter, value) VALUES (?, ?, 'true')")
        : null;
    $segParam = 'team_' . $segTeamId;
    $segAdded = 0;

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

        // Duplicate detection: email first, then name (in-memory maps)
        $existing = null;
        if ($email !== '') {
            $existing = $byEmail[mb_strtolower($email)] ?? null;
        }
        if (!$existing && $firstName !== '' && $lastName !== '') {
            $existing = $byName[mb_strtolower($firstName) . '|' . mb_strtolower($lastName)] ?? null;
        }

        if ($existing) {
            // Existing members in the imported list still join the target segment
            if ($segStmt) { $segStmt->execute([(int)$existing->id, $segParam]); $segAdded += $segStmt->rowCount(); }
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
        $user->sexe      = isset($data['sexe']) ? importNormalizeSexe($data['sexe']) : 'na';
        $user->title     = unquote($data['title'] ?? '');
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

        if ($segStmt) { $segStmt->execute([$newId, $segParam]); $segAdded += $segStmt->rowCount(); }

        // Register in lookup maps so a repeated row in the same file is flagged as duplicate
        $_new = (object)['id' => $newId, 'firstName' => $firstName, 'lastName' => $lastName,
                         'society' => $data['society'] ?? '', 'email' => $email];
        if ($email !== '') { $byEmail[mb_strtolower($email)] ??= $_new; }
        if ($firstName !== '' && $lastName !== '') {
            $byName[mb_strtolower($firstName) . '|' . mb_strtolower($lastName)] ??= $_new;
        }
    }
    $pdo->commit();

    if ($segTeamId > 0) {
        auditLog($pdo, 'importSegment', "segment: {$segTeamName} (id={$segTeamId}) | {$segAdded} membre(s) ajouté(s) | mode: {$segMode}");
    }

    // Parsed rows are no longer needed — free the session (can hold MBs for large files)
    unset($_SESSION['_import_headers'], $_SESSION['_import_rows'], $_SESSION['_import_delimiter'], $_SESSION['_import_truncated']);

    $_SESSION['_import_created']    = $created;
    $_SESSION['_import_duplicates'] = $duplicates;
    $_SESSION['_import_segment']    = $segTeamId > 0 ? ['id' => $segTeamId, 'name' => $segTeamName, 'added' => $segAdded] : null;

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
                $user->$field = importFieldValue($field, $val);
            }
        }
        $user->save();
        auditLog($pdo, 'importUserUpdate', 'import CSV | #' . $dup['existingId'] . ' ' . $dup['existingName'] . ' | mode: ' . $choice, $dup['existingId']);
        $resolved++;
    }

    unset($_SESSION['_import_created'], $_SESSION['_import_duplicates'], $_SESSION['_import_segment']);

    importRedirect($_SERVER['PHP_SELF'] . '?import_done=1&import_resolved=' . $resolved);
}
