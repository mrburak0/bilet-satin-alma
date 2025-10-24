<?php
require_once __DIR__ . '/../../config/db.php';

echo "<pre>";
echo "DB PATH (db.php): " . htmlspecialchars($db_path) . "\n\n";

$lists = $db->query("PRAGMA database_list")->fetchAll(PDO::FETCH_ASSOC);
echo "PRAGMA database_list:\n"; print_r($lists); echo "\n";

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n"; print_r($tables); echo "</pre>";
