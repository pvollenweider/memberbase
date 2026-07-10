<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Metagroup entity — loads and holds a filter group (group of groups).
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

class Metagroup
{
    public $id;
    public $name;

    public function __construct()
    {
    }

    public function lookupMetagroup(int $id): void
    {
        $stmt = db()->prepare("SELECT id,name FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
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
            db()->prepare("UPDATE metagroup SET name=? WHERE id=?")->execute([$this->name, $this->id]);
        } else {
            db()->prepare("INSERT INTO metagroup (name) VALUES (?)")->execute([$this->name]);
            $this->id = (int)db()->lastInsertId();
        }
    }

    /**
     * Named filter metagroups that contain at least one team, for the
     * members list filter dropdown.
     *
     * @return object[] rows: id, name
     */
    public static function filterList(): array
    {
        return db()->query(
            "SELECT DISTINCT m.id, m.name FROM metagroup m
             WHERE m.name IS NOT NULL AND m.is_filter = 1
               AND EXISTS (SELECT 1 FROM metagroup_member mm WHERE mm.metagroup_id=m.id)
             ORDER BY m.name"
        )->fetchAll(PDO::FETCH_OBJ);
    }

    /** Names of the segments belonging to a metagroup, sorted. @return string[] */
    public static function segmentNames(int $id): array
    {
        $stmt = db()->prepare(
            "SELECT t.name FROM segment t
             JOIN metagroup_member mm ON mm.segment_id = t.id
             WHERE mm.metagroup_id = ? ORDER BY t.name"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** IDs of the segments belonging to a metagroup. @return int[] */
    public static function segmentIds(int $id): array
    {
        $stmt = db()->prepare("SELECT segment_id FROM metagroup_member WHERE metagroup_id=?");
        $stmt->execute([$id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

}
