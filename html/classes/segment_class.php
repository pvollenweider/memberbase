<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Segment entity — loads and holds a single group record.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

class Segment
{
    public $id;
    public $name;
    public $hidden = 0;

    public function __construct()
    {
    }

    public function lookupSegment(int $id): void
    {
        $stmt = db()->prepare("SELECT id,name,hidden FROM segment WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id     = $row->id;
            $this->name   = $row->name;
            $this->hidden = (int) $row->hidden;
        } else {
            throw new \RuntimeException("Could not find segment with id [$id]");
        }
    }

    /**
     * Return a segment's name for audit log labels, or "id=N" fallback.
     * Avoids repeating the same SELECT name FROM segment pattern in callers.
     */
    public static function lookupName(int $id): string
    {
        $stmt = db()->prepare("SELECT name FROM segment WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() ?: "id=$id";
    }

    public function getId()        { return $this->id; }
    public function getName()      { return $this->name; }
    public function getHidden()    { return $this->hidden; }
    public function setName($v)    { $this->name = $v; }
    public function setHidden($v)  { $this->hidden = (int)(bool) $v; }

    public function save(): void
    {
        if ($this->id) {
            db()->prepare("UPDATE segment SET name=?,hidden=? WHERE id=?")->execute([$this->name, $this->hidden, $this->id]);
        } else {
            db()->prepare("INSERT INTO segment (name,hidden) VALUES (?,?)")->execute([$this->name, $this->hidden]);
            $this->id = (int)db()->lastInsertId();
        }
    }

    /** Segment name, or null if the segment does not exist. */
    public static function nameById(int $id): ?string
    {
        $stmt = db()->prepare("SELECT name FROM segment WHERE id=?");
        $stmt->execute([$id]);
        $name = $stmt->fetchColumn();
        return $name === false ? null : $name;
    }

    /**
     * Visible segments with their category and member count, ordered for the
     * members list filter dropdown (category sort order, then name).
     *
     * @return object[] rows: id, name, cat_name, cat_id, cat_sort, member_count
     */
    public static function listForDropdown(): array
    {
        return db()->query("
            SELECT t.id, t.name,
                   COALESCE(cat.name, '') AS cat_name,
                   COALESCE(cat.id, 0) AS cat_id,
                   COALESCE(cat.sort_order, 99999) AS cat_sort,
                   (SELECT COUNT(*) FROM contact_segment us WHERE us.segment_id = t.id) AS member_count
            FROM segment t
            LEFT JOIN (
                SELECT mm.segment_id, MIN(c.id) AS id, MIN(c.name) AS name, MIN(c.sort_order) AS sort_order
                FROM combined_segment_member mm
                JOIN combined_segment c ON c.id = mm.combined_segment_id AND c.name IS NOT NULL AND c.is_filter = 0
                GROUP BY mm.segment_id
            ) cat ON cat.segment_id = t.id
            WHERE t.hidden = 0
            ORDER BY cat_sort ASC, COALESCE(cat.name, 'ZZZZ'), t.name
        ")->fetchAll(PDO::FETCH_OBJ);
    }

    public function isMemberOfCombinedSegment(int $combinedSegmentId): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM combined_segment_member WHERE combined_segment_id=? AND segment_id=? LIMIT 1");
        $stmt->execute([$combinedSegmentId, $this->id]);
        $result = $stmt->fetchObject() !== false;
        return $result;
    }

    public function addCombinedSegmentMembership(int $combinedSegmentId): void
    {
        db()->prepare("INSERT IGNORE INTO combined_segment_member (combined_segment_id, segment_id) VALUES (?,?)")->execute([$combinedSegmentId, $this->id]);
    }

    public function removeCombinedSegmentMembership(int $combinedSegmentId): void
    {
        db()->prepare("DELETE FROM combined_segment_member WHERE segment_id=? AND combined_segment_id=?")->execute([$this->id, $combinedSegmentId]);
    }

    public function isUsed(): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM contact_segment WHERE segment_id=? LIMIT 1");
        $stmt->execute([$this->id]);
        $result = $stmt->fetchObject() !== false;
        return $result;
    }

    /**
     * Deletes the segment unless it still has members (use deleteSegmentForce
     * for that). Returns whether the row was deleted — no output here, the
     * caller decides how to surface a refusal.
     */
    public function remove(): bool
    {
        if ($this->isUsed()) {
            return false;
        }
        db()->prepare("DELETE FROM segment WHERE id=?")->execute([$this->id]);
        return true;
    }
}
