<?php
/**
 * One-shot migration: normalize comment HTML to match TipTap output.
 * Old format: <p>\r\n\ttext</p>\r\n  →  New: <p>text</p>
 *
 * Run once: php tools/normalize_comments.php
 */

require_once __DIR__ . '/../html/includes/declarations.inc';

function normalizeTiptap(string $html): string
{
    // Remove whitespace immediately after opening tags and before closing tags
    $html = preg_replace('/(<(?!\/)[^>]+>)\s+/', '$1', $html);
    $html = preg_replace('/\s+(<\/[^>]+>)/', '$1', $html);
    // Normalize remaining \r\n sequences between tags
    $html = preg_replace('/>\s*\r?\n\s*</', '><', $html);
    return trim($html);
}

$stmt = $pdo->query("SELECT id, comment FROM users WHERE comment IS NOT NULL AND comment != ''");
$rows = $stmt->fetchAll(PDO::FETCH_OBJ);

$update = $pdo->prepare("UPDATE users SET comment = ? WHERE id = ?");
$count  = 0;

foreach ($rows as $row) {
    $normalized = normalizeTiptap($row->comment);
    if ($normalized !== $row->comment) {
        $update->execute([$normalized, $row->id]);
        $count++;
    }
}

echo "Normalisé : $count lignes sur " . count($rows) . " commentaires.\n";
