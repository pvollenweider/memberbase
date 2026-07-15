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
    public $pausedAt;  // Unix timestamp or null — parked, hidden from the active list without being done
    public $doneAt;    // Unix timestamp or null (null = open)
    public $createdAt; // Unix timestamp

    public function __construct()
    {
    }

    public function lookupTask(int $id): void
    {
        $stmt = db()->prepare(
            "SELECT id,user_id,created_by,title,body,priority,rule_key,due_date,paused_at,done_at,created_at FROM suivi_task WHERE id=?"
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
            $this->pausedAt  = $row->paused_at ? strtotime($row->paused_at) : null;
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
    public function getPausedAt()  { return $this->pausedAt; }
    public function getRuleKey()   { return $this->ruleKey; }
    public function isOpen()       { return $this->doneAt === null; }
    public function isPaused()     { return $this->doneAt === null && $this->pausedAt !== null; }

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
        $dueDateVal   = ((int)$this->dueDate)  > 0 ? date('Y-m-d', (int)$this->dueDate) : null;
        $pausedAtVal  = ((int)$this->pausedAt) > 0 ? date('Y-m-d H:i:s', (int)$this->pausedAt) : null;
        $doneAtVal    = ((int)$this->doneAt)   > 0 ? date('Y-m-d H:i:s', (int)$this->doneAt) : null;
        if ($this->id) {
            db()->prepare(
                "UPDATE suivi_task SET user_id=?,title=?,body=?,priority=?,due_date=?,paused_at=?,done_at=? WHERE id=?"
            )->execute([
                $this->userId, $this->title, $this->body, $this->priority,
                $dueDateVal, $pausedAtVal, $doneAtVal, $this->id,
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
        $this->doneAt   = time();
        $this->pausedAt = null;
        db()->prepare("UPDATE suivi_task SET done_at=?, paused_at=NULL WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $this->id]);
    }

    /** Parks an open task — hidden from the active list, not counted as done. */
    public function pause(): void
    {
        $this->pausedAt = time();
        db()->prepare("UPDATE suivi_task SET paused_at=? WHERE id=?")
            ->execute([date('Y-m-d H:i:s'), $this->id]);
    }

    /** Brings a paused task back to the active list. */
    public function resume(): void
    {
        $this->pausedAt = null;
        db()->prepare("UPDATE suivi_task SET paused_at=NULL WHERE id=?")->execute([$this->id]);
    }

    public function remove(): void
    {
        db()->prepare("DELETE FROM suivi_task WHERE id=?")->execute([$this->id]);
    }

    /** Permanently deletes every completed (done_at IS NOT NULL) task. Returns the count removed. */
    public static function deleteAllCompleted(): int
    {
        $stmt = db()->query("DELETE FROM suivi_task WHERE done_at IS NOT NULL");
        return $stmt->rowCount();
    }

    /** Count open tasks whose due_date is in the past, for a member's overdue badge. */
    public static function overdueCountForUser(int $userId): int
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM suivi_task WHERE user_id=? AND done_at IS NULL AND paused_at IS NULL AND due_date IS NOT NULL AND due_date < ?"
        );
        $stmt->execute([$userId, date('Y-m-d')]);
        return (int)$stmt->fetchColumn();
    }

    /** Count open (non-paused) tasks for a member, for the fiche tab badge. */
    public static function openCountForUser(int $userId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM suivi_task WHERE user_id=? AND done_at IS NULL AND paused_at IS NULL");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /** Count all open, non-paused tasks (any member, plus global tasks), for the nav badge. */
    public static function openCount(): int
    {
        return (int)db()->query("SELECT COUNT(*) FROM suivi_task WHERE done_at IS NULL AND paused_at IS NULL")->fetchColumn();
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
            SELECT t.id, t.user_id, t.title, t.due_date, t.priority, u.firstname, u.lastname, u.society
            FROM suivi_task t
            LEFT JOIN contact u ON u.id = t.user_id
            WHERE t.done_at IS NULL AND t.paused_at IS NULL AND t.due_date IS NOT NULL AND t.due_date <= ?
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

    /**
     * How many members currently match FILTER_UNPAID_COTI_CURRENT for $year
     * without already having an open reminder task, i.e. what
     * generateUnpaidCotiTasks() would actually create right now. Used to hide
     * the "Générer" button when there's nothing to generate, rather than
     * showing a button that always reports "0 tâche créée".
     */
    public static function countUnpaidCotiPendingGeneration(int $year, array $appSettings): int
    {
        $memberIds = MemberFilter::resolveIds(FILTER_UNPAID_COTI_CURRENT, db(), $year, $appSettings);
        if (empty($memberIds)) {
            return 0;
        }
        $stmt = db()->prepare("SELECT user_id FROM suivi_task WHERE rule_key=? AND done_at IS NULL");
        $stmt->execute(["unpaid_coti_current_$year"]);
        $existing = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
        $count = 0;
        foreach (array_keys($memberIds) as $uid) {
            if (empty($existing[$uid])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Generates one task per member with unnotified compta entries for $year
     * (same source of truth as compta_recap.php's pending list). Dedup via
     * rule_key, same pattern as generateUnpaidCotiTasks() — closes tasks whose
     * member no longer has anything pending (sent through the batch flow,
     * entries deleted, etc.) instead of leaving them stale.
     *
     * @return array{created:int,closed:int}
     */
    public static function generateComptaRecapTasks(int $year, ?int $createdBy): array
    {
        global $GLOBAL;
        $ruleKey = "compta_recap_pending_$year";

        $stmt = db()->prepare(
            "SELECT DISTINCT c.user_id
             FROM compta c
             JOIN contact u ON u.id = c.user_id AND u.status = 1
             WHERE c.notified_at IS NULL AND c.sum <> 0 AND YEAR(c.date) = ?"
        );
        $stmt->execute([$year]);
        $memberIds = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);

        $stmt = db()->prepare("SELECT id, user_id FROM suivi_task WHERE rule_key=? AND done_at IS NULL");
        $stmt->execute([$ruleKey]);
        $openTasks = $stmt->fetchAll(PDO::FETCH_OBJ);
        $existingByUser = [];
        foreach ($openTasks as $t) {
            $existingByUser[(int)$t->user_id] = (int)$t->id;
        }

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
            $task->setTitle(sprintf($GLOBAL['taskRuleComptaRecapTitle'], $year));
            $task->setPriority(self::PRIORITY_NORMAL);
            $task->setRuleKey($ruleKey);
            $task->save();
            $created++;
        }
        return ['created' => $created, 'closed' => $closed];
    }

    /**
     * How many members with unnotified compta entries for $year don't
     * already have an open notification task, i.e. what
     * generateComptaRecapTasks() would actually create right now. Same
     * purpose as countUnpaidCotiPendingGeneration(): hide the "Générer"
     * button when there's nothing to generate.
     */
    public static function countComptaRecapPendingGeneration(int $year): int
    {
        $stmt = db()->prepare(
            "SELECT DISTINCT c.user_id
             FROM compta c
             JOIN contact u ON u.id = c.user_id AND u.status = 1
             WHERE c.notified_at IS NULL AND c.sum <> 0 AND YEAR(c.date) = ?"
        );
        $stmt->execute([$year]);
        $memberIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (empty($memberIds)) {
            return 0;
        }
        $stmt = db()->prepare("SELECT user_id FROM suivi_task WHERE rule_key=? AND done_at IS NULL");
        $stmt->execute(["compta_recap_pending_$year"]);
        $existing = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
        $count = 0;
        foreach ($memberIds as $uid) {
            if (empty($existing[$uid])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Shared engine for "global task per group" rules (duplicates, hidden
     * segments): dedups by exact rule_key, closes tasks whose group no
     * longer exists, creates one for every new group.
     *
     * @param array<string,string> $groups ruleKey => task title
     * @return array{created:int,closed:int}
     */
    private static function generateFromGroups(array $groups, string $likePrefix, ?int $createdBy, int $priority): array
    {
        $stmt = db()->prepare("SELECT id, rule_key FROM suivi_task WHERE rule_key LIKE ? AND done_at IS NULL");
        $stmt->execute([$likePrefix . '%']);
        $existing = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $existing[$row->rule_key] = (int)$row->id;
        }

        $closed = 0;
        foreach ($existing as $ruleKey => $taskId) {
            if (!isset($groups[$ruleKey])) {
                $task = new self();
                $task->lookupTask($taskId);
                if ($task->getId()) {
                    $task->close();
                    $closed++;
                }
            }
        }

        $created = 0;
        foreach ($groups as $ruleKey => $title) {
            if (isset($existing[$ruleKey])) {
                continue;
            }
            $task = new self();
            $task->setCreatedBy($createdBy);
            $task->setTitle($title);
            $task->setPriority($priority);
            $task->setRuleKey($ruleKey);
            $task->save();
            $created++;
        }
        return ['created' => $created, 'closed' => $closed];
    }

    private static function countPendingFromGroups(array $groups, string $likePrefix): int
    {
        if (empty($groups)) {
            return 0;
        }
        $stmt = db()->prepare("SELECT rule_key FROM suivi_task WHERE rule_key LIKE ? AND done_at IS NULL");
        $stmt->execute([$likePrefix . '%']);
        $existing = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
        $count = 0;
        foreach (array_keys($groups) as $ruleKey) {
            if (empty($existing[$ruleKey])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Potential duplicate contacts (same name, or same email), grouped —
     * same source queries as Réglages → Intégrité. rule_key embeds the
     * sorted member ids, so a group that changes (one member merged away)
     * naturally gets a fresh key rather than matching a stale one.
     *
     * @return array<string,string> ruleKey => task title
     */
    private static function currentDuplicateGroups(): array
    {
        global $GLOBAL;
        $groups = [];
        $nameRows = db()->query("
            SELECT firstname, lastname, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM contact
            WHERE status=1 AND (TRIM(firstname) != '' OR TRIM(lastname) != '')
            GROUP BY TRIM(LOWER(firstname)), TRIM(LOWER(lastname))
            HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_OBJ);
        foreach ($nameRows as $r) {
            $groups['dup_name_' . $r->ids] = sprintf($GLOBAL['taskRuleDupNameTitle'], trim($r->firstname . ' ' . $r->lastname));
        }
        $emailRows = db()->query("
            SELECT email, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
            FROM contact
            WHERE status=1 AND TRIM(email) != ''
            GROUP BY TRIM(LOWER(email))
            HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_OBJ);
        foreach ($emailRows as $r) {
            $groups['dup_email_' . $r->ids] = sprintf($GLOBAL['taskRuleDupEmailTitle'], $r->email);
        }
        return $groups;
    }

    public static function generateDuplicateTasks(?int $createdBy): array
    {
        return self::generateFromGroups(self::currentDuplicateGroups(), 'dup_', $createdBy, self::PRIORITY_LOW);
    }

    public static function countDuplicatePendingGeneration(): int
    {
        return self::countPendingFromGroups(self::currentDuplicateGroups(), 'dup_');
    }

    /**
     * Hidden segments still assigned to active members — same source query
     * as Réglages → Intégrité ("hiddenWithMembers").
     *
     * @return array<string,string> ruleKey => task title
     */
    private static function currentHiddenSegmentGroups(): array
    {
        global $GLOBAL;
        $rows = db()->query("
            SELECT t.id AS segment_id, t.name AS segment_name, COUNT(us.user_id) AS member_count
            FROM segment t
            JOIN contact_segment us ON us.segment_id = t.id
            JOIN contact u ON u.id = us.user_id AND u.status = 1
            WHERE t.hidden = 1
            GROUP BY t.id, t.name
        ")->fetchAll(PDO::FETCH_OBJ);
        $groups = [];
        foreach ($rows as $r) {
            $groups['hidden_segment_' . (int)$r->segment_id] = sprintf($GLOBAL['taskRuleHiddenSegmentTitle'], $r->segment_name, (int)$r->member_count);
        }
        return $groups;
    }

    public static function generateHiddenSegmentTasks(?int $createdBy): array
    {
        return self::generateFromGroups(self::currentHiddenSegmentGroups(), 'hidden_segment_', $createdBy, self::PRIORITY_LOW);
    }

    public static function countHiddenSegmentPendingGeneration(): int
    {
        return self::countPendingFromGroups(self::currentHiddenSegmentGroups(), 'hidden_segment_');
    }

    /**
     * Donation attestation tasks — one per qualifying donor of the previous
     * year not yet sent one, due January 30. Deliberately only generates
     * anything in January: attestations are a seasonal, once-a-year mailing,
     * generating/nagging about them the rest of the year would just be
     * noise (and the "previous year" framing only makes sense right after
     * the year closes).
     *
     * @return array{created:int,closed:int}
     */
    public static function generateAttestationTasks(?int $createdBy): array
    {
        if ((int)date('n') !== 1) {
            return ['created' => 0, 'closed' => 0];
        }
        global $GLOBAL;
        require_once __DIR__ . '/../includes/lib/attestation.php';
        $year    = (int)date('Y') - 1;
        $ruleKey = "attestation_pending_$year";

        $pendingIds = self::pendingAttestationUserIds($year);

        $stmt = db()->prepare("SELECT id, user_id FROM suivi_task WHERE rule_key=? AND done_at IS NULL");
        $stmt->execute([$ruleKey]);
        $existingByUser = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $t) {
            $existingByUser[(int)$t->user_id] = (int)$t->id;
        }

        $closed = 0;
        foreach ($existingByUser as $uid => $taskId) {
            if (empty($pendingIds[$uid])) {
                $task = new self();
                $task->lookupTask($taskId);
                if ($task->getId()) {
                    $task->close();
                    $closed++;
                }
            }
        }

        $dueDate = mktime(0, 0, 0, 1, 30, (int)date('Y'));
        $created = 0;
        foreach (array_keys($pendingIds) as $uid) {
            if (isset($existingByUser[$uid])) {
                continue;
            }
            $task = new self();
            $task->setUserId($uid);
            $task->setCreatedBy($createdBy);
            $task->setTitle(sprintf($GLOBAL['taskRuleAttestationTitle'], $year));
            $task->setPriority(self::PRIORITY_NORMAL);
            $task->setRuleKey($ruleKey);
            $task->setDueDate($dueDate);
            $task->save();
            $created++;
        }
        return ['created' => $created, 'closed' => $closed];
    }

    public static function countAttestationPendingGeneration(): int
    {
        if ((int)date('n') !== 1) {
            return 0;
        }
        require_once __DIR__ . '/../includes/lib/attestation.php';
        $year = (int)date('Y') - 1;
        $pendingIds = self::pendingAttestationUserIds($year);
        if (empty($pendingIds)) {
            return 0;
        }
        $stmt = db()->prepare("SELECT user_id FROM suivi_task WHERE rule_key=? AND done_at IS NULL");
        $stmt->execute(["attestation_pending_$year"]);
        $existing = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
        $count = 0;
        foreach (array_keys($pendingIds) as $uid) {
            if (empty($existing[$uid])) {
                $count++;
            }
        }
        return $count;
    }

    /** @return array<int,true> user_id => true, for donors of $year qualifying for an attestation, not yet sent, with an email on file. */
    private static function pendingAttestationUserIds(int $year): array
    {
        require_once __DIR__ . '/../includes/lib/attestation.php';
        $donors     = mbGetQualifyingDonors(db(), $year, 1);
        $donorIds   = array_map(fn($d) => (int)$d->id, $donors);
        $alreadyMap = mbGetAlreadySentAttestationIds(db(), $year, $donorIds);
        $pending = [];
        foreach ($donors as $d) {
            $uid = (int)$d->id;
            if (isset($alreadyMap[$uid])) {
                continue;
            }
            if (trim((string)$d->email) === '') {
                continue;
            }
            $pending[$uid] = true;
        }
        return $pending;
    }
}
