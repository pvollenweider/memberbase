<?php

class User
{
    public $id;
    public $firstName;
    public $lastName;
    public $society;
    public $sexe;
    public $title;
    public $address;
    public $npa;
    public $tel;
    public $telProf;
    public $fax;
    public $portable;
    public $email;
    public $web;
    public $birthDay;
    public $comment;
    public $creationDate;
    public $modificationDate;

    private $logger;

    public function __construct()
    {
        $this->logger = Logger::getLogger('User');
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
        $this->web              = $row->web;
        $this->birthDay         = $row->birthday;
        $this->comment          = $row->comment;
        $this->creationDate     = $row->creationDate;
        $this->modificationDate = $row->modificationDate;
    }

    private const SELECT_COLS = "id,firstname,lastname,society,sexe,title,address,npa,tel,telprof,portable,fax,email,web,birthday,comment,creationDate,modificationDate";

    public function lookupUserByEmail(string $email): void
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT " . self::SELECT_COLS . " FROM users WHERE email LIKE ?");
        $stmt->execute(["%" . $email . "%"]);
        foreach ($stmt as $row) {
            $this->hydrateFromRow($row);
        }
    }

    public function lookupUser(int $id): void
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT " . self::SELECT_COLS . " FROM users WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->hydrateFromRow($row);
        } else {
            $this->logger->debug("Could not find user with id [$id]");
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
        global $pdo;
        $stmt = $pdo->prepare("SELECT value FROM user_properties WHERE user_id=? AND parameter=?");
        $stmt->execute([$this->id, $parameter]);
        $row = $stmt->fetchObject();
        return $row ? (string) $row->value : "";
    }

    public function isMemberOfTeam(int $teamId): bool
    {
        return $this->getProperty("team_" . $teamId) === 'true';
    }

    private function firstComptaDate(string $sql, array $params): int
    {
        global $pdo;
        $stmt = $pdo->prepare($sql);
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
        global $pdo;
        $from = mktime(0, 0, 0, 1, 0, $year - $number);
        $to   = mktime(0, 0, 0, 1, 1, $year + 1);
        $stmt = $pdo->prepare("SELECT 1 FROM compta WHERE user_id=? AND date>? AND date<? LIMIT 1");
        $stmt->execute([$this->id, $from, $to]);
        return $stmt->fetchObject() !== false;
    }

    public function hasComptaEntry(): bool
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT 1 FROM compta WHERE user_id=? LIMIT 1");
        $stmt->execute([$this->id]);
        return $stmt->fetchObject() !== false;
    }

    public function addMembership(int $teamId): void
    {
        global $pdo;
        $pdo->prepare("INSERT IGNORE INTO user_properties (user_id,parameter,value) VALUES (?,?,?)")
            ->execute([$this->id, "team_$teamId", 'true']);
        $this->logger->info("Add team_$teamId for user $this->id");
    }

    public function removeMembership(int $teamId): void
    {
        global $pdo;
        $pdo->prepare("DELETE FROM user_properties WHERE user_id=? AND parameter=?")
            ->execute([$this->id, "team_$teamId"]);
        $this->logger->info("Remove team_$teamId for user $this->id");
    }

    public function save(): int
    {
        global $pdo;
        if ($this->id) {
            $pdo->prepare(
                "UPDATE users SET firstname=?,lastname=?,society=?,sexe=?,title=?,address=?,npa=?,
                 tel=?,telprof=?,portable=?,fax=?,email=?,web=?,birthday=?,comment=?,modificationDate=?
                 WHERE id=?"
            )->execute([
                $this->firstName, $this->lastName, $this->society, $this->sexe, $this->title,
                $this->address, $this->npa, $this->tel, $this->telProf, $this->portable,
                $this->fax, $this->email, $this->web, $this->birthDay, $this->comment,
                time(), $this->id,
            ]);
            $this->logger->info("Updated user $this->id");
            return (int) $this->id;
        } else {
            $userId = updateAndGetMaxVal("userid");
            $pdo->prepare(
                "INSERT INTO users (id,firstname,lastname,society,sexe,title,address,npa,
                 tel,telprof,portable,fax,email,web,birthday,comment,creationDate,modificationDate)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $userId, $this->firstName, $this->lastName, $this->society, $this->sexe,
                $this->title, $this->address, $this->npa, $this->tel, $this->telProf,
                $this->portable, $this->fax, $this->email, $this->web, $this->birthDay,
                $this->comment, time(), time(),
            ]);
            $this->logger->info("Inserted user $userId");
            return $userId;
        }
    }

    public function remove(): void
    {
        global $pdo;
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$this->id]);
        $pdo->prepare("DELETE FROM user_properties WHERE user_id=?")->execute([$this->id]);
        $pdo->prepare("DELETE FROM compta WHERE user_id=?")->execute([$this->id]);
        $this->logger->info("Removed user $this->id and related records");
    }
}
