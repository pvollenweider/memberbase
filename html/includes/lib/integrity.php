<?php
/**
 * Data integrity checks -- DB queries extracted from the integrity view.
 *
 * mbRunIntegrityChecks() returns a named array of result sets so the
 * view only needs to render the data, not fetch it.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Run all integrity checks and return a map of result sets.
 *
 * Keys match the variable names previously used in the view to keep
 * the template changes minimal.
 *
 * @param PDO $db Database connection
 * @param array $appSettings Needs 'membre_segment_prefix' (defaults to 'Membre')
 * @return array<string, object[]> Named result sets; each value is a (possibly empty) array of rows
 */
function mbRunIntegrityChecks(PDO $db, array $appSettings = []): array
{
    // Queries referencing segment/user_segment may fail before migrations 0013/0014 are applied.
    try {
        $hiddenInCats = $db->query("
            SELECT DISTINCT t.id AS segment_id, t.name AS segment_name,
                   m.id AS mg_id, m.name AS mg_name, m.sort_order AS mg_sort
            FROM segment t
            JOIN combined_segment_member mm ON mm.segment_id = t.id
            JOIN combined_segment m ON m.id = mm.combined_segment_id AND m.is_filter = 0
            WHERE t.hidden = 1
            ORDER BY m.sort_order, m.name, t.name
        ")->fetchAll(PDO::FETCH_OBJ);
        $hiddenInMeta = $db->query("
            SELECT DISTINCT t.id AS segment_id, t.name AS segment_name,
                   m.id AS mg_id, m.name AS mg_name, m.sort_order AS mg_sort
            FROM segment t
            JOIN combined_segment_member mm ON mm.segment_id = t.id
            JOIN combined_segment m ON m.id = mm.combined_segment_id AND m.is_filter = 1
            WHERE t.hidden = 1
            ORDER BY m.sort_order, m.name, t.name
        ")->fetchAll(PDO::FETCH_OBJ);
        $hiddenWithMembers = $db->query("
            SELECT t.id AS segment_id, t.name AS segment_name,
                   COUNT(us.user_id) AS member_count
            FROM segment t
            JOIN contact_segment us ON us.segment_id = t.id
            JOIN contact u ON u.id = us.user_id AND u.status = 1
            WHERE t.hidden = 1
            GROUP BY t.id, t.name
            ORDER BY t.name
        ")->fetchAll(PDO::FETCH_OBJ);
    } catch (PDOException $e) {
        $hiddenInCats = [];
        $hiddenInMeta = [];
        $hiddenWithMembers = [];
    }

    // Queries referencing the contact table fail if migration 0015 has not run yet.
    try {
        $contactChecks = [
        'dupNames' => $db->query("
            SELECT firstName, lastName, COUNT(*) AS cnt,
                   GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM contact
            WHERE status=1 AND (TRIM(firstName) != '' OR TRIM(lastName) != '')
            GROUP BY TRIM(LOWER(firstName)), TRIM(LOWER(lastName))
            HAVING COUNT(*) > 1
            ORDER BY lastName, firstName
        ")->fetchAll(PDO::FETCH_OBJ),

        'dupEmails' => $db->query("
            SELECT email, COUNT(*) AS cnt,
                   GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM contact
            WHERE status=1 AND TRIM(email) != ''
            GROUP BY TRIM(LOWER(email))
            HAVING COUNT(*) > 1
            ORDER BY email
        ")->fetchAll(PDO::FETCH_OBJ),

        // "Future" is computed in PHP (Europe/Zurich, forced app-wide) rather
        // than SQL NOW() -- MySQL's session timezone is typically UTC in this
        // container, 1-2h behind Zurich, which flagged same-day entries saved
        // in the last couple hours as spuriously "in the future" (see #143's
        // timezone fix for other columns; this query was missed then).
        'dateInvalid' => (function () use ($db) {
            $stmt = $db->prepare("
                SELECT c.id, c.date, c.user_id, u.firstname, u.lastname, c.libele
                FROM compta c
                LEFT JOIN contact u ON u.id = c.user_id
                WHERE c.date IS NULL OR c.date > ?
                ORDER BY c.id DESC
                LIMIT 100
            ");
            $stmt->execute([date('Y-m-d H:i:s')]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        })(),

        'typeNull' => $db->query("
            SELECT c.id, c.user_id, u.firstname, u.lastname, c.libele, c.sum
            FROM compta c
            LEFT JOIN contact u ON u.id = c.user_id
            WHERE c.type_id IS NULL
            ORDER BY c.id DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_OBJ),

        'emailInvalid' => $db->query("
            SELECT id, firstname, lastname, email
            FROM contact
            WHERE status=1 AND TRIM(email) != '' AND email NOT LIKE '%@%'
            ORDER BY lastname, firstname
        ")->fetchAll(PDO::FETCH_OBJ),

        'sexeInvalid' => $db->query("
            SELECT id, firstname, lastname, sexe
            FROM contact
            WHERE status=1 AND sexe NOT IN ('na','hf','f','m')
            ORDER BY lastname, firstname
        ")->fetchAll(PDO::FETCH_OBJ),

        'noName' => $db->query("
            SELECT id, firstname, lastname, society
            FROM contact
            WHERE status=1 AND TRIM(lastname) = '' AND TRIM(society) = ''
            ORDER BY id
        ")->fetchAll(PDO::FETCH_OBJ),

        'emailAltInvalid' => $db->query("
            SELECT id, firstname, lastname, email_alt
            FROM contact
            WHERE status=1 AND TRIM(email_alt) != '' AND email_alt NOT LIKE '%@%'
            ORDER BY lastname, firstname
        ")->fetchAll(PDO::FETCH_OBJ),

        'birthdayFuture' => (function () use ($db) {
            $stmt = $db->prepare("
                SELECT id, firstname, lastname, birthday
                FROM contact
                WHERE status=1 AND birthday IS NOT NULL AND birthday > ?
                ORDER BY lastname, firstname
            ");
            $stmt->execute([date('Y-m-d')]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        })(),
        ];
    } catch (PDOException $e) {
        $contactChecks = [
            'dupNames'       => [],
            'dupEmails'      => [],
            'dateInvalid'    => [],
            'typeNull'       => [],
            'emailInvalid'   => [],
            'sexeInvalid'    => [],
            'noName'         => [],
            'emailAltInvalid'=> [],
            'birthdayFuture' => [],
        ];
    }

    // segment_cascade_rule may not exist before migration 0034.
    try {
        $cascadeMissing = $db->query("
            SELECT c.id AS user_id, c.firstname, c.lastname,
                   s.id AS source_segment_id, s.name AS source_name,
                   t.id AS target_segment_id, t.name AS target_name
            FROM segment_cascade_rule r
            JOIN segment s ON s.id = r.source_segment_id
            JOIN segment t ON t.id = r.target_segment_id
            JOIN contact_segment cs ON cs.segment_id = r.source_segment_id
            JOIN contact c ON c.id = cs.user_id AND c.status = 1
            WHERE NOT EXISTS (
                SELECT 1 FROM contact_segment cs2
                WHERE cs2.user_id = cs.user_id AND cs2.segment_id = r.target_segment_id
            )
            ORDER BY s.name, c.lastname, c.firstname
        ")->fetchAll(PDO::FETCH_OBJ);
    } catch (PDOException $e) {
        $cascadeMissing = [];
    }

    // Cotisation payments should land the payer in "{prefix} <year>" —
    // segment_rollover.php normally handles this the moment the compta
    // entry is saved, but backfilled/imported entries can predate that
    // hook, or a segment membership can be removed by hand afterward.
    // Limited to the last 3 years: older gaps are historical bookkeeping,
    // not something worth flagging for action today.
    try {
        $prefix = trim($appSettings['membre_segment_prefix'] ?? '') ?: 'Membre';
        $thisYear = (int)date('Y');
        $cotiSegmentMissing = [];
        for ($y = $thisYear - 2; $y <= $thisYear; $y++) {
            $segName = "$prefix $y";
            $stmt = $db->prepare("
                SELECT DISTINCT c.id AS user_id, c.firstname, c.lastname, c.society, ? AS year
                FROM compta co
                JOIN compta_type ct ON ct.id = co.type_id AND ct.is_cotisation = 1
                JOIN contact c ON c.id = co.user_id AND c.status = 1
                WHERE COALESCE(co.cotisation_year, YEAR(co.date)) = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM contact_segment cs
                      JOIN segment s ON s.id = cs.segment_id AND s.name = ?
                      WHERE cs.user_id = c.id
                  )
                ORDER BY c.lastname, c.firstname
            ");
            $stmt->execute([$y, $y, $segName]);
            foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
                $cotiSegmentMissing[] = $row;
            }
        }
    } catch (PDOException $e) {
        $cotiSegmentMissing = [];
    }

    return array_merge($contactChecks, [
        'hiddenInCats'        => $hiddenInCats,
        'hiddenInMeta'        => $hiddenInMeta,
        'hiddenWithMembers'   => $hiddenWithMembers,
        'cascadeMissing'      => $cascadeMissing,
        'cotiSegmentMissing'  => $cotiSegmentMissing,
    ]);
}
