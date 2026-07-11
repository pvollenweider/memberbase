<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * UserProperty entity — loads and holds a single user attribute/suivi entry.
 *
 * @copyright 2026 Philippe Vollenweider
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
        $stmt = db()->prepare("SELECT id,user_id,parameter,date,value FROM contact_properties WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id        = $row->id;
            $this->userId    = $row->user_id;
            $this->parameter = $row->parameter;
            // date is a DATETIME column converted in PHP, not via MySQL FROM_UNIXTIME()/
            // UNIX_TIMESTAMP() — those use the session timezone, which differs from
            // PHP's hardcoded Europe/Zurich (bootstrap.php) and would silently shift
            // the date (see #143).
            $this->date       = $row->date ? strtotime($row->date) : 0;
            $this->value     = $row->value;
        }
        // Not found: the object stays empty ($id === null) — callers check id.
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
        // date is formatted via PHP's date() (matching the mktime()/DateTime-based
        // parsers callers use), never a MySQL date function — see lookupUserProperty().
        $dateVal = ((int)$this->date) > 0 ? date('Y-m-d H:i:s', (int)$this->date) : null;
        if ($this->id) {
            db()->prepare(
                "UPDATE contact_properties SET user_id=?,parameter=?,date=?,value=? WHERE id=?"
            )->execute([$this->userId, $this->parameter, $dateVal, $this->value, $this->id]);
        } else {
            db()->prepare(
                "INSERT INTO contact_properties (user_id,parameter,date,value) VALUES (?,?,?,?)"
            )->execute([$this->userId, $this->parameter, $dateVal, $this->value]);
            $this->id = (int) db()->lastInsertId();
        }
    }

    public function remove(): void
    {
        db()->prepare("DELETE FROM contact_properties WHERE id=?")->execute([$this->id]);
    }
}
