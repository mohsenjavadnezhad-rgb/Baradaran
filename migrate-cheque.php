<?php
/* اجرای یک‌بارهٔ ساخت ستون‌های چک/پرداخت‌های ویژهٔ همکاران. بعد از اجرا روی
   هاست با up.php به 404 خنثی می‌شود (طبق قرارداد پروژه) — نگه نمی‌ماند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

$stmts = [
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_bank VARCHAR(120) NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_number VARCHAR(60) NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_date VARCHAR(40) NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_amount INT NOT NULL DEFAULT 0",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_sayad VARCHAR(60) NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_payee VARCHAR(120) NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_reported_at DATETIME NULL",
    "ALTER TABLE orders ADD COLUMN IF NOT EXISTS cheque_received_at DATETIME NULL",
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
