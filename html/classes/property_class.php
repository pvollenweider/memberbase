<?php
/**
 * UserProperty entity — loads and holds a single user attribute/suivi entry.
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

class UserProperty
{
    public $id;
    public $userId;
    public $parameter;
    public $date;
    public $value;

    public function __construct() {}

    public function lookupUserProperty(int $id): void
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id,user_id,parameter,date,value FROM user_properties WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id        = $row->id;
            $this->userId    = $row->user_id;
            $this->parameter = $row->parameter;
            $this->date      = $row->date;
            $this->value     = $row->value;
        } else {
            print "Could not find user property with id [$id]";
        }
    }

    public function getId()        { return $this->id; }
    public function getUserId()    { return $this->userId; }
    public function getParameter() { return $this->parameter; }
    public function getDate()      { return $this->date; }
    public function getValue()     { return $this->value; }

    public function setUserId($v)    { $this->userId = $v; }
    public function setParameter($v) { $this->parameter = $v; }
    public function setDate($v)      { $this->date = $v; }
    public function setValue($v)     { $this->value = $v; }

    public function save(): void
    {
        global $pdo;
        if ($this->id) {
            $pdo->prepare(
                "UPDATE user_properties SET user_id=?,parameter=?,date=?,value=? WHERE id=?"
            )->execute([$this->userId, $this->parameter, $this->date, $this->value, $this->id]);
        } else {
            $pid = updateAndGetMaxVal("userpropertiesid");
            $pdo->prepare(
                "INSERT INTO user_properties (id,user_id,parameter,date,value) VALUES (?,?,?,?,?)"
            )->execute([$pid, $this->userId, $this->parameter, $this->date, $this->value]);
        }
    }

    public function remove(): void
    {
        global $pdo;
        $pdo->prepare("DELETE FROM user_properties WHERE id=?")->execute([$this->id]);
    }
}
