<?php
/* پروبِ موقت — فقط نامِ جدول‌ها و ستون‌ها را چاپ می‌کند (هیچ داده‌ای، هیچ
   مقدارِ محرمانه‌ای). برای ساختنِ صفحهٔ خروجی اکسل/پرینت لازم است. */
if (($_GET['k'] ?? '') !== 'p33x') { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$st = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                     ORDER BY TABLE_NAME, ORDINAL_POSITION");
$st->execute();
$cur = '';
$cols = [];
foreach ($st->fetchAll() as $r) {
    if ($r['TABLE_NAME'] !== $cur) {
        if ($cur !== '') echo $cur . ' (' . count($cols) . "): " . implode(', ', $cols) . "\n\n";
        $cur = $r['TABLE_NAME'];
        $cols = [];
    }
    $cols[] = $r['COLUMN_NAME'] . ':' . $r['DATA_TYPE'];
}
if ($cur !== '') echo $cur . ' (' . count($cols) . "): " . implode(', ', $cols) . "\n\n";

echo "--- row counts ---\n";
foreach ($pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'
                      ORDER BY TABLE_NAME")->fetchAll() as $t) {
    $n = $t['TABLE_NAME'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $n)) continue;
    try { echo $n . ' = ' . (int)$pdo->query("SELECT COUNT(*) FROM `$n`")->fetchColumn() . "\n"; }
    catch (Throwable $e) { echo $n . " = ?\n"; }
}
