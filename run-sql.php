<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = base64_decode($_POST['sql']);
    $dbHost = 'localhost'; $dbName = 'yadaki_db'; $dbUser = 'yadaki_dbuser'; $dbPass = 'R4shAd3AbJnQBJCmfWAq';
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    $ok = 0; $fail = 0;
    foreach ($stmts as $s) {
        try { $pdo->exec($s); $ok++; }
        catch (Exception $e) { $fail++; }
    }
    echo "OK: $ok, FAIL: $fail";
    @unlink(__FILE__);
    exit;
}
echo 'POST sql';