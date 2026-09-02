<?php
/* اجرای یک‌بارهٔ ساخت ستون‌های مالیات. بعد از اجرا روی هاست با up.php به
   404 خنثی می‌شود (طبق قرارداد پروژه) — نگه نمی‌ماند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$stmts = [
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS tax_enabled TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0",
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0",
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS tax_amount INT NOT NULL DEFAULT 0",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax_total INT NOT NULL DEFAULT 0",
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
