<?php
define('APP_ENTRY', true);
require_once __DIR__ . '/includes/lib/bootstrap.php';
$r = $pdo->query('SELECT DATABASE()');
var_dump($r->fetchColumn());
$r2 = $pdo->query('SHOW COLUMNS FROM contact LIKE \'email_alt\'');
var_dump($r2->fetchAll(PDO::FETCH_ASSOC));
