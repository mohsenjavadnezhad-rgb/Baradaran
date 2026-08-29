<?php
echo "<html dir='rtl'><head><meta charset='utf-8'><style>
body { font-family: Tahoma; background: #111827; color: #F9FAFB; padding: 2rem; }
.success { color: #10B981; } .error { color: #EF4444; }
</style></head><body>";

// Try to connect to MySQL without selecting a DB to find correct user
$host = 'localhost';
$pass = 'R4shAd3AbJnQBJCmfWAq';

// Common username patterns in DirectAdmin
$users = [
    'yadaki_dbuser',
    'yadaki__0O6R1kA2KqpIA4fJBfL2IoGi7qZl2AV6',
];

$db = 'yadaki_db';

foreach ($users as $u) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $u, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        echo "<p class='success'>✓ User: <strong>$u</strong> — CONNECTED to $db</p>";
        $t = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p class='success'>Tables: " . implode(', ', $t ?: ['none']) . "</p>";
        $pdo = null;
    } catch (Exception $e) {
        echo "<p class='error'>✗ $u — " . $e->getMessage() . "</p>";
    }
}

// Try to just connect without DB and list available DBs
foreach ($users as $u) {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $u, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        echo "<p class='success'>✓ User: <strong>$u</strong> — connected to MySQL (no DB)</p>";
        $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Databases: " . implode(', ', $dbs) . "</p>";
        $pdo = null;
    } catch (Exception $e) {
        echo "<p class='error'>✗ $u — " . $e->getMessage() . "</p>";
    }
}

echo "</body></html>";