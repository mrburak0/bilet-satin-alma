<?php
$db_path = realpath(__DIR__ . '/../database') . '/database.sqlite';
error_log("DB PATH = " . $db_path); // GEÇİCİ: sonra silebilirsin
try {
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    die('DB connection error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
