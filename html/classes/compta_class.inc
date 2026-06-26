<?php
class Compta
{
    public $id;
    public $userId;
    public $type_id;
    public $date;
    public $libele;
    public $sum;
    public $quittance;
    public $wants_attestation = 0;

    public function __construct()
    {
    }

    public function lookupCompta(int $id): void
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id,user_id,type_id,date,libele,sum,quittance,wants_attestation FROM compta WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetchObject();
        if ($row) {
            $this->id                = $row->id;
            $this->userId            = $row->user_id;
            $this->type_id           = $row->type_id;
            $this->date              = $row->date;
            $this->libele            = $row->libele;
            $this->sum               = $row->sum;
            $this->quittance         = $row->quittance;
            $this->wants_attestation = (int)$row->wants_attestation;
        }
    }

    public function getId()        { return $this->id; }
    public function getUserId()    { return $this->userId; }
    public function getTypeId()    { return $this->type_id; }
    public function getDate()      { return $this->date; }
    public function getLibele()    { return $this->libele; }
    public function getSum()       { return $this->sum; }
    public function getQuittance()        { return $this->quittance; }
    public function getWantsAttestation() { return $this->wants_attestation; }

    public function setUserId($v)    { $this->userId = $v; }
    public function setTypeId($v)    { $this->type_id = (int)$v; }
    public function setDate($v)      { $this->date = $v; }
    public function setlibele($v)    { $this->libele = $v; }
    public function setSum($v)       { $this->sum = $v; }
    public function setQuittance($v)       { $this->quittance = $v; }
    public function setWantsAttestation($v){ $this->wants_attestation = $v ? 1 : 0; }

    public function save(): void
    {
        global $pdo;
        if ($this->id) {
            $pdo->prepare(
                "UPDATE compta SET user_id=?,type_id=?,date=?,libele=?,sum=?,quittance=?,wants_attestation=? WHERE id=?"
            )->execute([
                $this->userId, $this->type_id, $this->date, $this->libele,
                $this->sum, $this->quittance, $this->wants_attestation, $this->id,
            ]);
        } else {
            $comptaid = updateAndGetMaxVal("comptaid");
            $pdo->prepare(
                "INSERT INTO compta (id,user_id,type_id,date,libele,sum,quittance,wants_attestation) VALUES (?,?,?,?,?,?,?,?)"
            )->execute([
                $comptaid, $this->userId, $this->type_id, $this->date,
                $this->libele, $this->sum, $this->quittance, $this->wants_attestation,
            ]);
        }
    }

    public function remove(): void
    {
        global $pdo;
        $pdo->prepare("DELETE FROM compta WHERE id=?")->execute([$this->id]);
    }
}
