<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * SuiviTask entity — a titled task with priority, due date, and open/closed
 * status, parallel to the free-text suivi notes in contact_properties.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

class SuiviTask
{
    public const PRIORITY_HIGH   = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_LOW    = 3;

    public $id;
    public $userId;
    public $createdBy;
    public $title;
    public $body;
    public $priority = self::PRIORITY_NORMAL;
    public $dueDate;   // Unix timestamp or 0/null
    public $doneAt;    // Unix timestamp or null (null = open)
    public $createdAt; // Unix timestamp

    public function __construct()
    {
    }

    public function lookupTask(int $id): void
    {
        $stmt = db()->prepare(
            "SELECT id,user_id,created_by,title,body,priority,due_date,done_at,created_at FROM suivi_task WHERE id=?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id        = (int)$row->id;
            $this->userId    = $row->user_id !== null ? (int)$row->user_id : null;
            $this->createdBy = $row->created_by !== null ? (int)$row->created_by : null;
            $this->title     = $row->title;
            $this->body      = $row->body;
            $this->priority  = (int)$row->priority;
            $this->dueDate   = $row->due_date ? strtotime($row->due_date) : null;
            $this->doneAt    = $row->done_at ? strtotime($row->done_at) : null;
            $this->createdAt = $row->created_at ? strtotime($row->created_at) : null;
        }
        // Not found: the object stays empty ($id === null) — callers check id.
    }

    public function getId()        { return $this->id; }
    public function getUserId()    { return $this->userId; }
    public function getTitle()     { return $this->title; }
    public function getBody()      { return $this->body; }
    public function getPriority()  { return $this->priority; }
    public function getDueDate()   { return $this->dueDate; }
    public function getDoneAt()    { return $this->doneAt; }
    public function isOpen()       { return $this->doneAt === null; }

    public function setUserId($v)    { $this->userId = $v !== null ? (int)$v : null; }
    public function setCreatedBy($v) { $this->createdBy = $v !== null ? (int)$v : null; }
    public function setTitle($v)     { $this->title = $v; }
    public function setBody($v)      { $this->body = $v; }
    public function setPriority($v)  { $this->priority = mbValidTaskPriority((int)$v); }
    public function setDueDate($v)   { $this->dueDate = $v; }

    public function save(): void
    {
        // Dates are formatted via PHP's date(), never a MySQL date function —
        // same convention as Compta/UserProperty (see #143).
        $dueDateVal = ((int)$this->dueDate) > 0 ? date('Y-m-d', (int)$this->dueDate) : null;
        $doneAtVal  = ((int)$this->doneAt)  > 0 ? date('Y-m-d H:i:s', (int)$this->doneAt) : null;
        if ($this->id) {
            db()->prepare(
                "UPDATE suivi_task SET user_id=?,title=?,body=?,priority=?,due_date=?,done_at=? WHERE id=?"
            )->execute([
                $this->userId, $this->title, $this->body, $this->priority,
                $dueDateVal, $doneAtVal, $this->id,
            ]);
        } else {
            db()->prepare(
                "INSERT INTO suivi_task (user_id,created_by,title,body,priority,due_date,done_at) VALUES (?,?,?,?,?,?,?)"
            )->execute([
                $this->userId, $this->createdBy, $this->title, $this->body,
                $this->priority, $dueDateVal, $doneAtVal,
            ]);
            $this->id = (int) db()->lastInsertId();
        }
    }

    public function close(): void
    {
        $this->doneAt = time();
        db()->prepare("UPDATE suivi_task SET done_at=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $this->id]);
    }

    public function remove(): void
    {
        db()->prepare("DELETE FROM suivi_task WHERE id=?")->execute([$this->id]);
    }

    /** Count open tasks whose due_date is in the past, for a member's overdue badge. */
    public static function overdueCountForUser(int $userId): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM suivi_task WHERE user_id=? AND done_at IS NULL AND due_date IS NOT NULL AND due_date < ?"
        );
        $stmt->execute([$userId, date('Y-m-d')]);
        return (int)$stmt->fetchColumn();
    }

    /** Count open tasks for a member, for the fiche tab badge. */
    public static function openCountForUser(int $userId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM suivi_task WHERE user_id=? AND done_at IS NULL");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
