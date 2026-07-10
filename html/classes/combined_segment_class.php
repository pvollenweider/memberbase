<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * CombinedSegment entity — loads and holds a combined segment (union filter
 * of several real segments), formerly called "metagroup".
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

class CombinedSegment
{
    public $id;
    public $name;

    public function __construct()
    {
    }

    public function lookupCombinedSegment(int $id): void
    {
        $stmt = db()->prepare("SELECT id,name FROM combined_segment WHERE id=? AND name IS NOT NULL LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id   = $row->id;
            $this->name = $row->name;
        }
        // Not found: the object stays empty ($id === null) — callers check id.
    }

    public function getId()     { return $this->id; }
    public function getName()   { return $this->name; }
    public function setName($v) { $this->name = $v; }

    public function save(): void
    {
        if ($this->id) {
            db()->prepare("UPDATE combined_segment SET name=? WHERE id=?")->execute([$this->name, $this->id]);
        } else {
            db()->prepare("INSERT INTO combined_segment (name) VALUES (?)")->execute([$this->name]);
            $this->id = (int)db()->lastInsertId();
        }
    }

    /**
     * Named filter combined segments that contain at least one segment, for
     * the members list filter dropdown.
     *
     * @return object[] rows: id, name
     */
    public static function filterList(): array
    {
        return db()->query(
            "SELECT DISTINCT m.id, m.name FROM combined_segment m
             WHERE m.name IS NOT NULL AND m.is_filter = 1
               AND EXISTS (SELECT 1 FROM combined_segment_member mm WHERE mm.combined_segment_id=m.id)
             ORDER BY m.name"
        )->fetchAll(PDO::FETCH_OBJ);
    }

    /** Names of the segments belonging to a combined segment, sorted. @return string[] */
    public static function segmentNames(int $id): array
    {
        $stmt = db()->prepare(
            "SELECT t.name FROM segment t
             JOIN combined_segment_member mm ON mm.segment_id = t.id
             WHERE mm.combined_segment_id = ? ORDER BY t.name"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** IDs of the segments belonging to a combined segment. @return int[] */
    public static function segmentIds(int $id): array
    {
        $stmt = db()->prepare("SELECT segment_id FROM combined_segment_member WHERE combined_segment_id=?");
        $stmt->execute([$id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

}
