<?php
/* مهاجرت «تأیید عکس نمونهٔ قطعه» — یک‌بار اجرا و سپس خودحذف.
   ---------------------------------------------------------------
   دو جدول ساخته می‌شود:
   1) part_checks     — یک ردیف برای هر درخواستِ بررسیِ عکس. پیش از ثبتِ سفارش
      ساخته می‌شود، پس در آن لحظه order_id خالی است و بعد از ثبتِ سفارش پر
      می‌شود تا در «جزئیات سفارش» ادمین دیده شود.
   2) part_check_images — عکس‌های هر درخواست (حداقل سه زاویهٔ مختلف).
   به‌علاوه سه کلیدِ تنظیمات (کلیدِ خاموش‌کردنِ کل مرحله، حداقل تعداد عکس، و
   متنِ دلخواهِ بالای صفحه) و پوشهٔ uploads/partchecks/.
   اطلاعات اتصال از includes/config.php خوانده می‌شود (هیچ رمزی در این فایل نیست). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (($_GET['k'] ?? '') !== 'pchk7v') { http_response_code(404); exit; }

header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><div style="font:14px tahoma;direction:rtl;padding:1rem;line-height:2;">';

$stmts = [
    /* درخواستِ بررسی. cart_sig امضای کالاهای سبد در لحظهٔ آپلود است تا اگر
       مشتری سبد را عوض کرد، تأییدِ قبلی به قطعهٔ دیگری منتقل نشود. */
    "CREATE TABLE IF NOT EXISTS part_checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        order_id INT NULL,
        product_id INT NULL,
        cart_sig VARCHAR(40) NOT NULL DEFAULT '',
        car_info VARCHAR(160) NOT NULL DEFAULT '',
        note TEXT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_note VARCHAR(255) NOT NULL DEFAULT '',
        stock_ok TINYINT(1) NOT NULL DEFAULT 0,
        stock_note VARCHAR(160) NOT NULL DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        INDEX idx_pc_cust (customer_id),
        INDEX idx_pc_order (order_id),
        INDEX idx_pc_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    /* عکس‌ها — همان الگوی product_images */
    "CREATE TABLE IF NOT EXISTS part_check_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        check_id INT NOT NULL,
        image VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pci_check (check_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$ok = 0; $fail = 0;
foreach ($stmts as $s) {
    try { $pdo->exec($s); $ok++; }
    catch (Exception $e) { echo 'ERR: ' . htmlspecialchars($e->getMessage()) . '<br>'; $fail++; }
}

/* کلیدهای پیش‌فرضِ تنظیمات */
$ins = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
foreach ([
    ['partcheck_enabled',     '1'],   // کلیدِ خاموش‌کردنِ کل این مرحله
    ['partcheck_min_photos',  '3'],   // حداقل تعداد عکس از زوایای مختلف
    ['partcheck_notice',      ''],    // متنِ دلخواهِ بالای صفحه (خالی = متنِ پیش‌فرضِ کد)
] as $d) {
    try { $ins->execute($d); $ok++; } catch (Exception $e) { $fail++; }
}

/* پوشهٔ عکس‌های مشتری */
$dir = __DIR__ . '/uploads/partchecks/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

echo '<b>مهاجرت تأیید عکس قطعه انجام شد.</b> موفق: ' . $ok . ' — ناموفق: ' . $fail . '<br>';
echo 'جدول part_checks: '       . (dbHasTable('part_checks')       ? '✅ ساخته شد' : '❌ ساخته نشد') . '<br>';
echo 'جدول part_check_images: ' . (dbHasTable('part_check_images') ? '✅ ساخته شد' : '❌ ساخته نشد') . '<br>';
echo 'پوشهٔ uploads/partchecks: ' . (is_dir($dir) ? '✅ آماده' : '❌ ساخته نشد')
   . (is_dir($dir) ? (is_writable($dir) ? ' (نوشتنی)' : ' <b>ولی نوشتنی نیست</b>') : '') . '<br>';
echo '</div>';

@unlink(__FILE__);
