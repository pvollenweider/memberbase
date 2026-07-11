<?php
/**
 * Donor business logic -- DB queries for donor analytics views.
 *
 * All functions take DATETIME-literal ranges (mbDateTimeBound()) computed by
 * the caller to remain consistent with the existing mktime()-based date
 * arithmetic in the views.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

/**
 * Return donors who gave in year-1 but NOT in year (lapsed donors).
 *
 * @param PDO $db   Database connection
 * @param int $year Target year
 * @return object[] PDO rows with id, firstname, lastname, society, sexe, address, npa, email, total_prev, last_date
 */
function mbGetLapsedDonors(PDO $db, int $year): array
{
    $excl   = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
    $kFrom  = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year));
    $kTo    = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year + 1));
    $kFrom1 = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year - 1));
    $kTo1   = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year));

    $stmt = $db->prepare("
        SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
               SUM(c.sum) AS total_prev,
               MAX(c.date) AS last_date
        FROM contact u
        JOIN compta c ON u.id = c.user_id
        WHERE u.status=1 AND c.date > ? AND c.date < ?
          AND c.type_id NOT IN ($excl)
          AND u.id NOT IN (
              SELECT DISTINCT user_id FROM compta
              WHERE date > ? AND date < ?
                AND type_id NOT IN ($excl)
          )
        GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
        ORDER BY total_prev DESC, u.lastname, u.firstname
    ");
    $stmt->execute([$kFrom1, $kTo1, $kFrom, $kTo]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Return donors who gave in BOTH year-1 and year (loyal/recurrent donors).
 *
 * @param PDO $db   Database connection
 * @param int $year Target year
 * @return object[] PDO rows with id, firstname, lastname, society, sexe, address, npa, email, total_curr, total_prev
 */
function mbGetLoyalDonors(PDO $db, int $year): array
{
    $excl   = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
    $kFrom  = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year));
    $kTo    = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year + 1));
    $kFrom1 = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year - 1));
    $kTo1   = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year));

    $stmt = $db->prepare("
        SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
               SUM(c.sum) AS total_curr,
               (SELECT COALESCE(SUM(c2.sum),0) FROM compta c2
                WHERE c2.user_id = u.id AND c2.date > ? AND c2.date < ?
                  AND c2.type_id NOT IN ($excl)) AS total_prev
        FROM contact u
        JOIN compta c ON u.id = c.user_id
        WHERE u.status=1 AND c.date > ? AND c.date < ?
          AND c.type_id NOT IN ($excl)
          AND u.id IN (
              SELECT DISTINCT user_id FROM compta
              WHERE date > ? AND date < ?
                AND type_id NOT IN ($excl)
          )
        GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
        ORDER BY total_curr DESC, u.lastname, u.firstname
    ");
    $stmt->execute([$kFrom1, $kTo1, $kFrom, $kTo, $kFrom1, $kTo1]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Return first-time donors in year (donated in year but NOT in year-1).
 *
 * @param PDO $db   Database connection
 * @param int $year Target year
 * @return object[] PDO rows with id, firstname, lastname, society, sexe, address, npa, email, total_curr, first_date
 */
function mbGetNewDonors(PDO $db, int $year): array
{
    $excl   = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";
    $kFrom  = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year));
    $kTo    = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year + 1));
    $kFrom1 = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year - 1));
    $kTo1   = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year));

    $stmt = $db->prepare("
        SELECT u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email,
               SUM(c.sum) AS total_curr,
               MIN(c.date) AS first_date
        FROM contact u
        JOIN compta c ON u.id = c.user_id
        WHERE u.status=1 AND c.date > ? AND c.date < ?
          AND c.type_id NOT IN ($excl)
          AND u.id NOT IN (
              SELECT DISTINCT user_id FROM compta
              WHERE date > ? AND date < ?
                AND type_id NOT IN ($excl)
          )
        GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email
        ORDER BY total_curr DESC, u.lastname, u.firstname
    ");
    $stmt->execute([$kFrom, $kTo, $kFrom1, $kTo1]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
