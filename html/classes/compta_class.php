<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Compta entity — loads and holds a single accounting entry.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

class Compta
{
    public $id;
    public $userId;
    public $type_id;
    public $date;
    public $libele;
    public $sum;
    public $quittance;
    public $wants_attestation = 0;
    public $cotisation_year   = null;

    public function __construct()
    {
    }

    public function lookupCompta(int $id): void
    {
        $stmt = db()->prepare("SELECT id,user_id,type_id,date,libele,sum,quittance,wants_attestation,cotisation_year FROM compta WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id                = $row->id;
            $this->userId            = $row->user_id;
            $this->type_id           = $row->type_id;
            $this->date              = $row->date;
            $this->libele            = $row->libele;
            $this->sum               = $row->sum;
            $this->quittance         = $row->quittance;
            $this->wants_attestation = (int)$row->wants_attestation;
            $this->cotisation_year   = $row->cotisation_year !== null ? (int)$row->cotisation_year : null;
        }
    }

    public function getId()        { return $this->id; }
    public function getUserId()    { return $this->userId; }
    public function getTypeId()    { return $this->type_id; }
    public function getDate()      { return $this->date; }
    public function getLibele()    { return $this->libele; }
    public function getSum()       { return $this->sum; }
    public function getQuittance()        { return $this->quittance; }
    public function getWantsAttestation() { return $this->wants_attestation; }
    public function getCotisationYear()   { return $this->cotisation_year; }

    public function setUserId($v)    { $this->userId = $v; }
    public function setTypeId($v)    { $this->type_id = (int)$v; }
    public function setDate($v)      { $this->date = $v; }
    public function setlibele($v)    { $this->libele = $v; }
    public function setSum($v)       { $this->sum = $v; }
    public function setQuittance($v)       { $this->quittance = $v; }
    public function setWantsAttestation($v){ $this->wants_attestation = $v ? 1 : 0; }
    public function setCotisationYear($v): void
    {
        if ($v === null || $v === '') {
            $this->cotisation_year = null;
            return;
        }
        $year = (int)$v;
        $now  = (int)date('Y');
        // Accept year-1 through year+1 relative to current year, at most 50 years back.
        if ($year < $now - 50 || $year > $now + 1) {
            $this->cotisation_year = null;
            return;
        }
        $this->cotisation_year = $year;
    }

    public function save(): void
    {
        if ($this->id) {
            db()->prepare(
                "UPDATE compta SET user_id=?,type_id=?,date=?,libele=?,sum=?,quittance=?,wants_attestation=?,cotisation_year=? WHERE id=?"
            )->execute([
                $this->userId, $this->type_id, $this->date, $this->libele,
                $this->sum, $this->quittance, $this->wants_attestation, $this->cotisation_year, $this->id,
            ]);
        } else {
            db()->prepare(
                "INSERT INTO compta (user_id,type_id,date,libele,sum,quittance,wants_attestation,cotisation_year) VALUES (?,?,?,?,?,?,?,?)"
            )->execute([
                $this->userId, $this->type_id, $this->date,
                $this->libele, $this->sum, $this->quittance, $this->wants_attestation, $this->cotisation_year,
            ]);
        }
    }

    public function remove(): void
    {
        db()->prepare("DELETE FROM compta WHERE id=?")->execute([$this->id]);
    }

    /**
     * Distinct accounting entry types per user, for badge display in lists.
     *
     * @param int[] $userIds
     * @return array<int, object[]> user_id => rows (type_id, label, color)
     */
    public static function typesByUser(array $userIds): array
    {
        if (!$userIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = db()->prepare("
            SELECT c.user_id, ct.id AS type_id, ct.label, ct.color
            FROM compta c
            JOIN compta_type ct ON ct.id = c.type_id
            WHERE c.user_id IN ($placeholders)
            GROUP BY c.user_id, ct.id, ct.label, ct.color
            ORDER BY ct.sort_order ASC, ct.label ASC
        ");
        $stmt->execute(array_values($userIds));
        $map = [];
        while ($r = $stmt->fetchObject()) {
            $map[(int)$r->user_id][] = $r;
        }
        return $map;
    }

    /**
     * Per-user accounting activity summary (entry count, cotisation count,
     * last entry date, count within the 10-year window ending at $year).
     * Used by the FILTER_NO_ACTIVITY_10Y history column.
     *
     * @return array<int, object> user_id => row (total, last_date, coti_count, recent_count)
     */
    public static function activitySummaryByUser(int $year): array
    {
        $from = mktime(0, 0, 0, 1, 0, $year - 10);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        $stmt = db()->prepare("
            SELECT c.user_id,
                   COUNT(*) AS total,
                   MAX(c.date) AS last_date,
                   SUM(CASE WHEN COALESCE(ct.is_cotisation,0)=1 THEN 1 ELSE 0 END) AS coti_count,
                   SUM(CASE WHEN c.date > ? AND c.date < ? THEN 1 ELSE 0 END) AS recent_count
            FROM compta c
            LEFT JOIN compta_type ct ON ct.id = c.type_id
            GROUP BY c.user_id
        ");
        $stmt->execute([$from, $to]);
        $map = [];
        while ($r = $stmt->fetchObject()) {
            $map[(int)$r->user_id] = $r;
        }
        return $map;
    }
}
