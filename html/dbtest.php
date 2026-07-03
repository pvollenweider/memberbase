<?php
$pdo = new PDO('mysql:host=mariadb;dbname=members', 'members', 'members');
$r = $pdo->query('SHOW COLUMNS FROM users LIKE \'email_alt\'');
var_dump($r->fetchAll(PDO::FETCH_ASSOC));
$r2 = $pdo->query('SELECT email_alt FROM users LIMIT 1');
var_dump($r2->fetchAll());
