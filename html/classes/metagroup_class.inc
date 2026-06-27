<?php
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
