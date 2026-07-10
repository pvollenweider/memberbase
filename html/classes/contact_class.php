<?php
defined('APP_ENTRY') or die('Direct access not permitted.');
/**
 * User entity — loads, holds, and persists a single member record.
 *
 * @copyright 2026 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

class Contact
{
    public $id;
    // NOT NULL columns — default to '' so a freshly-built User is always safe to save,
    // even when a caller (e.g. CSV import) only sets a subset of fields.
    public $firstName = '';
    public $lastName = '';
    public $society = '';
    public $sexe = 'na';
    public $title = '';
    public $address = '';
    public $npa = '';
    public $tel = '';
    public $telProf = '';
    public $fax = '';
    public $portable = '';
    public $email = '';
    public $emailAlt = '';
    public $web = '';
    public $birthDay = 0;
    public $comment = '';
    public $creationDate;
    public $modificationDate;
    public int $status = 1;


    public function __construct()
    {
    }

    private function hydrateFromRow(object $row): void
    {
        $this->id               = $row->id;
        $this->firstName        = $row->firstname;
        $this->lastName         = $row->lastname;
        $this->society          = $row->society;
        $this->sexe             = $row->sexe;
        $this->title            = $row->title;
        $this->address          = $row->address;
        $this->npa              = $row->npa;
        $this->tel              = $row->tel;
        $this->telProf          = $row->telprof;
        $this->portable         = $row->portable;
        $this->fax              = $row->fax;
        $this->email            = $row->email;
        $this->emailAlt         = $row->email_alt ?? '';
        $this->web              = $row->web;
        $this->birthDay         = $row->birthday;
        $this->comment          = $row->comment;
        $this->creationDate     = $row->creationDate;
        $this->modificationDate = $row->modificationDate;
        $this->status           = (int)$row->status;
    }

    private const SELECT_COLS = "id,firstname,lastname,society,sexe,title,address,npa,tel,telprof,portable,fax,email,email_alt,web,birthday,comment,creationDate,modificationDate,status";

    public function lookupUser(int $id): void
    {
        $stmt = db()->prepare("SELECT " . self::SELECT_COLS . " FROM contact WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->hydrateFromRow($row);
        } else {
        }
    }

    public function getId()               { return $this->id; }
    public function getFirstName()        { return $this->firstName; }
    public function getLastName()         { return $this->lastName; }
    public function getSociety()          { return $this->society; }
    public function getSexe()             { return $this->sexe; }
    public function getTitle()            { return $this->title; }
    public function getAddress()          { return $this->address; }
    public function getNpa()              { return $this->npa; }
    public function getTel()              { return $this->tel; }
    public function getTelProf()          { return $this->telProf; }
    public function getPortable()         { return $this->portable; }
    public function getFax()              { return $this->fax; }
    public function getEmail()            { return $this->email; }
    public function getEmailAlt()         { return $this->emailAlt; }
    public function getWeb()              { return $this->web; }
    public function getBirthDay()         { return $this->birthDay; }
    public function getComment()          { return $this->comment; }
    public function getCreationDate()     { return $this->creationDate; }
    public function getModificationDate() { return $this->modificationDate; }

    public function setFirstName($v)         { $this->firstName = $v; }
    public function setLastName($v)          { $this->lastName = $v; }
    public function setSociety($v)           { $this->society = $v; }
    public function setSexe($v)              { $this->sexe = $v; }
    public function setTitle($v)             { $this->title = $v; }
    public function setAddress($v)           { $this->address = $v; }
    public function setNpa($v)               { $this->npa = $v; }
    public function setTel($v)               { $this->tel = $v; }
    public function setTelProf($v)           { $this->telProf = $v; }
    public function setPortable($v)          { $this->portable = $v; }
    public function setFax($v)               { $this->fax = $v; }
    public function setEmail($v)             { $this->email = $v; }
    public function setEmailAlt($v)          { $this->emailAlt = $v; }
    public function setWeb($v)               { $this->web = $v; }
    public function setComment($v)           { $this->comment = $v; }
    public function setCreationDate($v)      { $this->creationDate = $v; }
    public function setModificationDate($v)  { $this->modificationDate = $v; }

    public function setBirthDay(string $birthDay): void
    {
        $parts = explode('/', $birthDay);
        $this->birthDay = mktime(0, 0, 0, (int)$parts[1], (int)$parts[0], (int)$parts[2]);
    }

    public function getProperty(string $parameter): string
    {
        $stmt = db()->prepare("SELECT value FROM contact_properties WHERE user_id=? AND parameter=?");
        $stmt->execute([$this->id, $parameter]);
        $row = $stmt->fetchObject();
        return $row ? (string) $row->value : "";
    }

    public function isMemberOfSegment(int $segmentId): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM contact_segment WHERE user_id=? AND segment_id=? LIMIT 1");
        $stmt->execute([$this->id, $segmentId]);
        return $stmt->fetchColumn() !== false;
    }

    private function firstComptaDate(string $sql, array $params): int
    {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetchObject();
        return $row ? (int) $row->date : -1;
    }

    public function isCotisationPayed(int $year): int
    {
        $from = mktime(0, 0, 0, 1, 0, $year);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        return $this->firstComptaDate(
            "SELECT date FROM compta WHERE user_id=? AND date>? AND date<? AND type_id IN (SELECT id FROM compta_type WHERE is_cotisation=1)",
            [$this->id, $from, $to]
        );
    }

    /* Any non-coti payment in a given year */
    public function hasPayed(int $year): int
    {
        $from = mktime(0, 0, 0, 1, 0, $year);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        return $this->firstComptaDate(
            "SELECT date FROM compta WHERE user_id=? AND date>? AND date<? AND type_id NOT IN (SELECT id FROM compta_type WHERE is_cotisation=1)",
            [$this->id, $from, $to]
        );
    }

    /* Pure donation (excludes coti, reintegration, evenementiel) */
    public function hasDonated(int $year): int
    {
        $from = mktime(0, 0, 0, 1, 0, $year);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        return $this->firstComptaDate(
            "SELECT date FROM compta WHERE user_id=? AND date>? AND date<? AND type_id NOT IN (SELECT id FROM compta_type WHERE is_excluded_from_donation=1)",
            [$this->id, $from, $to]
        );
    }

    /* Any compta entry of any type in a given year */
    public function hasAnyEntry(int $year): int
    {
        $from = mktime(0, 0, 0, 1, 0, $year);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        return $this->firstComptaDate(
            "SELECT date FROM compta WHERE user_id=? AND date>? AND date<?",
            [$this->id, $from, $to]
        );
    }

    public function hasComptaEntries(int $year, int $number): bool
    {
        $from = mktime(0, 0, 0, 1, 0, $year - $number);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        $stmt = db()->prepare("SELECT 1 FROM compta WHERE user_id=? AND date>? AND date<? LIMIT 1");
        $stmt->execute([$this->id, $from, $to]);
        return $stmt->fetchObject() !== false;
    }

    public function hasComptaEntry(): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM compta WHERE user_id=? LIMIT 1");
        $stmt->execute([$this->id]);
        return $stmt->fetchObject() !== false;
    }

    /**
     * Return "Firstname Lastname" for audit log labels, or "id=N" fallback.
     * Avoids repeating the same SELECT CONCAT(...) pattern in callers.
     */
    public static function getMemberName(int $id): string
    {
        $stmt = db()->prepare("SELECT CONCAT(firstname,' ',lastname) FROM contact WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() ?: "id=$id";
    }

    public function assignSegment(int $segmentId): void
    {
        db()->prepare("INSERT IGNORE INTO contact_segment (user_id, segment_id) VALUES (?, ?)")
            ->execute([$this->id, $segmentId]);
    }

    public function unassignSegment(int $segmentId): void
    {
        db()->prepare("DELETE FROM contact_segment WHERE user_id=? AND segment_id=?")
            ->execute([$this->id, $segmentId]);
    }

    public function save(): int
    {
        if ($this->id) {
            db()->prepare(
                "UPDATE contact SET firstname=?,lastname=?,society=?,sexe=?,title=?,address=?,npa=?,
                 tel=?,telprof=?,portable=?,fax=?,email=?,email_alt=?,web=?,birthday=?,comment=?,modificationDate=?
                 WHERE id=?"
            )->execute([
                $this->firstName, $this->lastName, $this->society, $this->sexe, $this->title,
                $this->address, $this->npa, $this->tel, $this->telProf, $this->portable,
                $this->fax, $this->email, $this->emailAlt ?? '', $this->web, $this->birthDay, $this->comment,
                time(), $this->id,
            ]);
            return (int) $this->id;
        } else {
            db()->prepare(
                "INSERT INTO contact (firstname,lastname,society,sexe,title,address,npa,
                 tel,telprof,portable,fax,email,email_alt,web,birthday,comment,creationDate,modificationDate)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $this->firstName, $this->lastName, $this->society, $this->sexe,
                $this->title, $this->address, $this->npa, $this->tel, $this->telProf,
                $this->portable, $this->fax, $this->email, $this->emailAlt ?? '', $this->web, $this->birthDay,
                $this->comment, time(), time(),
            ]);
            return (int)db()->lastInsertId();
        }
    }

    public function remove(): void
    {
        db()->prepare("DELETE FROM contact WHERE id=?")->execute([$this->id]);
        db()->prepare("DELETE FROM contact_properties WHERE user_id=?")->execute([$this->id]);
        db()->prepare("DELETE FROM compta WHERE user_id=?")->execute([$this->id]);
    }

    /**
     * Active member rows for the members list view, filtered by segment,
     * combined segment or text search. Virtual filters
     * (negative team IDs) are NOT applied here — the caller restricts rows
     * via MemberFilter::resolveIds().
     *
     * @param array $opts {
     *     team:            int     segment ID, 0 = all, virtual IDs pass through unfiltered
     *     combinedSegment: int     combined segment ID (0 = none; takes precedence over team)
     *     searchString:    string  text search (applied when action == 'search')
     *     action:          string  request action ('search' enables the text filter)
     *     membreTeam:      int     "membre" segment ID (legacy -1234 filter)
     *     orderColumn:     string  MUST be pre-validated against a whitelist
     *     orderSort:       string  'ASC' | 'DESC' (pre-validated)
     * }
     * @return object[] rows: id, firstname, lastname, society, sexe, address, npa, email, creationDate
     */
    public static function listWithFilters(array $opts): array
    {

        $team            = (int)($opts['team'] ?? 0);
        $combinedSegment = (int)($opts['combinedSegment'] ?? 0);
        $orderColumn = $opts['orderColumn'] ?? 'lastname';
        $orderSort   = $opts['orderSort'] ?? 'ASC';

        $query = "SELECT DISTINCT contact.id, contact.firstname, contact.lastname, contact.society,"
               . " contact.sexe, contact.address, contact.npa, contact.email, contact.creationDate"
               . " FROM contact";
        if ($combinedSegment > 0) {
            $query .= ",contact_segment ";
        } else {
            // Virtual filter IDs (resolved via MemberFilter) and team=0 (all members)
            // do not need a contact_segment join
            if ($team != 0 && $team != -1 && !MemberFilter::isVirtual($team)) {
                $query .= ",contact_segment ";
            }
        }
        $query .= " WHERE 1=1 AND contact.status=1 ";

        $queryParams = [];
        if (($opts['action'] ?? '') === 'search') {
            $like = '%' . ($opts['searchString'] ?? '') . '%';
            $query .= " AND (contact.firstname LIKE ?"
                    . " OR contact.lastname LIKE ?"
                    . " OR CONCAT(contact.firstname, ' ', contact.lastname) LIKE ?"
                    . " OR CONCAT(contact.lastname, ' ', contact.firstname) LIKE ?"
                    . " OR contact.society LIKE ?"
                    . " OR contact.npa LIKE ?"
                    . " OR contact.email LIKE ?"
                    . " OR contact.comment LIKE ?"
                    . " OR contact.address LIKE ?)";
            $queryParams = array_fill(0, 9, $like);
        }

        if ($combinedSegment > 0) {
            $mgSegmentIds = CombinedSegment::segmentIds($combinedSegment);
            if (count($mgSegmentIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($mgSegmentIds), '?'));
                $query .= " AND contact.id=contact_segment.user_id AND contact_segment.segment_id IN ($placeholders)";
                $queryParams = array_merge($queryParams, $mgSegmentIds);
            } else {
                $query .= " AND 1=0"; // combined segment has no segments — return empty
            }
        } else if ($team != -1) {
            if ($team == 0 || MemberFilter::isVirtual($team)) {
                // team=0 = all active members; virtual filters restrict rows
                // via the MemberFilter ID set applied by the caller
            } else if ($team == -1234) {
                $membreSegment = (int)($opts['membreTeam'] ?? 0);
                $query .= " AND contact.id=contact_segment.user_id AND contact_segment.segment_id=? ";
                $queryParams[] = $membreSegment;
            } else {
                $query .= " AND contact.id=contact_segment.user_id AND contact_segment.segment_id=?";
                $queryParams[] = $team;
            }
        }

        $query .= " ORDER BY $orderColumn $orderSort";

        $stmt = db()->prepare($query);
        $stmt->execute($queryParams);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
