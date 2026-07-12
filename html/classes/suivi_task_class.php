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
    public $ruleKey;   // string identifying the business rule that generated this task, or null (manual)
    public $dueDate;   // Unix timestamp or 0/null
    public $doneAt;    // Unix timestamp or null (null = open)
    public $createdAt; // Unix timestamp

    public function __construct()
    {
    }

    public function lookupTask(int $id): void
    {
        $stmt = db()->prepare(
            "SELECT id,user_id,created_by,title,body,priority,rule_key,due_date,done_at,created_at FROM suivi_task WHERE id=?"
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
            $this->ruleKey   = $row->rule_key;
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
    public function getRuleKey()   { return $this->ruleKey; }
    public function isOpen()       { return $this->doneAt === null; }

    public function setUserId($v)    { $this->userId = $v !== null ? (int)$v : null; }
    public function setCreatedBy($v) { $this->createdBy = $v !== null ? (int)$v : null; }
    public function setTitle($v)     { $this->title = $v; }
    public function setBody($v)      { $this->body = $v; }
    public function setPriority($v)  { $this->priority = mbValidTaskPriority((int)$v); }
    public function setRuleKey($v)   { $this->ruleKey = $v; }
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
                "INSERT INTO suivi_task (user_id,created_by,title,body,priority,rule_key,due_date,done_at) VALUES (?,?,?,?,?,?,?,?)"
            )->execute([
                $this->userId, $this->createdBy, $this->title, $this->body,
                $this->priority, $this->ruleKey, $dueDateVal, $doneAtVal,
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

    /**
     * Open tasks that are overdue or due within $horizonDays, for the cron
     * digest email (#150). Threshold computed in PHP, not via a MySQL date
     * function — same convention as the rest of the app (see #143).
     *
     * @return object[] rows: id, title, due_date, priority, firstname, lastname, society (member fields NULL for global tasks)
     */
    public static function dueSoonOrOverdue(int $horizonDays = 3): array
    {
        $threshold = date('Y-m-d', strtotime("+$horizonDays days"));
        $stmt = db()->prepare("
            SELECT t.id, t.title, t.due_date, t.priority, u.firstname, u.lastname, u.society
            FROM suivi_task t
            LEFT JOIN contact u ON u.id = t.user_id
            WHERE t.done_at IS NULL AND t.due_date IS NOT NULL AND t.due_date <= ?
            ORDER BY t.due_date ASC, t.priority ASC
        ");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Generates a relance task for every member matching FILTER_UNPAID_COTI_CURRENT
     * for $year, skipping members who already have an open task for this rule/year
     * (dedup via rule_key). Reuses MemberFilter — same rule as the members list
     * "Cotisation AAAA non payée" quick filter (issue #149).
     *
     * Also closes existing open tasks for this rule whose member no longer matches
     * the filter (e.g. the cotisation was recorded through another channel — the
     * secretary shouldn't have to remember to close the reminder task by hand).
     *
     * @return array{created:int,closed:int}
     */
    public static function generateUnpaidCotiTasks(int $year, array $appSettings, ?int $createdBy): array
    {
        global $GLOBAL;
        $ruleKey = "unpaid_coti_current_$year";
        $memberIds = MemberFilter::resolveIds(FILTER_UNPAID_COTI_CURRENT, db(), $year, $appSettings);

        $stmt = db()->prepare("SELECT id, user_id FROM suivi_task WHERE rule_key=? AND done_at IS NULL");
        $stmt->execute([$ruleKey]);
        $openTasks = $stmt->fetchAll(PDO::FETCH_OBJ);
        $existingByUser = [];
        foreach ($openTasks as $t) {
            $existingByUser[(int)$t->user_id] = (int)$t->id;
        }

        // Still-unpaid members with an open task: nothing to do. Members with an
        // open task who are NOT in the current unpaid list anymore: resolved
        // elsewhere (payment recorded directly, membership changed...) — close it.
        $closed = 0;
        foreach ($existingByUser as $uid => $taskId) {
            if (empty($memberIds[$uid])) {
                $task = new SuiviTask();
                $task->lookupTask($taskId);
                if ($task->getId()) {
                    $task->close();
                    $closed++;
                }
            }
        }

        $created = 0;
        foreach (array_keys($memberIds) as $uid) {
            if (isset($existingByUser[$uid])) {
                continue;
            }
            $task = new SuiviTask();
            $task->setUserId($uid);
            $task->setCreatedBy($createdBy);
            $task->setTitle(sprintf($GLOBAL['taskRuleUnpaidCotiTitle'], $year));
            $task->setPriority(self::PRIORITY_NORMAL);
            $task->setRuleKey($ruleKey);
            $task->save();
            $created++;
        }
        return ['created' => $created, 'closed' => $closed];
    }
}
