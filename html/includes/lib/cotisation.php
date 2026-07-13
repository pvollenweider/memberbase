<?php
/**
 * Cotisation business logic — DB queries extracted from the action layer.
 *
 * Functions here take an explicit PDO parameter so they can be tested
 * independently of the global scope. Pure template-variable helpers
 * (mbBuildCotiReminderVars) live in pure.php.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Return members who paid a cotisation in year-1 but not in year.
 *
 * @param PDO   $db          Database connection
 * @param int   $year        Target year (lapsed = paid $year-1, not paid $year)
 * @param int[] $cotiTypeIds compta_type IDs that count as a cotisation
 * @param int   $noCotiSegment  If > 0, exclude members of this segment from results
 * @return object[]          PDO rows with id, firstname, lastname, society, email
 */
function mbGetLapsedMembers(PDO $db, int $year, array $cotiTypeIds, int $noCotiSegment = 0): array
{
    if (empty($cotiTypeIds)) {
        return [];
    }
    $ph           = implode(',', array_fill(0, count($cotiTypeIds), '?'));
    $noCotiClause = $noCotiSegment > 0
        ? "AND NOT EXISTS (SELECT 1 FROM contact_segment WHERE user_id=u.id AND segment_id=$noCotiSegment)"
        : '';

    $stmt = $db->prepare("
        SELECT u.id, u.firstname, u.lastname, u.society, u.email
        FROM contact u
        WHERE u.status = 1
          $noCotiClause
          AND EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(c.date)) = ?
          )
          AND NOT EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(c.date)) = ?
          )
        ORDER BY u.lastname, u.firstname, u.society
    ");
    $params = array_merge(
        array_values($cotiTypeIds), [$year - 1],
        array_values($cotiTypeIds), [$year]
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Return members who paid a cotisation in year but NOT in year-1 (first-time
 * or returning-after-a-gap members for that year).
 *
 * @param PDO   $db          Database connection
 * @param int   $year        Target year (new = paid $year, not paid $year-1)
 * @param int[] $cotiTypeIds compta_type IDs that count as a cotisation
 * @return object[]          PDO rows with id, firstname, lastname, society, email
 */
function mbGetNewMembers(PDO $db, int $year, array $cotiTypeIds): array
{
    if (empty($cotiTypeIds)) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($cotiTypeIds), '?'));

    $stmt = $db->prepare("
        SELECT u.id, u.firstname, u.lastname, u.society, u.email
        FROM contact u
        WHERE u.status = 1
          AND EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(c.date)) = ?
          )
          AND NOT EXISTS (
              SELECT 1 FROM compta c
              WHERE c.user_id = u.id AND c.type_id IN ($ph)
                AND COALESCE(c.cotisation_year, YEAR(c.date)) = ?
          )
        ORDER BY u.lastname, u.firstname, u.society
    ");
    $params = array_merge(
        array_values($cotiTypeIds), [$year],
        array_values($cotiTypeIds), [$year - 1]
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Return a map of user_id => sent_at for members who already received a
 * cotisation reminder this year (guards against duplicate sends).
 *
 * @param PDO   $db        Database connection
 * @param int   $year      Year to check
 * @param int[] $memberIds IDs to check (must be non-empty)
 * @return array<int,string>  user_id => created_at timestamp string
 */
function mbGetAlreadyRemindedIds(PDO $db, int $year, array $memberIds): array
{
    if (empty($memberIds)) {
        return [];
    }
    $phIds = implode(',', array_fill(0, count($memberIds), '?'));
    try {
        $stmt = $db->prepare(
            "SELECT user_id, MAX(created_at) AS sent_at
             FROM email_log
             WHERE tpl_key = 'tpl_cotisation_reminder'
               AND YEAR(created_at) = ?
               AND user_id IN ($phIds)
             GROUP BY user_id"
        );
        $stmt->execute(array_merge([$year], $memberIds));
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $map[(int)$row->user_id] = $row->sent_at;
        }
        return $map;
    } catch (\Throwable) {
        return [];
    }
}
