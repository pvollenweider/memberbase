<?php
class Team
{
    public $id;
    public $name;
    public $hidden = 0;

    public function __construct()
    {
    }

    public function lookupTeam(int $id): void
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id,name,hidden FROM team WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id     = $row->id;
            $this->name   = $row->name;
            $this->hidden = (int) $row->hidden;
        } else {
            print "Could not find team with id [$id]";
        }
    }

    public function getId()        { return $this->id; }
    public function getName()      { return $this->name; }
    public function getHidden()    { return $this->hidden; }
    public function setName($v)    { $this->name = $v; }
    public function setHidden($v)  { $this->hidden = (int)(bool) $v; }

    public function save(): void
    {
        global $pdo;
        if ($this->id) {
            $pdo->prepare("UPDATE team SET name=?,hidden=? WHERE id=?")->execute([$this->name, $this->hidden, $this->id]);
        } else {
            $pdo->prepare("INSERT INTO team (name,hidden) VALUES (?,?)")->execute([$this->name, $this->hidden]);
            $this->id = (int)$pdo->lastInsertId();
        }
    }

    public function isMemberOfMetagroup(int $metagroupId): bool
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT 1 FROM metagroup WHERE id=? AND teamid=? LIMIT 1");
        $stmt->execute([$metagroupId, $this->id]);
        $result = $stmt->fetchObject() !== false;
        return $result;
    }

    public function addMetagroupMembership(int $metagroupId): void
    {
        global $pdo;
        if (!$this->isMemberOfMetagroup($metagroupId)) {
            $pdo->prepare("INSERT INTO metagroup (id,teamid) VALUES (?,?)")->execute([$metagroupId, $this->id]);
        }
    }

    public function removeMetagroupMembership(int $metagroupId): void
    {
        global $pdo;
        $pdo->prepare("DELETE FROM metagroup WHERE teamid=? AND id=?")->execute([$this->id, $metagroupId]);
    }

    public function isUsed(): bool
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT 1 FROM user_properties WHERE parameter=? LIMIT 1");
        $stmt->execute(["team_$this->id"]);
        $result = $stmt->fetchObject() !== false;
        return $result;
    }

    public function remove(): void
    {
        global $pdo;
        if (!$this->isUsed()) {
            $pdo->prepare("DELETE FROM team WHERE id=?")->execute([$this->id]);
        } else {
            print "Could not remove $this->name because some users are members.<br/>";
            print "Click <a href='" . $_SERVER['PHP_SELF'] . "?action=search&amp;team=" . $this->id . "'>here</a> to see the user list";
        }
    }
}
