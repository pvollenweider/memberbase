<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * Metagroup entity — loads and holds a filter group (group of groups).
 *
 * @copyright 2024 Philippe Vollenweider
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
        } else {
            print "Could not find metagroup with id [$id]";
        }
    }

    public function getId()     { return $this->id; }
    public function getName()   { return $this->name; }
    public function setName($v) { $this->name = $v; }

    public function save(): void
    {
        if ($this->id) {
            db()->prepare("UPDATE metagroup SET name=? WHERE id=?")->execute([$this->name, $this->id]);
        } else {
            $mid = updateAndGetMaxVal("metagroup_id");
            db()->prepare("INSERT INTO metagroup (id,name) VALUES (?,?)")->execute([$mid, $this->name]);
            $this->id = $mid;
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
               AND EXISTS (SELECT 1 FROM metagroup j WHERE j.id=m.id AND j.segmentid IS NOT NULL)
             ORDER BY m.name"
        )->fetchAll(PDO::FETCH_OBJ);
    }

    /** Names of the segments belonging to a metagroup, sorted. @return string[] */
    public static function segmentNames(int $id): array
    {
        $stmt = db()->prepare(
            "SELECT t.name FROM segment t
             JOIN metagroup j ON j.segmentid = t.id
             WHERE j.id = ? ORDER BY t.name"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** IDs of the segments belonging to a metagroup. @return int[] */
    public static function segmentIds(int $id): array
    {
        $stmt = db()->prepare("SELECT segmentid FROM metagroup WHERE id=? AND segmentid IS NOT NULL");
        $stmt->execute([$id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @deprecated Use segmentNames() instead. */
    public static function teamNames(int $id): array { return self::segmentNames($id); }
    /** @deprecated Use segmentIds() instead. */
    public static function teamIds(int $id): array { return self::segmentIds($id); }

    public function isUsed(): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM metagroup WHERE id=? LIMIT 1");
        $stmt->execute([$this->id]);
        $result = $stmt->fetchObject() !== false;
        return $result;
    }

    public function remove(): void
    {
        if (!$this->isUsed()) {
            db()->prepare("DELETE FROM metagroup WHERE id=?")->execute([$this->id]);
        } else {
            print "Could not remove $this->name because there is a segment inside.<br/>";
            print "Click <a href='" . $_SERVER['PHP_SELF'] . "?action=viewgroups&amp;metagroup=" . $this->id . "'>here</a> to see the segment list";
        }
    }
}
