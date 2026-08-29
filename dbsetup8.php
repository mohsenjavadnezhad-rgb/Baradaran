<?php
/* ------------------------------------------------------------------
   راه‌اندازی دیتابیس برای «بستهٔ ۸ قابلیت» (batch 8)
   یک‌بارمصرف — پس از اجرا باید با up.php به ۴۰۴ خنثی شود
   (طبق روش استقرار: نه self-delete، نه نامِ حاویِ «migration»).
   اجرا: http://yadakii.ir/dbsetup8.php?key=b8setup9427
   ------------------------------------------------------------------ */
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

if (($_GET['key'] ?? '') !== 'b8setup9427') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$log = [];
function step($label, $fn) {
    global $log;
    try {
        $r = $fn();
        $log[] = ['ok', $label . ($r !== null && $r !== '' ? ' — ' . $r : '')];
    } catch (Throwable $e) {
        $log[] = ['err', $label . ' — ' . $e->getMessage()];
    }
}

/* آیا ستون در جدول وجود دارد؟ (SHOW COLUMNS ... LIKE ? روی این MariaDB خراب است) */
function colExists(PDO $pdo, $table, $col) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $col]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function addCol(PDO $pdo, $table, $col, $def) {
    if (colExists($pdo, $table, $col)) return 'از قبل موجود بود';
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    return 'اضافه شد';
}

/* ---------- ۱) لایهٔ پیگیری روی orders ---------- */
$trackCols = [
    'track_confirmed_at'       => 'DATETIME NULL',
    'track_collecting_at'      => 'DATETIME NULL',
    'track_finding_courier_at' => 'DATETIME NULL',
    'track_courier_at'         => 'DATETIME NULL',
    'track_shipped_at'         => 'DATETIME NULL',
    'courier_name'             => 'VARCHAR(120) NULL',
    'courier_phone'            => 'VARCHAR(30) NULL',
];
foreach ($trackCols as $c => $def) {
    step("orders.$c", function () use ($pdo, $c, $def) { return addCol($pdo, 'orders', $c, $def); });
}

/* ---------- ۲) تگ فروش ویژه روی products ---------- */
step('products.is_special', function () use ($pdo) {
    return addCol($pdo, 'products', 'is_special', 'TINYINT(1) NOT NULL DEFAULT 0');
});

/* ---------- ۳) جدول آفر زمان‌دار (بنر چپ) ---------- */
step('CREATE TABLE timed_offers', function () use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS timed_offers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NULL,
        image VARCHAR(255) NULL,
        title VARCHAR(160) NULL,
        subtitle VARCHAR(200) NULL,
        end_at DATETIME NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_active (is_active, sort_order, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return 'آماده';
});

/* ---------- ۴) جدول نظرات + امتیاز ---------- */
step('CREATE TABLE product_reviews', function () use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        customer_id INT NULL,
        author_name VARCHAR(120) NULL,
        rating TINYINT NOT NULL DEFAULT 5,
        body TEXT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_prod_status (product_id, status, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return 'آماده';
});

/* ---------- ۵) جدول پرسش‌وپاسخ نخ‌دار ---------- */
step('CREATE TABLE product_qa', function () use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_qa (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        parent_id INT NULL,
        customer_id INT NULL,
        is_admin TINYINT(1) NOT NULL DEFAULT 0,
        author_name VARCHAR(120) NULL,
        body TEXT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_prod_parent (product_id, parent_id, status, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return 'آماده';
});

$errors = array_filter($log, function ($r) { return $r[0] === 'err'; });
?><!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>dbsetup8</title>
<style>body{font-family:Tahoma,sans-serif;background:#0f1115;color:#e5e7eb;padding:2rem;line-height:1.9}
.ok{color:#4ade80}.err{color:#f87171}code{background:#1f2430;padding:.1rem .3rem;border-radius:4px}
h1{font-size:1.1rem}li{margin:.2rem 0}</style></head><body>
<h1>راه‌اندازی دیتابیس — بستهٔ ۸ قابلیت</h1>
<ul>
<?php foreach ($log as $r): ?>
  <li class="<?= $r[0] ?>"><?= $r[0] === 'ok' ? '✔' : '✖' ?> <?= htmlspecialchars($r[1], ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
</ul>
<p><?= count($errors) === 0
    ? '<b class="ok">همه‌چیز با موفقیت انجام شد.</b> اکنون این فایل را از طریق up.php به ۴۰۴ خنثی کنید.'
    : '<b class="err">برخی مراحل خطا داشتند — پیام‌ها را بررسی کنید.</b>' ?></p>
</body></html>
