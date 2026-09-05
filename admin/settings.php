<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

/* هر بخش تنظیمات صفحهٔ جداگانهٔ خودش را دارد (settings.php?sec=...) و از منوی
   کشویی «تنظیمات سایت» در سایدبار باز می‌شود. فهرست بخش‌ها در
   includes/functions.php → settingsSections() است تا سایدبار و این صفحه یکی بمانند. */
$sections = settingsSections();
$sec = settingsSectionKey($_POST['sec'] ?? $_GET['sec'] ?? '');

/* کلیدهای متنی هر بخش — هنگام ذخیره فقط کلیدهای همان بخش ارسال‌شده نوشته
   می‌شوند. اگر مثل قبل روی همه کلیدها حلقه بزنیم، بخش‌هایی که در این صفحه
   رندر نشده‌اند (و در POST نیستند) خالی می‌شوند. */
$secTextKeys = [
    'footer' => [
        'footer_about', 'footer_copyright',
        'contact_phones', 'contact_mobiles', 'contact_email', 'contact_address', 'working_hours',
        'social_instagram', 'social_telegram', 'social_whatsapp',
        'social_twitter', 'social_bale', 'social_gap', 'social_rubika', 'social_eitaa',
    ],
    'decor'  => [],
    'sms'    => [
        'sms_api_key', 'sms_template_id', 'sms_param_name', 'sms_line_number',
        /* متن پیامک هر مرحلهٔ روند ارسال — خالی‌گذاشتن هرکدام = پیامک آن مرحله خاموش */
        'sms_track_stock', 'sms_track_confirmed', 'sms_track_collecting', 'sms_track_finding',
        'sms_track_courier', 'sms_track_post', 'sms_track_shipped',
    ],
    'pay'    => [
        'pay_desc', 'pay_cod_note', 'pay_card_holder', 'pay_card_bank', 'pay_card_note', 'pay_c2c_note',
        'pay_zarinpal_merchant', 'pay_zibal_merchant', 'pay_idpay_key',
        /* پیغام تأیید سفارش چکی — pay_cheque_deadline_days رقمی است و پایین‌تر جدا پاک‌سازی می‌شود */
        'pay_cheque_note',
    ],
    /* پرداخت اعتباری/اقساطی — بخش جداگانه، چون سرویس‌دهندهٔ متفاوتی دارد
       (BNPL، نه دروازهٔ بانکی) و کنار «درگاه پرداخت» شلوغش می‌کرد.
       تا وقتی pay_enable_credit خاموش است هیچ‌کجا دیده نمی‌شود.
       (pay_credit_min رقمی است و پایین‌تر جدا پاک‌سازی می‌شود.) */
    'paycredit' => [
        'pay_credit_label', 'pay_credit_note', 'pay_credit_merchant',
        'pay_credit_create_url', 'pay_credit_verify_url',
    ],
    'ship'   => ['ship_desc', 'ship_barbari_desc'],
    /* نرخ‌نامه بخش جداگانه‌ای است (settings.php?sec=shiprate) */
    'shiprate' => ['ship_rate_note'],
    /* بررسی عکس نمونهٔ قطعه (partcheck_min_photos عددی است و پایین‌تر جدا پاک‌سازی می‌شود) */
    'pchk'   => ['partcheck_notice'],
    /* شرایط و قوانین — یک متن آزاد بلند، همان الگوی partcheck_notice */
    'terms'  => ['terms_content'],
];

$saved = false;
/* فقط فرم اصلی همین بخش این پرچم را می‌فرستد. فرم‌های کوچک «افزودن روش
   ارسال» و «نرخ‌نامه» (پایین همین صفحه) آن را ندارند، وگرنه حلقهٔ بالا
   کلیدهای متنی بخش را که در آن فرم‌ها نیستند خالی می‌کرد. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_section'])) {
    foreach ($secTextKeys[$sec] as $k) {
        setSetting($k, trim((string)($_POST[$k] ?? '')));
    }

    if ($sec === 'decor') {
        setSetting('home_decor_enabled', isset($_POST['home_decor_enabled']) ? '1' : '0');
        $decorAllowed = ['auto', 'gears', 'tools', 'tire', 'car', 'road', 'engine', 'neon', 'float'];
        $decorStyle = in_array(($_POST['home_decor_style'] ?? 'auto'), $decorAllowed, true) ? $_POST['home_decor_style'] : 'auto';
        setSetting('home_decor_style', $decorStyle);
    }

    if ($sec === 'sms') {
        $method = (($_POST['sms_method'] ?? 'bulk') === 'verify') ? 'verify' : 'bulk';
        setSetting('sms_method', $method);

        $otpText = trim((string)($_POST['sms_otp_text'] ?? ''));
        if ($otpText === '') $otpText = 'کد تأیید شما: {code}';
        if (strpos($otpText, '{code}') === false) $otpText .= ' {code}';
        setSetting('sms_otp_text', $otpText);

        setSetting('sms_test_mode', isset($_POST['sms_test_mode']) ? '1' : '0');

        /* تأیید پیامکی برای ورود مشتریان — خاموش‌کردنش یعنی ورود فقط با شمارهٔ
           موبایل. چون چک‌باکس خاموش هیچ مقداری POST نمی‌کند و این بلوک فقط
           وقتی اجرا می‌شود که همین بخش ذخیره شده باشد، نبود کلید = خاموش. */
        setSetting('login_otp_required', isset($_POST['login_otp_required']) ? '1' : '0');

        /* کلید اصلی اطلاع‌رسانی مراحل ارسال: اگر پنل پیامک شارژ نداشت، همین
           یک تیک برداشته می‌شود و هیچ پیامکی برای مراحل ارسال نمی‌رود. */
        setSetting('sms_track_enabled', isset($_POST['sms_track_enabled']) ? '1' : '0');
    }

    if ($sec === 'pay') {
        setSetting('pay_test_mode',    isset($_POST['pay_test_mode'])    ? '1' : '0');
        setSetting('pay_enable_cod',   isset($_POST['pay_enable_cod'])   ? '1' : '0');
        setSetting('pay_enable_card',  isset($_POST['pay_enable_card'])  ? '1' : '0');
        setSetting('pay_enable_online',isset($_POST['pay_enable_online'])? '1' : '0');

        /* درگاه انتخابی فقط از میان درگاه‌های آنلاین «عادی» پذیرفته می‌شود.
           درگاه اعتباری (credit_only) گزینهٔ جداگانه‌ای است با تیک خودش و
           نباید جای درگاه بانکی اصلی را بگیرد. */
        $onlineKeys = [];
        foreach (paymentGateways() as $gk => $gd) {
            if ($gd['kind'] === 'online' && empty($gd['credit_only'])) $onlineKeys[] = $gk;
        }
        $gwSel = (string)($_POST['pay_gateway'] ?? '');
        setSetting('pay_gateway', in_array($gwSel, $onlineKeys, true) ? $gwSel : 'sim');

        setSetting('pay_unit', (($_POST['pay_unit'] ?? 'rial') === 'toman') ? 'toman' : 'rial');

        /* اعداد ممکن است فارسی تایپ شوند */
        $minAmt = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['pay_min_amount'] ?? '')));
        setSetting('pay_min_amount', $minAmt === '' ? '1000' : $minAmt);

        $pan = faToLatinDigits((string)($_POST['pay_card_number'] ?? ''));
        $pan = trim(preg_replace('/[^0-9\- ]/', '', $pan));
        setSetting('pay_card_number', $pan);

        /* دو روش ویژهٔ همکاران — پیش‌فرض روشن (چون خودشان از قبل به
           همکار تأییدشده محدودند، نیازی به یک تیک اضافه برای «فعال‌سازی
           عمومی» نیست، ولی مدیر می‌تواند اینجا خاموششان کند). */
        setSetting('pay_enable_partner_month', isset($_POST['pay_enable_partner_month']) ? '1' : '0');
        setSetting('pay_enable_cheque',        isset($_POST['pay_enable_cheque'])        ? '1' : '0');

        $chqDays = (int)faToLatinDigits((string)($_POST['pay_cheque_deadline_days'] ?? '10'));
        setSetting('pay_cheque_deadline_days', (string)max(1, min(90, $chqDays ?: 10)));

        /* عکس نمونهٔ چک — زیر کادر «اطلاعات چک» در تسویه‌حساب/صفحهٔ سفارش
           نشان داده می‌شود (خواستهٔ کاربر). حذف عکس فعلی با تیک جداگانه،
           چون input[type=file] خودش نمی‌تواند «خالی» بفرستد. */
        if (isset($_POST['cheque_sample_remove'])) {
            $old = trim((string)getSettingRaw('pay_cheque_sample', ''));
            if ($old !== '') { $op = __DIR__ . '/../uploads/settings/' . $old; if (is_file($op)) @unlink($op); }
            setSetting('pay_cheque_sample', '');
        } elseif (!empty($_FILES['cheque_sample']['name']) && $_FILES['cheque_sample']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['cheque_sample']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ALLOWED_EXTENSIONS, true) && $_FILES['cheque_sample']['size'] <= MAX_UPLOAD_SIZE) {
                $dir = __DIR__ . '/../uploads/settings/';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $newName = 'cheque_sample_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['cheque_sample']['tmp_name'], $dir . $newName)) {
                    $old = trim((string)getSettingRaw('pay_cheque_sample', ''));
                    if ($old !== '' && $old !== $newName) { $op = $dir . $old; if (is_file($op)) @unlink($op); }
                    setSetting('pay_cheque_sample', $newName);
                }
            }
        }
    }

    if ($sec === 'paycredit') {
        /* پرداخت اعتباری/اقساطی: پیش‌فرض خاموش. تا وقتی قرارداد با ارائه‌دهنده
           بسته نشده، این تیک برداشته می‌ماند و مشتری اصلا آن را نمی‌بیند. */
        setSetting('pay_enable_credit', isset($_POST['pay_enable_credit']) ? '1' : '0');
        $crMin = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['pay_credit_min'] ?? '')));
        setSetting('pay_credit_min', $crMin === '' ? '0' : $crMin);
    }

    if ($sec === 'ship') {
        /* هزینهٔ باربری فقط رقم ذخیره می‌شود (ممکن است فارسی تایپ شود) و
           اطلاع‌رسانی است؛ مبلغی که به سفارش اضافه می‌شود از نرخ‌نامه می‌آید. */
        $barbari = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['ship_barbari_cost'] ?? '')));
        setSetting('ship_barbari_cost', $barbari === '' ? '0' : $barbari);

        /* ویرایش گروهی روش‌ها. اگر جدول ساخته نشده باشد به همان کلیدهای
           قدیمی settings برمی‌گردیم تا صفحه بی‌کار نماند.
           «هزینهٔ ثابت» و «یادداشت داخلی» از این فرم برداشته شده‌اند: تنها
           منبع قیمت، نرخ‌نامهٔ شهر و وزن است. */
        if (shippingTableReady()) {
            $up = $pdo->prepare("UPDATE shipping_methods
                                 SET label=?, icon=?, freight_collect=?, cod_only=?, is_active=?
                                 WHERE method_key=?");
            foreach (array_keys(shippingMethods()) as $mk) {
                $label = trim((string)($_POST['m_label'][$mk] ?? ''));
                if ($label === '') continue;                 // نام خالی روش را بی‌نام می‌کرد
                $icon  = shipIconKey((string)($_POST['m_icon'][$mk] ?? 'truck'));
                $up->execute([
                    $label, $icon,
                    isset($_POST['m_collect'][$mk]) ? 1 : 0,
                    isset($_POST['m_cod'][$mk])     ? 1 : 0,
                    isset($_POST['m_on'][$mk])      ? 1 : 0,
                    $mk,
                ]);
            }
            shippingAllMethods(true);   // کش درون‌درخواستی تازه شود
        } else {
            foreach (array_keys(shippingMethods()) as $mk) {
                setSetting('ship_enable_' . $mk, isset($_POST['m_on'][$mk]) ? '1' : '0');
            }
        }
    }

    /* بررسی عکس نمونهٔ قطعه: کلید روشن/خاموش + حداقل تعداد عکس.
       چک‌باکس خاموش هیچ مقداری POST نمی‌کند و این بلوک فقط وقتی اجرا می‌شود که
       همین بخش ذخیره شده باشد، پس نبود کلید = خاموش. */
    if ($sec === 'pchk') {
        setSetting('partcheck_enabled', isset($_POST['partcheck_enabled']) ? '1' : '0');
        $mp = (int)preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['partcheck_min_photos'] ?? '3')));
        setSetting('partcheck_min_photos', (string)max(1, min(8, $mp ?: 3)));
    }

    if ($sec === 'stockcheck') {
        setSetting('partcheck_require_stock', isset($_POST['partcheck_require_stock']) ? '1' : '0');
    }

    if ($sec === 'checkout') {
        setSetting('allow_guest_checkout', isset($_POST['allow_guest_checkout']) ? '1' : '0');
        setSetting('allow_checkout_no_mobile', isset($_POST['allow_checkout_no_mobile']) ? '1' : '0');
        setSetting('login_partner_disabled', isset($_POST['login_partner_disabled']) ? '1' : '0');
        setSetting('login_retail_disabled', isset($_POST['login_retail_disabled']) ? '1' : '0');
    }

    if ($sec === 'productyear') {
        setSetting('product_year_enabled', isset($_POST['product_year_enabled']) ? '1' : '0');
        $pyMin = (int)preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['product_year_min'] ?? '')));
        $pyMax = (int)preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['product_year_max'] ?? '')));
        $pyToday = jalaliToday()[0];
        if ($pyMin <= 0) $pyMin = $pyToday - 30;
        if ($pyMax <= 0) $pyMax = $pyToday;
        if ($pyMin > $pyMax) { $pyTmp = $pyMin; $pyMin = $pyMax; $pyMax = $pyTmp; }
        setSetting('product_year_min', (string)$pyMin);
        setSetting('product_year_max', (string)$pyMax);
    }

    /* ۲۰۲۶-۰۹-۰۳: دسترسی مجزا (خواستهٔ کاربر) — قبلا همین دو کلید داخل
       بخش «سال تولید خودرو» ذخیره می‌شدند. */
    if ($sec === 'partsteps') {
        setSetting('parts_brand_step_enabled', isset($_POST['parts_brand_step_enabled']) ? '1' : '0');
        setSetting('parts_model_step_enabled', isset($_POST['parts_model_step_enabled']) ? '1' : '0');
    }

    if ($sec === 'shiprate') {
        /* ویرایش گروهی ردیف‌های نرخ‌نامه (شهر / واحد وزن / هزینهٔ هر واحد).
           فقط ردیف‌هایی نوشته می‌شوند که در POST آمده‌اند، پس اگر مدیر فهرست را
           فیلتر کرده باشد ردیف‌های پنهان دست‌نخورده می‌مانند. */
        if (shippingRatesReady() && !empty($_POST['rate_city']) && is_array($_POST['rate_city'])) {
            $unitCol = shippingRateUnitReady() ? 'weight_unit' : 'weight_to';
            $ru = $pdo->prepare("UPDATE shipping_rates SET city=?, city_norm=?, $unitCol=?, cost=?, is_active=? WHERE id=?");
            foreach ($_POST['rate_city'] as $rid => $rcity) {
                $rid   = (int)$rid;
                $rcity = trim((string)$rcity);
                if ($rid <= 0 || $rcity === '') continue;
                $rw = faToLatinDigits((string)($_POST['rate_weight'][$rid] ?? '1'));
                $rw = (float)preg_replace('/[^0-9.]/', '', $rw);
                if ($rw <= 0) $rw = 1;                       // واحد وزن هرگز صفر نمی‌شود
                $rc = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['rate_cost'][$rid] ?? '0')));
                try {
                    $ru->execute([shippingCityCanonical($rcity), shipNormCity($rcity), $rw,
                                  $rc === '' ? 0 : (int)$rc,
                                  isset($_POST['rate_on'][$rid]) ? 1 : 0, $rid]);
                } catch (Exception $e) {
                    /* برخورد با کلید یکتای «روش + شهر»: یعنی مدیر شهر یک ردیف را
                       به شهری عوض کرده که ردیف دیگری از قبل دارد. آن ردیف را
                       بی‌صدا رد می‌کنیم تا ذخیرهٔ بقیه خراب نشود. */
                }
            }
        }
    }

    $saved = true;
}

/* ---------- افزودن/حذف روش ارسال و ردیف‌های نرخ‌نامه ----------
   این‌ها فرم‌ها و لینک‌های جداگانه‌اند (نه بخشی از فرم بزرگ بالا) و با الگوی
   PRG کار می‌کنند: POST/GET ← تغییر ← ریدایرکت، تا رفرش صفحه دوباره ثبت نکند.
   همه پیش از هر خروجی HTML اجرا می‌شوند چون header() صدا می‌زنند. */
function shipIconKey($v) {
    $allowed = ['truck','bike','plane','mail','send','package','layers','store',
                'globe','factory','briefcase','bag','clock','tools'];
    $v = (string)$v;
    return in_array($v, $allowed, true) ? $v : 'truck';
}

/* پیام و بازگشت به همان بخشی که فرم از آن ارسال شده: روش‌های ارسال یا نرخ‌نامه */
function shipRedirect($msg, $to = 'ship') {
    $anchor = ($to === 'shiprate') ? '#ratecrud' : '#shipcrud';
    header('Location: settings.php?sec=' . $to . '&smsg=' . urlencode($msg) . $anchor);
    exit;
}

if ($sec === 'ship' && shippingTableReady()) {
    /* افزودن روش ارسال جدید */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ship_add'])) {
        $label = trim((string)($_POST['new_label'] ?? ''));
        if ($label === '') shipRedirect('نام روش ارسال را وارد کنید.');

        /* کلید فقط لاتین است (در آدرس و ستون سفارش‌ها ذخیره می‌شود). اگر مدیر
           چیزی نزند یا فارسی بزند، خودکار ساخته می‌شود. */
        $key = strtolower(trim((string)($_POST['new_key'] ?? '')));
        $key = trim(preg_replace('/[^a-z0-9_]+/', '_', $key), '_');
        if ($key === '') {
            $key = 'm' . ((int)$pdo->query("SELECT COALESCE(MAX(id),0) FROM shipping_methods")->fetchColumn() + 1);
        }
        $exists = $pdo->prepare("SELECT COUNT(*) FROM shipping_methods WHERE method_key = ?");
        $base = $key; $n = 2;
        while (true) { $exists->execute([$key]); if ((int)$exists->fetchColumn() === 0) break; $key = $base . '_' . $n; $n++; }

        $sort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM shipping_methods")->fetchColumn() + 10;
        try {
            $pdo->prepare("INSERT INTO shipping_methods
                (method_key, label, icon, hint, badge, badge_short, cod_only, freight_collect, cost, sort_order, is_active, is_deleted)
                VALUES (?,?,?,'',?,?,?,?,0,?,1,0)")
                ->execute([
                    $key, $label, shipIconKey($_POST['new_icon'] ?? 'truck'),
                    trim((string)($_POST['new_badge'] ?? '')),
                    mb_substr(trim((string)($_POST['new_badge'] ?? '')), 0, 40, 'UTF-8'),
                    isset($_POST['new_cod']) ? 1 : 0,
                    isset($_POST['new_collect']) ? 1 : 0,
                    $sort,
                ]);
            shipRedirect('روش ارسال «' . $label . '» اضافه شد.');
        } catch (Exception $e) { shipRedirect('خطا در افزودن روش ارسال.'); }
    }

    /* حذف روش ارسال: اگر سفارشی به آن اشاره کند «حذف نرم» می‌شود تا نام روش
       آن سفارش‌ها گم نشود؛ وگرنه ردیف و نرخ‌نامه‌اش کامل پاک می‌شوند. */
    if (isset($_GET['mdel'])) {
        $k = (string)$_GET['mdel'];
        $label = shippingLabel($k);
        $used = 0;
        if (shippingReady()) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE shipping_method = ?");
            $st->execute([$k]);
            $used = (int)$st->fetchColumn();
        }
        try {
            if ($used > 0) {
                $pdo->prepare("UPDATE shipping_methods SET is_deleted = 1, is_active = 0 WHERE method_key = ?")->execute([$k]);
                shipRedirect('روش «' . $label . '» حذف شد. (' . $used . ' سفارش قدیمی به آن اشاره دارد، پس نامش برای همان سفارش‌ها نگه داشته شد.)');
            }
            $pdo->prepare("DELETE FROM shipping_methods WHERE method_key = ?")->execute([$k]);
            if (shippingRatesReady()) $pdo->prepare("DELETE FROM shipping_rates WHERE method_key = ?")->execute([$k]);
            shipRedirect('روش «' . $label . '» حذف شد.');
        } catch (Exception $e) { shipRedirect('خطا در حذف روش ارسال.'); }
    }

    /* روشن/خاموش کردن سریع یک روش، بدون ذخیرهٔ کل بخش */
    if (isset($_GET['mtoggle'])) {
        $k = (string)$_GET['mtoggle'];
        try {
            $pdo->prepare("UPDATE shipping_methods SET is_active = 1 - is_active WHERE method_key = ?")->execute([$k]);
            shipRedirect('وضعیت نمایش «' . shippingLabel($k) . '» عوض شد.');
        } catch (Exception $e) { shipRedirect('خطا در تغییر وضعیت.'); }
    }
}

if ($sec === 'shiprate' && shippingRatesReady()) {
    /* افزودن نرخ: روش + شهر (از فهرست یا دستی) + واحد وزن + هزینهٔ هر واحد.
       upsert است چون کلید یکتای (روش، شهر) اجازهٔ دو نرخ متناقض برای یک شهر
       را نمی‌دهد — پس «افزودن دوبارهٔ» یک شهر نرخش را به‌روز می‌کند. */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_add'])) {
        $mk   = (string)($_POST['r_method'] ?? '');
        /* شهر دستی بر انتخاب از فهرست مقدم است (مدیر شهری نوشته که در فهرست نیست) */
        $city = trim((string)($_POST['r_city_new'] ?? ''));
        if ($city === '') $city = trim((string)($_POST['r_city'] ?? ''));
        if (shippingMethodDef($mk) === null) shipRedirect('روش ارسال را انتخاب کنید.', 'shiprate');
        if ($city === '')  shipRedirect('شهر مقصد را انتخاب یا وارد کنید.', 'shiprate');
        if (shippingIsFreightCollect($mk)) {
            shipRedirect('«' . shippingLabel($mk) . '» پس‌کرایه است؛ کرایه هنگام تحویل گرفته می‌شود و نرخ‌نامه برایش کاربردی ندارد.', 'shiprate');
        }

        $city = shippingCityCanonical($city);       // اگر شهر در فهرست بود، نام رسمی‌اش
        $w = (float)preg_replace('/[^0-9.]/', '', faToLatinDigits((string)($_POST['r_weight'] ?? '1')));
        if ($w <= 0) $w = 1;                        // «هر ۱ کیلوگرم» پیش‌فرض
        $c = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['r_cost'] ?? '')));

        /* شهر دستی به فهرست شهرها هم اضافه شود تا بار بعد از انتخابگر بیاید */
        if (shippingCitiesReady()) {
            try {
                $pdo->prepare("INSERT IGNORE INTO cities (name, name_norm, province, sort_order, is_active)
                               VALUES (?,?,?,9000,1)")
                    ->execute([$city, shipNormCity($city), trim((string)($_POST['r_province'] ?? ''))]);
                shippingCityRows(true);
            } catch (Exception $e) { /* فهرست شهرها اختیاری است، نرخ باید ثبت شود */ }
        }

        $unitCol = shippingRateUnitReady() ? 'weight_unit' : 'weight_to';
        try {
            $pdo->prepare("INSERT INTO shipping_rates (method_key, city, city_norm, $unitCol, cost, is_active)
                           VALUES (?,?,?,?,?,1)
                           ON DUPLICATE KEY UPDATE city = VALUES(city), $unitCol = VALUES($unitCol),
                                                   cost = VALUES(cost), is_active = 1")
                ->execute([$mk, $city, shipNormCity($city), $w, $c === '' ? 0 : (int)$c]);
            shipRedirect('نرخ «' . $city . '» برای «' . shippingLabel($mk) . '» ثبت شد: هر ' . shippingWeightText($w) . ' کیلوگرم ' . formatPrice($c === '' ? 0 : (int)$c) . '.', 'shiprate');
        } catch (Exception $e) { shipRedirect('خطا در ثبت نرخ.', 'shiprate'); }
    }

    if (isset($_GET['rdel'])) {
        try {
            $pdo->prepare("DELETE FROM shipping_rates WHERE id = ?")->execute([(int)$_GET['rdel']]);
            shipRedirect('ردیف نرخ‌نامه حذف شد.', 'shiprate');
        } catch (Exception $e) { shipRedirect('خطا در حذف ردیف.', 'shiprate'); }
    }
}

$s = getAllSettings(true); // refresh
function sv($key) { return getSettingRaw($key, ''); }
$testMode = getSettingRaw('sms_test_mode', '1') === '1';
$smsMethod = getSetting('sms_method', 'bulk');
$smsTrackOn = smsTrackEnabled();
/* برچسب و آیکن مراحل از همان منبعی که روند ارسال از آن می‌خواند، تا اگر
   مرحله‌ای اضافه/کم شد این صفحه هم خودکار هم‌گام شود. */
$trackLabels = [];
$trackIcons  = [];
foreach (orderTrackSteps() as $__c => $__d) {
    $trackLabels[$__c] = $__d['label'];
    $trackIcons[$__c]  = $__d['icon'];
}
$otpRequired = loginOtpRequired();
$decorEnabled = getSettingRaw('home_decor_enabled', '1') === '1';
$decorStyle = getSetting('home_decor_style', 'auto');

/* وضعیت درگاه پرداخت */
$payReady    = paymentReady();
$payTest     = getSettingRaw('pay_test_mode', '1') === '1';
$payGwSel    = getSetting('pay_gateway', 'sim');
$payUnit     = getSetting('pay_unit', 'rial');
$payEnCod    = getSettingRaw('pay_enable_cod', '1') === '1';
$payEnCard   = getSettingRaw('pay_enable_card', '0') === '1';
$payEnOnline = getSettingRaw('pay_enable_online', '1') === '1';
$payEnPMonth = getSettingRaw('pay_enable_partner_month', '1') === '1';
$payEnCheque = getSettingRaw('pay_enable_cheque', '1') === '1';
$payActive   = paymentActiveGateway();
/* پرداخت اعتباری: تیک فعال‌سازی و آماده‌بودن تنظیماتش جدا سنجیده می‌شوند تا
   ادمین ببیند «روشن است ولی کلید/آدرس ندارد» یا «همه چیز هست ولی خاموش است». */
$payEnCredit = getSettingRaw('pay_enable_credit', '0') === '1';
$payCrOk     = paymentIsConfigured('credit');
$payCrMin    = paymentCreditMin();

/* وضعیت روش‌های ارسال */
$shipReady = shippingReady();
$shipAll   = shippingMethods();
$shipTbl   = shippingTableReady();
$shipRtOn  = shippingRatesReady();
$shipWtOn  = shippingWeightReady();
$shipRtU   = shippingRateUnitReady();   // ستون weight_unit ساخته شده؟
$shipCtOn  = shippingCitiesReady();     // جدول شهرها ساخته شده؟
/* شهرها برای انتخابگر نرخ‌نامه، گروه‌بندی‌شده بر اساس استان */
$shipCityGroups = $shipCtOn ? shippingCityGroups() : [];
$shipCityCount  = 0;
foreach ($shipCityGroups as $__g) $shipCityCount += count($__g);
/* آیکون‌های قابل انتخاب برای یک روش ارسال (همان فهرست مجاز shipIconKey) */
$shipIcons = ['truck' => 'کامیون / باربری', 'bike' => 'پیک موتوری', 'plane' => 'هوایی',
              'mail' => 'پست', 'send' => 'پست پیشتاز', 'package' => 'بسته',
              'layers' => 'محموله', 'store' => 'فروشگاه', 'globe' => 'بین‌شهری',
              'factory' => 'کارخانه', 'briefcase' => 'اداری', 'bag' => 'کیف',
              'clock' => 'زمان‌بندی‌شده', 'tools' => 'خدمات'];
/* ردیف‌های نرخ‌نامه، گروه‌بندی‌شده بر اساس روش */
$shipRates = [];
if ($shipRtOn) {
    foreach (array_keys($shipAll) as $__mk) {
        $__r = shippingRates($__mk);
        if ($__r) $shipRates[$__mk] = $__r;
    }
}
/* همان ردیف‌ها، تخت‌شده برای جدول فشردهٔ بخش «نرخ‌نامه‌های ارسال»: یک جدول با
   ستون «روش» جای چند جدول جدا، تا صفحه با هر شهر تازه بلندتر نشود. کلید روش
   روی هر ردیف می‌نشیند تا فیلتر سمت مرورگر بتواند ردیف‌ها را پنهان کند. */
$rateRows  = [];
$rateCities = [];
foreach ($shipRates as $__mk => $__rows) {
    foreach ($__rows as $__r) {
        $__r['method_key'] = $__mk;
        $rateRows[] = $__r;
        $rateCities[shipNormCity((string)$__r['city'])] = true;
    }
}
$rateCount     = count($rateRows);
$rateCityCount = count($rateCities);
/* روش‌هایی که می‌توانند نرخ داشته باشند (پس‌کرایه‌ها کرایه‌شان محاسبه نمی‌شود) */
$rateMethods = [];
foreach ($shipAll as $__mk => $__md) {
    if (!shippingIsFreightCollect($__mk)) $rateMethods[$__mk] = $__md;
}
/* برای توضیح قاعدهٔ «ارسال ↔ پرداخت» پایین همین بخش: آیا جز «پرداخت در محل»
   روش پرداخت دیگری فعال است؟ اگر نه، قاعده برای روش‌های غیرپیک عملا کاری
   نمی‌کند، چون shippingAllowedPayKeys() عمدا فهرست کامل را برمی‌گرداند تا
   مشتری بی‌راه پرداخت نماند. */
$shipPayOther = array_values(array_diff(array_keys(paymentAvailableMethods()), ['cod']));
$shipMsg = trim((string)($_GET['smsg'] ?? ''));

require_once __DIR__ . '/layout-top.php';
?>
<h2 style="margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;">
  <?= icon($sections[$sec]['icon']) ?> تنظیمات سایت — <?= h($sections[$sec]['label']) ?>
</h2>

<?php /* همان تب‌های صفحهٔ مشتریان: جابه‌جایی بین بخش‌ها بدون رفتن به سایدبار */ ?>
<div class="cust-tabs">
  <?php foreach ($sections as $sk => $sd): ?>
  <a href="settings.php?sec=<?= h($sk) ?>" class="cust-tab<?= $sk === $sec ? ' active' : '' ?>">
    <?= icon($sd['icon'], 'ic-sm') ?><?= h($sd['label']) ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($saved): ?><div class="flash flash-success"><?= icon('check', 'ic-sm') ?> تنظیمات بخش «<?= h($sections[$sec]['label']) ?>» ذخیره شد.</div><?php endif; ?>
<?php if ($shipMsg !== ''): ?><div class="flash flash-success"><?= icon('check', 'ic-sm') ?> <?= h($shipMsg) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="admin-form-full<?= ($sec === 'ship' || $sec === 'shiprate') ? ' ff-wide' : '' ?>">
  <?php /* بخش ارسال‌شده، تا ذخیره فقط کلیدهای همین بخش را بازنویسی کند */ ?>
  <input type="hidden" name="sec" value="<?= h($sec) ?>">
  <?php /* پرچم «این فرم اصلی بخش است» — فرم‌های کوچک پایین صفحه آن را
           ندارند تا کلیدهای متنی بخش را خالی نکنند. */ ?>
  <input type="hidden" name="save_section" value="1">

  <?php if ($sec === 'footer'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('layers') ?> فوتر سایت</h3>

    <div class="form-group"><label>دربارهٔ فروشگاه</label>
      <textarea name="footer_about" class="form-control" rows="3"><?= h(sv('footer_about')) ?></textarea></div>

    <div class="form-group"><label>متن کپی‌رایت (پایین فوتر)</label>
      <input type="text" name="footer_copyright" class="form-control" value="<?= h(sv('footer_copyright')) ?>" placeholder="خالی = پیش‌فرض با سال جاری">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group"><label>تلفن‌های ثابت</label>
        <textarea name="contact_phones" class="form-control" rows="3" dir="ltr" placeholder="هر شماره در یک خط"><?= h(sv('contact_phones') !== '' ? sv('contact_phones') : sv('contact_phone')) ?></textarea>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">می‌توانید چند شماره وارد کنید؛ هر شماره را در یک خط جدا بنویسید.</div>
      </div>
      <div class="form-group"><label>شماره‌های همراه</label>
        <textarea name="contact_mobiles" class="form-control" rows="3" dir="ltr" placeholder="هر شماره در یک خط"><?= h(sv('contact_mobiles') !== '' ? sv('contact_mobiles') : sv('contact_mobile')) ?></textarea>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">هر شماره در یک خط. در فوتر به‌صورت لینک تماس نمایش داده می‌شوند.</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
      <div class="form-group"><label>ایمیل</label><input type="text" name="contact_email" class="form-control" value="<?= h(sv('contact_email')) ?>"></div>
      <div class="form-group"><label>ساعات کاری</label><input type="text" name="working_hours" class="form-control" value="<?= h(sv('working_hours')) ?>"></div>
    </div>
    <div class="form-group"><label>آدرس</label><textarea name="contact_address" class="form-control" rows="2"><?= h(sv('contact_address')) ?></textarea></div>

    <h4 style="font-size:0.82rem;color:var(--text-muted);margin:0.5rem 0 0.75rem;">شبکه‌های اجتماعی و پیام‌رسان‌ها</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
      <div class="form-group"><label>اینستاگرام</label><input type="text" name="social_instagram" class="form-control" value="<?= h(sv('social_instagram')) ?>" placeholder="آدرس یا شناسه" dir="ltr"></div>
      <div class="form-group"><label>تلگرام</label><input type="text" name="social_telegram" class="form-control" value="<?= h(sv('social_telegram')) ?>" placeholder="آدرس یا شناسه" dir="ltr"></div>
      <div class="form-group"><label>واتساپ</label><input type="text" name="social_whatsapp" class="form-control" value="<?= h(sv('social_whatsapp')) ?>" placeholder="شماره یا آدرس" dir="ltr"></div>
      <div class="form-group"><label>توییتر (X)</label><input type="text" name="social_twitter" class="form-control" value="<?= h(sv('social_twitter')) ?>" placeholder="آدرس یا شناسه" dir="ltr"></div>
      <div class="form-group"><label>بله</label><input type="text" name="social_bale" class="form-control" value="<?= h(sv('social_bale')) ?>" placeholder="آدرس یا شناسه" dir="ltr"></div>
      <div class="form-group"><label>گپ</label><input type="text" name="social_gap" class="form-control" value="<?= h(sv('social_gap')) ?>" placeholder="آدرس یا شناسه" dir="ltr"></div>
      <div class="form-group"><label>روبیکا</label><input type="text" name="social_rubika" class="form-control" value="<?= h(sv('social_rubika')) ?>" placeholder="آدرس یا شناسه" dir="ltr"></div>
      <div class="form-group"><label>ایتا</label><input type="text" name="social_eitaa" class="form-control" value="<?= h(sv('social_eitaa')) ?>" placeholder="آدرس یا شناسه" dir="ltr"></div>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);">فیلدهای خالی در فوتر نمایش داده نمی‌شوند. می‌توانید آدرس کامل (https://...) یا فقط شناسه (مثلا <code dir="ltr">@myshop</code>) را وارد کنید.</div>
  </div>
  <?php endif; ?>

  <?php if ($sec === 'decor'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('palette') ?> تزیینات صفحهٔ اصلی</h3>
    <div class="form-group" style="margin-bottom:0.5rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="home_decor_enabled" value="1" <?= $decorEnabled ? 'checked' : '' ?>>
        نمایش تصاویر تزیینی در گوشه‌های صفحهٔ اصلی (بنرها)
      </label>
    </div>
    <div class="form-group"><label>طرح تزیینات</label>
      <select name="home_decor_style" class="form-control">
        <optgroup label="ساده (فقط گوشه‌ها)">
          <option value="auto"  <?= $decorStyle === 'auto'  ? 'selected' : '' ?>>خودکار (ترکیبی)</option>
          <option value="gears" <?= $decorStyle === 'gears' ? 'selected' : '' ?>>چرخ‌دنده‌ها</option>
          <option value="tools" <?= $decorStyle === 'tools' ? 'selected' : '' ?>>ابزارآلات</option>
          <option value="tire"  <?= $decorStyle === 'tire'  ? 'selected' : '' ?>>لاستیک و رینگ</option>
        </optgroup>
        <optgroup label="متحرک (طرح‌های جدید)">
          <option value="car"    <?= $decorStyle === 'car'    ? 'selected' : '' ?>>ماشین در حرکت (از پشت بنرها رد می‌شود)</option>
          <option value="road"   <?= $decorStyle === 'road'   ? 'selected' : '' ?>>جاده و ماشین (خط‌چین متحرک)</option>
          <option value="engine" <?= $decorStyle === 'engine' ? 'selected' : '' ?>>موتور و پیستون (بالا-پایین)</option>
          <option value="neon"   <?= $decorStyle === 'neon'   ? 'selected' : '' ?>>مدار نئون (خطوط درخشان)</option>
          <option value="float"  <?= $decorStyle === 'float'  ? 'selected' : '' ?>>قطعات شناور (پیچ و فنر و روغن)</option>
        </optgroup>
      </select>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);">این تصاویر پشت محتوا و در فضای خالی گوشه‌ها نمایش داده می‌شوند و در نمایشگرهای کوچک به‌صورت خودکار پنهان می‌شوند. طرح‌های متحرک برای کاربرانی که در سیستم‌عامل «کاهش انیمیشن» را فعال کرده‌اند خودکار ساکن می‌شوند.</div>
  </div>
  <?php endif; ?>

  <?php if ($sec === 'sms'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('shield-check') ?> تأیید شماره برای ورود مشتریان</h3>

    <div class="form-group" style="margin-bottom:0.75rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="login_otp_required" value="1" <?= $otpRequired ? 'checked' : '' ?>>
        ورود مشتری با کد تأیید پیامکی انجام شود
      </label>
    </div>

    <div style="font-size:0.78rem;color:var(--text-secondary);line-height:1.9;">
      • <b>تیک‌خورده (پیشنهادی):</b> مشتری شماره را وارد می‌کند، کد ۵ رقمی پیامک می‌شود و تا کد را نزند وارد نمی‌شود.<br>
      • <b>بدون تیک:</b> مرحلهٔ کد کاملا حذف می‌شود؛ مشتری با زدن شمارهٔ موبایل بلافاصله وارد می‌شود (اگر حساب نداشته باشد، مثل قبل خودکار ساخته می‌شود).
      این گزینه به «حالت آزمایشی» پایین ربطی ندارد و مستقل از آن کار می‌کند.
    </div>

    <?php if (!$otpRequired): ?>
    <div class="flash" style="margin-top:0.75rem;background:rgba(220,38,38,0.14);border:1px solid rgba(220,38,38,0.4);color:#FCA5A5;font-size:0.78rem;line-height:1.9;">
      <?= icon('alert', 'ic-sm') ?> <b>هم‌اکنون ورود بدون تأیید شماره فعال است.</b>
      در این حالت هر کسی که شمارهٔ موبایل یک مشتری را بداند می‌تواند وارد حساب او شود و آدرس، شماره و سفارش‌هایش را ببیند.
      بهتر است فقط برای آزمایش یا زمانی که پیامک قطع است روشن بماند و بعد دوباره تیک بزنید.
    </div>
    <?php endif; ?>
  </div>

  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('mobile') ?> پیامک (SMS.ir) — کد ورود مشتریان</h3>

    <?php if (!$otpRequired): ?>
    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:1rem;">
      چون «تأیید شماره برای ورود» بالا خاموش است، این تنظیمات فعلا برای ورود مشتریان به کار نمی‌رود؛ ذخیره می‌شود و به‌محض تیک‌زدن آن گزینه فعال می‌گردد.
    </div>
    <?php endif; ?>

    <div class="form-group"><label>روش ارسال</label>
      <select name="sms_method" id="sms_method" class="form-control">
        <option value="bulk"   <?= $smsMethod === 'bulk'   ? 'selected' : '' ?>>ارسال انبوه — متن آزاد + شمارهٔ خط (پیشنهادی)</option>
        <option value="verify" <?= $smsMethod === 'verify' ? 'selected' : '' ?>>قالب تأیید (Template)</option>
      </select>
    </div>

    <div class="form-group"><label>کلید API (x-api-key)</label>
      <input type="text" name="sms_api_key" class="form-control" value="<?= h(sv('sms_api_key')) ?>" autocomplete="off" dir="ltr"></div>

    <div class="sms-bulk">
      <div class="form-group"><label>شمارهٔ خط (Line Number)</label>
        <input type="text" name="sms_line_number" class="form-control" value="<?= h(sv('sms_line_number')) ?>" dir="ltr" placeholder="مثلا 30007732xxxx"></div>
      <div class="form-group"><label>متن پیامک کد</label>
        <input type="text" name="sms_otp_text" class="form-control" value="<?= h(sv('sms_otp_text') ?: 'کد تأیید شما: {code}') ?>">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">
          <code dir="ltr">{code}</code> جای کد و <code dir="ltr">{site}</code> جای نام سایت قرار می‌گیرد. اگر <code dir="ltr">{code}</code> را ننویسید خودکار افزوده می‌شود.
        </div>
      </div>
    </div>

    <div class="sms-verify">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group"><label>شناسهٔ قالب (Template ID)</label><input type="text" name="sms_template_id" class="form-control" value="<?= h(sv('sms_template_id')) ?>" dir="ltr"></div>
        <div class="form-group"><label>نام پارامتر کد در قالب</label><input type="text" name="sms_param_name" class="form-control" value="<?= h(sv('sms_param_name') ?: 'CODE') ?>" dir="ltr" placeholder="CODE"></div>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:0.5rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="sms_test_mode" value="1" <?= $testMode ? 'checked' : '' ?>>
        حالت آزمایشی (پیامک واقعی ارسال نمی‌شود؛ کد فقط اینجا نمایش داده می‌شود)
      </label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);">تا زمانی که کلید API و (برای ارسال انبوه: شمارهٔ خط، برای قالب: شناسهٔ قالب) کامل نشده باشند، سیستم خودکار در حالت آزمایشی کار می‌کند.</div>
    <?php if ($testMode && ($last = getSettingRaw('sms_last_test', '')) !== ''): ?>
    <div class="flash" style="margin-top:0.75rem;background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#FBBF24;" dir="ltr"><?= h($last) ?></div>
    <?php endif; ?>
  </div>

  <?php /* ---------- اطلاع‌رسانی پیامکی مراحل روند ارسال ----------
           یک کلید اصلی (برای وقتی پنل شارژ ندارد) + یک متن برای هر مرحله.
           متن‌ها از orderTrackSmsKeys() می‌آیند تا فهرست این صفحه و فرستنده
           هیچ‌وقت از هم جدا نشوند. مرحله‌ای که ستونش ساخته نشده نشان داده
           نمی‌شود ولی متنش حفظ می‌ماند (کلیدش در $secTextKeys هست). */ ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('truck') ?> اطلاع‌رسانی پیامکی مراحل ارسال سفارش</h3>

    <div class="form-group" style="margin-bottom:0.5rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="sms_track_enabled" value="1" <?= $smsTrackOn ? 'checked' : '' ?>>
        با ثبت هر مرحله در «روند ارسال سفارش»، برای مشتری پیامک فرستاده شود
      </label>
    </div>
    <div style="font-size:0.75rem;color:var(--text-muted);line-height:1.9;margin-bottom:1rem;">
      اگر پنل پیامک شارژ نداشت همین تیک را بردارید؛ مراحل مثل قبل ثبت و برای مشتری نمایش داده می‌شوند، فقط پیامکی ارسال نمی‌شود.
      پیامک فقط <b>یک‌بار</b> و لحظهٔ تیک‌خوردن مرحله می‌رود (ذخیرهٔ دوبارهٔ همان مرحله پیامک تکراری نمی‌فرستد).
      این پیامک‌ها همیشه از «ارسال انبوه» استفاده می‌کنند، پس <b>کلید API</b> و <b>شمارهٔ خط</b> باید پر باشند.
    </div>

    <?php if (!$smsTrackOn): ?>
    <div class="flash" style="margin-bottom:1rem;background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#FBBF24;font-size:0.78rem;">
      <?= icon('info', 'ic-sm') ?> اطلاع‌رسانی پیامکی مراحل هم‌اکنون خاموش است. متن‌های زیر ذخیره می‌شوند و به‌محض تیک‌زدن بالا به کار می‌آیند.
    </div>
    <?php endif; ?>

    <div class="sms-steps">
      <?php foreach (orderTrackSmsKeys() as $tcol => $tdef):
            $tLabel = $trackLabels[$tcol] ?? $tcol;
            $tMissing = !isset($trackLabels[$tcol]); ?>
      <div class="form-group" style="margin-bottom:0.75rem;">
        <label><?= icon($trackIcons[$tcol] ?? 'message', 'ic-sm') ?> <?= h($tLabel) ?>
          <?php if ($tMissing): ?><i style="font-style:normal;color:#FBBF24;font-size:0.7rem;">(این مرحله روی سایت فعال نیست)</i><?php endif; ?>
        </label>
        <input type="text" name="<?= h($tdef['key']) ?>" class="form-control"
               value="<?= h(getSettingRaw($tdef['key'], $tdef['default'])) ?>"
               placeholder="<?= h($tdef['default']) ?>">
      </div>
      <?php endforeach; ?>
    </div>

    <div style="font-size:0.72rem;color:var(--text-muted);line-height:1.9;">
      جای‌گذارها: <code dir="ltr">{order}</code> شمارهٔ سفارش ·
      <code dir="ltr">{name}</code> نام مشتری ·
      <code dir="ltr">{site}</code> نام سایت ·
      <code dir="ltr">{code}</code> کد رهگیری پست ·
      <code dir="ltr">{courier}</code> نام و شمارهٔ پیک.<br>
      متن هر مرحله را <b>خالی</b> بگذارید تا پیامک همان مرحله ارسال نشود (مثلا فقط «تحویل به پست» و «ارسال شد» پیامک شوند).
      گزارش ارسال‌ها در <code dir="ltr">storage/sms.log</code> ذخیره می‌شود.
    </div>
  </div>

  <script>
  (function(){
    var sel = document.getElementById('sms_method');
    if (!sel) return;
    function upd(){
      var bulk = sel.value === 'bulk';
      var b = document.querySelector('.sms-bulk'), v = document.querySelector('.sms-verify');
      if (b) b.style.display = bulk ? '' : 'none';
      if (v) v.style.display = bulk ? 'none' : '';
    }
    sel.addEventListener('change', upd); upd();
  })();
  </script>
  <?php endif; ?>

  <?php if ($sec === 'pay'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('credit-card') ?> درگاه پرداخت</h3>

    <?php if (!$payReady): ?>
    <div class="flash" style="background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.35);color:#F87171;margin-bottom:1rem;">
      <?= icon('alert', 'ic-sm') ?> ستون‌های پرداخت روی جدول سفارش‌ها ساخته نشده‌اند. فایل <code dir="ltr">migrate-payments.php</code> را یک‌بار در مرورگر باز کنید (خودش پس از اجرا حذف می‌شود).
    </div>
    <?php else: ?>
    <div class="auth-note" style="margin-bottom:1rem;font-size:0.78rem;">
      <?= icon('info', 'ic-sm') ?> درگاه فعال فعلی:
      <b><?= $payActive === '' ? 'هیچ درگاه آنلاینی فعال نیست' : h(paymentLabel($payActive)) ?></b>
      <?php if ($payTest): ?> — <span style="color:#FBBF24;">حالت آزمایشی روشن است</span><?php endif; ?>
    </div>
    <?php endif; ?>

    <h4 style="font-size:0.82rem;color:var(--text-muted);margin:0.25rem 0 0.75rem;">روش‌های پرداخت فعال</h4>
    <div class="form-group" style="margin-bottom:0.35rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="pay_enable_cod" value="1" <?= $payEnCod ? 'checked' : '' ?>>
        پرداخت در محل / هنگام تحویل
      </label>
    </div>
    <div class="form-group" style="margin-bottom:0.35rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="pay_enable_card" value="1" <?= $payEnCard ? 'checked' : '' ?>>
        کارت به کارت (نیازمند پر‌کردن شمارهٔ کارت)
      </label>
    </div>
    <div class="form-group" style="margin-bottom:0.75rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="pay_enable_online" value="1" <?= $payEnOnline ? 'checked' : '' ?>>
        پرداخت آنلاین (انتقال به درگاه بانکی)
      </label>
    </div>

    <h4 style="font-size:0.82rem;color:var(--text-muted);margin:1rem 0 0.5rem;">روش‌های ویژهٔ همکاران تأییدشده</h4>
    <p style="font-size:0.75rem;color:var(--text-muted);margin:0 0 0.6rem;line-height:1.8;">
      این دو روش فقط به حساب همکار تأییدشده نمایش داده می‌شوند — مشتری جزئی و همکار در انتظار تأیید آن‌ها را نمی‌بینند.
    </p>
    <div class="form-group" style="margin-bottom:0.35rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="pay_enable_partner_month" value="1" <?= $payEnPMonth ? 'checked' : '' ?>>
        پرداخت اول ماه
      </label>
    </div>
    <div class="form-group" style="margin-bottom:0.75rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="pay_enable_cheque" value="1" <?= $payEnCheque ? 'checked' : '' ?>>
        پرداخت با چک
      </label>
    </div>

    <?php $chqSample = trim((string)getSettingRaw('pay_cheque_sample', '')); ?>
    <div class="form-group" style="margin-bottom:1rem;">
      <label>عکس نمونهٔ چک <span style="color:var(--text-muted);font-weight:400;font-size:0.72rem;">(زیر کادر «اطلاعات چک» به همکار نشان داده می‌شود)</span></label>
      <?php if ($chqSample !== ''): ?>
      <div class="image-preview" style="margin-bottom:0.5rem;"><img src="../uploads/settings/<?= h($chqSample) ?>" style="max-height:140px;border-radius:8px;"></div>
      <label style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.5rem;font-size:0.78rem;color:var(--text-muted);">
        <input type="checkbox" name="cheque_sample_remove" value="1"> حذف عکس فعلی
      </label>
      <?php endif; ?>
      <input type="file" name="cheque_sample" class="form-control" accept="image/*">
    </div>

    <?php /* دیگر فرم ثبت بانک/شماره/تاریخ/مبلغ چک نداریم (خواستهٔ کاربر) —
             فقط یک پیغام تأیید سفارش + مهلت تحویل اصل چک، که همین‌جا
             قابل تنظیم است. {days} در متن با عدد زیر جایگزین می‌شود. */ ?>
    <div class="form-group"><label>مهلت ارسال/تحویل اصل چک (روز)</label>
      <input type="number" name="pay_cheque_deadline_days" class="form-control" style="width:140px;"
             value="<?= (int)(sv('pay_cheque_deadline_days') ?: 10) ?>" min="1" max="90"></div>

    <div class="form-group"><label>متن پیغام تأیید سفارش چکی <span style="color:var(--text-muted);font-weight:400;font-size:0.72rem;">(به‌جای <code dir="ltr">{days}</code> عدد بالا نوشته می‌شود)</span></label>
      <textarea name="pay_cheque_note" class="form-control" rows="3"><?= h(sv('pay_cheque_note') ?: 'سفارش شما تأیید شد؛ اما اصل چک را هم باید برایمان تا {days} روز دیگر ارسال یا تحویل دهید. سفارش تا رسیدن فیزیکی چک «در انتظار دریافت چک» می‌ماند. ارسال سفارش شما انجام خواهد شد.') ?></textarea>
    </div>

    <div class="form-group"><label>یادداشت «پرداخت در محل» (زیر گزینه در صفحهٔ تسویه)</label>
      <input type="text" name="pay_cod_note" class="form-control" value="<?= h(sv('pay_cod_note') ?: 'مبلغ سفارش هنگام تحویل کالا دریافت می‌شود.') ?>"></div>

    <div class="pay-card-fields">
      <h4 style="font-size:0.82rem;color:var(--text-muted);margin:0.75rem 0;">اطلاعات کارت به کارت</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group"><label>شمارهٔ کارت</label>
          <input type="text" name="pay_card_number" class="form-control" value="<?= h(sv('pay_card_number')) ?>" dir="ltr" inputmode="numeric" placeholder="6037-9911-0000-0000"></div>
        <div class="form-group"><label>به نام</label>
          <input type="text" name="pay_card_holder" class="form-control" value="<?= h(sv('pay_card_holder')) ?>"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group"><label>نام بانک</label>
          <input type="text" name="pay_card_bank" class="form-control" value="<?= h(sv('pay_card_bank')) ?>" placeholder="مثلا ملی / ملت"></div>
        <div class="form-group"><label>راهنمای پس از واریز</label>
          <input type="text" name="pay_card_note" class="form-control" value="<?= h(sv('pay_card_note')) ?>"></div>
      </div>

      <div class="form-group"><label>توضیح بالای فرم «ثبت واریز» (اختیاری)</label>
        <input type="text" name="pay_c2c_note" class="form-control" value="<?= h(sv('pay_c2c_note')) ?>"
               placeholder="مثلا: پس از واریز، اطلاعات زیر را پر کنید تا سفارش‌تان تأیید شود."></div>

      <div class="auth-note" style="font-size:0.76rem;line-height:1.9;">
        <?= icon('info', 'ic-sm') ?>
        <b>روند کارت به کارت:</b> مشتری در صفحهٔ تسویه شمارهٔ کارت را می‌بیند، سفارش را ثبت می‌کند،
        بعد در صفحهٔ «سفارش ثبت شد» چهار مورد را پر می‌کند:
        <b>شناسهٔ واریز، مبلغ، چهار رقم آخر کارت مبدأ و زمان واریز</b>.
        سفارش تا تأیید شما <b>«در انتظار تأیید واریز»</b> می‌ماند؛ در
        <b>سفارش‌ها ← جزئیات سفارش</b> این چهار مورد را می‌بینید و با دکمهٔ
        <b>«تأیید واریز»</b> سفارش را پرداخت‌شده می‌کنید.
      </div>
    </div>

    <div class="pay-online-fields">
      <h4 style="font-size:0.82rem;color:var(--text-muted);margin:0.75rem 0;">درگاه بانکی</h4>
      <div class="form-group"><label>انتخاب درگاه</label>
        <select name="pay_gateway" id="pay_gateway" class="form-control">
          <?php /* درگاه اعتباری در این فهرست نمی‌آید: کارت خودش را دارد و
                   «درگاه بانکی اصلی» نیست (credit_only) */ ?>
          <?php foreach (paymentGateways() as $gk => $gd): if ($gd['kind'] !== 'online' || !empty($gd['credit_only'])) continue; ?>
          <option value="<?= h($gk) ?>" <?= $payGwSel === $gk ? 'selected' : '' ?>>
            <?= h($gd['label']) ?><?= paymentIsConfigured($gk) ? ' — آماده' : ' — تنظیم نشده' ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pay-gw pay-gw-zarinpal">
        <div class="form-group"><label>شناسهٔ پذیرندهٔ زرین‌پال (Merchant ID)</label>
          <input type="text" name="pay_zarinpal_merchant" class="form-control" value="<?= h(sv('pay_zarinpal_merchant')) ?>" dir="ltr" autocomplete="off" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">آدرس بازگشت را در پنل زرین‌پال روی <code dir="ltr"><?= h(rtrim(SITE_URL, '/')) ?>/payment-callback.php?gw=zarinpal</code> بگذارید.</div>
        </div>
      </div>

      <div class="pay-gw pay-gw-zibal">
        <div class="form-group"><label>شناسهٔ پذیرندهٔ زیبال (Merchant)</label>
          <input type="text" name="pay_zibal_merchant" class="form-control" value="<?= h(sv('pay_zibal_merchant')) ?>" dir="ltr" autocomplete="off">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">در حالت آزمایشی اگر خالی باشد، از پذیرندهٔ نمونهٔ <code dir="ltr">zibal</code> استفاده می‌شود.</div>
        </div>
      </div>

      <div class="pay-gw pay-gw-idpay">
        <div class="form-group"><label>کلید API آیدی‌پی</label>
          <input type="text" name="pay_idpay_key" class="form-control" value="<?= h(sv('pay_idpay_key')) ?>" dir="ltr" autocomplete="off">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">آدرس بازگشت: <code dir="ltr"><?= h(rtrim(SITE_URL, '/')) ?>/payment-callback.php?gw=idpay</code></div>
        </div>
      </div>

      <div class="pay-gw pay-gw-sim">
        <div class="auth-note" style="font-size:0.78rem;">
          <?= icon('tools', 'ic-sm') ?> درگاه آزمایشی هیچ تنظیمی لازم ندارد و فقط وقتی «حالت آزمایشی» روشن باشد کار می‌کند.
          با آن می‌توانید کل مسیر پرداخت را قبل از گرفتن درگاه واقعی تست کنید.
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group"><label>واحد مبلغ ارسالی به درگاه</label>
          <select name="pay_unit" class="form-control">
            <option value="rial"  <?= $payUnit === 'rial'  ? 'selected' : '' ?>>ریال (قیمت سایت × ۱۰) — پیش‌فرض</option>
            <option value="toman" <?= $payUnit === 'toman' ? 'selected' : '' ?>>تومان (بدون تبدیل)</option>
          </select>
        </div>
        <div class="form-group"><label>حداقل مبلغ پرداخت آنلاین (تومان)</label>
          <input type="text" name="pay_min_amount" class="form-control" value="<?= h(sv('pay_min_amount') ?: '1000') ?>" dir="ltr" inputmode="numeric"></div>
      </div>

      <div class="form-group"><label>شرح تراکنش</label>
        <input type="text" name="pay_desc" class="form-control" value="<?= h(sv('pay_desc') ?: 'پرداخت سفارش {order} در {site}') ?>">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">
          <code dir="ltr">{order}</code> شمارهٔ سفارش و <code dir="ltr">{site}</code> نام سایت است.
        </div>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:0.5rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="pay_test_mode" value="1" <?= $payTest ? 'checked' : '' ?>>
        حالت آزمایشی (بدون تراکنش واقعی — درگاه آزمایشی داخلی و sandbox درگاه‌ها فعال می‌شود)
      </label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);">
      پس از گرفتن درگاه واقعی: اطلاعات درگاه را وارد کنید، آن را از فهرست بالا انتخاب کنید و همین تیک «حالت آزمایشی» را بردارید. هیچ تغییری در کد لازم نیست.
    </div>
  </div>

  <script>
  /* فقط فیلدهای درگاه انتخاب‌شده نمایش داده شوند */
  (function(){
    var gw = document.getElementById('pay_gateway');
    if (!gw) return;
    function upd(){
      document.querySelectorAll('.pay-gw').forEach(function(el){
        el.style.display = el.classList.contains('pay-gw-' + gw.value) ? '' : 'none';
      });
    }
    gw.addEventListener('change', upd); upd();
  })();
  /* بلوک‌های «کارت به کارت» و «درگاه بانکی» تابع تیک فعال‌سازی خودشان هستند */
  (function(){
    var card = document.querySelector('input[name="pay_enable_card"]'),
        onl  = document.querySelector('input[name="pay_enable_online"]');
    function upd(){
      var cf = document.querySelector('.pay-card-fields'),
          of = document.querySelector('.pay-online-fields');
      if (cf && card) cf.style.opacity = card.checked ? '1' : '0.45';
      if (of && onl)  of.style.opacity = onl.checked  ? '1' : '0.45';
    }
    if (card) card.addEventListener('change', upd);
    if (onl)  onl.addEventListener('change', upd);
    upd();
  })();
  </script>
  <?php endif; ?>

  <?php if ($sec === 'paycredit'): ?>
  <?php /* ---------- درگاه پرداخت اعتباری / اقساطی ----------
           کارت جدا از درگاه بانکی، چون گزینهٔ مستقلی در صفحهٔ تسویه است (نه
           جانشین درگاه اصلی): مشتری می‌تواند هم «پرداخت اینترنتی» ببیند و هم
           «پرداخت اعتباری». پیش‌فرض خاموش است تا وقتی قرارداد بسته شود. */ ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('receipt') ?> پرداخت اعتباری / اقساطی</h3>

    <div class="form-group" style="margin-bottom:0.5rem;">
      <label class="cat-checkbox" style="font-size:0.9rem;">
        <input type="checkbox" name="pay_enable_credit" value="1" id="pay_enable_credit" <?= $payEnCredit ? 'checked' : '' ?>>
        نمایش «پرداخت اعتباری» در صفحهٔ تسویه
      </label>
    </div>

    <?php if (!$payEnCredit): ?>
    <div class="auth-note" style="font-size:0.76rem;background:rgba(234,179,8,0.10);border:1px solid rgba(234,179,8,0.32);color:#FCD34D;margin-bottom:1rem;">
      <?= icon('info', 'ic-sm') ?> این روش <b>خاموش</b> است و مشتری آن را نمی‌بیند. تنظیماتش را از حالا پر کنید؛
      هر وقت قرارداد اعتباری‌تان بسته شد فقط همین تیک بالا را بزنید.
    </div>
    <?php elseif (!$payCrOk): ?>
    <div class="flash" style="background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.35);color:#F87171;margin-bottom:1rem;font-size:0.78rem;">
      <?= icon('alert', 'ic-sm') ?> تیک روشن است اما <b>شناسهٔ پذیرنده</b> یا <b>آدرس ایجاد پرداخت</b> خالی است،
      پس این گزینه به مشتری نشان داده نمی‌شود. هر دو را پر کنید.
    </div>
    <?php else: ?>
    <div class="auth-note" style="font-size:0.78rem;margin-bottom:1rem;">
      <?= icon('check-circle', 'ic-sm') ?> فعال و آمادهٔ استفاده است<?php if ($payCrMin > 0): ?> — فقط برای سفارش‌های <?= formatPrice($payCrMin) ?> و بیشتر<?php endif; ?>.
    </div>
    <?php endif; ?>

    <div class="pay-credit-fields">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group"><label>نام گزینه (همان که مشتری می‌بیند)</label>
          <input type="text" name="pay_credit_label" class="form-control" value="<?= h(sv('pay_credit_label') ?: 'پرداخت اعتباری (اقساطی)') ?>" placeholder="مثلا پرداخت اعتباری اسنپ‌پی"></div>
        <div class="form-group"><label>حداقل مبلغ سفارش (تومان)</label>
          <input type="text" name="pay_credit_min" class="form-control" value="<?= h(sv('pay_credit_min') ?: '0') ?>" dir="ltr" inputmode="numeric" placeholder="0">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">۰ یعنی بدون حد؛ اگر ارائه‌دهنده حد پایین دارد همان را بگذارید تا مشتری بی‌جهت به درگاه نرود.</div>
        </div>
      </div>

      <div class="form-group"><label>توضیح زیر گزینه در صفحهٔ تسویه</label>
        <input type="text" name="pay_credit_note" class="form-control" value="<?= h(sv('pay_credit_note') ?: 'مبلغ سفارش را به‌صورت اعتباری/اقساطی بپردازید. پس از انتخاب، به درگاه ارائه‌دهندهٔ اعتبار می‌روید.') ?>"></div>

      <div class="form-group"><label>شناسهٔ پذیرنده / توکن ارائه‌دهنده</label>
        <input type="text" name="pay_credit_merchant" class="form-control" value="<?= h(sv('pay_credit_merchant')) ?>" dir="ltr" autocomplete="off"></div>

      <div class="form-group"><label>آدرس ایجاد پرداخت (Create / Request URL)</label>
        <input type="text" name="pay_credit_create_url" class="form-control" value="<?= h(sv('pay_credit_create_url')) ?>" dir="ltr" autocomplete="off" placeholder="https://api.provider.ir/v1/payment/request"></div>

      <div class="form-group"><label>آدرس تأیید پرداخت (Verify URL)</label>
        <input type="text" name="pay_credit_verify_url" class="form-control" value="<?= h(sv('pay_credit_verify_url')) ?>" dir="ltr" autocomplete="off" placeholder="https://api.provider.ir/v1/payment/verify">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">
          تا وقتی این آدرس خالی باشد، سفارش‌های اعتباری <b>خودکار پرداخت‌شده نمی‌شوند</b> و برای بررسی دستی در انتظار می‌مانند.
        </div>
      </div>

      <div class="auth-note" style="font-size:0.76rem;line-height:1.9;">
        <?= icon('info', 'ic-sm') ?>
        آدرس بازگشت را در پنل ارائه‌دهنده روی
        <code dir="ltr"><?= h(rtrim(SITE_URL, '/')) ?>/payment-callback.php?gw=credit</code> بگذارید.
        درخواست ایجاد پرداخت به‌صورت JSON با فیلدهای
        <code dir="ltr">merchant، amount، callbackUrl، description، orderId، mobile</code> فرستاده می‌شود
        و پاسخ می‌تواند توکن را در <code dir="ltr">token / trackId / authority</code> و آدرس را در
        <code dir="ltr">paymentUrl / redirectUrl / url</code> برگرداند.
        اگر ارائه‌دهندهٔ شما نام فیلدهای دیگری داشت، همین را بگویید تا نگاشتش اصلاح شود.
      </div>
    </div>
  </div>

  <script>
  /* بلوک فیلدهای پرداخت اعتباری تابع تیک فعال‌سازی خودش است */
  (function(){
    var crd = document.querySelector('input[name="pay_enable_credit"]');
    function upd(){
      var rf = document.querySelector('.pay-credit-fields');
      if (rf && crd) rf.style.opacity = crd.checked ? '1' : '0.45';
    }
    if (crd) crd.addEventListener('change', upd);
    upd();
  })();
  </script>
  <?php endif; ?>

  <?php if ($sec === 'ship'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('truck') ?> روش‌های ارسال</h3>

    <?php if (!$shipReady): ?>
    <div class="flash" style="background:rgba(220,38,38,0.12);border:1px solid rgba(220,38,38,0.35);color:#F87171;margin-bottom:1rem;">
      <?= icon('alert', 'ic-sm') ?> ستون‌های ارسال روی جدول سفارش‌ها ساخته نشده‌اند. فایل <code dir="ltr">migrate-shipping.php</code> را یک‌بار در مرورگر باز کنید (خودش پس از اجرا حذف می‌شود). تا آن زمان انتخابگر ارسال در صفحهٔ تسویه نمایش داده نمی‌شود.
    </div>
    <?php endif; ?>

    <?php /* تا وقتی این مهاجرت اجرا نشده، فهرست روش‌ها همان هشت روش ثابت کد است
             و «افزودن/حذف روش»، «نرخ‌نامه» و «وزن محصول» در دسترس نیستند. صفحه
             خطا نمی‌دهد، فقط این سه امکان خاموش‌اند. */ ?>
    <?php if (!$shipTbl || !$shipRtOn || !$shipWtOn || !$shipRtU || !$shipCtOn): ?>
    <div class="flash" style="background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#FCD34D;margin-bottom:1rem;">
      <?= icon('alert', 'ic-sm') ?>
      برای «افزودن و حذف روش ارسال»، «نرخ‌نامهٔ شهر و وزن» و «وزن محصول» فایل
      <code dir="ltr">migrate-shiprates.php</code> و برای «فهرست شهرها» و «نرخ به‌ازای هر واحد وزن» فایل
      <code dir="ltr">migrate-cityrates.php</code> را یک‌بار در مرورگر باز کنید.
      <div style="margin-top:0.4rem;font-size:0.72rem;">
        وضعیت فعلی — جدول روش‌ها: <b><?= $shipTbl ? 'آماده' : 'ساخته نشده' ?></b> ·
        نرخ‌نامه: <b><?= $shipRtOn ? 'آماده' : 'ساخته نشده' ?></b> ·
        واحد وزن: <b><?= $shipRtU ? 'آماده' : 'ساخته نشده' ?></b> ·
        فهرست شهرها: <b><?= $shipCtOn ? 'آماده (' . $shipCityCount . ' شهر)' : 'ساخته نشده' ?></b> ·
        وزن محصول: <b><?= $shipWtOn ? 'آماده' : 'ساخته نشده' ?></b>
      </div>
    </div>
    <?php endif; ?>

    <div class="auth-note" style="margin-bottom:1rem;font-size:0.78rem;line-height:1.9;">
      <?= icon('info', 'ic-sm') ?>
      هزینهٔ ارسال از <b><a href="settings.php?sec=shiprate">نرخ‌نامه‌های ارسال</a></b> (بخش جدا) حساب می‌شود و به
      <b>مبلغ قابل پرداخت</b> اضافه می‌شود. برای هر روش و هر شهر می‌گویید «هر ‎<b>N</b>‎ کیلوگرم،
      ‎<b>X</b>‎ تومان»؛ وزن سبد بر آن واحد تقسیم و <b>به بالا رند</b> می‌شود.
      مثال: نرخ تبریز برای پست «هر ۱ کیلوگرم ۵۰٬۰۰۰» ⇒ سبد ۲ کیلویی ۱۰۰٬۰۰۰ و سبد ۲٫۳ کیلویی ۱۵۰٬۰۰۰.
      <br>
      اگر برای شهری نرخ ثبت نکرده باشید، روش <b>حذف نمی‌شود</b>؛ فقط به مشتری می‌گوییم
      «هزینهٔ ارسال پس از ثبت سفارش هماهنگ می‌شود» و مبلغی به سفارش اضافه نمی‌شود.
      <br>
      تیک <b>پس‌کرایه</b> یعنی کرایه هنگام تحویل از مشتری گرفته می‌شود: برای آن روش
      <b>هیچ محاسبه‌ای انجام نمی‌شود</b>، نرخ‌نامه نادیده گرفته می‌شود و به مشتری فقط
      کلمهٔ «پس‌کرایه» نشان داده می‌شود. تیک را بردارید تا کرایه <b>هنگام خرید</b> حساب و پرداخت شود.
      <br>
      «ارسال با پیک (مشهد)» همیشه در فهرست نمایش داده می‌شود و فقط یادآوری «فقط برای شهر مشهد» می‌گیرد؛
      بر اساس شهر انتخاب‌شدهٔ مشتری پنهان نمی‌شود تا سفارشی به اشتباه بسته نشود.
    </div>

    <?php /* قاعدهٔ ارسال↔پرداخت از تیک «پرداخت در محل مجاز است» (ستون cod_only)
             خوانده می‌شود و اینجا فقط توضیح داده می‌شود تا مدیر بداند چرا
             گزینه‌های پرداخت در صفحهٔ تسویه کم و زیاد می‌شوند. */ ?>
    <div class="auth-note" style="margin-bottom:1rem;font-size:0.78rem;line-height:1.9;">
      <?= icon('credit-card', 'ic-sm') ?>
      <b>قاعدهٔ ارسال و پرداخت:</b>
      روشی که تیک <b>«پرداخت در محل مجاز است»</b> دارد (پیش‌فرض: ارسال با پیک)، <b>هم</b> پرداخت در محل و
      <b>هم</b> پرداخت اینترنتی و کارت‌به‌کارت را می‌پذیرد — چون ممکن است مشتری همان شهر بخواهد آنلاین پرداخت کند.
      روش‌های دیگر (پست، باربری، چاپار…) <b>پرداخت در محل ندارند</b> و مبلغ باید پیش از ارسال پرداخت شود.
      این قاعده هم در فرم و هم هنگام ثبت سفارش در سرور بررسی می‌شود.
      <?php if (!$shipPayOther): ?>
      <br>
      <span style="color:#FBBF24;">
        <?= icon('alert', 'ic-sm') ?>
        در حال حاضر تنها روش پرداخت فعال «پرداخت در محل» است، پس این قاعده برای روش‌های غیرپیک اثری ندارد
        (اگر اثر می‌کرد، مشتری هیچ راهی برای پرداخت نداشت). برای فعال‌شدن کامل قاعده، از بخش
        <b>درگاه پرداخت</b> دست‌کم یک روش دیگر — کارت‌به‌کارت یا پرداخت اینترنتی — را روشن کنید.
      </span>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label>توضیح بالای انتخابگر ارسال (اختیاری)</label>
      <input type="text" name="ship_desc" class="form-control ff-cap" value="<?= h(sv('ship_desc')) ?>" placeholder="مثلا: هزینهٔ ارسال بر اساس روش انتخابی شما محاسبه می‌شود.">
    </div>

    <div class="tbl-scroll">
    <table class="admin-table ship-mtable" style="margin-top:0.5rem;">
      <thead>
        <tr>
          <th style="width:34%;">نام روش</th>
          <th style="width:22%;">آیکون</th>
          <th style="width:32%;">وضعیت و نوع کرایه</th>
          <?php if ($shipTbl): ?><th style="width:12%;"></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($shipAll as $mk => $md):
            $mRates   = $shipRates[$mk] ?? [];
            $mCollect = !empty($md['freight_collect']);
        ?>
        <tr>
          <td>
            <input type="text" name="m_label[<?= h($mk) ?>]" class="form-control"
                   value="<?= h($md['label']) ?>" style="margin:0;" <?= $shipTbl ? '' : 'readonly' ?>>
            <div style="color:var(--text-muted);font-size:0.66rem;margin-top:3px;" dir="ltr"><?= h($mk) ?></div>
            <?php if (!empty($md['badge'])): ?>
            <div style="color:#FBBF24;font-size:0.7rem;margin-top:2px;"><?= icon('map-pin', 'ic-sm') ?> <?= h($md['badge']) ?></div>
            <?php endif; ?>
            <?php if ($mCollect): ?>
            <div style="color:#FBBF24;font-size:0.7rem;margin-top:2px;">
              <?= icon('info', 'ic-sm') ?> پس‌کرایه — بدون محاسبهٔ هزینه
            </div>
            <?php elseif ($mRates): ?>
            <div style="color:#4ADE80;font-size:0.7rem;margin-top:2px;">
              <?= icon('map-pin', 'ic-sm') ?> <?= count($mRates) ?> شهر در نرخ‌نامه
            </div>
            <?php elseif ($shipRtOn): ?>
            <div style="color:var(--text-muted);font-size:0.7rem;margin-top:2px;">
              <?= icon('map-pin', 'ic-sm') ?> نرخی ثبت نشده
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($shipTbl): ?>
            <select name="m_icon[<?= h($mk) ?>]" class="form-control" style="margin:0;">
              <?php foreach ($shipIcons as $ik => $iLbl): ?>
              <option value="<?= h($ik) ?>" <?= $md['icon'] === $ik ? 'selected' : '' ?>><?= h($iLbl) ?></option>
              <?php endforeach; ?>
            </select>
            <div style="margin-top:4px;color:var(--text-secondary);"><?= icon($md['icon'], 'ic-lg') ?></div>
            <?php else: ?>
            <?= icon($md['icon'], 'ic-lg') ?>
            <?php endif; ?>
          </td>
          <td>
            <label class="cat-checkbox" style="font-size:0.8rem;display:block;margin-bottom:5px;">
              <input type="checkbox" name="m_on[<?= h($mk) ?>]" value="1" <?= !empty($md['is_active']) ? 'checked' : '' ?>>
              نمایش در تسویه
            </label>
            <label class="cat-checkbox" style="font-size:0.8rem;display:block;margin-bottom:5px;">
              <input type="checkbox" name="m_collect[<?= h($mk) ?>]" value="1"
                     <?= $mCollect ? 'checked' : '' ?> <?= $shipTbl ? '' : 'disabled' ?>>
              پس‌کرایه (بدون محاسبه)
            </label>
            <label class="cat-checkbox" style="font-size:0.8rem;display:block;">
              <input type="checkbox" name="m_cod[<?= h($mk) ?>]" value="1"
                     <?= !empty($md['cod_only']) ? 'checked' : '' ?> <?= $shipTbl ? '' : 'disabled' ?>>
              پرداخت در محل مجاز است
            </label>
          </td>
          <?php if ($shipTbl): ?>
          <td style="white-space:nowrap;">
            <a href="settings.php?sec=ship&amp;mtoggle=<?= urlencode($mk) ?>" class="btn btn-sm"
               style="background:var(--bg-input);color:var(--text-secondary);"
               title="<?= !empty($md['is_active']) ? 'خاموش کن' : 'روشن کن' ?>">
              <?= icon(!empty($md['is_active']) ? 'x' : 'check', 'ic-sm') ?>
            </a>
            <a href="settings.php?sec=ship&amp;mdel=<?= urlencode($mk) ?>" class="btn btn-sm btn-danger"
               data-confirm="این روش ارسال و نرخ‌نامه‌اش حذف شود؟"
               title="حذف روش «<?= h($md['label']) ?>»">
              <?= icon('trash', 'ic-sm') ?>
            </a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.75rem;line-height:1.9;">
      <?= icon('info', 'ic-sm') ?>
      اگر تیک «نمایش در تسویه» یک روش را بردارید، آن روش در صفحهٔ تسویه دیده نمی‌شود.
      اگر تیک همه روش‌ها برداشته شود، انتخابگر ارسال کامل حذف می‌شود و سفارش‌ها مثل قبل بدون روش ارسال ثبت می‌شوند.
      قیمت اینجا وارد نمی‌شود — قیمت را در بخش <a href="settings.php?sec=shiprate"><b>نرخ‌نامه‌های ارسال</b></a>، شهر به شهر، وارد کنید.
      <?php if ($shipTbl): ?>
      <br>
      حذف روشی که سفارش قدیمی دارد، آن را از فهرست‌ها برمی‌دارد ولی نامش را برای همان سفارش‌ها نگه می‌دارد.
      <?php endif; ?>
    </div>
  </div>

  <?php /* ---------- هزینه‌های باربری (بخش جدا، خواستهٔ کاربر) ---------- */ ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('factory') ?> هزینه‌های باربری</h3>

    <div class="auth-note" style="margin-bottom:1rem;font-size:0.78rem;line-height:1.9;">
      <?= icon('info', 'ic-sm') ?>
      این دو مقدار زیر گزینهٔ <b>باربری</b> در صفحهٔ تسویه به مشتری نشان داده می‌شود
      (فقط برای اطلاع‌رسانی). اگر مبلغ را صفر بگذارید فقط متن توضیح دیده می‌شود،
      و اگر توضیح را خالی بگذارید فقط مبلغ.
      مبلغی که واقعا به سفارش اضافه می‌شود همان نرخ نرخ‌نامه است.
    </div>

    <div class="form-group">
      <label>هزینهٔ پایهٔ باربری (تومان)</label>
      <input type="text" name="ship_barbari_cost" class="form-control ff-cap" dir="ltr" inputmode="numeric"
             value="<?= h(sv('ship_barbari_cost')) ?>" placeholder="0">
      <div style="color:var(--text-muted);font-size:0.72rem;margin-top:0.35rem;">
        <?= icon('receipt', 'ic-sm') ?>
        <?= shippingBarbariCost() > 0 ? h(formatPrice(shippingBarbariCost())) : 'ثبت نشده — به مشتری مبلغی نشان داده نمی‌شود.' ?>
      </div>
    </div>

    <div class="form-group">
      <label>توضیح باربری برای مشتری (اختیاری)</label>
      <textarea name="ship_barbari_desc" class="form-control ff-cap" rows="2"
                placeholder="مثلا: کرایهٔ باربری بر اساس وزن و مقصد محاسبه و هنگام تحویل دریافت می‌شود."><?= h(sv('ship_barbari_desc')) ?></textarea>
    </div>
  </div>

  <?php endif; ?>

  <?php /* ---------- بخش جدا: «نرخ‌نامه‌های ارسال» ----------
           خواستهٔ مدیر: «هر چی اضافه می‌کنم رو اضافه می‌کنه توی همون صفحه،
           این‌جوری صفحه در آینده خیلی بزرگ می‌شه… به‌صورت مرتب نشون بده که خیلی
           طولانی نشه و چیدمانش ریزتر باشه».
           پس: یک جدول فشردهٔ یک‌خطی جای چند جدول جدا برای هر روش، داخل کادری
           با ارتفاع محدود و اسکرول خودش — با هر شهر تازه صفحه بلندتر نمی‌شود —
           به‌همراه فیلتر روش و جستجوی شهر تا فهرست صد شهری هم قابل استفاده بماند. */ ?>
  <?php if ($sec === 'shiprate'): ?>
  <div class="rt-card">
    <div class="rt-top">
      <h3><?= icon('scale', 'ic-sm') ?> نرخ‌های ثبت‌شده</h3>
      <?php if ($shipRtOn): ?>
      <span class="rt-sum">
        <b><?= (int)$rateCount ?></b> نرخ · <b><?= (int)$rateCityCount ?></b> شهر · <b><?= count($shipRates) ?></b> روش
      </span>
      <?php endif; ?>
      <a href="#ratecrud" class="rt-jump"><?= icon('plus', 'ic-sm') ?> افزودن نرخ شهر</a>
    </div>

    <?php if (!$shipRtOn): ?>
    <p class="rt-empty" style="color:#FCD34D;">
      <?= icon('alert', 'ic-sm') ?> نرخ‌نامه هنوز ساخته نشده — فایل <code dir="ltr">migrate-shiprates.php</code> را یک‌بار در مرورگر باز کنید.
    </p>
    <?php elseif (!$rateRows): ?>
    <p class="rt-empty">
      <?= icon('info', 'ic-sm') ?> هنوز هیچ نرخی ثبت نشده. از کادر
      <a href="#ratecrud">افزودن نرخ یک شهر</a> پایین همین صفحه شروع کنید.
    </p>
    <?php else: ?>

    <div class="rt-bar">
      <input type="text" id="rt-q" class="form-control rt-q" placeholder="جستجوی شهر…" autocomplete="off">
      <div class="rt-chips">
        <button type="button" class="rt-chip is-on" data-m="">همه <i><?= (int)$rateCount ?></i></button>
        <?php foreach ($shipRates as $rmk => $rrows): ?>
        <button type="button" class="rt-chip" data-m="<?= h($rmk) ?>">
          <?= icon(shippingIcon($rmk), 'ic-sm') ?><?= h(shippingLabel($rmk)) ?> <i><?= count($rrows) ?></i>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rt-scroll">
      <table class="rt-table">
        <thead>
          <tr>
            <th>روش</th>
            <th>شهر مقصد</th>
            <th>هر چند کیلوگرم</th>
            <th>هزینهٔ هر واحد</th>
            <th>فعال</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rateRows as $rr):
              $rid  = (int)$rr['id'];
              $rmk  = (string)$rr['method_key'];
              $rUnt = shipRateUnit($rr);
              $rCst = (int)$rr['cost'];
          ?>
          <tr class="rt-row" data-m="<?= h($rmk) ?>" data-city="<?= h(shipNormCity((string)$rr['city'])) ?>">
            <td class="rt-m">
              <?= icon(shippingIcon($rmk), 'ic-sm') ?><span><?= h(shippingLabel($rmk)) ?></span>
              <?php if (shippingIsFreightCollect($rmk)): ?>
              <i class="rt-warn" title="این روش پس‌کرایه است، پس این نرخ محاسبه نمی‌شود">پس‌کرایه</i>
              <?php endif; ?>
            </td>
            <td>
              <input type="text" name="rate_city[<?= $rid ?>]" class="form-control rt-in"
                     value="<?= h((string)$rr['city']) ?>" list="ship-city-list">
            </td>
            <td>
              <div class="wt-stepper wt-mini">
                <button type="button" class="wt-btn" data-wt="-1" title="کمتر">−</button>
                <input type="text" name="rate_weight[<?= $rid ?>]" class="form-control wt-field" dir="ltr"
                       inputmode="decimal" value="<?= h(shippingWeightText($rUnt)) ?>">
                <button type="button" class="wt-btn" data-wt="1" title="بیشتر">+</button>
              </div>
            </td>
            <td>
              <input type="text" name="rate_cost[<?= $rid ?>]" class="form-control rt-in rt-cost" dir="ltr"
                     inputmode="numeric" value="<?= $rCst ?>">
              <i class="rt-hint"><?= $rCst > 0 ? h(formatPrice($rCst)) : 'مبلغی ثبت نشده' ?></i>
            </td>
            <td class="rt-c">
              <input type="checkbox" name="rate_on[<?= $rid ?>]" value="1" class="rt-ck"
                     <?= (int)$rr['is_active'] === 1 ? 'checked' : '' ?> title="نمایش و محاسبهٔ این نرخ">
            </td>
            <td class="rt-c">
              <a href="settings.php?sec=shiprate&amp;rdel=<?= $rid ?>" class="rt-del"
                 data-confirm="نرخ این شهر حذف شود؟" title="حذف نرخ «<?= h((string)$rr['city']) ?>»">
                <?= icon('trash', 'ic-sm') ?>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="rt-none" id="rt-none" hidden><?= icon('info', 'ic-sm') ?> شهری با این نام در فهرست نیست.</p>

    <p class="rt-foot">
      <?= icon('info', 'ic-sm') ?>
      تغییر ردیف‌های بالا با دکمهٔ <b>ذخیرهٔ تنظیمات</b> پایین ثبت می‌شود.
      فیلتر و جستجو فقط نمایش را کم می‌کنند و ردیف‌های پنهان دست‌نخورده می‌مانند.
      هر شهر برای هر روش یک نرخ دارد؛ اگر شهر ردیفی را به شهری عوض کنید که همان روش
      از قبل برایش نرخ دارد، آن ردیف تغییر نمی‌کند.
    </p>
    <?php endif; ?>

    <div class="rt-note">
      <label for="ship_rate_note">توضیح نرخ‌نامه برای مشتری در صفحهٔ سبد و تسویه (اختیاری)</label>
      <input type="text" id="ship_rate_note" name="ship_rate_note" class="form-control"
             value="<?= h(sv('ship_rate_note')) ?>"
             placeholder="مثلا: هزینهٔ دقیق پس از وزن‌کشی نهایی اعلام می‌شود.">
    </div>

    <details class="rt-help">
      <summary><?= icon('help', 'ic-sm') ?> راهنمای محاسبه و چند نکته</summary>
      <div>
        برای هر روش ارسال، شهرها را یکی‌یکی اضافه کنید: <b>شهر + واحد وزن + هزینهٔ هر واحد</b> —
        یعنی «برای تبریز با پست، هر ۱ کیلوگرم ۵۰٬۰۰۰ تومان».
        <br>
        <b>محاسبه:</b> وزن سبد بر واحد وزن تقسیم و <b>به بالا رند</b> می‌شود، بعد در هزینه ضرب می‌شود.
        با نرخ «هر ۱ کیلوگرم ۵۰٬۰۰۰»: سبد ۲ کیلویی ⇒ ۱۰۰٬۰۰۰ · سبد ۳ کیلویی ⇒ ۱۵۰٬۰۰۰ ·
        سبد ۲٫۱ کیلویی ⇒ ۱۵۰٬۰۰۰. چند عدد از یک کالا، وزن و هزینه را هم چند برابر می‌کند.
        <br>
        وزن هر کالا در <b>محصولات ← ویرایش محصول ← وزن</b> ثبت می‌شود و به مشتری نشان داده نمی‌شود.
        اگر وزن کالاهای سبد ثبت نشده باشد <b>یک واحد</b> حساب می‌شود تا مبلغ صفر نشود.
        <br>
        مقایسهٔ نام شهر ساده‌گیر است: «مشهد» با «مشهد مقدس» هم می‌خواند. اگر برای شهری نرخ
        ثبت نشده باشد روش <b>حذف نمی‌شود</b>؛ فقط به مشتری می‌گوییم «هزینهٔ ارسال پس از ثبت
        سفارش هماهنگ می‌شود» و مبلغی به سفارش اضافه نمی‌شود.
        <br>
        خود روش‌های ارسال (نام، آیکون، نمایش، پس‌کرایه، پرداخت در محل) در بخش
        <a href="settings.php?sec=ship">روش‌های ارسال</a> تنظیم می‌شوند.
      </div>
    </details>
  </div>
  <?php endif; ?>

  <?php /* ---------- بررسی عکس نمونهٔ قطعه ---------- */ ?>
  <?php if ($sec === 'pchk'): $pchkTbl = partChecksReady(); ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('camera') ?> بررسی عکس نمونهٔ قطعه پیش از خرید</h3>

    <?php if (!$pchkTbl): ?>
    <div class="flash flash-error" style="margin-bottom:1rem;">
      <?= icon('alert', 'ic-sm') ?> جدول‌های این بخش ساخته نشده‌اند؛ یک‌بار فایل <b>migrate-partcheck.php</b> را اجرا کنید.
      تا آن زمان این مرحله در سایت نمایش داده نمی‌شود.
    </div>
    <?php endif; ?>

    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      مشتری پس از سبد خرید به صفحهٔ <b>بررسی عکس قطعه</b> می‌رود و چند عکس از زوایای مختلف قطعهٔ
      خودش می‌فرستد (یا این مرحله را رد می‌کند). شما در بخش <a href="part-checks.php">بررسی عکس قطعه</a>
      فقط <b>مطابقت</b> عکس‌ها را با کالای سبد تأیید/رد می‌کنید. الزام «تأیید موجودی» جدا، در بخش
      <a href="settings.php?sec=stockcheck">تأیید موجودی</a> تنظیم می‌شود و صف/صفحهٔ داوری مستقل خودش
      (<code>stock-checks.php</code>) را دارد — یک همکار دیگر می‌تواند فقط همان را ببیند، بدون نیاز به
      دیدن این عکس‌ها.
    </p>

    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="partcheck_enabled" id="partcheck_enabled" value="1"
             <?= getSettingRaw('partcheck_enabled', '1') === '1' ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="partcheck_enabled" style="margin:0;cursor:pointer;">این مرحله فعال باشد (بررسی عکس)</label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin:-0.5rem 0 1rem;">
      با برداشتن تیک، صفحهٔ بررسی عکس کنار گذاشته می‌شود و کلید سبد خرید مستقیم به ثبت سفارش و پرداخت
      می‌رود — این کلید روی الزام «تأیید موجودی» هم اثر دارد (توضیح در بخش <a href="settings.php?sec=stockcheck">تأیید موجودی</a>).
    </div>

    <div class="form-group ff-cap">
      <label for="partcheck_min_photos">حداقل تعداد عکس از زوایای مختلف</label>
      <input type="text" name="partcheck_min_photos" id="partcheck_min_photos" class="form-control" dir="ltr"
             inputmode="numeric" style="max-width:7rem;text-align:center;" value="<?= (int)partCheckMinPhotos() ?>">
      <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">
        بین ۱ تا ۸ (پیش‌فرض ۳). اگر مشتری کمتر از این تعداد بفرستد، فرم پذیرفته نمی‌شود.
      </div>
    </div>

    <div class="form-group">
      <label for="partcheck_notice">متن درشت بالای صفحه برای مشتری</label>
      <textarea name="partcheck_notice" id="partcheck_notice" class="form-control" rows="5"
                placeholder="خالی = متن پیش‌فرض (دربارهٔ اینکه قطعه باید دقیقا به خودرو بخورد و امکان مرجوعی نیست)"><?= h(sv('partcheck_notice')) ?></textarea>
      <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">
        <?php if (trim(sv('partcheck_notice')) === ''): ?>
        هم‌اکنون خالی است، پس این متن پیش‌فرض به مشتری نشان داده می‌شود:
        <br><span style="color:var(--text-secondary);"><?= h(partCheckNotice()) ?></span>
        <?php else: ?>
        متن بالا جای متن پیش‌فرض را گرفته است. اگر این کادر را خالی کنید، متن پیش‌فرض سایت برمی‌گردد.
        <?php endif; ?>
      </div>
    </div>

    <?php if ($pchkTbl): ?>
    <div style="font-size:0.8rem;color:var(--text-secondary);padding:0.7rem 0.9rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-sm);">
      <?= icon('clock', 'ic-sm') ?> در انتظار بررسی: <b><?= (int)partCheckPendingCount() ?></b> درخواست —
      <a href="part-checks.php" style="color:var(--red-light);">رفتن به صفحهٔ بررسی</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* ---------- تأیید موجودی ---------- */ ?>
  <?php if ($sec === 'stockcheck'): $pchkTbl2 = partCheckStockSplitReady(); ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('shield-check') ?> تأیید موجودی پیش از پرداخت</h3>

    <?php if (!$pchkTbl2): ?>
    <div class="flash flash-error" style="margin-bottom:1rem;">
      <?= icon('alert', 'ic-sm') ?> ستون‌های این بخش ساخته نشده‌اند؛ یک‌بار فایل <b>migrate-partcheck-split.php</b> را اجرا کنید.
      تا آن زمان این گیت در سایت فعال نمی‌شود.
    </div>
    <?php endif; ?>

    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      ۲۰۲۶-۰۹-۰۳: «تأیید موجودی» یک مرحلهٔ کاملا <b>مستقل</b> از «بررسی عکس قطعه» است — چه آن مرحله
      روشن باشد چه خاموش، این کلید به‌تنهایی تعیین می‌کند مشتری پیش از ثبت سفارش باید منتظر تأیید
      موجودی بماند یا نه. یک همکار جدا در صفحهٔ <a href="stock-checks.php">تأیید موجودی</a> فقط همین
      صف را می‌بیند و تأیید می‌کند — بدون نیاز به دیدن عکس یا اثرگذاری روی مطابقت قطعه. تا وقتی تأیید
      نشده، مشتری معطل می‌ماند و کلید «ثبت سفارش و پرداخت» برایش باز نمی‌شود؛ این گیت کاملا مسدودکننده
      است و راه فراری ندارد.
    </p>

    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="partcheck_require_stock" id="partcheck_require_stock" value="1"
             <?= getSettingRaw('partcheck_require_stock', '1') === '1' ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="partcheck_require_stock" style="margin:0;cursor:pointer;">تأیید موجودی برای ادامه الزامی باشد</label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin:-0.5rem 0 1rem;">
      با برداشتن تیک، این گیت کلا خاموش می‌شود — نه در checkout.php (وقتی بررسی عکس هم خاموش است) و نه در
      «بررسی عکس قطعه» (وقتی آن روشن است)، دیگر منتظر تأیید موجودی نمی‌ماند.
    </div>

    <?php if ($pchkTbl2): ?>
    <div style="font-size:0.8rem;color:var(--text-secondary);padding:0.7rem 0.9rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-sm);">
      <?= icon('clock', 'ic-sm') ?> در انتظار بررسی: <b><?= (int)stockCheckPendingCount() ?></b> درخواست —
      <a href="stock-checks.php" style="color:var(--red-light);">رفتن به صفحهٔ تأیید موجودی</a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* ---------- ثبت سفارش / ورود ---------- */ ?>
  <?php if ($sec === 'checkout'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('login') ?> ثبت سفارش و ورود مشتری</h3>
    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      به‌طور پیش‌فرض، مشتری واردنشده برای زدن کلید «ادامه» در سبد خرید باید وارد حساب شود
      (با شمارهٔ موبایل و کد تأیید یا بدونش، بسته به تنظیم پیامک).
      با روشن‌کردن گزینهٔ زیر، اگر مشتری نخواهد وارد/عضو شود، فقط شمارهٔ موبایلش را می‌گذارد —
      بدون کد تأیید — و مستقیم به مراحل ثبت آدرس و تکمیل سفارش می‌رود؛ حسابش پشت صحنه و
      خودکار ساخته می‌شود، دقیقا مثل مشتری‌های دیگر در پنل سفارش‌ها دیده می‌شود.
    </p>

    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="allow_guest_checkout" id="allow_guest_checkout" value="1"
             <?= getSettingRaw('allow_guest_checkout', '0') === '1' ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="allow_guest_checkout" style="margin:0;cursor:pointer;">خرید بدون ثبت‌نام (فقط شمارهٔ موبایل) مجاز باشد</label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin:-0.5rem 0 1rem;">
      با برداشتن تیک، رفتار قبلی برمی‌گردد: مشتری واردنشده برای ادامه به صفحهٔ ورود هدایت می‌شود.
    </div>

    <hr style="border:none;border-top:1px solid var(--border-color);margin:1.1rem 0;">

    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      <?= icon('info', 'ic-sm') ?>
      گزینهٔ زیر یک قدم فراتر می‌رود — سخت‌گیرتر از بالا: مشتری واردنشده حتی همان شمارهٔ
      موبایل را هم در ابتدا نمی‌گذارد؛ کلید «ادامه» مستقیم او را به مراحل بررسی عکس/ثبت
      آدرس و سفارش می‌برد (بدون هیچ صفحهٔ میانی). شمارهٔ تماس واقعی (برای هماهنگی ارسال)
      خود آن‌جا، در فرم ثبت سفارش گرفته می‌شود. <b>توجه:</b> حساب زیرین این مشتری گمنام
      می‌ماند (شماره‌ای که خودش نمی‌داند) — یعنی نمی‌تواند بعدا با همان مشخصات دوباره وارد
      شود یا سوابق خریدش را ببیند؛ هر سفارش را جدا در پنل سفارش‌ها (با شمارهٔ تماس درستش)
      می‌بینید. اگر هردو تیک روشن باشند، همین یکی برتری دارد (کم‌اصطکاک‌تر است).
    </p>
    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="allow_checkout_no_mobile" id="allow_checkout_no_mobile" value="1"
             <?= getSettingRaw('allow_checkout_no_mobile', '0') === '1' ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="allow_checkout_no_mobile" style="margin:0;cursor:pointer;">ثبت سفارش کاملا بدون گرفتن شمارهٔ موبایل در ورود مجاز باشد</label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin:-0.5rem 0 1.1rem;">
      با برداشتن تیک، این رفتار خاموش می‌شود و طبق تیک بالا (یا رفتار پیش‌فرض) عمل می‌شود.
    </div>

    <hr style="border:none;border-top:1px solid var(--border-color);margin:1.1rem 0;">

    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      <?= icon('info', 'ic-sm') ?>
      صفحهٔ ورود (<code dir="ltr">login.php</code>) دو تب مستقل دارد: «ورود مشتری» و «ورود همکار».
      با هرکدام از دو تیک زیر، همان تب کلا از صفحهٔ ورود برداشته می‌شود — فقط تب دیگر می‌ماند،
      بدون هیچ سردرگمی برای بازدیدکننده. اگر هردو را هم‌زمان تیک بزنید (که ورود را کلا می‌بندد)،
      هردو نادیده گرفته می‌شوند و مثل حالت پیش‌فرض هردو تب نشان داده می‌شود.
    </p>
    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="login_partner_disabled" id="login_partner_disabled" value="1"
             <?= getSettingRaw('login_partner_disabled', '0') === '1' ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="login_partner_disabled" style="margin:0;cursor:pointer;">غیرفعال کردن ورود همکار (فقط تب «ورود مشتری» نمایش داده شود)</label>
    </div>
    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;margin-top:0.6rem;">
      <input type="checkbox" name="login_retail_disabled" id="login_retail_disabled" value="1"
             <?= getSettingRaw('login_retail_disabled', '0') === '1' ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="login_retail_disabled" style="margin:0;cursor:pointer;">غیرفعال کردن ورود مشتری (فقط تب «ورود همکار» نمایش داده شود)</label>
    </div>
  </div>
  <?php endif; ?>

  <?php /* ---------- سال تولید خودرو ---------- */ ?>
  <?php /* ۲۰۲۶-۰۹-۰۳: دسترسی مجزا — قبلا همین کادر داخل تب «سال تولید
          خودرو» بود، خواستهٔ کاربر جداکردنش به تب مستقل خودش بود. */ ?>
  <?php if ($sec === 'partsteps'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('layers') ?> مراحل برند و مدل در «دسته‌بندی قطعات»</h3>
    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      کنترل همان دو مرحله‌ای که پیش از نمایش محصولات یک دسته‌ی قطعه در
      <a href="../parts.php" target="_blank">دسته‌بندی قطعات</a> می‌آیند. با خاموش‌کردن «مرحلهٔ برند»، محصولات آن دسته
      بدون نیاز به انتخاب برند مستقیم نشان داده می‌شوند — یعنی هرچه زیر آن دسته ثبت شده، همان‌جا دیده می‌شود.
    </p>
    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="parts_brand_step_enabled" id="parts_brand_step_enabled" value="1"
             <?= partsBrandStepEnabled() ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="parts_brand_step_enabled" style="margin:0;cursor:pointer;">مرحلهٔ «اول برند خودروتان را انتخاب کنید» نشان داده شود</label>
    </div>
    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="parts_model_step_enabled" id="parts_model_step_enabled" value="1"
             <?= partsModelStepEnabled() ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="parts_model_step_enabled" style="margin:0;cursor:pointer;">مرحلهٔ «مدل خودرو» نشان داده شود</label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin:0;line-height:1.8;">
      اگر «مرحلهٔ برند» خاموش باشد، این دسته دیگر اصلا برند نمی‌خواهد — محصولاتش را همان لحظه که دسته باز می‌شود می‌بینید.
      «مرحلهٔ مدل» مستقل است: فقط چیپ‌های مدل را پنهان می‌کند؛ اگر برند هنوز روشن باشد، بعد از انتخاب برند مستقیم به سال/محصولات می‌رسید.
    </div>
  </div>
  <?php endif; ?>

  <?php if ($sec === 'productyear'): $pyRange = productYearRange(); ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('calendar') ?> بازهٔ سال تولید خودرو</h3>

    <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
      <input type="checkbox" name="product_year_enabled" id="product_year_enabled" value="1"
             <?= productYearEnabled() ? 'checked' : '' ?>
             style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
      <label for="product_year_enabled" style="margin:0;cursor:pointer;">این قابلیت فعال باشد</label>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin:-0.5rem 0 1rem;">
      با برداشتن تیک، هم فیلد «سال تولید» از فرم محصول و هم چیپ‌های سال از فروشگاه پنهان می‌شوند — فقط مرحلهٔ
      انتخاب برند می‌ماند. سال‌هایی که قبلا برای محصولات ثبت کرده‌اید پاک نمی‌شوند، فقط پنهان می‌مانند تا دوباره روشن کنید.
    </div>

    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      بازهٔ زیر، فهرست چیپ‌های «سال تولید» را می‌سازد که در فروشگاه، بعد از انتخاب برند، به مشتری نشان داده می‌شود
      (<a href="../parts.php" target="_blank">دسته‌بندی قطعات</a>). با این دو عدد می‌توانید سال‌های نمایش‌داده‌شده را
      کم یا زیاد کنید — مثلا اگر خودروهای فروشگاهتان قدیمی‌تر هستند، «از سال» را عقب‌تر ببرید.
    </p>
    <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
      <div>
        <label>از سال (شمسی)</label>
        <input type="text" name="product_year_min" class="form-control" dir="ltr" inputmode="numeric" maxlength="4"
               value="<?= (int)$pyRange[0] ?>" style="width:120px;">
      </div>
      <div>
        <label>تا سال (شمسی)</label>
        <input type="text" name="product_year_max" class="form-control" dir="ltr" inputmode="numeric" maxlength="4"
               value="<?= (int)$pyRange[1] ?>" style="width:120px;">
      </div>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.35rem;line-height:1.8;">
      پیش‌فرض، از ۳۰ سال پیش تا امسال (شمسی) است. اگر عددی نامعتبر یا خالی وارد کنید، همین پیش‌فرض دوباره جایگزین می‌شود.
      این بازه فقط روی «فهرست چیپ‌ها»ی سال اثر دارد؛ محصولی که سالش خارج از این بازه ثبت شده باشد، همچنان با
      لینک مستقیم یا جست‌وجو قابل‌دیدن و خرید می‌ماند.
    </div>
  </div>
  <?php endif; ?>

  <?php /* ---------- شرایط و قوانین ---------- */ ?>
  <?php if ($sec === 'terms'): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('clipboard-list') ?> شرایط و قوانین سایت</h3>
    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1rem;">
      این متن در صفحهٔ <a href="../terms.php" target="_blank">شرایط و قوانین</a> (لینک فوتر سایت) به همه نمایش داده می‌شود.
      هر پاراگراف را در یک خط بنویسید؛ خط‌های خالی هم حفظ می‌شوند.
    </p>
    <div class="form-group">
      <label for="terms_content">متن شرایط و قوانین</label>
      <textarea name="terms_content" id="terms_content" class="form-control" rows="18"
                placeholder="مثلا: شرایط ارسال، مهلت مرجوعی، حریم خصوصی، نحوهٔ پرداخت و ..."><?= h(sv('terms_content')) ?></textarea>
    </div>
    <?php if (trim(sv('terms_content')) === ''): ?>
    <div class="flash" style="background:rgba(234,179,8,0.12);border:1px solid rgba(234,179,8,0.35);color:#FBBF24;font-size:0.78rem;">
      <?= icon('info', 'ic-sm') ?> هنوز متنی وارد نشده — صفحهٔ عمومی فعلا پیام «به‌زودی» نشان می‌دهد.
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary">ذخیرهٔ تنظیمات<?= ' — ' . h($sections[$sec]['label']) ?></button>
</form>

<?php /* ---------- فرم‌های «افزودن» — بیرون از فرم بزرگ ----------
         HTML اجازهٔ فرم تودرتو نمی‌دهد، پس این فرم‌ها بعد از </form> بالا
         می‌آیند. هیچ‌کدام کلید save_section را نمی‌فرستند تا بلوک ذخیرهٔ
         بخش اجرا نشود و کلیدهای متنی این بخش خالی نشوند.
         «افزودن روش ارسال» به بخش روش‌ها تعلق دارد و «افزودن نرخ یک شهر» به
         بخش نرخ‌نامه‌ها. */ ?>
<?php if ($sec === 'ship'): ?>
<div id="shipcrud">

  <?php if ($shipTbl): ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1.25rem;margin-top:1.5rem;">
    <h3 style="font-size:0.95rem;color:var(--red-primary);margin-bottom:1rem;"><?= icon('plus') ?> افزودن روش ارسال</h3>

    <form method="POST" action="settings.php?sec=ship">
      <input type="hidden" name="ship_add" value="1">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
        <div class="form-group" style="margin:0;">
          <label>نام روش <span style="color:var(--red-light);">*</span></label>
          <input type="text" name="new_label" class="form-control" placeholder="مثلا: ماهکس" required>
        </div>
        <div class="form-group" style="margin:0;">
          <label>آیکون</label>
          <select name="new_icon" class="form-control">
            <?php foreach ($shipIcons as $ik => $iLbl): ?>
            <option value="<?= h($ik) ?>"><?= h($iLbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label>یادآوری محدودیت (اختیاری)</label>
          <input type="text" name="new_badge" class="form-control" placeholder="مثلا: فقط برای شهر مشهد">
        </div>
        <div class="form-group" style="margin:0;">
          <label>کلید لاتین (اختیاری)</label>
          <input type="text" name="new_key" class="form-control" dir="ltr" placeholder="mahex">
        </div>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:1.25rem;margin:0.75rem 0;">
        <label class="cat-checkbox" style="font-size:0.85rem;">
          <input type="checkbox" name="new_collect" value="1"> پس‌کرایه (کرایه هنگام تحویل، بدون محاسبه)
        </label>
        <label class="cat-checkbox" style="font-size:0.85rem;">
          <input type="checkbox" name="new_cod" value="1"> پرداخت در محل مجاز است
        </label>
      </div>

      <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.75rem;line-height:1.9;">
        <?= icon('info', 'ic-sm') ?>
        روش جدید بلافاصله در صفحهٔ تسویه نمایش داده می‌شود (می‌توانید تیک «نمایش» را از جدول بالا بردارید).
        قیمتش را بعد از ساخت، در بخش <a href="settings.php?sec=shiprate"><b>نرخ‌نامه‌های ارسال</b></a> شهر به شهر وارد کنید.
        «کلید لاتین» همان چیزی است که در ستون سفارش‌ها ذخیره می‌شود؛ خالی بگذارید تا خودکار ساخته شود،
        و بعد از ساخت قابل تغییر نیست.
      </div>

      <button type="submit" class="btn btn-primary"><?= icon('plus', 'ic-sm') ?> افزودن روش ارسال</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ---------- بخش نرخ‌نامه: فرم افزودن + استپر + فهرست شهرها ---------- */ ?>
<?php if ($sec === 'shiprate'): ?>
<div id="ratecrud">

  <?php /* فرم افزودن، فشرده: همه فیلدها در یک ردیف باریک تا کادر کوتاه بماند.
           راهنمای بلند قبلی به <details> بالای صفحه («راهنمای محاسبه») منتقل شد. */ ?>
  <?php if ($shipRtOn): ?>
  <div class="rt-card rt-add">
    <div class="rt-top">
      <h3><?= icon('plus', 'ic-sm') ?> افزودن نرخ یک شهر</h3>
      <span class="rt-sum">هر شهر برای هر روش یک نرخ — شهر تکراری به‌روز می‌شود</span>
    </div>

    <form method="POST" action="settings.php?sec=shiprate" class="rt-form">
      <input type="hidden" name="rate_add" value="1">
      <div class="rt-grid">
        <div class="rt-f">
          <label>روش ارسال <span class="rt-req">*</span></label>
          <select name="r_method" class="form-control">
            <?php foreach ($rateMethods as $mk => $md): ?>
            <option value="<?= h($mk) ?>"><?= h($md['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($shipCityGroups): ?>
        <div class="rt-f">
          <label>شهر مقصد <span class="rt-req">*</span></label>
          <select name="r_city" class="form-control">
            <option value="">— انتخاب شهر (<?= (int)$shipCityCount ?> شهر) —</option>
            <?php foreach ($shipCityGroups as $prov => $cityList): ?>
            <optgroup label="<?= h($prov) ?>">
              <?php foreach ($cityList as $cName): ?>
              <option value="<?= h($cName) ?>"><?= h($cName) ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rt-f">
          <label>یا شهر تازه</label>
          <input type="text" name="r_city_new" class="form-control" placeholder="اگر در فهرست نبود">
        </div>
        <div class="rt-f">
          <label>استان شهر تازه</label>
          <input type="text" name="r_province" class="form-control" placeholder="اختیاری">
        </div>
        <?php else: ?>
        <div class="rt-f">
          <label>شهر مقصد <span class="rt-req">*</span></label>
          <input type="text" name="r_city_new" class="form-control" placeholder="مثلا: تبریز" required>
          <i class="rt-hint" style="color:#FCD34D;">فهرست شهرها ساخته نشده — <code dir="ltr">migrate-cityrates.php</code> را یک‌بار باز کنید.</i>
        </div>
        <?php endif; ?>

        <div class="rt-f">
          <label>هر چند کیلوگرم</label>
          <div class="wt-stepper wt-mini">
            <button type="button" class="wt-btn" data-wt="-1" title="کمتر">−</button>
            <input type="text" name="r_weight" class="form-control wt-field" dir="ltr" inputmode="decimal" value="1">
            <button type="button" class="wt-btn" data-wt="1" title="بیشتر">+</button>
          </div>
        </div>
        <div class="rt-f">
          <label>هزینهٔ هر واحد (تومان)</label>
          <input type="text" name="r_cost" class="form-control" dir="ltr" inputmode="numeric" placeholder="50000">
        </div>
        <div class="rt-f rt-f-go">
          <button type="submit" class="btn btn-primary btn-sm"><?= icon('plus', 'ic-sm') ?> ثبت نرخ</button>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php /* فهرست مشترک شهرها برای ورودی‌های متنی نرخ‌نامه (خودتکمیل) */ ?>
  <?php if ($shipCityGroups): ?>
  <datalist id="ship-city-list">
    <?php foreach ($shipCityGroups as $cityList): foreach ($cityList as $cName): ?>
    <option value="<?= h($cName) ?>"></option>
    <?php endforeach; endforeach; ?>
  </datalist>
  <?php endif; ?>

  <?php /* استپر +/− واحد وزن. پلهٔ ۰٫۵ کیلوگرم، هرگز کمتر از ۰٫۵.
           ارقام فارسی تایپ‌شده هم خوانده می‌شوند. */ ?>
  <script>
  (function () {
    var FA = '۰۱۲۳۴۵۶۷۸۹', AR = '٠١٢٣٤٥٦٧٨٩';
    function num(v) {
      v = String(v == null ? '' : v);
      var out = '';
      for (var i = 0; i < v.length; i++) {
        var ch = v.charAt(i), k = FA.indexOf(ch);
        if (k < 0) k = AR.indexOf(ch);
        out += (k >= 0) ? String(k) : ch;
      }
      out = out.replace(/[^0-9.]/g, '');
      var f = parseFloat(out);
      return isNaN(f) ? 0 : f;
    }
    function fmt(n) {
      n = Math.round(n * 100) / 100;
      var s = n.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
      return s === '' ? '0' : s;
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('.wt-btn') : null;
      if (!b) return;
      e.preventDefault();
      var box = b.closest('.wt-stepper');
      var f = box ? box.querySelector('.wt-field') : null;
      if (!f) return;
      var step = 0.5, dir = parseFloat(b.getAttribute('data-wt')) || 1;
      var v = num(f.value) + dir * step;
      if (v < step) v = step;
      f.value = fmt(v);
    });
  })();
  </script>

  <?php /* فیلتر سمت مرورگر برای جدول نرخ‌ها: تراشه‌های روش + جستجوی شهر.
           فقط نمایش را کم می‌کند؛ ردیف‌های پنهان همچنان با فرم ارسال می‌شوند
           (input‌ها داخل DOM می‌مانند) پس ذخیره چیزی را پاک نمی‌کند. */ ?>
  <script>
  (function () {
    var q = document.getElementById('rt-q');
    var rows = [].slice.call(document.querySelectorAll('.rt-row'));
    if (!rows.length) return;
    var chips = [].slice.call(document.querySelectorAll('.rt-chip'));
    var none = document.getElementById('rt-none');
    var FA = '۰۱۲۳۴۵۶۷۸۹', AR = '٠١٢٣٤٥٦٧٨٩';
    /* همان نرمال‌سازی shipNormCity در PHP: ی/ک عربی، اعراب و فاصله‌ها */
    function norm(s) {
      s = String(s == null ? '' : s).toLowerCase();
      var out = '';
      for (var i = 0; i < s.length; i++) {
        var ch = s.charAt(i), k = FA.indexOf(ch);
        if (k < 0) k = AR.indexOf(ch);
        if (k >= 0) { out += String(k); continue; }
        if (ch === 'ي') ch = 'ی';
        if (ch === 'ك') ch = 'ک';
        if (ch === 'ة') ch = 'ه';
        if (ch === 'أ' || ch === 'إ' || ch === 'آ') ch = 'ا';
        if (ch === '‌') ch = ' ';
        out += ch;
      }
      return out.replace(/\s+/g, ' ').trim();
    }
    var mk = '';
    function apply() {
      var t = norm(q ? q.value : ''), shown = 0;
      for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        var ok = (mk === '' || r.getAttribute('data-m') === mk) &&
                 (t === '' || norm(r.getAttribute('data-city')).indexOf(t) >= 0);
        r.hidden = !ok;
        if (ok) shown++;
      }
      if (none) none.hidden = (shown > 0);
    }
    if (q) q.addEventListener('input', apply);
    chips.forEach(function (c) {
      c.addEventListener('click', function () {
        mk = c.getAttribute('data-m') || '';
        chips.forEach(function (o) { o.classList.toggle('is-on', o === c); });
        apply();
      });
    });
    /* پرش نرم به فرم افزودن بدون تغییر آدرس */
    var jump = document.querySelector('.rt-jump');
    if (jump) jump.addEventListener('click', function (e) {
      var box = document.getElementById('ratecrud');
      if (!box) return;
      e.preventDefault();
      box.scrollIntoView({ behavior: 'smooth', block: 'start' });
      var f = box.querySelector('select, input');
      if (f) setTimeout(function () { f.focus(); }, 320);
    });
  })();
  </script>

</div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php';
