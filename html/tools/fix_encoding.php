<?php
/**
 * One-shot encoding repair for users table.
 * Run once, then delete this file.
 *
 * Usage: php fix_encoding.php [--dry-run] [--column=npa]
 * Or via browser (restrict access first).
 */

$dryRun = in_array('--dry-run', $argv ?? []);
$col    = 'npa'; // change to address, society, etc. and re-run
foreach ($argv ?? [] as $a) {
    if (str_starts_with($a, '--column=')) {
        $col = substr($a, 9);
    }
}

chdir(__DIR__ . '/..');
require_once 'includes/lib/bootstrap.php';

$allowedCols = ['npa', 'address', 'society', 'firstname', 'lastname', 'libele'];
if (!in_array($col, $allowedCols)) {
    die("Column not allowed: $col\n");
}

// Debug: show raw hex for rows with suspicious bytes
if ($dryRun) {
    $dbg = $pdo->query("SELECT id, HEX(`$col`) as h, `$col` as v FROM users WHERE `$col` LIKE '%â%' OR `$col` LIKE '%Ã%' LIMIT 5");
    foreach ($dbg->fetchAll(PDO::FETCH_OBJ) as $d) {
        echo "DEBUG id={$d->id} hex={$d->h} val=" . json_encode($d->v) . "\n";
        $as_bytes = @iconv('UTF-8', 'CP1252', $d->v);
        echo "  iconv(UTF-8→CP1252): " . ($as_bytes === false ? 'FALSE' : bin2hex($as_bytes)) . "\n";
        if ($as_bytes !== false) {
            echo "  is_utf8: " . (mb_check_encoding($as_bytes, 'UTF-8') ? 'yes' : 'no') . "\n";
            echo "  len_orig=" . mb_strlen($d->v, 'UTF-8') . " len_candidate=" . mb_strlen($as_bytes, 'UTF-8') . "\n";
        }
    }
    echo "\n";
}

$stmt = $pdo->query("SELECT id, `$col` FROM users WHERE `$col` != ''");
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);

$fixed = 0;
$skipped = 0;

foreach ($rows as $row) {
    $original = $row->$col;

    // Already valid UTF-8 and ASCII-only → skip
    if (mb_check_encoding($original, 'UTF-8') && !preg_match('/[\x80-\xFF]/', $original)) {
        $skipped++;
        continue;
    }

    $repaired = $original;

    if (!mb_check_encoding($original, 'UTF-8')) {
        // Raw cp1252 bytes in column — convert directly to UTF-8
        $converted = @iconv('CP1252', 'UTF-8', $original);
        if ($converted !== false) {
            $repaired = $converted;
        }
    } else {
        // Valid UTF-8, but may be mojibake:
        // Each cp1252 byte (e.g. 0xE2 0x80 0x99) was stored as 3 cp1252 chars
        // (â € ™) then re-encoded as UTF-8. To recover: map UTF-8 chars back
        // to cp1252 bytes — those bytes should then be valid UTF-8 themselves.
        $as_bytes = @iconv('UTF-8', 'CP1252', $original);
        if ($as_bytes !== false
            && mb_check_encoding($as_bytes, 'UTF-8')
            && mb_strlen($as_bytes, 'UTF-8') < mb_strlen($original, 'UTF-8')
        ) {
            $repaired = $as_bytes;
        }
    }

    if ($repaired === $original) {
        $skipped++;
        continue;
    }

    echo "id={$row->id}: " . json_encode($original) . " → " . json_encode($repaired) . "\n";

    if (!$dryRun) {
        $upd = $pdo->prepare("UPDATE users SET `$col` = ? WHERE id = ?");
        $upd->execute([$repaired, $row->id]);
    }
    $fixed++;
}

echo "\nColumn: $col | Fixed: $fixed | Skipped: $skipped" . ($dryRun ? " [DRY RUN]" : "") . "\n";
