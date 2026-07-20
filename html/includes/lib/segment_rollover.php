<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Yearly member-segment rollover (issue #150 follow-up): each January, the
 * association starts a fresh "Membre <year>" segment for the incoming
 * cotisation year. This used to be a manual admin chore every January —
 * create the segment, flip two settings, copy early payers in.
 *
 * Idempotent and safe to run any time (hourly cron, or by hand): it only
 * acts once per year, the moment "{prefix} <year>" doesn't exist yet. Once
 * created, later runs are a no-op even if an admin has since changed
 * default_segment/membre_segment for their own reasons — this never
 * re-asserts those settings outside of the one-time creation.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Find-or-create the segment category (combined_segment, is_filter=0) named
 * after the prefix, and put the given segment in it. Segments named
 * "{prefix} <year>" belong together in the filter menu — an admin shouldn't
 * have to remember to file each newly-rolled-over year into "Membre" by hand.
 */
function mbEnsureSegmentInPrefixCategory(PDO $pdo, string $prefix, int $segmentId): void
{
    $stmt = $pdo->prepare("SELECT id FROM combined_segment WHERE name = ? AND is_filter = 0 LIMIT 1");
    $stmt->execute([$prefix]);
    $categoryId = (int)($stmt->fetchColumn() ?: 0);

    if ($categoryId === 0) {
        $category = new CombinedSegment();
        $category->name = $prefix;
        $category->save();
        $categoryId = (int)$category->id;
        $pdo->prepare("UPDATE combined_segment SET is_filter = 0 WHERE id = ?")->execute([$categoryId]);
    }

    $pdo->prepare("INSERT IGNORE INTO combined_segment_member (combined_segment_id, segment_id) VALUES (?, ?)")
        ->execute([$categoryId, $segmentId]);
}

/**
 * @return array{created:bool,segmentId:?int,name:?string,prefilled:int}
 */
function mbRolloverYearlyMemberSegment(PDO $pdo, array $appSettings, int $year): array
{
    $prefix = trim($appSettings['membre_segment_prefix'] ?? '') ?: 'Membre';
    $name   = "$prefix $year";
    $prevName = "$prefix " . ($year - 1);

    $exists = $pdo->prepare("SELECT id FROM segment WHERE name = ? LIMIT 1");
    $exists->execute([$name]);
    if ($exists->fetchColumn()) {
        return ['created' => false, 'segmentId' => null, 'name' => $name, 'prefilled' => 0];
    }

    $segment = new Segment();
    $segment->name = $name;
    $segment->setHidden(0);
    $segment->save();
    $segmentId = (int)$segment->id;
    mbEnsureSegmentInPrefixCategory($pdo, $prefix, $segmentId);

    $upsert = $pdo->prepare(
        "INSERT INTO app_settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $upsert->execute(['default_segment', (string)$segmentId]);

    $prevStmt = $pdo->prepare("SELECT id FROM segment WHERE name = ? LIMIT 1");
    $prevStmt->execute([$prevName]);
    $prevId = (int)($prevStmt->fetchColumn() ?: 0);
    if ($prevId > 0) {
        $upsert->execute(['membre_segment', (string)$prevId]);
    }

    // Pre-fill with anyone who already has a cotisation-type entry for the
    // new year (e.g. paid in advance in December) — same query as the
    // "Importer les cotisants d'une année" one-off segment import.
    $comptaTypes = $pdo->query("SELECT id, is_cotisation FROM compta_type")->fetchAll(PDO::FETCH_OBJ);
    $cotisTypeIds = array_values(array_map(
        fn($ct) => (int)$ct->id,
        array_filter($comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1)
    ));
    $prefilled = 0;
    if ($cotisTypeIds) {
        $ph = implode(',', array_fill(0, count($cotisTypeIds), '?'));
        $ins = $pdo->prepare("
            INSERT IGNORE INTO contact_segment (user_id, segment_id)
            SELECT u.id, ?
            FROM contact u
            JOIN compta c ON c.user_id = u.id
            WHERE c.type_id IN ($ph)
              AND COALESCE(c.cotisation_year, YEAR(c.date)) = ?
              AND u.id NOT IN (SELECT user_id FROM contact_segment WHERE segment_id = ?)
            GROUP BY u.id
        ");
        $ins->execute(array_merge([$segmentId], $cotisTypeIds, [$year, $segmentId]));
        $prefilled = $ins->rowCount();
    }

    return ['created' => true, 'segmentId' => $segmentId, 'name' => $name, 'prefilled' => $prefilled];
}

/**
 * Whenever a contact pays a cotisation-type entry for year <year>, they
 * belong in "{prefix} <year>" — create that segment on first use (e.g. mid-
 * year, someone paying a year ahead of the rollover job above) and add the
 * contact to it. Idempotent: INSERT IGNORE on membership, find-or-create on
 * the segment itself.
 */
function mbEnsureCotisationSegmentMembership(PDO $pdo, array $appSettings, int $userId, int $year): int
{
    $prefix = trim($appSettings['membre_segment_prefix'] ?? '') ?: 'Membre';
    $name   = "$prefix $year";

    $stmt = $pdo->prepare("SELECT id FROM segment WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $segmentId = (int)($stmt->fetchColumn() ?: 0);

    if ($segmentId === 0) {
        $segment = new Segment();
        $segment->name = $name;
        $segment->setHidden(0);
        $segment->save();
        $segmentId = (int)$segment->id;
        mbEnsureSegmentInPrefixCategory($pdo, $prefix, $segmentId);
    }

    $pdo->prepare("INSERT IGNORE INTO contact_segment (user_id, segment_id) VALUES (?, ?)")
        ->execute([$userId, $segmentId]);

    return $segmentId;
}
