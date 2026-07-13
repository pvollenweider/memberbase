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
               ct.icon AS ct_icon, ct.label AS ct_label,
               SUM(c.sum) AS total_curr,
               MIN(c.date) AS first_date
        FROM contact u
        JOIN compta c ON u.id = c.user_id
        LEFT JOIN contact_type ct ON ct.id = u.contact_type_id
        WHERE u.status=1 AND c.date > ? AND c.date < ?
          AND c.type_id NOT IN ($excl)
          AND u.id NOT IN (
              SELECT DISTINCT user_id FROM compta
              WHERE date > ? AND date < ?
                AND type_id NOT IN ($excl)
          )
        GROUP BY u.id, u.firstname, u.lastname, u.society, u.sexe, u.address, u.npa, u.email, ct.icon, ct.label
        ORDER BY total_curr DESC, u.lastname, u.firstname
    ");
    $stmt->execute([$kFrom, $kTo, $kFrom1, $kTo1]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}

/**
 * Computes the KPI figures shown on the donors-summary cards + pie chart
 * (contributions total/delta/YTD, donor counts/loyal/new/lapsed, active
 * members, per-type breakdown). Shared by donors_summary.php (year-filterable
 * standalone page) and dashboard.php (fixed current-year snapshot) so the
 * ~100 lines of SQL aren't duplicated between the two.
 *
 * @param PDO   $db
 * @param array $comptaTypes  id => compta_type row (needs ->is_cotisation)
 * @param array $appSettings  needs 'default_segment', 'member_no_coti_segment'
 * @param int   $year         calendar year (not one of the -2/-3/-4 "all time" sentinels)
 * @return object all $k* fields consumed by the cards/pie markup
 */
function mbComputeDonorKpis(PDO $db, array $comptaTypes, array $appSettings, int $year): object
{
    $kFrom  = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year));
    $kTo    = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year + 1));
    $kFrom1 = mbDateTimeBound(mktime(0, 0, 0, 1, 0, $year - 1));
    $kTo1   = mbDateTimeBound(mktime(0, 0, 0, 1, 1, $year));

    $excl = "SELECT id FROM compta_type WHERE is_excluded_from_donation = 1";

    $s = $db->prepare("SELECT COALESCE(SUM(c.sum),0) FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($excl)");
    $s->execute([$kFrom, $kTo]);   $kTotal  = (float)$s->fetchColumn();
    $s->execute([$kFrom1, $kTo1]); $kTotal1 = (float)$s->fetchColumn();

    $sDon = $db->prepare("SELECT COUNT(DISTINCT c.user_id) FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($excl)");
    $sDon->execute([$kFrom, $kTo]);   $kDonateurs  = (int)$sDon->fetchColumn();
    $sDon->execute([$kFrom1, $kTo1]); $kDonateurs1 = (int)$sDon->fetchColumn();
    $kDonDelta = $kDonateurs1 > 0 ? (($kDonateurs - $kDonateurs1) / $kDonateurs1 * 100) : null;

    $sRec = $db->prepare("SELECT COUNT(DISTINCT c.user_id) FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($excl) AND c.user_id IN (SELECT DISTINCT user_id FROM compta WHERE date>? AND date<? AND type_id NOT IN ($excl))");
    $sRec->execute([$kFrom, $kTo, $kFrom1, $kTo1]);
    $kRecurrents = (int)$sRec->fetchColumn();
    $kNouveaux   = $kDonateurs - $kRecurrents;
    $kLapsed     = $kDonateurs1 - $kRecurrents;

    $sTypeBreak = $db->prepare("SELECT ct.label, ct.color, COALESCE(SUM(c.sum),0) AS total FROM compta c JOIN compta_type ct ON ct.id=c.type_id WHERE c.date>? AND c.date<? AND ct.is_excluded_from_donation=0 GROUP BY ct.id, ct.label, ct.color ORDER BY total DESC");
    $sTypeBreak->execute([$kFrom, $kTo]);
    $typeBreakdown = $sTypeBreak->fetchAll(PDO::FETCH_OBJ);
    $typeTotal = array_sum(array_map(fn($r) => (float)$r->total, $typeBreakdown));

    // Member counts by cotisation_year (fallback: YEAR of payment date)
    $cotiTypeIds = array_keys(array_filter((array)$comptaTypes, fn($ct) => (int)$ct->is_cotisation === 1));
    $noCotiSegment = (int)($appSettings['member_no_coti_segment'] ?? 0);
    $noCotiJoin = $noCotiSegment > 0
        ? "AND NOT EXISTS (SELECT 1 FROM contact_segment WHERE user_id=u.id AND segment_id=$noCotiSegment)"
        : '';
    $kMembres = 0;
    $kMembresPrev = 0;
    $kMembresDelta = null;
    $kMembresLapsed = 0;
    if (!empty($cotiTypeIds)) {
        $ph = implode(',', array_fill(0, count($cotiTypeIds), '?'));
        $sM = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM contact u JOIN compta c ON c.user_id=u.id WHERE u.status=1 $noCotiJoin AND c.type_id IN ($ph) AND COALESCE(c.cotisation_year,YEAR(c.date))=?");
        $sM->execute(array_merge(array_values($cotiTypeIds), [$year]));
        $kMembres = (int)$sM->fetchColumn();
        $sM->execute(array_merge(array_values($cotiTypeIds), [$year - 1]));
        $kMembresPrev = (int)$sM->fetchColumn();
        $kMembresDelta = $kMembresPrev > 0 ? (($kMembres - $kMembresPrev) / $kMembresPrev * 100) : null;

        $sLapsedM = $db->prepare("
            SELECT COUNT(*) FROM contact u
            WHERE u.status=1
              $noCotiJoin
              AND EXISTS (SELECT 1 FROM compta c WHERE c.user_id=u.id AND c.type_id IN ($ph) AND COALESCE(c.cotisation_year,YEAR(c.date))=?)
              AND NOT EXISTS (SELECT 1 FROM compta c WHERE c.user_id=u.id AND c.type_id IN ($ph) AND COALESCE(c.cotisation_year,YEAR(c.date))=?)
        ");
        $sLapsedM->execute(array_merge(array_values($cotiTypeIds), [$year - 1], array_values($cotiTypeIds), [$year]));
        $kMembresLapsed = (int)$sLapsedM->fetchColumn();
    }

    $kDelta = $kTotal1 > 0 ? (($kTotal - $kTotal1) / $kTotal1 * 100) : null;

    // YTD "même période" -- only meaningful when viewing current year
    $kYtd = null;
    $kDonateursYtd1 = null;
    $kTotalYtd1 = null;
    if ($year === (int)date("Y")) {
        $kToYtd1 = mbDateTimeBound(mktime(23, 59, 59, (int)date("m"), (int)date("d"), $year - 1));
        $sYtd = $db->prepare("SELECT COALESCE(SUM(c.sum),0) FROM compta c WHERE c.date>? AND c.date<=? AND c.type_id NOT IN ($excl)");
        $sYtd->execute([$kFrom1, $kToYtd1]);
        $kTotalYtd1 = (float)$sYtd->fetchColumn();
        $kYtd = $kTotalYtd1 > 0 ? (($kTotal - $kTotalYtd1) / $kTotalYtd1 * 100) : null;

        $sDonYtd = $db->prepare("SELECT COUNT(DISTINCT c.user_id) FROM compta c WHERE c.date>? AND c.date<=? AND c.type_id NOT IN ($excl)");
        $sDonYtd->execute([$kFrom1, $kToYtd1]);
        $kDonateursYtd1 = (int)$sDonYtd->fetchColumn();
    }

    $membreSegmentId = (int)($appSettings['default_segment'] ?? 0);
    $membreSegmentLabel = null;
    if ($membreSegmentId > 0) {
        $r = $db->prepare("SELECT name FROM segment WHERE id = ?");
        $r->execute([$membreSegmentId]);
        $membreSegmentLabel = $r->fetchColumn() ?: null;
    }

    // Cumulative monthly revenue (Jan..Dec) for the current and prior year,
    // overlaid as two lines in the dashboard's Contributions card. Months
    // after the current one (current year only) are left null so the line
    // stops instead of flatlining at the year-to-date total.
    $sMonthly = $db->prepare(
        "SELECT MONTH(c.date) AS m, COALESCE(SUM(c.sum),0) AS total
         FROM compta c WHERE c.date>? AND c.date<? AND c.type_id NOT IN ($excl)
         GROUP BY MONTH(c.date)"
    );
    $sMonthly->execute([$kFrom, $kTo]);
    $monthlyCurrRaw = array_fill(1, 12, 0.0);
    foreach ($sMonthly->fetchAll(PDO::FETCH_OBJ) as $r) { $monthlyCurrRaw[(int)$r->m] = (float)$r->total; }
    $sMonthly->execute([$kFrom1, $kTo1]);
    $monthlyPrevRaw = array_fill(1, 12, 0.0);
    foreach ($sMonthly->fetchAll(PDO::FETCH_OBJ) as $r) { $monthlyPrevRaw[(int)$r->m] = (float)$r->total; }

    $isCurrentYear = $year === (int)date("Y");
    $currentMonth  = (int)date("n");
    $monthlyCurr = [];
    $monthlyPrev = [];
    $run = 0.0;
    for ($m = 1; $m <= 12; $m++) {
        $run += $monthlyCurrRaw[$m];
        $monthlyCurr[] = (!$isCurrentYear || $m <= $currentMonth) ? round($run, 2) : null;
    }
    $run = 0.0;
    for ($m = 1; $m <= 12; $m++) {
        $run += $monthlyPrevRaw[$m];
        $monthlyPrev[] = round($run, 2);
    }

    return (object)compact(
        'kTotal', 'kTotal1', 'kDelta', 'kYtd', 'kTotalYtd1',
        'kDonateurs', 'kDonateurs1', 'kDonDelta', 'kDonateursYtd1',
        'kRecurrents', 'kNouveaux', 'kLapsed',
        'kMembres', 'kMembresPrev', 'kMembresDelta', 'kMembresLapsed',
        'typeBreakdown', 'typeTotal',
        'membreSegmentId', 'membreSegmentLabel',
        'monthlyCurr', 'monthlyPrev'
    );
}
