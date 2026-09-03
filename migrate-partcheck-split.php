<?php
/* اجرای یک‌بارهٔ جداکردن «تأیید موجودی» از «بررسی عکس نمونهٔ قطعه» سمت ادمین
   (۲۰۲۶-۰۹-۰۳، خواستهٔ کاربر) — چهار ستون تازه روی part_checks:
     stock_status      — وضعیت مستقل موجودی (pending/approved/rejected)،
                          هم‌الگوی ستون status ولی جدا، تا یک همکار دیگر
                          بتواند فقط همین را در admin/stock-checks.php ببیند.
     stock_reviewed_at — چه‌وقت موجودی داوری شد (جدا از reviewed_at که برای
                          مطابقت عکس است).
     stock_admin_note  — یادداشت همکار موجودی برای مشتری (جدا از admin_note
                          که یادداشت همکار بررسی عکس است) — تا دو همکار
                          هم‌زمان یک ستون را رونویسی نکنند.
     photo_required    — آیا این ردیف اصلا باید در صف «بررسی عکس قطعه» دیده
                          شود؟ ردیف‌های خالص «فقط موجودی» (stockCheckEnsureRow،
                          وقتی بررسی عکس خاموش است) صفر می‌گیرند.
   بک‌فیل: ردیف‌های قدیمی که قبلا با تیک «موجودی را تأیید می‌کنم» تأیید شده
   بودند (stock_ok=1) همان لحظه به stock_status='approved' منتقل می‌شوند تا
   چیزی برای مشتری‌های در جریان عوض نشود.
   بعد از اجرا روی هاست با up.php به 404 خنثی می‌شود (طبق قرارداد پروژه). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

if (!dbHasTableLocal($pdo, 'part_checks')) {
    echo "FAIL: part_checks table not found — run migrate-partcheck.php first.\n";
    exit;
}

$stmts = [
    "ALTER TABLE part_checks ADD COLUMN IF NOT EXISTS stock_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER stock_ok",
    "ALTER TABLE part_checks ADD COLUMN IF NOT EXISTS stock_reviewed_at DATETIME NULL AFTER reviewed_at",
    "ALTER TABLE part_checks ADD COLUMN IF NOT EXISTS stock_admin_note VARCHAR(255) NOT NULL DEFAULT '' AFTER admin_note",
    "ALTER TABLE part_checks ADD COLUMN IF NOT EXISTS photo_required TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE part_checks ADD INDEX IF NOT EXISTS idx_pc_stockstatus (stock_status)",
];

foreach ($stmts as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (Throwable $e) {
        echo "FAIL: $sql -- " . $e->getMessage() . "\n";
    }
}

try {
    $n = $pdo->exec("UPDATE part_checks
                        SET stock_status = 'approved', stock_reviewed_at = reviewed_at, stock_admin_note = admin_note
                      WHERE stock_ok = 1 AND stock_status <> 'approved'");
    echo "OK: backfilled $n previously-approved stock row(s)\n";
} catch (Throwable $e) {
    echo "FAIL (backfill): " . $e->getMessage() . "\n";
}

echo "DONE\n";

/* یک نسخهٔ محلی سبک از dbHasTable — چون includes/functions.php را کامل
   لود نمی‌کنیم (برای سبک‌ماندن اسکریپت مهاجرت، هم‌الگوی migrate-productyear.php). */
function dbHasTableLocal($pdo, $table) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->execute([$table]);
        return ((int)$st->fetchColumn()) > 0;
    } catch (Throwable $e) { return false; }
}
