<?php
/* مهاجرت «نرخ ارسال بر پایهٔ شهر و وزن» + «کارت به کارت» — یک‌بار اجرا و بعد خنثی.
   ---------------------------------------------------------------------------
   چهار کار:
   1) `shipping_rates.weight_unit` — معنای نرخ‌نامه از «پله‌ای» به «به‌ازای هر
      واحد وزن» تغییر می‌کند: هر ردیف می‌گوید «هر {weight_unit} کیلوگرم،
      {cost} تومان». وزن سبد بر واحد تقسیم و به بالا رند می‌شود
      (۳ کیلو با نرخ ۵۰٬۰۰۰ برای هر ۱ کیلو ⇒ ۱۵۰٬۰۰۰).
      ردیف‌های قدیمی پله‌ای هم منتقل می‌شوند (weight_to ⇒ weight_unit).
   2) کلید یکتا روی (method_key, city_norm) — برای هر روش، هر شهر فقط یک ردیف.
      پس فرم ادمین «افزودن» می‌تواند upsert کند و هیچ‌وقت دو نرخ متناقض
      برای یک شهر پیش نمی‌آید.
   3) جدول `cities` — فهرست آمادهٔ شهرها (مراکز استان + شهرهای بزرگ) تا مدیر
      شهر را از یک انتخابگر بردارد، نه با تایپ. افزودن دستی هم ممکن است.
   4) ستون‌های «کارت به کارت» روی orders — مشتری شناسهٔ واریز، مبلغ، چهار رقم
      آخر کارت مبدأ و زمان واریز را ثبت می‌کند و سفارش «در انتظار تأیید واریز»
      می‌ماند تا مدیر تأیید کند.
   اطلاعات اتصال از includes/config.php خوانده می‌شود (هیچ رمزی در این فایل نیست). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');
echo '<meta charset="utf-8"><div style="font:14px tahoma;direction:rtl">';

$ok = 0; $fail = 0;

function crRun($sql, $label = '') {
    global $pdo, $ok, $fail;
    try { $pdo->exec($sql); $ok++; return true; }
    catch (Throwable $e) {
        echo 'ERR ' . htmlspecialchars($label) . ': ' . htmlspecialchars($e->getMessage()) . '<br>';
        $fail++; return false;
    }
}

/* آیا ستون هست؟ روی این MariaDB باید از information_schema با placeholder
   استفاده شود؛ «SHOW COLUMNS ... LIKE ?» خطای ۱۰۶۴ می‌دهد. */
function crHasCol($table, $col) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$table, $col]);
        return ((int)$st->fetchColumn() > 0);
    } catch (Throwable $e) { return false; }
}

function crHasIndex($table, $index) {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $st->execute([$table, $index]);
        return ((int)$st->fetchColumn() > 0);
    } catch (Throwable $e) { return false; }
}

/* ===================== ۱) واحد وزن روی نرخ‌نامه ===================== */
$hadUnit = crHasCol('shipping_rates', 'weight_unit');
if (!dbHasTable('shipping_rates')) {
    echo 'ERR: جدول shipping_rates وجود ندارد — ابتدا مهاجرت قبلی را اجرا کنید.<br>';
    $fail++;
} else {
    if (!$hadUnit) {
        crRun("ALTER TABLE shipping_rates ADD COLUMN weight_unit DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER city_norm", 'add weight_unit');
        /* ردیف‌های پله‌ای قبلی: «تا N کیلو» ⇒ «هر N کیلو». نزدیک‌ترین معنا
           به داده‌ای که مدیر قبلا وارد کرده بوده. */
        crRun("UPDATE shipping_rates SET weight_unit = weight_to WHERE weight_to > 0", 'backfill weight_unit');
    } else {
        echo 'weight_unit از قبل بود.<br>';
    }
    crRun("UPDATE shipping_rates SET weight_unit = 1.00 WHERE weight_unit IS NULL OR weight_unit <= 0", 'fix weight_unit');

    /* ===================== ۲) هر شهر یک ردیف ===================== */
    if (!crHasIndex('shipping_rates', 'uk_rate_method_city')) {
        /* اول ردیف‌های تکراری (روش + شهر) پاک شوند، وگرنه ساخت کلید یکتا
           شکست می‌خورد. کوچک‌ترین id می‌ماند. DELETE ... IN (...) با آرایهٔ
           صریح، تا به خطای «can't reopen table» نخوریم. */
        try {
            $dupIds = $pdo->query("SELECT r.id FROM shipping_rates r
                                   JOIN (SELECT method_key, city_norm, MIN(id) AS keep_id
                                         FROM shipping_rates GROUP BY method_key, city_norm) k
                                     ON k.method_key = r.method_key AND k.city_norm = r.city_norm
                                   WHERE r.id > k.keep_id")->fetchAll(PDO::FETCH_COLUMN);
            if ($dupIds) {
                $in = implode(',', array_map('intval', $dupIds));
                $pdo->exec("DELETE FROM shipping_rates WHERE id IN ($in)");
                echo 'ردیف‌های تکراری پاک شد: ' . count($dupIds) . '<br>';
            }
            $ok++;
        } catch (Throwable $e) { echo 'ERR dedupe: ' . htmlspecialchars($e->getMessage()) . '<br>'; $fail++; }

        crRun("ALTER TABLE shipping_rates ADD UNIQUE KEY uk_rate_method_city (method_key, city_norm)", 'unique method+city');
    } else {
        echo 'کلید یکتای روش+شهر از قبل بود.<br>';
    }
}

/* ===================== ۳) جدول شهرها ===================== */
crRun("CREATE TABLE IF NOT EXISTS cities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        name_norm VARCHAR(80) NOT NULL DEFAULT '',
        province VARCHAR(60) NOT NULL DEFAULT '',
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_city_name (name),
        INDEX idx_city_norm (name_norm)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", 'create cities');

/* مراکز استان (اول هر استان) + شهرهای بزرگ. مدیر می‌تواند شهر تازه هم
   دستی اضافه کند، پس این فهرست «شروع» است نه «سقف». */
$provinces = [
    'آذربایجان شرقی'          => ['تبریز','مراغه','مرند','اهر','میانه','بناب','شبستر','سراب','آذرشهر','هادی‌شهر'],
    'آذربایجان غربی'          => ['ارومیه','خوی','میاندوآب','مهاباد','بوکان','سلماس','نقده','پیرانشهر','ماکو','شاهین‌دژ'],
    'اردبیل'                  => ['اردبیل','پارس‌آباد','مشکین‌شهر','خلخال','گرمی','بیله‌سوار'],
    'اصفهان'                  => ['اصفهان','کاشان','خمینی‌شهر','نجف‌آباد','شهرضا','شاهین‌شهر','فولادشهر','زرین‌شهر','مبارکه','گلپایگان','اردستان','نطنز','سمیرم'],
    'البرز'                   => ['کرج','فردیس','هشتگرد','نظرآباد','اشتهارد','ماهدشت'],
    'ایلام'                   => ['ایلام','دهلران','آبدانان','ایوان','مهران'],
    'بوشهر'                   => ['بوشهر','برازجان','بندر گناوه','خورموج','بندر دیر','عسلویه','بندر کنگان'],
    'تهران'                   => ['تهران','اسلامشهر','شهریار','ورامین','پاکدشت','قرچک','رباط‌کریم','دماوند','پردیس','لواسان','فیروزکوه','شهر قدس','بومهن'],
    'چهارمحال و بختیاری'      => ['شهرکرد','بروجن','فارسان','لردگان','سامان'],
    'خراسان جنوبی'            => ['بیرجند','قائن','طبس','فردوس','نهبندان','سربیشه'],
    'خراسان رضوی'             => ['مشهد','نیشابور','سبزوار','تربت حیدریه','قوچان','کاشمر','تربت جام','گناباد','چناران','فریمان','طرقبه','بردسکن','خواف','سرخس','درگز','تایباد','فیروزه','گلبهار','بینالود'],
    'خراسان شمالی'            => ['بجنورد','شیروان','اسفراین','آشخانه','فاروج'],
    'خوزستان'                 => ['اهواز','آبادان','خرمشهر','دزفول','اندیمشک','بهبهان','بندر ماهشهر','ایذه','شوشتر','مسجدسلیمان','شوش','رامهرمز','امیدیه','هندیجان'],
    'زنجان'                   => ['زنجان','ابهر','خرمدره','قیدار','خدابنده'],
    'سمنان'                   => ['سمنان','شاهرود','گرمسار','دامغان','مهدی‌شهر','ایوانکی'],
    'سیستان و بلوچستان'       => ['زاهدان','زابل','ایرانشهر','چابهار','سراوان','خاش','کنارک','نیک‌شهر'],
    'فارس'                    => ['شیراز','مرودشت','کازرون','جهرم','فسا','داراب','لار','آباده','اقلید','صدرا','نی‌ریز','زرقان','سپیدان'],
    'قزوین'                   => ['قزوین','آبیک','تاکستان','الوند','بوئین‌زهرا'],
    'قم'                      => ['قم'],
    'کردستان'                 => ['سنندج','سقز','مریوان','بانه','قروه','بیجار','کامیاران'],
    'کرمان'                   => ['کرمان','رفسنجان','سیرجان','جیرفت','بم','زرند','کهنوج','شهربابک','بردسیر'],
    'کرمانشاه'                => ['کرمانشاه','اسلام‌آباد غرب','هرسین','سنقر','کنگاور','پاوه','جوانرود','صحنه'],
    'کهگیلویه و بویراحمد'     => ['یاسوج','دوگنبدان','دهدشت','سی‌سخت'],
    'گلستان'                  => ['گرگان','گنبد کاووس','علی‌آباد کتول','آق‌قلا','کردکوی','بندر ترکمن','آزادشهر','مینودشت','گمیشان'],
    'گیلان'                   => ['رشت','بندر انزلی','لاهیجان','لنگرود','تالش','آستارا','صومعه‌سرا','رودسر','فومن','آستانه اشرفیه','رودبار','ماسال'],
    'لرستان'                  => ['خرم‌آباد','بروجرد','دورود','الیگودرز','نورآباد','کوهدشت','ازنا','پلدختر'],
    'مازندران'                => ['ساری','بابل','آمل','قائم‌شهر','نوشهر','چالوس','بهشهر','تنکابن','رامسر','محمودآباد','نور','فریدونکنار','بابلسر','جویبار','نکا','سوادکوه'],
    'مرکزی'                   => ['اراک','ساوه','خمین','محلات','دلیجان','شازند','تفرش'],
    'هرمزگان'                 => ['بندرعباس','میناب','بندر لنگه','قشم','کیش','بندر خمیر','رودان','پارسیان'],
    'همدان'                   => ['همدان','ملایر','نهاوند','تویسرکان','اسدآباد','کبودرآهنگ','بهار'],
    'یزد'                     => ['یزد','میبد','اردکان','بافق','مهریز','تفت','ابرکوه'],
];

$cityAdded = 0;
if (dbHasTable('cities')) {
    try {
        $ins = $pdo->prepare("INSERT IGNORE INTO cities (name, name_norm, province, sort_order, is_active)
                              VALUES (?,?,?,?,1)");
        $p = 0;
        foreach ($provinces as $prov => $list) {
            $i = 0;
            foreach ($list as $cty) {
                $ins->execute([$cty, shipNormCity($cty), $prov, $p * 100 + $i]);
                $cityAdded += $ins->rowCount();
                $i++;
            }
            $p++;
        }
        $ok++;
    } catch (Throwable $e) { echo 'ERR seed cities: ' . htmlspecialchars($e->getMessage()) . '<br>'; $fail++; }

    /* اگر ردیفی name_norm خالی داشت (افزودهٔ دستی قدیمی) پرش کن */
    try {
        $rows = $pdo->query("SELECT id, name FROM cities WHERE name_norm = ''")->fetchAll();
        if ($rows) {
            $up = $pdo->prepare("UPDATE cities SET name_norm = ? WHERE id = ?");
            foreach ($rows as $r) $up->execute([shipNormCity($r['name']), (int)$r['id']]);
        }
        $ok++;
    } catch (Throwable $e) { $fail++; }
}

/* ===================== ۴) کارت به کارت روی سفارش‌ها ===================== */
foreach ([
    ['c2c_ref',         "VARCHAR(60) NULL"],       // شناسهٔ واریز / شمارهٔ پیگیری
    ['c2c_amount',      "DECIMAL(15,0) NULL"],     // مبلغ واریزی به تومان
    ['c2c_last4',       "VARCHAR(8) NULL"],        // چهار رقم آخر کارت مبدأ
    ['c2c_paid_text',   "VARCHAR(60) NULL"],       // زمان واریز، همان‌طور که مشتری نوشته
    ['c2c_reported_at', "DATETIME NULL"],          // چه وقت مشتری فرم را فرستاد
    ['c2c_verified_at', "DATETIME NULL"],          // چه وقت مدیر تأیید کرد
] as $c) {
    if (crHasCol('orders', $c[0])) continue;
    crRun("ALTER TABLE orders ADD COLUMN {$c[0]} {$c[1]}", 'add orders.' . $c[0]);
}

/* ===================== کلیدهای تنظیمات ===================== */
try {
    $st = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ([
        ['pay_enable_card', '1'],   // «کارت به کارت» در فهرست روش‌های پرداخت
        ['pay_c2c_note',    ''],    // توضیح اختیاری بالای فرم ثبت واریز
    ] as $d) { $st->execute($d); }
    $ok++;
} catch (Throwable $e) { $fail++; }

/* ===================== گزارش ===================== */
echo '<hr>پایان مهاجرت. موفق: ' . (int)$ok . ' — ناموفق: ' . (int)$fail . '<br>';
echo 'ستون weight_unit: ' . (crHasCol('shipping_rates', 'weight_unit') ? 'هست' : 'نیست') . '<br>';
echo 'کلید یکتای روش+شهر: ' . (crHasIndex('shipping_rates', 'uk_rate_method_city') ? 'هست' : 'نیست') . '<br>';
echo 'جدول شهرها: ' . (dbHasTable('cities') ? 'هست' : 'نیست') . ' — شهرهای تازه‌افزوده: ' . (int)$cityAdded;
try { echo ' — کل شهرها: ' . (int)$pdo->query("SELECT COUNT(*) FROM cities")->fetchColumn(); } catch (Throwable $e) {}
echo '<br>ستون‌های کارت به کارت: ';
foreach (['c2c_ref','c2c_amount','c2c_last4','c2c_paid_text','c2c_reported_at','c2c_verified_at'] as $c) {
    echo $c . '=' . (crHasCol('orders', $c) ? 'ok' : 'no') . ' ';
}
echo '<br>ردیف‌های نرخ‌نامه: ';
try { echo (int)$pdo->query("SELECT COUNT(*) FROM shipping_rates")->fetchColumn(); } catch (Throwable $e) { echo '?'; }
echo '</div>';
