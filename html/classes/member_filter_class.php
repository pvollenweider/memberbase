<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Virtual member filters — single source of truth shared by the members
 * list view (users_list.php) and the REST API (api/members.php).
 *
 * A "virtual filter" is a negative team ID (constants FILTER_* in
 * lib/bootstrap.php) that selects members by business rules on their
 * accounting history instead of segment membership.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */
class MemberFilter
{
    /** Filters implemented by resolveIds(). FILTER_ALL_EXCEPT_ARCHIVES is
     *  intentionally absent: "all active members" needs no ID restriction. */
    public const RESOLVABLE = [
        FILTER_UNPAID_COTI_3Y,
        FILTER_NO_ACTIVITY_10Y,
        FILTER_NON_INSTIT_LAST_YEAR,
        FILTER_UNPAID_COTI_CURRENT,
    ];

    public static function isVirtual(int $teamId): bool
    {
        return $teamId === FILTER_ALL_EXCEPT_ARCHIVES || in_array($teamId, self::RESOLVABLE, true);
    }

    /**
     * Returns the set of active user IDs matching a virtual filter,
     * as an id => true map (O(1) membership tests in render loops).
     *
     * @param int   $filterId    one of self::RESOLVABLE
     * @param PDO   $pdo
     * @param int   $year        reference year (the view allows ?year override)
     * @param array $appSettings needs member_no_coti_team, membre_team
     * @return array<int,true>
     */
    public static function resolveIds(int $filterId, PDO $pdo, int $year, array $appSettings): array
    {
        switch ($filterId) {

            // Ever paid a cotisation, but none in the last 3 years.
            // Members of the "no coti" segment are excluded.
            case FILTER_UNPAID_COTI_3Y: {
                $cutoff = mktime(0, 0, 0, 1, 0, $year - 2);
                $noCoti = self::noCotiMembers($pdo, $appSettings);
                $ids = [];
                $st = $pdo->prepare("
                    SELECT c.user_id
                    FROM compta c
                    JOIN compta_type ct ON ct.id = c.type_id AND ct.is_cotisation = 1
                    JOIN users u ON u.id = c.user_id AND u.status = 1
                    GROUP BY c.user_id
                    HAVING COUNT(*) > 0
                       AND SUM(CASE WHEN c.date > ? THEN 1 ELSE 0 END) = 0
                ");
                $st->execute([$cutoff]);
                while ($r = $st->fetchObject()) {
                    $uid = (int)$r->user_id;
                    if (empty($noCoti[$uid])) {
                        $ids[$uid] = true;
                    }
                }
                return $ids;
            }

            // No accounting entry at all in the last 10 years.
            case FILTER_NO_ACTIVITY_10Y: {
                $from = mktime(0, 0, 0, 1, 0, $year - 10);
                $to   = mktime(0, 0, 0, 1, 1, $year + 1);
                $ids = [];
                $st = $pdo->prepare("
                    SELECT u.id
                    FROM users u
                    WHERE u.status = 1
                      AND NOT EXISTS (
                          SELECT 1 FROM compta c
                          WHERE c.user_id = u.id
                            AND c.date > ? AND c.date < ?
                      )
                ");
                $st->execute([$from, $to]);
                while ($r = $st->fetchObject()) {
                    $ids[(int)$r->id] = true;
                }
                return $ids;
            }

            // At least one non-institutional payment in the previous year.
            case FILTER_NON_INSTIT_LAST_YEAR: {
                $from = mktime(0, 0, 0, 1, 0, $year - 1);
                $to   = mktime(0, 0, 0, 1, 1, $year);
                $institIds = array_column(
                    $pdo->query("SELECT id FROM compta_type WHERE is_institutional=1")->fetchAll(PDO::FETCH_OBJ),
                    'id'
                );
                $notIn = count($institIds) ? implode(',', array_map('intval', $institIds)) : '0';
                $ids = [];
                $st = $pdo->prepare("
                    SELECT DISTINCT c.user_id
                    FROM compta c
                    JOIN users u ON u.id = c.user_id AND u.status = 1
                    WHERE c.date > ? AND c.date < ?
                      AND (c.type_id IS NULL OR c.type_id NOT IN ($notIn))
                ");
                $st->execute([$from, $to]);
                while ($r = $st->fetchObject()) {
                    $ids[(int)$r->user_id] = true;
                }
                return $ids;
            }

            // Members of the "membre" segment without a cotisation this year.
            // Members of the "no coti" segment are excluded.
            case FILTER_UNPAID_COTI_CURRENT: {
                $membreTeam = (int)($appSettings['membre_team'] ?? 0);
                if ($membreTeam <= 0) {
                    return [];
                }
                $from = mktime(0, 0, 0, 1, 0, $year);
                $to   = mktime(0, 0, 0, 1, 1, $year + 1);
                $noCoti = self::noCotiMembers($pdo, $appSettings);
                $ids = [];
                $st = $pdo->prepare("
                    SELECT u.id
                    FROM users u
                    JOIN user_properties up ON up.user_id = u.id AND up.parameter = ?
                    WHERE u.status = 1
                      AND NOT EXISTS (
                          SELECT 1 FROM compta c
                          JOIN compta_type ct ON ct.id = c.type_id AND ct.is_cotisation = 1
                          WHERE c.user_id = u.id
                            AND c.date > ? AND c.date < ?
                      )
                ");
                $st->execute(["team_$membreTeam", $from, $to]);
                while ($r = $st->fetchObject()) {
                    $uid = (int)$r->id;
                    if (empty($noCoti[$uid])) {
                        $ids[$uid] = true;
                    }
                }
                return $ids;
            }

            default:
                return [];
        }
    }

    /** id => true map of members in the "no cotisation expected" segment. */
    private static function noCotiMembers(PDO $pdo, array $appSettings): array
    {
        $noCotiTeam = (int)($appSettings['member_no_coti_team'] ?? 0);
        if ($noCotiTeam <= 0) {
            return [];
        }
        $st = $pdo->prepare("SELECT user_id FROM user_properties WHERE parameter=?");
        $st->execute(["team_$noCotiTeam"]);
        $map = [];
        while ($r = $st->fetchObject()) {
            $map[(int)$r->user_id] = true;
        }
        return $map;
    }
}
