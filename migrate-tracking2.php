<?php
/* مهاجرت «تحویل به پست + پیامکِ مراحل + درگاه اعتباری» — یک‌بار اجرا.
   ---------------------------------------------------------------
   سه چیز اضافه می‌شود:
   1) orders.track_post_at — مرحلهٔ تازهٔ «تحویل به پست» در روند ارسال. بین
      «تحویل به پیک» و «سفارش ارسال شد» می‌نشیند تا مدیر هرکدام (پیک یا پست)
      را که واقعاً انجام شده تیک بزند.
   2) orders.post_tracking_code — کد رهگیریِ مرسولهٔ پست. ادمین با تیک‌زدن
      «تحویل به پست» کادرش را می‌بیند و کد را وارد می‌کند؛ مشتری همان کد را
      در تایم‌لاین می‌بیند.
   3) کلیدهای settings برای اطلاع‌رسانی پیامکیِ مراحل و درگاه اعتباری. همه با
      INSERT IGNORE بذر می‌شوند، پس اجرای دوباره چیزی را بازنویسی نمی‌کند.
      پیش‌فرض‌ها: پیامکِ مراحل روشن (sms_track_enabled=1) اما لایهٔ پیامک خودش
      در حالت آزمایشی است، و درگاه اعتباری خاموش (pay_enable_credit=0) چون
      هنوز قراردادی با ارائه‌دهنده بسته نشده.
   اطلاعات اتصال از includes/config.php خوانده می‌شود (هیچ رمزی در این فایل نیست). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><div style="font:14px tahoma;direction:rtl">';

$ok = 0; $fail = 0;

function mtRun($sql, $label) {
    global $pdo, $ok, $fail;
    try { $pdo->exec($sql); $ok++; echo "✅ $label<br>"; }
    catch (Exception $e) { $fail++; echo "❌ $label — " . htmlspecialchars($e->getMessage()) . "<br>"; }
}

/* بررسی وجود ستون با information_schema و placeholder.
   (روی همین MariaDB، «SHOW COLUMNS ... LIKE ?» با خطای 1064 می‌افتد.) */
function mtHasCol($table, $col) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$table, $col]);
        return ((int)$st->fetchColumn()) > 0;
    } catch (Exception $e) { return false; }
}

/* ---- ۱) ستون‌های تازهٔ orders ---- */
echo '<h3>ستون‌های جدول orders</h3>';
$cols = [
    ['track_post_at',      'DATETIME NULL'],
    ['post_tracking_code', 'VARCHAR(60) NULL'],
];
foreach ($cols as $c) {
    if (mtHasCol('orders', $c[0])) { echo "ℹ️ {$c[0]} از قبل هست<br>"; continue; }
    mtRun("ALTER TABLE orders ADD COLUMN {$c[0]} {$c[1]}", "افزودن ستون {$c[0]}");
}

/* ---- ۲) بذرِ کلیدهای تنظیمات ---- */
echo '<h3>کلیدهای تنظیمات</h3>';
$seed = [
    /* پیامکِ مراحلِ ارسال (بخش ۵) */
    'sms_track_enabled'      => '1',
    'sms_track_confirmed'    => 'سفارش شما با شمارهٔ {order} تأیید شد. {site}',
    'sms_track_collecting'   => 'کالاهای سفارش {order} در حال جمع‌آوری است. {site}',
    'sms_track_finding'      => 'برای سفارش {order} در حال هماهنگی با پیک هستیم. {site}',
    'sms_track_courier'      => 'سفارش {order} به پیک تحویل شد. پیک: {courier}',
    'sms_track_post'         => 'سفارش {order} به پست تحویل شد. کد رهگیری: {code}',
    'sms_track_shipped'      => 'سفارش {order} ارسال شد. از خرید شما سپاسگزاریم. {site}',
    /* درگاه پرداخت اعتباری (بخش ۶) — خاموش تا وقتی قرارداد بسته شود */
    'pay_enable_credit'      => '0',
    'pay_credit_label'       => 'پرداخت اعتباری (اقساطی)',
    'pay_credit_note'        => 'مبلغ سفارش را به‌صورت اعتباری/اقساطی بپردازید. پس از انتخاب، به درگاه ارائه‌دهندهٔ اعتبار می‌روید.',
    'pay_credit_merchant'    => '',
    'pay_credit_create_url'  => '',
    'pay_credit_verify_url'  => '',
    'pay_credit_min'         => '0',
];
try {
    $ins = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    $added = 0;
    foreach ($seed as $k => $v) {
        $ins->execute([$k, $v]);
        if ($ins->rowCount() > 0) $added++;
    }
    $ok++;
    echo "✅ بذرِ تنظیمات — $added کلید تازه اضافه شد (کلیدهای موجود دست‌نخورده)<br>";
} catch (Exception $e) {
    $fail++;
    echo "❌ بذرِ تنظیمات — " . htmlspecialchars($e->getMessage()) . "<br>";
}

/* ---- گزارش ---- */
echo '<h3>نتیجه</h3>';
echo "موفق: <b>$ok</b> — ناموفق: <b>$fail</b><hr>";
echo 'وضعیت ستون‌ها: ';
foreach ($cols as $c) {
    echo $c[0] . ' = ' . (mtHasCol('orders', $c[0]) ? '<b style="color:green">ok</b>' : '<b style="color:red">no</b>') . ' &nbsp; ';
}
echo '<hr><p>اگر همه ok بود، این فایل کارش تمام است. برای بستنِ دسترسی، محتوایش را با
      <code dir="ltr">&lt;?php http_response_code(404); exit;</code> جایگزین کنید.</p>';
echo '<p><a href="admin/orders.php">→ رفتن به سفارشات</a> &nbsp;
      <a href="admin/settings.php?sec=sms">→ تنظیمات پیامک</a> &nbsp;
      <a href="admin/settings.php?sec=pay">→ تنظیمات پرداخت</a></p>';
echo '</div>';
