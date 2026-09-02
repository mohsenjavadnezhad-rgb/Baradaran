<?php
/* اجرای یک‌بارهٔ ساخت ستون «لوگوی برند» روی categories. بعد از اجرا روی
   هاست با up.php به 404 خنثی می‌شود (طبق قرارداد پروژه) — نگه نمی‌ماند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->exec("ALTER TABLE categories ADD COLUMN IF NOT EXISTS logo VARCHAR(500) NULL");
    echo "OK: logo column ready\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
echo "DONE\n";
