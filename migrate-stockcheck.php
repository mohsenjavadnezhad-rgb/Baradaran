<?php
/* اجرای یک‌بارهٔ ساخت ستون «بررسی موجودی» (اولین مرحلهٔ روند ارسال). بعد از
   اجرا روی هاست با up.php به 404 خنثی می‌شود (طبق قرارداد پروژه) — نگه نمی‌ماند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS track_stock_at DATETIME NULL");
    echo "OK: track_stock_at\n";
} catch (Throwable $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}

/* بک‌فیل سفارش‌های موجود: این گیت نباید سفارش‌هایی را قفل کند که پیش از
   وجود این قابلیت، طبق روال قبلی (پرداخت بی‌درنگ) ثبت شده‌اند — وگرنه هر
   سفارش آنلاین نیمه‌کاره و هر سفارش در جریان، ناگهان قفل می‌شد. پس مهر
   زمان ثبت خود سفارش را می‌گذاریم؛ گیت فقط سفارش‌های از این لحظه به بعد
   را می‌بندد. */
try {
    $n = $pdo->exec("UPDATE orders SET track_stock_at = created_at WHERE track_stock_at IS NULL");
    echo "OK: backfilled $n existing order(s)\n";
} catch (Throwable $e) {
    echo "FAIL (backfill): " . $e->getMessage() . "\n";
}
echo "DONE\n";
