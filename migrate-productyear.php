<?php
/* اجرای یک‌بارهٔ ساخت ستون‌های «سال تولید خودرو» روی محصولات (از/تا، سال شمسی).
   بعد از اجرا روی هاست با up.php به 404 خنثی می‌شود (طبق قرارداد پروژه) —
   نگه نمی‌ماند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$stmts = [
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS year_from SMALLINT NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS year_to SMALLINT NULL",
    "ALTER TABLE products ADD INDEX IF NOT EXISTS idx_year_range (year_from, year_to)",
];

foreach ($stmts as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (Throwable $e) {
        echo "FAIL: $sql -- " . $e->getMessage() . "\n";
    }
}
echo "DONE\n";
