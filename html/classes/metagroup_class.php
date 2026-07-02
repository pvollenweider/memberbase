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
        global $pdo;
        $stmt = $pdo->prepare("SELECT id,name FROM metagroup WHERE id=? AND name IS NOT NULL LIMIT 1");
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
        global $pdo;
        if ($this->id) {
            $pdo->prepare("UPDATE metagroup SET name=? WHERE id=?")->execute([$this->name, $this->id]);
        } else {
            $mid = updateAndGetMaxVal("metagroup_id");
            $pdo->prepare("INSERT INTO metagroup (id,name) VALUES (?,?)")->execute([$mid, $this->name]);
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
        global $pdo;
        return $pdo->query(
            "SELECT DISTINCT m.id, m.name FROM metagroup m
             WHERE m.name IS NOT NULL AND m.is_filter = 1
               AND EXISTS (SELECT 1 FROM metagroup j WHERE j.id=m.id AND j.teamid IS NOT NULL)
             ORDER BY m.name"
        )->fetchAll(PDO::FETCH_OBJ);
    }

    /** Names of the teams belonging to a metagroup, sorted. @return string[] */
    public static function teamNames(int $id): array
    {
        global $pdo;
        $stmt = $pdo->prepare(
            "SELECT t.name FROM team t
             JOIN metagroup j ON j.teamid = t.id
             WHERE j.id = ? ORDER BY t.name"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** IDs of the teams belonging to a metagroup. @return int[] */
    public static function teamIds(int $id): array
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT teamid FROM metagroup WHERE id=? AND teamid IS NOT NULL");
        $stmt->execute([$id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function isUsed(): bool
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT 1 FROM metagroup WHERE id=? LIMIT 1");
        $stmt->execute([$this->id]);
        $result = $stmt->fetchObject() !== false;
        return $result;
    }

    public function remove(): void
    {
        global $pdo;
        if (!$this->isUsed()) {
            $pdo->prepare("DELETE FROM metagroup WHERE id=?")->execute([$this->id]);
        } else {
            print "Could not remove $this->name because there is a team inside.<br/>";
            print "Click <a href='" . $_SERVER['PHP_SELF'] . "?action=viewgroups&amp;metagroup=" . $this->id . "'>here</a> to see the team list";
        }
    }
}
