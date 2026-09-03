<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/icons.php';   // آیکون‌های SVG درون‌خطی — تابع icon()

function redirect($url) {
    header("Location: $url");
    exit;
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatPrice($price) {
    return number_format($price, 0, '.', ',') . ' تومان';
}

/* آیا درصد تخفیف معتبر است (۱ تا ۹۹) */
function hasDiscount($percent) {
    $p = (int)$percent;
    return $p > 0 && $p < 100;
}

/* قیمت پس از کسر درصد تخفیف (گرد‌شده) */
function discountedPrice($price, $percent) {
    if (!hasDiscount($percent)) return (int)$price;
    return (int)round((int)$price * (100 - (int)$percent) / 100);
}

/* بلوک قیمت کارت محصول با در نظر گرفتن تخفیف (قیمت قدیمی خط‌خورده + قیمت جدید) */
function productCardPrices($p) {
    $rOrig = (int)($p['retail_price'] ?? 0);
    $wOrig = (int)($p['wholesale_price'] ?? 0);
    $rd = (int)($p['retail_discount'] ?? 0);
    $wd = (int)($p['wholesale_discount'] ?? 0);

    if (hasDiscount($rd)) {
        $retail = 'جزئی: <s class="price-old">' . number_format($rOrig, 0, '.', ',') . '</s> '
                . '<span class="price-new">' . formatPrice(discountedPrice($rOrig, $rd)) . '</span>';
    } else {
        $retail = 'جزئی: ' . formatPrice($rOrig);
    }

    if (hasDiscount($wd)) {
        $whole = 'کلی: <s class="price-old">' . number_format($wOrig, 0, '.', ',') . '</s> '
               . '<span class="price-new">' . formatPrice(discountedPrice($wOrig, $wd)) . '</span>';
    } else {
        $whole = 'کلی: ' . formatPrice($wOrig);
    }

    return '<div class="product-card-prices">'
         . '<span class="price-retail">' . $retail . '</span>'
         . '<span class="price-wholesale">' . $whole . '</span>'
         . '</div>';
}

/* عدد + واحد «تومان» با فاصلهٔ خوانا (برای سبد خرید و پیش‌فاکتور).
   جدا از formatPrice نگه داشته شده چون خروجی HTML دارد و همه‌جا قابل استفاده نیست. */
function formatPriceUnit($price) {
    return '<span class="pnum">' . number_format((int)$price, 0, '.', ',') . '</span><span class="tmn">تومان</span>';
}

/* مقدار داخل «کارت قیمت» صفحهٔ محصول با در نظر گرفتن تخفیف */
function priceBoxValue($orig, $percent) {
    if (hasDiscount($percent)) {
        return '<span class="price-old">' . number_format((int)$orig, 0, '.', ',') . '</span> '
             . '<span class="price-new">' . formatPrice(discountedPrice($orig, $percent)) . '</span>';
    }
    return formatPrice($orig);
}

/* ===================== تنظیمات سایت (settings) ===================== */
function getAllSettings($refresh = false) {
    global $pdo;
    if (!$refresh && isset($GLOBALS['__settings_cache'])) return $GLOBALS['__settings_cache'];
    $out = [];
    try {
        foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $row) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) {
        $out = []; // جدول هنوز ساخته نشده
    }
    $GLOBALS['__settings_cache'] = $out;
    return $out;
}

function getSetting($key, $default = '') {
    $all = getAllSettings();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

/* حالت نمایش نام انگلیسی برند (اسلاگ) روی تگ‌های برند shop.php:
   'off' (پیش‌فرض، هیچ‌وقت نشان نده) / 'hover' (فقط با نگه‌داشتن موس) /
   'always' (همیشه به‌جای فارسی). از admin/categories.php تنظیم می‌شود —
   [[batch9-fixes]]. مقدار نامعتبر یا خالی → 'off'، نه خطا. */
function brandEnMode() {
    $v = getSettingRaw('brand_en_mode', 'off');
    return in_array($v, ['off', 'hover', 'always'], true) ? $v : 'off';
}

function getSettingRaw($key, $default = '') {
    $all = getAllSettings();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, (string)$value]);
    if (isset($GLOBALS['__settings_cache'])) $GLOBALS['__settings_cache'][$key] = (string)$value;
}

/* بخش‌های صفحهٔ تنظیمات ادمین — منبع یگانه برای نوار کشویی سایدبار
   (admin/layout-top.php) و خود صفحه (admin/settings.php?sec=...).
   هر بخش جدا نمایش و جدا ذخیره می‌شود، پس کلید بخش هم در آدرس و هم در فیلد
   مخفی فرم می‌آید تا هنگام ذخیره فقط کلیدهای همان بخش نوشته شوند. */
function settingsSections() {
    return [
        'footer' => ['label' => 'فوتر سایت',           'icon' => 'layers',      'title' => 'فوتر سایت'],
        'decor'  => ['label' => 'تزیینات صفحهٔ اصلی',  'icon' => 'palette',     'title' => 'تزیینات صفحهٔ اصلی'],
        'sms'    => ['label' => 'پیامک',               'icon' => 'mobile',      'title' => 'پیامک (SMS.ir) — کد ورود مشتریان'],
        'pay'    => ['label' => 'درگاه پرداخت',        'icon' => 'credit-card', 'title' => 'درگاه پرداخت'],
        /* پرداخت اعتباری/اقساطی از «درگاه پرداخت» جدا شد چون سرویس‌دهندهٔ
           متفاوتی دارد (اعتبارسنجی BNPL، نه دروازهٔ بانکی) و با هم بخش
           پرداخت را شلوغ می‌کردند — [[credit-payment-gateway]]. */
        'paycredit' => ['label' => 'پرداخت اعتباری / اقساطی', 'icon' => 'percent', 'title' => 'پرداخت اعتباری / اقساطی'],
        'ship'   => ['label' => 'روش‌های ارسال',       'icon' => 'truck',       'title' => 'روش‌های ارسال و هزینهٔ آن‌ها'],
        /* نرخ‌نامه صفحهٔ خودش را دارد چون با هر شهر تازه یک ردیف اضافه می‌شود و
           اگر کنار روش‌های ارسال بماند آن صفحه بی‌انتها بلند می‌شود. */
        'shiprate' => ['label' => 'نرخ‌نامه‌های ارسال', 'icon' => 'scale',     'title' => 'نرخ‌نامه‌های ارسال — شهر، واحد وزن و هزینه'],
        'pchk'   => ['label' => 'بررسی عکس قطعه',      'icon' => 'camera',      'title' => 'بررسی عکس نمونهٔ قطعه پیش از خرید'],
        /* ۲۰۲۶-۰۹-۰۳: تأیید موجودی از داخل «بررسی عکس قطعه» بیرون آمد و
           دسترسی مجزای خودش را گرفت (خواستهٔ کاربر) — قبلا هر دو کلید همان
           یک بخش بودند، اما موضوعا جداست: بررسی عکس یعنی «قطعه با خودرو
           مطابقت دارد»، تأیید موجودی یعنی «همین الان در انبار موجود است».
           هر دو همچنان در همان یک صفحهٔ ادمین (part-checks.php) بررسی
           می‌شوند — فقط تنظیمشان جدا شد، مثل partsteps/productyear. */
        'stockcheck' => ['label' => 'تأیید موجودی', 'icon' => 'package', 'title' => 'تأیید موجودی پیش از پرداخت'],
        'checkout' => ['label' => 'ثبت سفارش / ورود', 'icon' => 'login',       'title' => 'ثبت سفارش و ورود مشتری'],
        'productyear' => ['label' => 'سال تولید خودرو', 'icon' => 'calendar', 'title' => 'بازهٔ سال تولید خودرو در فروشگاه'],
        /* ۲۰۲۶-۰۹-۰۳: از داخل بخش «سال تولید خودرو» بیرون آمد و دسترسی
           مجزای خودش را گرفت (خواستهٔ کاربر) — قبلا هر دو کلید همان‌جا
           بودند، اما موضوعا جداست (مراحل ناوبری، نه بازهٔ سال). */
        'partsteps' => ['label' => 'مراحل برند و مدل', 'icon' => 'layers', 'title' => 'مراحل برند و مدل در دسته‌بندی قطعات'],
        'terms'  => ['label' => 'شرایط و قوانین',      'icon' => 'clipboard-list', 'title' => 'شرایط و قوانین سایت'],
    ];
}

/* کلید بخش پس از اعتبارسنجی (ورودی نامعتبر → اولین بخش) */
function settingsSectionKey($raw) {
    $secs = settingsSections();
    $raw = (string)$raw;
    return isset($secs[$raw]) ? $raw : 'footer';
}

/* ===================== شمارهٔ موبایل ===================== */
function faToLatinDigits($s) {
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $la = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $la, str_replace($fa, $la, (string)$s));
}

function normalizeMobile($m) {
    $m = faToLatinDigits($m);
    $m = preg_replace('/\D+/', '', $m);
    if (strpos($m, '0098') === 0) $m = substr($m, 4);
    elseif (strpos($m, '98') === 0 && strlen($m) === 12) $m = substr($m, 2);
    if (strlen($m) === 10 && isset($m[0]) && $m[0] === '9') $m = '0' . $m;
    return $m;
}

function isValidMobile($m) {
    return (bool)preg_match('/^09\d{9}$/', (string)$m);
}

/* href مناسب برای لینک tel: — فقط رقم و + (ارقام فارسی هم لاتین می‌شوند) */
function telHref($n) {
    return preg_replace('/[^\d+]/', '', faToLatinDigits((string)$n));
}

/* چند شماره در یک textarea (هر شماره یک خط، یا جداشده با کاما) → آرایهٔ تمیز.
   اگر کلید جمع خالی بود، به مقدار تکی قدیمی برمی‌گردد. */
function contactNumbers($pluralKey, $legacyKey) {
    $out = [];
    foreach (preg_split('/[\r\n,]+/', (string)getSettingRaw($pluralKey, '')) as $line) {
        $line = trim($line);
        if ($line !== '') $out[] = $line;
    }
    if (!$out) {
        $legacy = trim((string)getSetting($legacyKey, ''));
        if ($legacy !== '') $out[] = $legacy;
    }
    return $out;
}

/* ===================== حساب مشتری (بدون رمز - OTP) ===================== */
function isCustomerLoggedIn() {
    return isset($_SESSION['customer_id']);
}

function currentCustomer($refresh = false) {
    if (!isCustomerLoggedIn()) return null;
    if (!$refresh && array_key_exists('__customer_cache', $GLOBALS)) return $GLOBALS['__customer_cache'];
    global $pdo;
    $c = null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([(int)$_SESSION['customer_id']]);
        $c = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $c = null;
    }
    if (!$c) unset($_SESSION['customer_id']);
    $GLOBALS['__customer_cache'] = $c;
    return $c;
}

function requireCustomerLogin($returnTo = '') {
    if (!isCustomerLoggedIn()) {
        $q = $returnTo !== '' ? ('?return=' . urlencode($returnTo)) : '';
        redirect('login.php' . $q);
    }
}

/* آیا «خرید بدون ثبت‌نام» روشن است؟ (پنل مدیریت ← تنظیمات ← ثبت سفارش/ورود)
   با روشن‌بودن، کلید «ادامه»ی سبد خرید برای مشتری واردنشده به‌جای اجبار
   ورود/کد تأیید، فقط شمارهٔ موبایل می‌گیرد (guest-checkout.php) و بدون کد
   پیامکی، بی‌صدا او را وارد می‌کند؛ از همان‌جا مسیر همیشگی ادامه پیدا می‌کند —
   پس part-check.php/checkout.php/stock-check.php هیچ تغییری نمی‌خواهند. */
function guestCheckoutEnabled() {
    return getSettingRaw('allow_guest_checkout', '0') === '1';
}

function customerProfileComplete($c) {
    if (!$c) return false;
    return trim($c['full_name'] ?? '') !== ''
        && trim((string)($c['address'] ?? '')) !== ''
        && trim($c['postal_code'] ?? '') !== '';
}

/* $type: 'retail' (مشتری) یا 'partner' (همکار).
   نکته: قیمت‌گذاری کلی به این نوع وابسته نیست — همچنان با رسیدن تعداد به آستانه
   برای همه اعمال می‌شود. این فیلد فقط برای شناسایی، تفکیک در ادمین و آمار است. */
function findOrCreateCustomer($mobile, $type = 'retail') {
    global $pdo;
    $mobile = normalizeMobile($mobile);
    $type = ($type === 'partner') ? 'partner' : 'retail';
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE mobile = ?");
    $stmt->execute([$mobile]);
    $c = $stmt->fetch();
    if (!$c) {
        try {
            $pdo->prepare("INSERT INTO customers (mobile, customer_type, partner_status, partner_requested_at) VALUES (?, ?, ?, ?)")
                ->execute([$mobile, $type, $type === 'partner' ? 'pending' : 'none', $type === 'partner' ? date('Y-m-d H:i:s') : null]);
        } catch (Throwable $e) {
            // ستون‌های همکار هنوز ساخته نشده‌اند (dbsetup3 اجرا نشده) → ثبت‌نام ساده
            $pdo->prepare("INSERT INTO customers (mobile) VALUES (?)")->execute([$mobile]);
        }
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $c = $stmt->fetch();
    } elseif ($type === 'partner' && ($c['customer_type'] ?? 'retail') !== 'partner') {
        // مشتری موجود از «ورود همکار» آمده → درخواست همکاری ثبت می‌شود (بدون تغییر قیمت‌ها)
        try {
            $pdo->prepare("UPDATE customers SET customer_type = 'partner', partner_status = IF(partner_status = 'approved', 'approved', 'pending'), partner_requested_at = COALESCE(partner_requested_at, NOW()) WHERE id = ?")
                ->execute([(int)$c['id']]);
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([(int)$c['id']]);
            $c = $stmt->fetch();
        } catch (Throwable $e) {}
    }
    return $c;
}

/* برچسب فارسی نوع حساب */
function customerTypeLabel($c) {
    return (($c['customer_type'] ?? 'retail') === 'partner') ? 'همکار' : 'مشتری';
}

/* آیا حساب، همکار تأییدشده است؟ (فقط برای نشان «تأییدشده» — روی قیمت اثری ندارد) */
function isApprovedPartner($c) {
    return $c && ($c['customer_type'] ?? '') === 'partner' && ($c['partner_status'] ?? '') === 'approved';
}

/* ---------- سبد خرید و مالکیت آن ----------
   سبد خرید در نشست ذخیره می‌شود (`$_SESSION['cart']`) و نشست به مرورگر بسته است،
   نه به حساب کاربری. پس اگر کسی خارج شود و با حساب دیگری وارد شود، سبد حساب
   قبلی همان‌جا می‌ماند و به حساب تازه نشان داده می‌شود — باگ گزارش‌شدهٔ مدیر:
   «اگر از اکانتم خارج بشم و برم توی یک اکانت دیگه باز هم سبد خرید اکانت قبلی رو
   نشون میده».
   راه‌حل: کنار خود سبد، شناسهٔ صاحبش هم نگه داشته می‌شود (`cart_owner`؛ ۰ یا
   نبودنش یعنی «سبد مهمان»). قاعده:
   • سبد مهمان با ورود به همان حساب واگذار می‌شود — کسی که پیش از ورود سبد را پر
     کرده و سر تسویه وارد می‌شود نباید سبدش را از دست بدهد.
   • سبد حساب دیگر هرگز به حساب تازه نمی‌رسد؛ دور ریخته می‌شود.
   • خروج، سبد را هم می‌برد، وگرنه نفر بعدی روی همین مرورگر آن را می‌دید.
   روش ارسال انتخاب‌شده هم با سبد می‌رود (همان کاری که cartClear() می‌کند)،
   وگرنه سبد خالی حساب تازه با روش ارسال حساب قبلی شروع می‌شد. */
function cartForgetSession() {
    unset($_SESSION['cart'], $_SESSION['cart_owner'], $_SESSION['ship_method']);
}

/* نگهبان مالکیت، یک‌بار در هر درخواست (انتهای همین فایل صدا زده می‌شود).
   لایهٔ دوم همان قاعده است تا مسیرهای دیگر هم پوشیده شوند: نشستی که پیش از این
   تغییر ساخته شده (سبد دارد و `cart_owner` ندارد)، حسابی که از دیتابیس حذف شده
   و currentCustomer() شناسه‌اش را برداشته، یا هر ورودی که از loginCustomer()
   نگذرد. تا وقتی سبد خالی است و مالکی ثبت نشده، هیچ کاری نمی‌کند. */
function cartOwnerSync() {
    if (empty($_SESSION['cart']) && !isset($_SESSION['cart_owner'])) return;
    $now   = isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : 0;
    $owner = isset($_SESSION['cart_owner'])  ? (int)$_SESSION['cart_owner']  : 0;
    if ($owner === $now) return;                       // همان صاحب (یا هر دو مهمان)
    if ($owner !== 0) cartForgetSession();             // سبد حساب دیگر ⇒ دور ریخته می‌شود
    if ($now !== 0) $_SESSION['cart_owner'] = $now;    // سبد مهمان ⇒ واگذاری به همین حساب
}

function loginCustomer($customer) {
    global $pdo;
    $id = (int)$customer['id'];
    /* تصمیم درست‌ترین جا همین‌جاست: لحظهٔ عوض‌شدن حساب. (cartOwnerSync() در
       ابتدای درخواست با شناسهٔ حساب قبلی اجرا شده و این تغییر را ندیده.) */
    $owner = isset($_SESSION['cart_owner']) ? (int)$_SESSION['cart_owner'] : 0;
    if ($owner !== 0 && $owner !== $id) cartForgetSession();
    $_SESSION['customer_id'] = $id;
    $_SESSION['cart_owner']  = $id;
    try { $pdo->prepare("UPDATE customers SET last_login_at = NOW() WHERE id = ?")->execute([$id]); } catch (Throwable $e) {}
    unset($GLOBALS['__customer_cache']);
}

function logoutCustomer() {
    cartForgetSession();
    unset($_SESSION['customer_id']);
    unset($_SESSION['otp_mobile'], $_SESSION['otp_type'], $_SESSION['otp_return']);
    unset($GLOBALS['__customer_cache']);
}

/* ===================== کد یکبار‌مصرف (OTP) ===================== */
/* آیا ورود مشتری به کد تأیید پیامکی نیاز دارد؟
   کلید تنظیمات: login_otp_required — '1' یعنی کد لازم است (پیش‌فرض)،
   '0' یعنی ورود فقط با شمارهٔ موبایل و بدون تأیید شماره.
   ادمین آن را از «تنظیمات سایت ← پیامک» تغییر می‌دهد.
   نکتهٔ مهم: پیش‌فرض نبود کلید در جدول settings عمدا «لازم است» گذاشته شده
   تا روی نصب‌های قدیمی‌تر ورود بی‌تأیید به‌طور ناخواسته باز نشود. */
function loginOtpRequired() {
    return getSettingRaw('login_otp_required', '1') !== '0';
}

function otpGenerateAndSend($mobile) {
    global $pdo;
    $mobile = normalizeMobile($mobile);
    if (!isValidMobile($mobile)) return ['ok' => false, 'error' => 'شماره موبایل نامعتبر است.'];

    // پاک‌سازی ردیف‌های خیلی قدیمی (پنجرهٔ یک‌ساعته دست‌نخورده می‌ماند)
    $pdo->prepare("DELETE FROM customer_otps WHERE mobile = ? AND created_at < (NOW() - INTERVAL 1 DAY)")->execute([$mobile]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_otps WHERE mobile = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
    $stmt->execute([$mobile]);
    if ((int)$stmt->fetchColumn() >= 5) return ['ok' => false, 'error' => 'تعداد درخواست کد بیش از حد مجاز است. کمی بعد تلاش کنید.'];

    $stmt = $pdo->prepare("SELECT created_at FROM customer_otps WHERE mobile = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$mobile]);
    $last = $stmt->fetchColumn();
    if ($last && (time() - strtotime($last)) < 60) return ['ok' => false, 'error' => 'کمی صبر کنید و سپس دوباره کد بخواهید.'];

    $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO customer_otps (mobile, code_hash, expires_at, attempts) VALUES (?, ?, (NOW() + INTERVAL 2 MINUTE), 0)")
        ->execute([$mobile, $hash]);

    $send = smsSendOtp($mobile, $code);
    return ['ok' => true, 'mobile' => $mobile, 'test' => !empty($send['test']), 'send' => $send];
}

function otpVerify($mobile, $code) {
    global $pdo;
    $mobile = normalizeMobile($mobile);
    $code = preg_replace('/\D+/', '', faToLatinDigits($code));
    if (!isValidMobile($mobile)) return ['ok' => false, 'error' => 'شماره موبایل نامعتبر است.'];

    $stmt = $pdo->prepare("SELECT * FROM customer_otps WHERE mobile = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$mobile]);
    $row = $stmt->fetch();
    if (!$row) return ['ok' => false, 'error' => 'ابتدا کد تأیید را درخواست کنید.'];
    if (strtotime($row['expires_at']) < time()) return ['ok' => false, 'error' => 'کد منقضی شده است. دوباره درخواست دهید.'];
    if ((int)$row['attempts'] >= 5) return ['ok' => false, 'error' => 'تعداد تلاش‌های نادرست زیاد است. کد جدید بخواهید.'];

    if (!password_verify($code, $row['code_hash'])) {
        $pdo->prepare("UPDATE customer_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
        return ['ok' => false, 'error' => 'کد وارد‌شده صحیح نیست.'];
    }
    $pdo->prepare("DELETE FROM customer_otps WHERE mobile = ?")->execute([$mobile]);
    return ['ok' => true, 'mobile' => $mobile];
}

/* ===================== ارسال پیامک (SMS.ir) ===================== */
function smsSendOtp($mobile, $code) {
    $method     = getSetting('sms_method', 'bulk');   // 'bulk' (متن آزاد + شماره خط) یا 'verify' (قالب)
    $apiKey     = getSetting('sms_api_key', '');
    $testMode   = getSettingRaw('sms_test_mode', '1') === '1';
    $templateId = getSetting('sms_template_id', '');
    $lineNumber = preg_replace('/\D+/', '', faToLatinDigits(getSetting('sms_line_number', '')));

    // اگر پیکربندی کامل نباشد یا حالت آزمایشی روشن باشد → پیامک واقعی ارسال نمی‌شود
    $cannotSend = ($apiKey === '')
        || ($method === 'verify' && $templateId === '')
        || ($method === 'bulk'   && $lineNumber === '');

    if ($testMode || $cannotSend) {
        setSetting('sms_last_test', $mobile . ' → کد: ' . $code . ' @ ' . date('H:i:s'));
        smsLog("TEST mode | method=$method | mobile=$mobile | code=$code | (پیامک واقعی ارسال نشد)");
        return ['ok' => true, 'test' => true];
    }

    if ($method === 'bulk') {
        $text = getSetting('sms_otp_text', 'کد تأیید شما: {code}');
        $text = str_replace(['{code}', '{site}'], [(string)$code, defined('SITE_NAME') ? SITE_NAME : ''], $text);
        $endpoint = 'https://api.sms.ir/v1/send/bulk';
        $payload  = json_encode([
            'lineNumber'   => (int)$lineNumber,
            'messageText'  => $text,
            'mobiles'      => [$mobile],
            'sendDateTime' => null,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $paramName = getSetting('sms_param_name', 'CODE');
        $endpoint  = 'https://api.sms.ir/v1/send/verify';
        $payload   = json_encode([
            'mobile'     => $mobile,
            'templateId' => (int)$templateId,
            'parameters' => [ ['name' => $paramName, 'value' => (string)$code] ],
        ], JSON_UNESCAPED_UNICODE);
    }

    $res = httpPostJson($endpoint, $payload, [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-api-key: ' . $apiKey,
    ]);

    // موفقیت = کد HTTP 2xx و (نبود فیلد status یا status==1 در پاسخ SMS.ir)
    $apiOk = !empty($res['ok']);
    if ($apiOk && !empty($res['body'])) {
        $j = json_decode($res['body'], true);
        if (is_array($j) && array_key_exists('status', $j) && (int)$j['status'] !== 1) $apiOk = false;
    }
    if (!$apiOk) {
        smsLog("SEND FAIL | method=$method | mobile=$mobile | http=" . ($res['status'] ?? '?') . ' | ' . ($res['error'] ?? ($res['body'] ?? '')));
        return ['ok' => false, 'test' => false, 'error' => 'ارسال پیامک ناموفق بود.'];
    }
    smsLog("SEND OK | method=$method | mobile=$mobile | http=" . ($res['status'] ?? '?'));
    return ['ok' => true, 'test' => false];
}

/* ---------- اطلاع‌رسانی پیامکی مراحل روند ارسال ----------
   کلید اصلی: sms_track_enabled. اگر پنل پیامک شارژ نداشت، ادمین همین یک تیک را
   برمی‌دارد و هیچ پیامکی ارسال نمی‌شود (بقیهٔ سایت بی‌تغییر کار می‌کند). */
function smsTrackEnabled() {
    return getSettingRaw('sms_track_enabled', '1') === '1';
}

/* نقشهٔ «ستون مرحله → کلید تنظیمات + متن پیش‌فرض».
   منبع یگانه برای صفحهٔ تنظیمات و برای فرستنده، تا هر دو یک متن را بشناسند. */
function orderTrackSmsKeys() {
    return [
        'track_stock_at'           => ['key' => 'sms_track_stock',      'default' => 'موجودی سفارش {order} بررسی و تأیید شد؛ اکنون می‌توانید سفارش را پرداخت کنید. {site}'],
        'track_confirmed_at'       => ['key' => 'sms_track_confirmed',  'default' => 'سفارش شما با شمارهٔ {order} تأیید شد. {site}'],
        'track_collecting_at'      => ['key' => 'sms_track_collecting', 'default' => 'کالاهای سفارش {order} در حال جمع‌آوری است. {site}'],
        'track_finding_courier_at' => ['key' => 'sms_track_finding',    'default' => 'برای سفارش {order} در حال هماهنگی با پیک هستیم. {site}'],
        'track_courier_at'         => ['key' => 'sms_track_courier',    'default' => 'سفارش {order} به پیک تحویل شد. پیک: {courier}'],
        'track_post_at'            => ['key' => 'sms_track_post',       'default' => 'سفارش {order} به پست تحویل شد. کد رهگیری: {code}'],
        'track_shipped_at'         => ['key' => 'sms_track_shipped',    'default' => 'سفارش {order} ارسال شد. از خرید شما سپاسگزاریم. {site}'],
    ];
}

/* متن خام پیامک یک مرحله (بدون جایگزینی متغیرها). '' یعنی این مرحله پیامک ندارد.
   getSettingRaw (نه getSetting) تا اگر مدیر عمدا متن را خالی کند، همان «خاموش»
   بماند و پیش‌فرض برنگردد؛ پیش‌فرض فقط وقتی به کار می‌آید که کلید هنوز ساخته نشده. */
function smsTrackText($col) {
    $map = orderTrackSmsKeys();
    if (!isset($map[$col])) return '';
    return trim((string)getSettingRaw($map[$col]['key'], $map[$col]['default']));
}

/* ارسال یک پیامک متن آزاد. همیشه از سرویس bulk سرویس‌دهنده استفاده می‌شود
   چون «verify» فقط قالب ثابت می‌فرستد و متن مرحله متغیر است.
   در حالت آزمایشی یا پیکربندی ناقص، فقط در storage/sms.log ثبت می‌شود. */
function smsSendText($mobile, $text) {
    $mobile = preg_replace('/\D+/', '', faToLatinDigits((string)$mobile));
    $text   = trim((string)$text);
    if ($mobile === '' || $text === '') return ['ok' => false, 'error' => 'شماره یا متن خالی است.'];

    $apiKey     = getSetting('sms_api_key', '');
    $testMode   = getSettingRaw('sms_test_mode', '1') === '1';
    $lineNumber = preg_replace('/\D+/', '', faToLatinDigits(getSetting('sms_line_number', '')));

    if ($testMode || $apiKey === '' || $lineNumber === '') {
        smsLog("TRACK TEST | mobile=$mobile | text=$text | (پیامک واقعی ارسال نشد)");
        return ['ok' => true, 'test' => true];
    }

    $res = httpPostJson('https://api.sms.ir/v1/send/bulk', json_encode([
        'lineNumber'   => (int)$lineNumber,
        'messageText'  => $text,
        'mobiles'      => [$mobile],
        'sendDateTime' => null,
    ], JSON_UNESCAPED_UNICODE), [
        'Content-Type: application/json',
        'Accept: application/json',
        'x-api-key: ' . $apiKey,
    ]);

    $apiOk = !empty($res['ok']);
    if ($apiOk && !empty($res['body'])) {
        $j = json_decode($res['body'], true);
        if (is_array($j) && array_key_exists('status', $j) && (int)$j['status'] !== 1) $apiOk = false;
    }
    if (!$apiOk) {
        smsLog("TRACK FAIL | mobile=$mobile | http=" . ($res['status'] ?? '?') . ' | ' . ($res['error'] ?? ($res['body'] ?? '')));
        return ['ok' => false, 'error' => 'ارسال پیامک ناموفق بود.'];
    }
    smsLog("TRACK OK | mobile=$mobile | http=" . ($res['status'] ?? '?'));
    return ['ok' => true, 'test' => false];
}

/* پیامک یک مرحله برای مشتری همان سفارش.
   $order باید ردیف به‌روزشدهٔ سفارش باشد (تا {code} و {courier} تازه باشند).
   خروجی: [ok, test, error, skipped] — هیچ خطایی به بالا پرت نمی‌شود چون
   ناموفق‌بودن پیامک نباید ثبت مرحله را خراب کند. */
function smsNotifyTrackStep($order, $col) {
    if (!smsTrackEnabled()) return ['ok' => false, 'skipped' => 'off'];

    $text = smsTrackText($col);
    if ($text === '') return ['ok' => false, 'skipped' => 'no-text'];

    $mobile = (string)($order['customer_mobile'] ?? '');
    if (trim($mobile) === '') return ['ok' => false, 'skipped' => 'no-mobile'];

    $code    = trim((string)($order['post_tracking_code'] ?? ''));
    $courier = trim((string)($order['courier_name'] ?? ''));
    $phone   = trim((string)($order['courier_phone'] ?? ''));
    if ($courier !== '' && $phone !== '') $courier .= ' - ' . $phone;
    elseif ($courier === '') $courier = $phone;

    $text = str_replace(
        ['{order}', '{name}', '{site}', '{code}', '{courier}'],
        [(int)($order['id'] ?? 0), (string)($order['customer_name'] ?? ''),
         defined('SITE_NAME') ? SITE_NAME : '', $code, $courier],
        $text
    );

    try { return smsSendText($mobile, $text); }
    catch (Throwable $e) {
        smsLog('TRACK EXCEPTION | ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function smsLog($line) {
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    @file_put_contents($dir . '/sms.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND);
}

function httpPostJson($url, $payload, $headers) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $body = curl_exec($ch);
        if ($body === false) { $err = curl_error($ch); curl_close($ch); return ['ok' => false, 'error' => $err]; }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body];
    }
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $payload,
        'timeout'       => 15,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return ['ok' => false, 'error' => 'request failed'];
    $status = 200;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $mm)) $status = (int)$mm[1];
    return ['ok' => ($status >= 200 && $status < 300), 'status' => $status, 'body' => $body];
}

function slugify($text) {
    $text = preg_replace('/[^a-zA-Z0-9\x{0600}-\x{06FF}\s-]/u', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return mb_strtolower($text);
}

function getCategories($parentId = null) {
    global $pdo;
    if ($parentId === null) {
        $stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
        $stmt->execute([$parentId]);
    }
    return $stmt->fetchAll();
}

function getAllBrands() {
    global $pdo;
    return $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();
}

function getSubCategories($brandId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
    $stmt->execute([$brandId]);
    return $stmt->fetchAll();
}

/* آیا ستون «لوگوی برند» روی categories ساخته شده؟ (migrate-brandlogo.php) */
function categoryLogoReady() {
    if (isset($GLOBALS['__catlogo_ready'])) return $GLOBALS['__catlogo_ready'];
    return $GLOBALS['__catlogo_ready'] = dbHasColumn('categories', 'logo');
}

/* ۲۰۲۶-۰۹-۰۳: لوگوی خام آپلودی را به یک PNG شفاف تمیز تبدیل می‌کند —
   خواستهٔ کاربر: «هر لوگویی که وارد میکنم خودت مرتبش کن و با فرمت درست
   نشونش بده». نمایش سایت (filter: brightness(0) invert(1) روی .pby-brand-logo/
   .mm-brand-icon، پس‌زمینه‌های تیره) فقط وقتی درست دیده می‌شود که خود
   تصویر پس‌زمینهٔ شفاف داشته باشد؛ خیلی از لوگوهای آپلودی (JPG همیشه،
   PNG/WebP هم گاهی) پس‌زمینهٔ سفید/توپر دارند و بدون این تبدیل، فیلتر
   سفیدکننده کل تصویر را یک بلوک سفید نشان می‌داد.
   الگوریتم: رنگ هر گوشه را می‌گیرد و از همان چهار گوشه flood-fill شفاف
   می‌کند (fuzz=~18% تا فشردگی JPEG/نویز لبه هم پوشش داده شود) — فقط
   ناحیهٔ بیرونی متصل به گوشه‌ها پاک می‌شود، نه هر پیکسل هم‌رنگ در کل
   تصویر، پس رنگ‌های داخلی خود لوگو دست‌نخورده می‌مانند. روی لوگویی که
   از قبل شفاف است هم بی‌خطر است (پرکردن شفاف با شفاف کاری نمی‌کند)، پس
   عمدا همیشه بدون گارد «آیا لازم است» اجرا می‌شود — خواندن آلفای هر
   پیکسل از طریق Imagick برای PNG‌های ایندکس‌شده/بدون‌کانال‌آلفای
   واقعی قابل‌اعتماد نبود (چند لوگوی واقعا توپر اشتباهی «شفاف» تشخیص
   داده می‌شدند)، پس این گارد برداشته شد. بعد trim می‌کند تا حاشیهٔ اضافه
   هم برود. برمی‌گرداند: true اگر موفق بود (نتیجه در $destPath نوشته
   شده)، وگرنه false (آپلود خام حفظ می‌شود). */
function normalizeBrandLogo($srcPath, $destPath) {
    if (!extension_loaded('imagick') || !is_file($srcPath)) return false;
    try {
        $img = new Imagick($srcPath);
        $img->setImageFormat('png32');
        if (!$img->getImageAlphaChannel()) {
            $img->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        }
        $w = $img->getImageWidth();
        $h = $img->getImageHeight();
        if ($w < 2 || $h < 2) { $img->clear(); return false; }

        $corners = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
        $fill = new ImagickPixel('transparent');
        foreach ($corners as $c) {
            $target = $img->getImagePixelColor($c[0], $c[1]);
            $img->floodFillPaintImage($fill, 12000, $target, $c[0], $c[1], false);
        }
        $img->trimImage(0);
        $img->setImagePage(0, 0, 0, 0);

        /* گارد: اگر flood-fill کل تصویر را خورد (لوگویی یکدست بدون تباین
           کافی)، نتیجه بی‌معنا می‌شود — همان خام آپلودی بهتر از هیچ‌چیز است. */
        if ($img->getImageWidth() < 2 || $img->getImageHeight() < 2) { $img->clear(); return false; }

        $ok = $img->writeImage($destPath);
        $img->clear();
        return (bool)$ok;
    } catch (Throwable $e) {
        return false;
    }
}

/* مسیر لوگوی یک برند، نسبت به ریشهٔ سایت (بدون «../» — هر صفحه خودش اضافه
   می‌کند اگر داخل admin/ باشد). اول لوگوی آپلودی از پنل (uploads/brands/…)
   را می‌گیرد؛ اگر نبود، به قرارداد قدیمی سایت برمی‌گردد: یک فایل SVG که
   دستی با نام اسلاگ برند در assets/images/brands/ گذاشته شده. هیچ‌کدام
   نبود، رشتهٔ خالی برمی‌گرداند تا آیکون عمومی جایگزین شود. */
function brandLogoSrc($brand) {
    if (categoryLogoReady() && !empty($brand['logo'])) {
        $path = 'uploads/brands/' . $brand['logo'];
        if (is_file(__DIR__ . '/../' . $path)) return $path;
    }
    $slug = (string)($brand['slug'] ?? '');
    if ($slug !== '') {
        $path = 'assets/images/brands/' . $slug . '.svg';
        if (is_file(__DIR__ . '/../' . $path)) return $path;
    }
    return '';
}

/* آیا ستون‌های «سال تولید خودرو» روی محصولات ساخته شده‌اند؟ (migrate-productyear.php) */
function productYearReady() {
    if (isset($GLOBALS['__pyear_ready'])) return $GLOBALS['__pyear_ready'];
    return $GLOBALS['__pyear_ready'] = dbHasColumn('products', 'year_from');
}

/* ۲۰۲۶-۰۹-۰۳: آیا ستون‌های «بدون نیاز به برند/مدل/سال خودرو» روی products
   ساخته شده‌اند؟ (migrate-productuniversal.php). خواستهٔ کاربر: بعضی
   محصولات (مثل لوازم عمومی) اصلا به یک خودروی خاص مقید نیستند — با
   تیک‌زدن این‌ها در فرم محصول، آن محصول در فروشگاه/دسته‌بندی قطعات
   بدون توجه به برند/مدل/سال انتخاب‌شده هم دیده می‌شود. */
function productUniversalReady() {
    if (isset($GLOBALS['__puniv_ready'])) return $GLOBALS['__puniv_ready'];
    return $GLOBALS['__puniv_ready'] = dbHasColumn('products', 'no_brand_required');
}

/* آیا قابلیت «سال تولید» روشن است؟ (پنل مدیریت ← تنظیمات ← سال تولید خودرو).
   با خاموش‌بودن، هم فیلد سال در فرم محصول و هم چیپ‌های سال در فروشگاه
   پنهان می‌شوند — فقط مرحلهٔ انتخاب برند می‌ماند. دادهٔ سال محصولات قبلی
   پاک نمی‌شود، فقط پنهان می‌ماند تا دوباره روشن شود. پیش‌فرض روشن است تا
   نصب‌هایی که همین حالا این قابلیت را می‌بینند رفتارشان عوض نشود. */
function productYearEnabled() {
    return getSettingRaw('product_year_enabled', '1') === '1';
}

/* ۲۰۲۶-۰۹-۰۳: آیا مرحلهٔ «اول برند خودروتان را انتخاب کنید» در parts.php
   نشان داده شود؟ خاموش‌بودن یعنی گیت برند به‌کلی برداشته می‌شود — محصولات
   همان دستهٔ قطعه بدون نیاز به انتخاب برند مستقیم نشان داده می‌شوند
   (خواستهٔ کاربر: «هرچی که زیرشاخهٔ این دسته‌بندی وارد شده رو نشون بده»).
   پیش‌فرض روشن (رفتار فعلی حفظ می‌شود). */
function partsBrandStepEnabled() {
    return getSettingRaw('parts_brand_step_enabled', '1') === '1';
}

/* آیا مرحلهٔ «مدل خودرو» نشان داده شود؟ خاموش‌بودن فقط چیپ‌های مدل را
   پنهان می‌کند؛ برند هنوز لازم است (اگر partsBrandStepEnabled() روشن
   باشد) — انتخاب مدل از قبل هم اختیاری بود، این کلید فقط خود UI را
   پنهان می‌کند. */
function partsModelStepEnabled() {
    return getSettingRaw('parts_model_step_enabled', '1') === '1';
}

/* بازهٔ سال تولید (از پنل مدیریت ← تنظیمات ← سال تولید خودرو، تنظیم‌شده با
   product_year_min/max)؛ پیش‌فرض ۳۰ سال اخیر شمسی، یعنی مدیر تا وقتی چیزی
   تنظیم نکرده همان رفتار قبلی را می‌بیند. */
function productYearRange() {
    $today = jalaliToday()[0];
    $min = (int)faToLatinDigits((string)getSettingRaw('product_year_min', (string)($today - 30)));
    $max = (int)faToLatinDigits((string)getSettingRaw('product_year_max', (string)$today));
    if ($min <= 0) $min = $today - 30;
    if ($max <= 0) $max = $today;
    if ($min > $max) { $tmp = $min; $min = $max; $max = $tmp; }
    return [$min, $max];
}

/* گزینه‌های سال تولید برای انتخابگر سمت فروشگاه — نزولی (جدیدترین بالا)،
   دقیقا همان بازه‌ای که مدیر تنظیم کرده (productYearRange). اگر محصولی
   خارج از این بازه سالی ثبت شده باشد باز هم قابل‌خریداری می‌ماند (فقط
   چیپش در انتخابگر دیده نمی‌شود)، چون فیلتر getProducts() جدا از این
   فهرست عمل می‌کند. */
function productYearOptions() {
    list($start, $end) = productYearRange();
    return range($end, $start);
}

function getProductById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getProductCategories($productId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.* FROM categories c
        JOIN product_categories pc ON c.id = pc.category_id
        WHERE pc.product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/* تصاویر گالری محصول (کاروسل صفحهٔ محصول) — ترتیب با id پایدار می‌شود تا اگر
   چند ردیف sort_order یکسان داشتند، خروجی بین درخواست‌ها جابه‌جا نشود. */
function getProductImages($productId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/* ===================== گاردهای تدریجی (تا پیش از اجرای dbsetup8، صفحات ۵۰۰ نشوند) =====================
   الگو مطابق paymentReady(): نتیجه در $GLOBALS کش می‌شود. روی این MariaDB باید از
   information_schema استفاده کرد (SHOW COLUMNS ... LIKE ? خطای ۱۰۶۴ می‌دهد). */
function dbHasColumn($table, $column) {
    global $pdo;
    $key = '__col_' . $table . '_' . $column;
    if (array_key_exists($key, $GLOBALS)) return $GLOBALS[$key];
    $ok = false;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        $ok = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) { $ok = false; }
    return $GLOBALS[$key] = $ok;
}

function dbHasTable($table) {
    global $pdo;
    $key = '__tbl_' . $table;
    if (array_key_exists($key, $GLOBALS)) return $GLOBALS[$key];
    $ok = false;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        $ok = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) { $ok = false; }
    return $GLOBALS[$key] = $ok;
}

/* آیا لایهٔ پیگیری سفارش (ستون‌های track_*) روی جدول orders ساخته شده؟ */
function trackingReady() {
    return dbHasColumn('orders', 'track_confirmed_at');
}

/* مرحلهٔ «تحویل به پست» + کد رهگیری (migrate-tracking2.php).
   جدا از trackingReady() چک می‌شود تا اگر فقط مهاجرت قبلی اجرا شده باشد،
   بقیهٔ روند ارسال سر جایش بماند و فقط این یک مرحله نشان داده نشود
   (وگرنه هنگام ذخیره به ستونی نوشته می‌شد که وجود ندارد). */
function trackingPostReady() {
    return dbHasColumn('orders', 'track_post_at');
}

/* «بررسی موجودی» — همیشه اولین مرحلهٔ روند ارسال (migrate-stockcheck.php).
   جدا از trackingReady() سنجیده می‌شود، هم‌الگوی trackingPostReady() بالا. */
function trackStockReady() {
    return trackingReady() && dbHasColumn('orders', 'track_stock_at');
}

/* آیا پرداخت این سفارش باز است؟ پیش از مهاجرت (یا وقتی روند ارسال اصلا
   فعال نیست) گیت خاموش می‌ماند — همان رفتار قبلی (پرداخت بی‌درنگ) — تا
   نصب‌های قدیمی‌تر نشکنند. بعد از مهاجرت: تا مدیر «بررسی موجودی» را تیک
   نزند (یا این مرحله خودکار طی نشده باشد)، هیچ روش پرداختی — حتی درگاه
   بانکی — برای مشتری در دسترس نیست (خواستهٔ مدیر). */
function orderPaymentUnlocked($order) {
    if (!trackStockReady()) return true;
    return !empty($order['track_stock_at']);
}

/* اگر «بررسی عکس نمونهٔ قطعه» همین سفارش را از قبل approved و stock_ok=1
   کرده، گیت «بررسی موجودی» خودکار طی می‌شود — کاری که مدیر همان‌جا انجام
   داده، دوباره از او خواسته نمی‌شود. بعد از partCheckAttachToOrder() در
   checkout.php صدا زده می‌شود. */
function orderAutoPassStockCheck($orderId) {
    global $pdo;
    if (!trackStockReady()) return;
    $pc = partCheckForOrder((int)$orderId);
    if (!$pc || partCheckStockStatus($pc) !== 'approved') return;
    try {
        $pdo->prepare("UPDATE orders SET track_stock_at = NOW() WHERE id = ? AND track_stock_at IS NULL")
            ->execute([(int)$orderId]);
    } catch (Throwable $e) {}
}

/* آیا تگ «فروش ویژه» روی جدول products ساخته شده؟ */
function specialSaleReady() {
    return dbHasColumn('products', 'is_special');
}

/* آیا جدول‌های نظرات و پرسش‌وپاسخ ساخته شده‌اند؟ */
function reviewsReady() {
    return dbHasTable('product_reviews') && dbHasTable('product_qa');
}

/* آیا جدول آفر زمان‌دار ساخته شده؟ */
function timedOffersReady() {
    return dbHasTable('timed_offers');
}

/* آیا نشان «فروخته شد» روی جدول آفرها ساخته شده؟ */
function timedOffersSoldReady() {
    return timedOffersReady() && dbHasColumn('timed_offers', 'is_sold');
}

/* آیا جدول‌های «تأیید عکس نمونهٔ قطعه» ساخته شده‌اند؟ (migrate-partcheck.php) */
function partChecksReady() {
    return dbHasTable('part_checks') && dbHasTable('part_check_images');
}

/* آیا ستون‌های «تأیید موجودی، جدا از بررسی عکس» ساخته شده‌اند؟
   (migrate-partcheck-split.php، ۲۰۲۶-۰۹-۰۳) — از این نقطه به بعد، «مطابقت
   قطعه» (status) و «موجودی» (stock_status) دو وضعیت کاملا مستقل روی همان
   ردیف part_checks‌اند، هرکدام با ادمین/صف/تب‌های خودشان، تا دو همکار جدا
   بتوانند هرکدام را بی‌نیاز از دیگری ببینند و تأیید کنند. */
function partCheckStockSplitReady() {
    return partChecksReady() && dbHasColumn('part_checks', 'stock_status');
}

/* وضعیت «موجودی» یک ردیف part_checks — pending/approved/rejected.
   اگر مهاجرت split هنوز اجرا نشده (لحظهٔ کوتاه میان دیپلوی کد و اجرای
   migrate-partcheck-split.php)، از ستون قدیمی stock_ok مشتق می‌شود تا سایت
   نشکند. */
function partCheckStockStatus($row) {
    if (!$row) return 'pending';
    if (partCheckStockSplitReady()) return (string)($row['stock_status'] ?? 'pending');
    return !empty($row['stock_ok']) ? 'approved' : 'pending';
}

/* ===================== تأیید عکس نمونهٔ قطعه =====================
   مرحله‌ای اختیاری میان سبد خرید و ثبت سفارش: مشتری چند عکس از زوایای مختلف
   قطعهٔ موردنیازش می‌فرستد، مدیر با محصول سبد مقایسه می‌کند و در همان صفحه
   موجودی را هم تأیید می‌کند؛ سپس مشتری «مرحلهٔ بعد» را می‌زند و به ثبت سفارش
   و پرداخت می‌رود. چون مرجوعی ممکن نیست، این مرحله جلوی خرید قطعهٔ اشتباه را
   می‌گیرد. مشتری می‌تواند این مرحله را رد کند (کلید «رد کردن»).
   کل مرحله با کلید partcheck_enabled در تنظیمات خاموش می‌شود. */

/* آیا این مرحله برای مشتری فعال است؟ (جدول‌ها ساخته شده و مدیر خاموش نکرده) */
function partCheckOn() {
    return partChecksReady() && getSettingRaw('partcheck_enabled', '1') === '1';
}

/* حداقل تعداد عکس — پیش‌فرض خواستهٔ مدیر: سه زاویهٔ مختلف */
function partCheckMinPhotos() {
    /* پیش‌فرض ۱ (خواستهٔ کاربر: «برای سه تا عکس اجبار نباشه») — ادمین همچنان
       می‌تواند در تنظیمات عدد بالاتر بگذارد. */
    $n = (int)faToLatinDigits((string)getSettingRaw('partcheck_min_photos', '1'));
    return max(1, min(8, $n ?: 1));
}

/* امضای کالاهای سبد (فقط شناسهٔ محصول‌ها، نه تعداد) — کم/زیادکردن تعداد
   تأیید گرفته‌شده را باطل نمی‌کند، ولی افزودن قطعهٔ تازه بله. */
function partCheckCartSig($cartItems) {
    $ids = [];
    foreach ((array)$cartItems as $it) {
        $pid = (int)($it['product']['id'] ?? 0);
        if ($pid > 0) $ids[$pid] = true;
    }
    if (!$ids) return '';
    $ids = array_keys($ids);
    sort($ids, SORT_NUMERIC);
    return md5(implode(',', $ids));
}

/* آخرین درخواست باز (بی‌سفارش) این مشتری */
function partCheckCurrent($customerId) {
    global $pdo;
    if (!partChecksReady() || (int)$customerId <= 0) return null;
    try {
        $st = $pdo->prepare("SELECT * FROM part_checks
                             WHERE customer_id = ? AND order_id IS NULL
                             ORDER BY id DESC LIMIT 1");
        $st->execute([(int)$customerId]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

/* عکس‌های یک درخواست */
function partCheckImages($checkId) {
    global $pdo;
    if (!partChecksReady() || (int)$checkId <= 0) return [];
    try {
        $st = $pdo->prepare("SELECT * FROM part_check_images WHERE check_id = ? ORDER BY sort_order, id");
        $st->execute([(int)$checkId]);
        return $st->fetchAll() ?: [];
    } catch (Throwable $e) { return []; }
}

function partCheckStatusLabels() {
    return ['pending'  => 'در انتظار بررسی مدیر',
            'approved' => 'تأیید شد',
            'rejected' => 'تأیید نشد'];
}

function partCheckStatusLabel($status) {
    $m = partCheckStatusLabels();
    return $m[(string)$status] ?? (string)$status;
}

/* آیا الزام «تأیید موجودی» (نه فقط مطابقت عکس) برای رد‌شدن از این مرحله
   فعال است؟ کلید مستقل از خود partCheckOn() — ادمین می‌تواند کل مرحله را
   نگه دارد ولی این الزام را خاموش کند (برگشت به رفتار پیش‌از ۲۰۲۶-۰۸-۳۰:
   تأیید مطابقت عکس به‌تنهایی کافی بود). پیش‌فرض روشن. */
function partCheckStockRequired() {
    return getSettingRaw('partcheck_require_stock', '1') === '1';
}

/* آیا گیت «تأیید موجودی» فعال است — مستقل از «بررسی عکس قطعه»؟
   ۲۰۲۶-۰۹-۰۳ (خواستهٔ کاربر): این دو در تنظیمات از هم جدا شدند و حالا
   تأیید موجودی می‌تواند بدون الزام فرستادن عکس هم فعال باشد — یعنی
   partCheckOn() (بررسی عکس) می‌تواند خاموش بماند ولی این یکی روشن باشد. */
function stockGateActive() {
    return partChecksReady() && partCheckStockRequired();
}

/* آیا مشتری می‌تواند از این مرحله بگذرد؟
   «آری» یعنی درخواستش برای همین سبد مطابقتش تأیید شده، و — اگر
   partCheckStockRequired() روشن باشد (پیش‌فرض) — موجودی‌اش هم، یعنی
   status='approved' و stock_ok=1 با هم (خواستهٔ کاربر ۲۰۲۶-۰۸-۳۰: «در
   انتظار بررسی موجودی» یک گیت کاملا مسدودکننده باشد، بدون راه فرار).
   پیش از این تاریخ، «رد کردن مرحله» با یک پرچم session بی‌درنگ رد می‌شد —
   آن مسیر حذف شد: حالا رد کردن هم یک ردیف part_checks (بدون عکس) می‌سازد و
   وارد همین صف/گیت می‌شود، پس دیگر بایپس ندارد — مگر اینکه ادمین کل مرحله
   را از partCheckOn() خاموش کند (آن‌وقت این تابع بی‌درنگ true برمی‌گرداند و
   مشتری از سبد مستقیم به تسویه‌حساب می‌رود). ۲۰۲۶-۰۹-۰۳: با روشن‌بودن
   stockGateActive() به‌تنهایی (بررسی عکس خاموش)، همین شرط برقرار می‌ماند —
   فقط ردیفش را stockCheckEnsureRow() بی‌صدا و بدون عکس می‌سازد. */
function partCheckPassed($cartItems, $customer = null) {
    if (!partCheckOn() && !stockGateActive()) return true;
    $sig = partCheckCartSig($cartItems);
    $cid = (int)($customer['id'] ?? ($_SESSION['customer_id'] ?? 0));
    $row = partCheckCurrent($cid);
    if (!$row) return false;
    /* ۲۰۲۶-۰۹-۰۳: «مطابقت قطعه» و «موجودی» حالا دو وضعیت مستقل‌اند (هرکدام
       صف/ادمین خودش را دارد) — هرکدام فقط وقتی همان مرحله‌اش فعال است سنجیده
       می‌شود، نه هردو همیشه با هم. */
    if (partCheckOn() && (string)$row['status'] !== 'approved') return false;
    if (stockGateActive() && partCheckStockStatus($row) !== 'approved') return false;
    /* تأیید قطعهٔ دیگری به این سبد منتقل نمی‌شود */
    return ((string)$row['cart_sig'] === $sig) || (string)$row['cart_sig'] === '';
}

/* حالت «فقط تأیید موجودی» (بررسی عکس خاموش، تأیید موجودی روشن): مشتری
   نباید مجبور به دیدن فرم آپلود part-check.php شود. اگر ردیف در-انتظار
   منطبقی برای همین سبد نبود، بی‌صدا یکی می‌سازیم — دقیقا هم‌الگوی «رد
   کردن» در part-check.php، فقط خودکار و بدون اقدام مشتری؛ در صف ادمین
   (part-checks.php) عادی دیده و تأیید می‌شود. */
function stockCheckEnsureRow($cartItems, $customer) {
    global $pdo;
    if (!stockGateActive()) return;
    $cid = (int)($customer['id'] ?? 0);
    if ($cid <= 0) return;
    $sig = partCheckCartSig($cartItems);
    $cur = partCheckCurrent($cid);
    $already = $cur && (string)$cur['status'] === 'pending'
             && ((string)$cur['cart_sig'] === $sig || (string)$cur['cart_sig'] === '');
    if ($already) return;
    $firstPid = null;
    foreach ((array)$cartItems as $it) { $firstPid = (int)($it['product']['id'] ?? 0) ?: null; if ($firstPid) break; }
    $note = '(فقط تأیید موجودی — بررسی عکس برای این سفارش فعال نیست)';
    try {
        /* photo_required=0: این ردیف صرفا برای موجودی است و نباید در صف
           «بررسی عکس قطعه» (admin/part-checks.php) دیده شود — با
           partCheckStockSplitReady() هم‌زمان مهاجرت شده (migrate-partcheck-split.php).
           تا وقتی آن ستون نیست، به INSERT سادهٔ قدیمی برمی‌گردیم. */
        if (partCheckStockSplitReady()) {
            $pdo->prepare("INSERT INTO part_checks
                    (customer_id, product_id, cart_sig, car_info, note, status, photo_required)
                    VALUES (?,?,?,?,?, 'pending', 0)")
                ->execute([$cid, $firstPid, $sig, '', $note]);
        } else {
            $pdo->prepare("INSERT INTO part_checks
                    (customer_id, product_id, cart_sig, car_info, note, status)
                    VALUES (?,?,?,?,?, 'pending')")
                ->execute([$cid, $firstPid, $sig, '', $note]);
        }
    } catch (Throwable $e) {}
}

/* اگر مشتری هنوز از این مرحله نگذشته، باید به کدام صفحه برود؟ ۲۰۲۶-۰۸-۳۰:
   مسیر ۴ گامی شد (سبد → بررسی عکس → بررسی موجودی → ثبت سفارش)، پس یک گیت
   واحد دیگر کافی نیست — باید بین دو صفحهٔ مقصد یکی را انتخاب کرد:
   - هنوز درخواستی برای همین سبد نساخته (یا رد شده) ⇒ part-check.php
     (اقدام اول: آپلود یا رد کردن)
   - درخواست ساخته شده و pending/approved است (فقط منتظر ادمین) ⇒ stock-check.php
   خروجی '' یعنی گذشته، ریدایرکتی لازم نیست. */
function partCheckGateUrl($cartItems, $customer = null) {
    if (!partCheckOn()) return '';
    if (partCheckPassed($cartItems, $customer)) return '';
    $sig = partCheckCartSig($cartItems);
    $cid = (int)($customer['id'] ?? ($_SESSION['customer_id'] ?? 0));
    $row = partCheckCurrent($cid);
    $sameCart = $row && ((string)$row['cart_sig'] === $sig || (string)$row['cart_sig'] === '');
    if (!$row || !$sameCart || (string)$row['status'] === 'rejected') return 'part-check.php';
    return 'stock-check.php';
}

/* پس از ثبت موفق سفارش: درخواست باز به همان سفارش گره می‌خورد تا مدیر آن را
   در «جزئیات سفارش» ببیند، و پرچم «رد کردن» پاک می‌شود. */
function partCheckAttachToOrder($orderId, $customerId) {
    global $pdo;
    unset($_SESSION['partcheck_skip_sig']);
    if (!partChecksReady() || (int)$orderId <= 0 || (int)$customerId <= 0) return;
    try {
        $pdo->prepare("UPDATE part_checks SET order_id = ?
                       WHERE customer_id = ? AND order_id IS NULL
                       ORDER BY id DESC LIMIT 1")
            ->execute([(int)$orderId, (int)$customerId]);
    } catch (Throwable $e) {}
}

/* درخواست گره‌خورده به یک سفارش (برای صفحهٔ جزئیات سفارش ادمین) */
function partCheckForOrder($orderId) {
    global $pdo;
    if (!partChecksReady() || (int)$orderId <= 0) return null;
    try {
        $st = $pdo->prepare("SELECT * FROM part_checks WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([(int)$orderId]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

/* تعداد درخواست‌های در انتظار «بررسی عکس» — نشان کنار منوی ادمین. فقط
   ردیف‌هایی که واقعا در این صف دیده می‌شوند (photo_required=1) شمرده
   می‌شوند، وگرنه ردیف‌های خالص «فقط موجودی» هم اینجا حساب می‌شدند. */
function partCheckPendingCount() {
    global $pdo;
    if (!partChecksReady()) return 0;
    try {
        $sql = "SELECT COUNT(*) FROM part_checks WHERE status = 'pending'";
        if (partCheckStockSplitReady()) $sql .= " AND photo_required = 1";
        return (int)$pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/* همتای partCheckPendingCount() برای صف مستقل «تأیید موجودی». */
function stockCheckPendingCount() {
    global $pdo;
    if (!partCheckStockSplitReady()) return 0;
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM part_checks WHERE stock_status = 'pending'")->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/* آپلود چند عکس مشتری → آرایهٔ نام فایل‌های ذخیره‌شده.
   همان اعتبارسنجی گالری محصول: پسوند مجاز + سقف حجم. نام فایل کاربر هرگز
   استفاده نمی‌شود (نام تازه ساخته می‌شود) تا مسیرپیمایی و پسوند دوگانه ممکن
   نباشد. */
function partCheckSaveUploads($field, $max = 8) {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) return [];
    $dir = __DIR__ . '/../uploads/partchecks/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $saved = [];
    $count = count($_FILES[$field]['name']);
    for ($i = 0; $i < $count && count($saved) < $max; $i++) {
        if (($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        if (($_FILES[$field]['size'][$i] ?? 0) > MAX_UPLOAD_SIZE) continue;
        $ext = strtolower(pathinfo($_FILES[$field]['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) continue;
        if (!@getimagesize($_FILES[$field]['tmp_name'][$i])) continue;   /* واقعا عکس باشد */

        do { $newName = 'pc' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext; } while (is_file($dir . $newName));
        if (move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dir . $newName)) $saved[] = $newName;
    }
    return $saved;
}

/* متن درشت بالای صفحه — مدیر می‌تواند در تنظیمات جای آن متن خودش را بگذارد */
function partCheckNotice() {
    $t = trim((string)getSettingRaw('partcheck_notice', ''));
    if ($t !== '') return $t;
    return 'پیش از ثبت سفارش، عکس قطعهٔ خودتان را برای ما بفرستید تا کارشناسان ما آن را با '
         . 'کالای سبد خریدتان مقایسه کنند. هدف این مرحله یک چیز است: کالایی که می‌خرید '
         . 'دقیقا همان کالایی باشد که نیاز دارید و روی خودروی شما بخورد — چون پس از خرید، '
         . 'امکان مرجوعی و تعویض قطعه وجود ندارد.';
}

/* ===================== روند پیگیری سفارش =====================
   لایه‌ای در کنار ENUM موجود orders.status (نه جایگزین آن)؛ هر مرحله یک ستون
   DATETIME است که ادمین با تیک‌زدن مهر زمان می‌کند. ترتیب آرایه = ترتیب نمایش.
   «تحویل به پست» فقط وقتی ستونش ساخته شده باشد وارد فهرست می‌شود؛ کنار
   «تحویل به پیک» می‌نشیند تا مدیر هرکدام را که واقعا انجام شده تیک بزند.
   کلید 'code' یعنی این مرحله یک فیلد متنی هم دارد (کد رهگیری). */
function orderTrackSteps() {
    $steps = [];
    /* «بررسی موجودی» همیشه اولین مرحله است (خواستهٔ مدیر) — تا این مرحله ثبت
       نشده، مشتری نمی‌تواند پرداخت کند (orderPaymentUnlocked()، پایین‌تر در
       همین فایل). اگر سفارش قبلا از «بررسی عکس نمونهٔ قطعه» با موجودی
       تأییدشده (stock_ok=1) رد شده باشد، این مرحله خودکار طی می‌شود —
       orderAutoPassStockCheck() در لحظهٔ ثبت سفارش صدا زده می‌شود. */
    if (trackStockReady()) {
        $steps['track_stock_at'] = ['label' => 'بررسی موجودی', 'icon' => 'shield-check'];
    }
    $steps += [
        'track_confirmed_at'       => ['label' => 'سفارش شما تأیید شد',   'icon' => 'check-circle'],
        'track_collecting_at'      => ['label' => 'در حال جمع‌آوری کالا', 'icon' => 'package'],
        'track_finding_courier_at' => ['label' => 'در حال جستجوی پیک',    'icon' => 'search'],
        'track_courier_at'         => ['label' => 'تحویل به پیک',         'icon' => 'user-check'],
    ];
    if (trackingPostReady()) {
        $steps['track_post_at'] = ['label' => 'تحویل به پست', 'icon' => 'send', 'code' => 'post_tracking_code'];
    }
    $steps['track_shipped_at'] = ['label' => 'سفارش ارسال شد', 'icon' => 'truck'];
    return $steps;
}

/* آدرس رهگیری مرسولهٔ پست برای کدی که ادمین وارد کرده.
   فقط کد رقمی لینک می‌شود تا ورودی بی‌ربط، لینک خراب نسازد. */
function postTrackingUrl($code) {
    $code = preg_replace('/\D+/', '', faToLatinDigits((string)$code));
    return $code === '' ? '' : 'https://tracking.post.ir/?id=' . $code;
}

/* ---------- شهر مقصد سفارش ----------
   جدول orders ستون شهر ندارد؛ checkout.php نشانی را به شکل
   «استان - شهر - نشانی - کدپستی» در customer_address می‌چیند و استان می‌تواند
   خالی باشد، پس فقط دو بخش نخست نامزد نام شهرند. همین محدودکردن است که
   خیابانی به‌نام یک شهر («بلوار تهران») را شهر مقصد نمی‌خواند.
   ناخوانا ⇒ '' یعنی «نمی‌دانیم». */
function orderDestCity($order) {
    if (!function_exists('shippingCityKnown')) return '';
    $addr = trim((string)($order['customer_address'] ?? ''));
    if ($addr === '') return '';
    $i = 0;
    foreach (explode(' - ', $addr) as $part) {
        if (++$i > 2) break;
        $c = shippingCityKnown($part);
        if ($c !== '') return $c;
    }
    return '';
}

/* ---------- شیوهٔ تحویل سفارش ----------
   تا روند ارسال مشتری فقط مرحله‌های مربوط به خود سفارش را نشان دهد:
     'courier' = پیک درون‌شهری ⇒ «تحویل به پست» برای این سفارش بی‌معناست
     'ship'    = پست/باربری/…   ⇒ «در حال جستجوی پیک» و «تحویل به پیک» بی‌معناست
     ''        = نمی‌دانیم      ⇒ هیچ مرحله‌ای پنهان نمی‌شود
   روش ارسال خود سفارش حرف آخر است، چون همان چیزی است که مشتری خریده و
   درست‌تر از حدس‌زدن از شهر است (مشتری مشهدی هم می‌تواند پست پیشتاز بخرد و
   آن‌وقت کد رهگیری‌اش را لازم دارد). فقط برای سفارش‌های قدیمی بدون روش ارسال،
   شهر نشانی جای آن را می‌گیرد. */
function orderDeliveryMode($order) {
    $sm = trim((string)($order['shipping_method'] ?? ''));
    if ($sm !== '' && function_exists('shippingIsCityCourier')) {
        return shippingIsCityCourier($sm) ? 'courier' : 'ship';
    }
    $city = orderDestCity($order);
    if ($city === '' || !function_exists('shippingAvailableMethods')) return '';
    foreach (shippingAvailableMethods() as $k => $_) {
        if (shippingCityMatchesLimit($k, $city)) return 'courier';
    }
    return 'ship';
}

/* همان orderTrackSteps() ولی فقط مرحله‌هایی که مشتری این سفارش باید ببیند.
   خود orderTrackSteps() دست‌نخورده می‌ماند: تیک‌های پنل ادمین و کارت تنظیمات
   پیامک به همه مرحله‌ها نیاز دارند («فقط سمت ادمین باشه»)؛ این فیلتر تنها
   برای چشم مشتری است. سه مرحلهٔ تأیید/جمع‌آوری/ارسال هرگز حذف نمی‌شوند. */
function orderTrackStepsVisible($order) {
    $steps = orderTrackSteps();
    $mode  = orderDeliveryMode($order);
    if ($mode === 'courier') {
        unset($steps['track_post_at']);
    } elseif ($mode === 'ship') {
        unset($steps['track_finding_courier_at'], $steps['track_courier_at']);
    }
    return $steps;
}

/* تایم‌لاین عمودی روند ارسال برای یک ردیف سفارش.
   مراحل طی‌شده با مهر زمان شمسی، مراحل بعدی کم‌رنگ. در مرحلهٔ «تحویل به پیک»
   نام و شمارهٔ پیک و در «تحویل به پست» کد رهگیری نمایش داده می‌شود.
   اگر هیچ مرحله‌ای ثبت نشده باشد '' برمی‌گرداند. */
function renderOrderTimeline($order, $showEmptyNote = true) {
    if (!trackingReady()) return '';

    $steps = orderTrackStepsVisible($order);
    $doneCount = 0;
    foreach ($steps as $col => $_) {
        if (!empty($order[$col])) $doneCount++;
    }

    if ($doneCount === 0) {
        if (!$showEmptyNote) return '';
        return '<div class="track-timeline track-empty">' . icon('clock', 'ic-sm')
             . ' سفارش شما ثبت شده و به‌زودی بررسی می‌شود.</div>';
    }

    $courierName  = trim((string)($order['courier_name'] ?? ''));
    $courierPhone = trim((string)($order['courier_phone'] ?? ''));

    $out = '<ol class="track-timeline">';
    foreach ($steps as $col => $def) {
        $at   = $order[$col] ?? null;
        $done = !empty($at);
        $out .= '<li class="track-step ' . ($done ? 'is-done' : 'is-todo') . '">'
              . '<span class="track-dot">' . icon($done ? $def['icon'] : 'clock', 'ic-sm') . '</span>'
              . '<div class="track-body">'
              . '<span class="track-label">' . h($def['label']) . '</span>'
              . ($done ? '<span class="track-time">' . h(jDate($at, true)) . '</span>' : '');

        if ($col === 'track_courier_at' && $done && ($courierName !== '' || $courierPhone !== '')) {
            $out .= '<div class="track-courier">';
            if ($courierName !== '') {
                $out .= '<span>' . icon('user', 'ic-sm') . ' ' . h($courierName) . '</span>';
            }
            if ($courierPhone !== '') {
                $out .= '<a href="tel:' . h(telHref($courierPhone)) . '" dir="ltr">'
                      . icon('phone', 'ic-sm') . ' ' . h($courierPhone) . '</a>';
            }
            $out .= '</div>';
        }

        /* کد رهگیری پست: قابل انتخاب برای کپی، و اگر رقمی بود لینک به رهگیری پست */
        if ($done && !empty($def['code'])) {
            $code = trim((string)($order[$def['code']] ?? ''));
            if ($code !== '') {
                $url = postTrackingUrl($code);
                $out .= '<div class="track-courier track-code">'
                      . '<span>' . icon('receipt', 'ic-sm') . ' کد رهگیری:'
                      . ' <b dir="ltr">' . h($code) . '</b></span>';
                if ($url !== '') {
                    $out .= '<a href="' . h($url) . '" target="_blank" rel="noopener">'
                          . icon('external', 'ic-sm') . ' رهگیری در سایت پست</a>';
                }
                $out .= '</div>';
            }
        }

        $out .= '</div></li>';
    }
    $out .= '</ol>';
    return $out;
}

/* ===================== کارت سفارش در پنل مشتری =====================
   یک رندرکنندهٔ مشترک برای سه جا: فهرست «سفارش‌های در جریان» در account.php،
   صفحهٔ «سفارش‌های گذشته» و پاسخ بروزرسانی درجا (frag=orders). پیش‌تر همین
   نشانه‌گذاری داخل account.php بود؛ یک‌جا نگه‌داشتنش جلوی واگرایی سه نسخه را
   می‌گیرد. */

/* شمارهٔ نمایشی سفارش — تاریخ ثبت (میلادی، هم‌الگوی شمارهٔ فاکتور قبلی در
   admin/invoice.php) + شناسهٔ یکتا با صفرهای ابتدایی. خود id همچنان کلید
   اصلی و مبنای URLها می‌ماند (order-detail.php?id=، order-success.php?id=،
   کلیدهای خارجی order_items/payments/...)؛ این فقط شکل نمایشی است که
   خواستهٔ کاربر بود: «بر اساس سال ماه روز و شماره یونیک». همه‌جا که قبلا
   «سفارش #<id>» نوشته می‌شد از همین تابع می‌آید تا یک عدد، همه‌جا یکی باشد. */
function orderNumber($order) {
    $ts = !empty($order['created_at']) ? strtotime((string)$order['created_at']) : false;
    if (!$ts) $ts = time();
    return date('ymd', $ts) . '-' . str_pad((string)(int)($order['id'] ?? 0), 5, '0', STR_PAD_LEFT);
}

/* برچسب‌های فارسی ENUM ستون orders.status */
function orderStatusLabels() {
    return ['pending' => 'در انتظار بررسی', 'confirmed' => 'تأیید شده',
            'shipped' => 'ارسال شده', 'cancelled' => 'لغو شده'];
}

function orderStatusLabel($status) {
    $m = orderStatusLabels();
    return $m[(string)$status] ?? (string)$status;
}

/* تعداد روزهایی که یک سفارش «ارسال‌شده» هنوز جزو سفارش‌های در جریان
   دیده می‌شود (به‌جای پریدن فوری به سفارش‌های گذشته) — خواستهٔ مدیر تا
   مشتری چند روز فرصت پیگیری داشته باشد. */
function orderPastDelayDays() {
    return 7;
}

/* سفارش «در جریان» = لغوشده نیست، و اگر ارسال‌شده هم هست، هنوز کمتر از
   orderPastDelayDays() روز از تیک «سفارش ارسال شد» (track_shipped_at)
   نگذشته. اگر آن ستون به‌هردلیل خالی باشد (سفارش قدیمی/بدون ثبت زمان)،
   محتاطانه فورا گذشته حساب می‌شود — رفتار قبل این تغییر. */
function orderIsOpen($order) {
    $status = (string)($order['status'] ?? '');
    if ($status === 'cancelled') return false;
    if ($status !== 'shipped') return true;
    $shippedAt = $order['track_shipped_at'] ?? null;
    if (!$shippedAt) return false;
    $cutoff = strtotime((string)$shippedAt) + orderPastDelayDays() * 86400;
    return $cutoff !== false && time() < $cutoff;
}

/* سفارش‌ها را به دو دستهٔ [در جریان، گذشته] تقسیم می‌کند (ترتیب حفظ می‌شود) */
function splitCustomerOrders(array $orders) {
    $open = []; $past = [];
    foreach ($orders as $o) {
        if (orderIsOpen($o)) $open[] = $o; else $past[] = $o;
    }
    return [$open, $past];
}

function renderCustomerOrderCard($order) {
    $payOn   = paymentReady();
    $trackOn = trackingReady();
    $shipOn  = shippingReady();
    $c2cOn   = paymentC2cReady();
    $id      = (int)$order['id'];
    $st      = (string)($order['status'] ?? '');

    $out  = '<div class="account-order">';
    $out .= '<div class="account-order-row">'
          . '<span>سفارش <b dir="ltr" style="font-weight:600;">' . h(orderNumber($order)) . '</b></span>'
          . '<span class="order-status status-' . h($st) . '">' . h(orderStatusLabel($st)) . '</span>'
          . '</div>';
    $out .= '<div class="account-order-row">'
          . '<span style="color:var(--text-muted);font-size:0.8rem;">' . h(jDate($order['created_at'], true)) . '</span>'
          . '<span>' . formatPrice($order['total_amount']) . '</span>'
          . '</div>';

    if ($payOn) {
        $ps = (string)($order['payment_status'] ?? 'unpaid');
        $pm = (string)($order['payment_method'] ?? 'cod');
        $out .= '<div class="account-order-row">'
              . '<span style="color:var(--text-muted);font-size:0.78rem;">'
              . icon(paymentIcon($pm), 'ic-sm') . ' ' . h(paymentLabel($pm)) . '</span>'
              . paymentStatusBadgeFor($ps, $pm) . '</div>';

        if ($ps !== 'paid' && paymentIsOnline($pm) && $st !== 'cancelled' && orderPaymentUnlocked($order)) {
            $out .= '<div class="account-order-row" style="justify-content:flex-end;">'
                  . '<a href="payment-start.php?order=' . $id . '" class="btn btn-primary btn-sm">'
                  . icon('credit-card', 'ic-sm') . ' پرداخت</a></div>';
        } elseif ($ps !== 'paid' && paymentIsOnline($pm) && $st !== 'cancelled') {
            /* آنلاین است ولی گیت «بررسی موجودی» هنوز باز نشده */
            $out .= '<div class="account-order-row" style="justify-content:flex-end;">'
                  . '<span style="color:#FCD34D;font-size:0.76rem;">' . icon('shield-check', 'ic-sm') . ' در انتظار بررسی موجودی</span></div>';
        } elseif ($c2cOn && $pm === 'card' && $ps !== 'paid' && $st !== 'cancelled') {
            /* کارت به کارت: راه رسیدن به فرم «ثبت واریز» از حساب کاربری،
               چون ممکن است مشتری صفحهٔ پس از ثبت سفارش را بسته باشد. */
            $lbl = trim((string)($order['c2c_ref'] ?? '')) !== '' ? 'پیگیری واریز' : 'ثبت واریز';
            $out .= '<div class="account-order-row" style="justify-content:flex-end;">'
                  . '<a href="order-success.php?id=' . $id . '" class="btn btn-secondary btn-sm">'
                  . icon('receipt', 'ic-sm') . ' ' . $lbl . '</a></div>';
        } elseif (function_exists('paymentChequeReady') && paymentChequeReady() && $pm === 'cheque' && $ps !== 'paid' && $st !== 'cancelled') {
            /* چک از ۲۰۲۶-۰۸-۳۰ دیگر فرمی ندارد (نه ثبت، نه ویرایش) — فقط یک
               یادآوری صریح تا رسیدن فیزیکی چک، بعد نشان سبز. اگر مدیر
               «دریافت چک» را زده باشد یعنی رسیده — که با payment_status یکی
               نیست، چک رسیده‌بودن یعنی وصول‌شدنش، نه. */
            if (!empty($order['cheque_received_at'])) {
                $out .= '<div class="account-order-row"><span style="color:#4ADE80;font-size:0.78rem;">'
                      . icon('check-circle', 'ic-sm') . ' چک دریافت شد</span></div>';
            } elseif (function_exists('paymentChequeNoteText')) {
                $out .= '<div class="account-order-row"><span style="color:#FBBF24;font-size:0.78rem;">'
                      . icon('alert', 'ic-sm') . ' ' . h(paymentChequeNoteText()) . '</span></div>';
            }
        }
    }

    if ($shipOn && ($sm = trim((string)($order['shipping_method'] ?? ''))) !== '') {
        $out .= '<div class="account-order-row">'
              . '<span style="color:var(--text-muted);font-size:0.78rem;">'
              . icon(shippingIcon($sm), 'ic-sm') . ' ' . h(shippingLabel($sm)) . '</span>'
              . '<span style="font-size:0.78rem;">' . h(shippingCostText((int)($order['shipping_cost'] ?? 0), $sm)) . '</span>'
              . '</div>';
    }

    if ($trackOn && $st !== 'cancelled') {
        $tl = renderOrderTimeline($order);
        if ($tl !== '') {
            $open = orderIsOpen($order) ? ' open' : ''; /* در جریان باز، تحویل‌شده بسته */
            $out .= '<details class="order-track"' . $open . '>'
                  . '<summary>' . icon('truck', 'ic-sm') . ' پیگیری سفارش</summary>'
                  . $tl . '</details>';
        }
    }

    $out .= '</div>';
    return $out;
}

/* ناحیهٔ زندهٔ فهرست سفارش‌ها: هم در نخستین رندر صفحه و هم در پاسخ
   بروزرسانی درجا از همین تابع ساخته می‌شود، پس دو نسخه از یک HTML نداریم. */
function renderCustomerOrdersLive(array $orders, $statsHtml = '', $emptyHtml = '') {
    $out = '';
    if ($statsHtml !== '') {
        $out .= '<div class="auth-note" style="margin-bottom:0.75rem;">' . $statsHtml . '</div>';
    }
    if ($orders) {
        $out .= '<div class="account-orders">';
        foreach ($orders as $o) $out .= renderCustomerOrderCard($o);
        $out .= '</div>';
    } else {
        $out .= $emptyHtml;
    }
    return $out;
}

/* ===================== بنرهای کناری صفحهٔ اصلی =====================
   بنر راست («تخفیف ویژه») زنده از خود محصول تیک‌خورده ساخته می‌شود — هیچ ردیف
   بنری ذخیره نمی‌شود تا همیشه با محصول هم‌گام بماند.
   بنر چپ («آفر زمان‌دار») توسط ادمین در جدول timed_offers ساخته می‌شود. */

/* جدیدترین محصول «فروش ویژه» (تیک is_special در پنل ادمین) */
function getSpecialSaleProduct() {
    global $pdo;
    if (!specialSaleReady()) return null;
    try {
        $row = $pdo->query("SELECT * FROM products
            WHERE is_special = 1 AND is_active = 1
            ORDER BY id DESC LIMIT 1")->fetch();
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/* همه آفرهای زمان‌دار فعال — ورودی اسلایدر صفحهٔ اصلی.
   آفر منقضی حذف نمی‌شود: بنرش می‌ماند و روی آن «مهلت خرید تمام شد» نوشته
   می‌شود (خواستهٔ کاربر)؛ فقط به انتهای صف می‌رود تا آفرهای زندهٔ قابل خرید
   اول دیده شوند. «فروخته شد» هم همین‌طور — بنر با مهر گوشه می‌ماند.
   فقط تیک «نمایش/مخفی» ادمین (is_active) بنر را برمی‌دارد.
   (اگر end_at خالی باشد آفر بی‌زمان تلقی می‌شود و همیشه زنده است.) */
function getActiveTimedOffers($limit = 8) {
    global $pdo;
    if (!timedOffersReady()) return [];
    $limit = max(1, min(20, (int)$limit));   // در SQL درج می‌شود؛ پس عدد و کران‌دار
    /* «تمام‌شده» = مهلتش گذشته یا ادمین فروخته‌شد زده. ستون is_sold تدریجی است،
       پس فقط وقتی مهاجرت اجرا شده باشد وارد ORDER BY می‌شود. */
    $doneExpr = "(o.end_at IS NOT NULL AND o.end_at <= NOW())";
    if (timedOffersSoldReady()) $doneExpr = "(o.is_sold = 1 OR $doneExpr)";
    try {
        $rows = $pdo->query("SELECT o.*, p.name AS product_name, p.image AS product_image,
                   p.retail_price AS product_price, p.retail_discount AS product_discount
            FROM timed_offers o
            LEFT JOIN products p ON p.id = o.product_id
            WHERE o.is_active = 1
            ORDER BY $doneExpr ASC, o.sort_order, o.id DESC LIMIT $limit")->fetchAll();
        return $rows ?: [];
    } catch (Throwable $e) { return []; }
}

/* تک اولین آفر فعال (نگه‌داشته شده برای فراخوان‌های قدیمی) */
function getActiveTimedOffer() {
    $rows = getActiveTimedOffers(1);
    return $rows ? $rows[0] : null;
}

/* هر چند ثانیه اسلاید آفرها عوض شود؟ ادمین در «مدیریت بنرها» تعیین می‌کند.
   کران ۲ تا ۶۰ ثانیه تا مقدار پرت (صفر یا عددی نجومی) اسلایدر را از کار نیندازد. */
function offerSlideSeconds() {
    $s = (int) faToLatinDigits((string) getSettingRaw('offer_slide_seconds', '6'));
    if ($s < 2)  $s = 2;
    if ($s > 60) $s = 60;
    return $s;
}

function getProducts($filters = []) {
    global $pdo;
    $sql = "SELECT DISTINCT p.* FROM products p";
    $params = [];
    $conditions = ["p.is_active = 1"];

    /* ۲۰۲۶-۰۹-۰۳: محصول «بدون نیاز به برند/مدل» (تیک فرم محصول) باید در
       هر فیلتر برند/مدلی دیده شود، حتی اگر اصلا به هیچ دسته‌ای وصل نباشد
       — پس JOIN باید LEFT باشد (نه INNER)، وگرنه محصولی که هیچ ردیف
       product_categories ندارد از کل نتیجه حذف می‌شد. */
    $univ = productUniversalReady();
    if (!empty($filters['category_id']) || !empty($filters['brand_id'])) {
        $sql .= " LEFT JOIN product_categories pc ON p.id = pc.product_id";
    }

    if (!empty($filters['category_id'])) {
        $conditions[] = $univ ? "(p.no_model_required = 1 OR pc.category_id = ?)" : "pc.category_id = ?";
        $params[] = $filters['category_id'];
    }

    if (!empty($filters['brand_id'])) {
        $cond = "pc.category_id IN (SELECT id FROM categories WHERE parent_id = ? OR id = ?)";
        $conditions[] = $univ ? "(p.no_brand_required = 1 OR $cond)" : $cond;
        $params[] = $filters['brand_id'];
        $params[] = $filters['brand_id'];
    }

    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $conditions[] = "(p.name LIKE ? OR p.technical_number LIKE ?)";
        $params[] = $search;
        $params[] = $search;
    }

    if (!empty($filters['part_category_id'])) {
        $conditions[] = "p.part_category_id = ?";
        $params[] = (int)$filters['part_category_id'];
    }

    if (!empty($filters['part_category_ids']) && is_array($filters['part_category_ids'])) {
        $ids = array_values(array_filter(array_map('intval', $filters['part_category_ids'])));
        if ($ids) {
            $conditions[] = "p.part_category_id IN (" . implode(',', $ids) . ")";
        }
    }

    if (!empty($filters['new_within_days'])) {
        $days = (int)$filters['new_within_days'];
        if ($days > 0) {
            $conditions[] = "p.created_at >= (NOW() - INTERVAL $days DAY)";
        }
    }

    if (!empty($filters['on_sale'])) {
        $conditions[] = "(p.retail_discount > 0 OR p.wholesale_discount > 0)";
    }

    /* سال تولید خودرو (شمسی): محصولی که برایش بازه‌ای ثبت نشده «برای همه
       سال‌ها» حساب می‌شود (خالی‌گذاشتن سال در فرم محصول یعنی محدودیتی
       ندارد)، نه اینکه از نتیجه بیفتد. */
    if (!empty($filters['year']) && productYearReady()) {
        $y = (int)$filters['year'];
        $conditions[] = "(p.year_from IS NULL OR p.year_from <= ?) AND (p.year_to IS NULL OR p.year_to >= ?)";
        $params[] = $y;
        $params[] = $y;
    }

    if ($conditions) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.created_at DESC";

    if (!empty($filters['limit'])) {
        $sql .= " LIMIT ?";
        $params[] = (int)$filters['limit'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* پرفروش‌ترین محصولات بر اساس مجموع تعداد فروش‌رفته در order_items */
function getBestSellerProducts($limit = 24) {
    global $pdo;
    $limit = max(1, (int)$limit);
    $sql = "SELECT p.*, COALESCE(SUM(oi.quantity), 0) AS sold
            FROM products p
            LEFT JOIN order_items oi ON oi.product_id = p.id
            WHERE p.is_active = 1
            GROUP BY p.id
            HAVING sold > 0
            ORDER BY sold DESC, p.created_at DESC
            LIMIT $limit";
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getCartItems() {
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) return [];

    global $pdo;
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    $items = [];
    foreach ($products as $p) {
        $qty = (int)$cart[$p['id']];
        $isWholesale = ($qty >= $p['wholesale_min_qty']);
        $price = $isWholesale
            ? discountedPrice($p['wholesale_price'], $p['wholesale_discount'] ?? 0)
            : discountedPrice($p['retail_price'], $p['retail_discount'] ?? 0);
        $subtotal = $price * $qty;
        $taxPct   = productTaxPercent($p);
        $items[] = [
            'product' => $p,
            'quantity' => $qty,
            'price' => $price,
            'price_type' => $isWholesale ? 'wholesale' : 'retail',
            'subtotal' => $subtotal,
            'tax_percent' => $taxPct,
            'tax_amount'  => taxAmountFor($subtotal, $taxPct),
        ];
    }
    return $items;
}

function getCartTotal() {
    $items = getCartItems();
    return array_sum(array_column($items, 'subtotal'));
}

/* جمع مالیات سبد فعلی — جدا از getCartTotal() نگه داشته شده چون خیلی جا
   (نرخ ارسال، حد پایین پرداخت اعتباری، …) از getCartTotal() به‌عنوان
   «جمع کالاها»ی خالص استفاده می‌کنند و نباید بی‌سروصدا معنایش عوض شود. */
function getCartTaxTotal() {
    return itemsTaxTotal(getCartItems());
}

function getCartCount() {
    $cart = $_SESSION['cart'] ?? [];
    return array_sum($cart);
}

function getOrdersCount() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
}

function getProductsCount() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
}

function getPendingOrdersCount() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../admin/login.php');
    }
}

function getPartCategoriesTree() {
    global $pdo;
    $parents = $pdo->query("SELECT * FROM part_categories WHERE parent_id IS NULL ORDER BY sort_order")->fetchAll();
    $tree = [];
    foreach ($parents as $p) {
        $children = $pdo->prepare("SELECT * FROM part_categories WHERE parent_id = ? ORDER BY sort_order");
        $children->execute([$p['id']]);
        $tree[] = ['parent' => $p, 'children' => $children->fetchAll()];
    }
    return $tree;
}

function getPartCategories() {
    global $pdo;
    return $pdo->query("SELECT * FROM part_categories ORDER BY sort_order")->fetchAll();
}

/* یک دستهٔ قطعه با شناسه (والد یا زیرشاخه) */
function getPartCategory($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM part_categories WHERE id = ?");
    $stmt->execute([(int)$id]);
    return $stmt->fetch();
}

/* زیرشاخه‌های یک دستهٔ والد */
function getPartChildren($parentId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM part_categories WHERE parent_id = ? ORDER BY sort_order, name");
    $stmt->execute([(int)$parentId]);
    return $stmt->fetchAll();
}

/* تعداد محصولات هر دستهٔ قطعه: [part_category_id => count] */
function getPartCategoryProductCounts() {
    global $pdo;
    try {
        $rows = $pdo->query("SELECT part_category_id, COUNT(*) AS c FROM products WHERE is_active = 1 AND part_category_id IS NOT NULL GROUP BY part_category_id")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
    $map = [];
    foreach ($rows as $r) $map[(int)$r['part_category_id']] = (int)$r['c'];
    return $map;
}

function getBanners($position) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM banners WHERE position = ? AND is_active = 1 ORDER BY sort_order, id");
        $stmt->execute([$position]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return []; // جدول هنوز ساخته نشده → فراخوان به حالت پیش‌فرض برمی‌گردد
    }
}

function getMainBanner() {
    $rows = getBanners('main');
    return $rows[0] ?? null;
}

function getProductVariants($productId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function getVariantById($variantId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE id = ?");
    $stmt->execute([$variantId]);
    return $stmt->fetch();
}

function uploadImage($file, $existingPath = null) {
    if ($file['error'] !== UPLOAD_ERROR_OK) return $existingPath;
    if ($file['size'] > MAX_UPLOAD_SIZE) return $existingPath;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) return $existingPath;

    $newName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = UPLOAD_DIR . $newName;

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        if ($existingPath && file_exists(UPLOAD_DIR . $existingPath)) {
            unlink(UPLOAD_DIR . $existingPath);
        }
        return $newName;
    }
    return $existingPath;
}

/* ===================== تاریخ شمسی ===================== */

/* تبدیل میلادی به شمسی — خروجی: [سال, ماه, روز] */
function gregorianToJalali($gy, $gm, $gd) {
    $gy = (int)$gy; $gm = (int)$gm; $gd = (int)$gd;
    $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    if ($gm < 1) $gm = 1;
    if ($gm > 12) $gm = 12;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100)
          + (int)(($gy2 + 399) / 400) + $gd + $gDaysInMonth[$gm - 1];
    $jy = -1595 + (33 * (int)($days / 12053));
    $days %= 12053;
    $jy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + (int)($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int)(($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalaliMonthName($m) {
    $names = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
              'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $i = (int)$m - 1;
    return isset($names[$i]) ? $names[$i] : '';
}

/* معکوس gregorianToJalali — همان الگوریتمی که در تقویم جاوااسکریپتی
   checkout.php (j2g) استفاده شده، اینجا به PHP پورت شده تا محاسباتی مثل
   «چند روز تا پایان ماه شمسی» سمت سرور هم ممکن باشد (مثلا پیگیری تسویه
   همکاران). */
function jalaliToGregorian($jy, $jm, $jd) {
    $jy = (int)$jy + 1595;
    $jm = (int)$jm; $jd = (int)$jd;
    $days = -355668 + (365 * $jy) + ((int)($jy / 33) * 8) + (int)((($jy % 33) + 3) / 4)
          + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * (int)($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * (int)($days / 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = (($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0);
    $dim = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    while ($gm < 13 && $gd > $dim[$gm]) { $gd -= $dim[$gm]; $gm++; }
    return [$gy, $gm, $gd];
}

/* تعداد روزهای یک ماه شمسی — ۶ ماه اول ۳۱، ۵ ماه بعد ۳۰، اسفند ۲۹ یا ۳۰
   (کبیسه‌بودن با رفت‌وبرگشت «۳۰ اسفند» سنجیده می‌شود، مثل نسخهٔ جاوااسکریپتی). */
function jalaliMonthDays($jy, $jm) {
    $jm = (int)$jm;
    if ($jm < 7)  return 31;
    if ($jm < 12) return 30;
    $g = jalaliToGregorian($jy, 12, 30);
    $b = gregorianToJalali($g[0], $g[1], $g[2]);
    return ($b[0] == $jy && $b[1] == 12 && $b[2] == 30) ? 30 : 29;
}

/* «همین امروز» به وقت تهران، به‌صورت [سال، ماه، روز شمسی] — منطقهٔ زمانی
   صریحا تهران گرفته می‌شود چون منطقهٔ زمانی سرور تنظیم نشده (همان قاعده‌ای
   که در checkout.php برای کارت‌به‌کارت/چک استفاده شده). */
function jalaliToday() {
    try { $now = new DateTime('now', new DateTimeZone('Asia/Tehran')); }
    catch (Throwable $e) { $now = new DateTime(); }
    return gregorianToJalali((int)$now->format('Y'), (int)$now->format('n'), (int)$now->format('j'));
}

/* تاریخ شمسی از یک مقدار DATETIME/DATE دیتابیس. مثال: 1405/05/26 */
function jDate($dateTime, $withTime = false) {
    if ($dateTime === null || $dateTime === '' || $dateTime === '0000-00-00 00:00:00') return '—';
    $ts = strtotime((string)$dateTime);
    if (!$ts) return '—';
    $j = gregorianToJalali(date('Y', $ts), date('n', $ts), date('j', $ts));
    $out = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
    if ($withTime) $out .= ' - ' . date('H:i', $ts);
    return $out;
}

/* تاریخ شمسی خوانا. مثال: ۲۶ مرداد ۱۴۰۵ */
function jDateLong($dateTime) {
    $ts = strtotime((string)$dateTime);
    if (!$ts) return '—';
    $j = gregorianToJalali(date('Y', $ts), date('n', $ts), date('j', $ts));
    return $j[2] . ' ' . jalaliMonthName($j[1]) . ' ' . $j[0];
}

/* ===================== آمار و نمودار خرید ===================== */

/* مجموع مبلغ و تعداد سفارش‌ها به تفکیک ماه شمسی (۱۲ ماه اخیر تا ماه جاری).
   $customerId = null → کل فروشگاه. سفارش‌های لغوشده شمرده نمی‌شوند. */
function jalaliMonthlySales($customerId = null, $months = 12) {
    global $pdo;
    $months = max(1, (int)$months);
    $now = gregorianToJalali(date('Y'), date('n'), date('j'));
    $buckets = [];
    $keys = [];
    $y = $now[0]; $m = $now[1];
    for ($i = 0; $i < $months; $i++) {
        $key = $y . '-' . $m;
        $keys[] = $key;
        $buckets[$key] = [
            'label' => jalaliMonthName($m),
            'full'  => jalaliMonthName($m) . ' ' . $y,
            'total' => 0,
            'count' => 0,
        ];
        $m--;
        if ($m < 1) { $m = 12; $y--; }
    }
    $keys = array_reverse($keys);

    try {
        $sql = "SELECT created_at, total_amount FROM orders
                WHERE status <> 'cancelled' AND created_at >= (NOW() - INTERVAL " . ($months + 1) . " MONTH)";
        $params = [];
        if ($customerId !== null) {
            $sql .= " AND customer_id = ?";
            $params[] = (int)$customerId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $ts = strtotime((string)$r['created_at']);
            if (!$ts) continue;
            $j = gregorianToJalali(date('Y', $ts), date('n', $ts), date('j', $ts));
            $key = $j[0] . '-' . $j[1];
            if (!isset($buckets[$key])) continue;
            $buckets[$key]['total'] += (float)$r['total_amount'];
            $buckets[$key]['count']++;
        }
    } catch (Throwable $e) {
        // جدول/ستون موجود نیست → سری خالی برگردانده می‌شود
    }

    $out = [];
    foreach ($keys as $k) $out[] = $buckets[$k];
    return $out;
}

/* عدد فشرده برای برچسب نمودار: 12500000 → «۱۲.۵ م» */
function compactMoney($n) {
    $n = (float)$n;
    if ($n <= 0) return '0';
    if ($n >= 1000000000) return rtrim(rtrim(number_format($n / 1000000000, 1, '.', ''), '0'), '.') . ' میلیارد';
    if ($n >= 1000000)    return rtrim(rtrim(number_format($n / 1000000, 1, '.', ''), '0'), '.') . ' م';
    if ($n >= 1000)       return rtrim(rtrim(number_format($n / 1000, 1, '.', ''), '0'), '.') . ' هـ';
    return number_format($n, 0, '.', ',');
}

/* نمودار میله‌ای SVG درون‌خطی (بدون کتابخانهٔ خارجی).
   $series = خروجی jalaliMonthlySales() */
function renderSalesBarChart($series, $opts = []) {
    $n = count($series);
    if ($n === 0) return '<div class="chart-empty">داده‌ای برای نمایش نیست.</div>';

    $slot   = isset($opts['slot']) ? (int)$opts['slot'] : 62;
    $height = isset($opts['height']) ? (int)$opts['height'] : 230;
    $padTop = 26; $padBottom = 46;
    $plot   = $height - $padTop - $padBottom;
    $width  = $slot * $n;
    $barW   = (int)round($slot * 0.46);

    $max = 0;
    foreach ($series as $s) if ($s['total'] > $max) $max = $s['total'];
    $hasData = $max > 0;
    if (!$hasData) $max = 1;

    $svg  = '<svg class="sales-chart" viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" height="' . $height . '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="نمودار مجموع خرید ماهانه">';
    $svg .= '<defs><linearGradient id="scBar" x1="0" y1="0" x2="0" y2="1">'
          . '<stop offset="0%" stop-color="#EF4444"/><stop offset="100%" stop-color="#7F1D1D"/></linearGradient></defs>';

    // خطوط راهنما
    for ($g = 0; $g <= 4; $g++) {
        $gy = $padTop + ($plot * $g / 4);
        $svg .= '<line x1="0" y1="' . round($gy, 1) . '" x2="' . $width . '" y2="' . round($gy, 1)
              . '" stroke="rgba(255,255,255,0.07)" stroke-width="1"/>';
    }
    // خط پایه
    $baseY = $padTop + $plot;
    $svg .= '<line x1="0" y1="' . $baseY . '" x2="' . $width . '" y2="' . $baseY . '" stroke="rgba(255,255,255,0.18)" stroke-width="1"/>';

    $i = 0;
    foreach ($series as $s) {
        $cx = ($i * $slot) + ($slot / 2);
        $bh = $hasData && $s['total'] > 0 ? max(3, ($s['total'] / $max) * $plot) : 0;
        $by = $baseY - $bh;
        $x  = $cx - ($barW / 2);

        $svg .= '<g>';
        $svg .= '<title>' . h($s['full'] . ' — ' . number_format($s['total'], 0, '.', ',') . ' تومان (' . $s['count'] . ' سفارش)') . '</title>';
        // شبح میله برای ماه‌های بدون فروش
        $svg .= '<rect x="' . round($x, 1) . '" y="' . $padTop . '" width="' . $barW . '" height="' . round($plot, 1)
              . '" rx="4" fill="rgba(255,255,255,0.03)"/>';
        if ($bh > 0) {
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($by, 1) . '" width="' . $barW . '" height="' . round($bh, 1)
                  . '" rx="4" fill="url(#scBar)"/>';
            $svg .= '<text x="' . round($cx, 1) . '" y="' . round($by - 7, 1) . '" text-anchor="middle" font-size="10.5" fill="#F87171">'
                  . h(compactMoney($s['total'])) . '</text>';
        }
        $svg .= '<text x="' . round($cx, 1) . '" y="' . ($baseY + 17) . '" text-anchor="middle" font-size="11" fill="#9CA3AF">' . h($s['label']) . '</text>';
        if ($s['count'] > 0) {
            $svg .= '<text x="' . round($cx, 1) . '" y="' . ($baseY + 32) . '" text-anchor="middle" font-size="9.5" fill="#6B7280">' . (int)$s['count'] . ' سفارش</text>';
        }
        $svg .= '</g>';
        $i++;
    }
    $svg .= '</svg>';

    return '<div class="chart-wrap">' . $svg . '</div>';
}

/* ===================== شمارهٔ فنی خودکار ===================== */

/* نقشهٔ نام قطعه (فارسی) → اختصار لاتین. طولانی‌ترین تطبیق برنده است. */
function partAbbrMap() {
    return [
        'لنت ترمز'        => 'LN',
        'لنت'             => 'LN',
        'دیسک ترمز'       => 'DSK',
        'دیسک'            => 'DSK',
        'کاسه ترمز'       => 'DRM',
        'فیلتر روغن'      => 'FO',
        'فیلتر هوا'       => 'FA',
        'فیلتر کابین'     => 'FC',
        'فیلتر بنزین'     => 'FB',
        'فیلتر سوخت'      => 'FB',
        'فیلتر'           => 'FL',
        'شمع'             => 'SH',
        'کویل'            => 'CL',
        'واشر سرسیلندر'   => 'WSR',
        'سرسیلندر'        => 'SIL',
        'کمک فنر'         => 'KF',
        'کمک'             => 'KF',
        'فنر'             => 'SPR',
        'سیبک'            => 'SK',
        'طبق'             => 'TB',
        'تسمه تایم'       => 'TD',
        'تسمه دینام'      => 'TDN',
        'تسمه'            => 'TS',
        'رادیاتور'        => 'RD',
        'واتر پمپ'        => 'WP',
        'واترپمپ'         => 'WP',
        'پمپ بنزین'       => 'PB',
        'پمپ آب'          => 'PA',
        'پمپ هیدرولیک'    => 'PH',
        'پمپ'             => 'PMP',
        'دینام'           => 'DN',
        'استارت'          => 'ST',
        'باتری'           => 'BT',
        'کلاچ'            => 'KL',
        'صفحه کلاچ'       => 'SKL',
        'دیسک و صفحه'     => 'DSC',
        'گیربکس'          => 'GB',
        'چراغ جلو'        => 'CHJ',
        'چراغ عقب'        => 'CHA',
        'چراغ'            => 'CH',
        'آینه'            => 'AY',
        'سپر'             => 'SP',
        'گلگیر'           => 'GG',
        'درب'             => 'DR',
        'کاپوت'           => 'KP',
        'شیشه'            => 'SHS',
        'برف پاک کن'      => 'BPK',
        'برف‌پاک‌کن'      => 'BPK',
        'روغن موتور'      => 'RGM',
        'روغن'            => 'RG',
        'ضدیخ'            => 'ZY',
        'کن'              => 'KN',
        'بلبرینگ'         => 'BL',
        'سگدست'           => 'SG',
        'اگزوز'           => 'EX',
        'منیفولد'         => 'MNF',
        'سنسور'           => 'SN',
        'ترموستات'        => 'TRM',
        'واشر'            => 'WSR',
        'پیستون'          => 'PST',
        'میل لنگ'         => 'MLG',
        'سوپاپ'           => 'SPP',
        'دلکو'            => 'DLK',
        'کاربراتور'       => 'KRB',
        'انژکتور'         => 'ANJ',
        'رینگ'            => 'RNG',
        'لاستیک'          => 'LST',
        'سیم'             => 'SM',
        'شلگیر'           => 'SL',
        'قرقری'           => 'GHR',
        'جعبه فرمان'      => 'JF',
        'فرمان'           => 'FR',
    ];
}

/* حروف فارسی → لاتین (برای زمانی که هیچ اختصار آماده‌ای پیدا نشود) */
function faTranslit($text) {
    $map = [
        'ا'=>'A','آ'=>'A','أ'=>'A','ب'=>'B','پ'=>'P','ت'=>'T','ث'=>'S','ج'=>'J','چ'=>'CH','ح'=>'H','خ'=>'KH',
        'د'=>'D','ذ'=>'Z','ر'=>'R','ز'=>'Z','ژ'=>'ZH','س'=>'S','ش'=>'SH','ص'=>'S','ض'=>'Z','ط'=>'T','ظ'=>'Z',
        'ع'=>'A','غ'=>'GH','ف'=>'F','ق'=>'GH','ک'=>'K','گ'=>'G','ل'=>'L','م'=>'M','ن'=>'N',
        'و'=>'V','ه'=>'H','ی'=>'Y','ي'=>'Y','ئ'=>'Y','ة'=>'H','ك'=>'K',
    ];
    $out = '';
    $len = mb_strlen($text, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_substr($text, $i, 1, 'UTF-8');
        if (isset($map[$ch])) $out .= $map[$ch];
        elseif (preg_match('/[A-Za-z0-9]/', $ch)) $out .= strtoupper($ch);
    }
    return $out;
}

/* اختصار قطعه از نام محصول (طولانی‌ترین کلیدواژهٔ منطبق) */
function partCodeFromName($name) {
    $name = trim((string)$name);
    if ($name === '') return '';
    $name = str_replace(['‌', 'ي', 'ك'], [' ', 'ی', 'ک'], $name);
    $map = partAbbrMap();
    $bestKey = ''; $bestCode = '';
    foreach ($map as $kw => $code) {
        $kwN = str_replace(['‌', 'ي', 'ك'], [' ', 'ی', 'ک'], $kw);
        if (mb_strpos($name, $kwN, 0, 'UTF-8') !== false && mb_strlen($kwN, 'UTF-8') > mb_strlen($bestKey, 'UTF-8')) {
            $bestKey = $kwN; $bestCode = $code;
        }
    }
    if ($bestCode !== '') return $bestCode;
    // بدون تطبیق: از اولین واژهٔ نام، سه حرف لاتین بساز
    $first = preg_split('/\s+/u', $name);
    $tr = faTranslit($first[0]);
    return $tr !== '' ? substr($tr, 0, 3) : 'PRT';
}

/* اختصار مدل خودرو: از slug لاتین دستهٔ مدل، وگرنه از نام محصول حدس می‌زند */
function modelCodeFor($categoryIds = [], $productName = '') {
    global $pdo;
    $categoryIds = array_values(array_filter(array_map('intval', (array)$categoryIds)));
    try {
        if ($categoryIds) {
            $in = implode(',', array_fill(0, count($categoryIds), '?'));
            $stmt = $pdo->prepare("SELECT name, slug, parent_id FROM categories WHERE id IN ($in) ORDER BY (parent_id IS NULL), id");
            $stmt->execute($categoryIds);
            foreach ($stmt->fetchAll() as $row) {
                $code = preg_replace('/[^A-Za-z0-9]/', '', (string)$row['slug']);
                if ($code !== '') return strtoupper(substr($code, 0, 4));
                $tr = faTranslit($row['name']);
                if ($tr !== '') return substr($tr, 0, 4);
            }
        }
        // هیچ مدلی تیک نخورده → نام مدل را داخل نام محصول جستجو کن
        $name = trim((string)$productName);
        if ($name !== '') {
            $rows = $pdo->query("SELECT name, slug FROM categories WHERE parent_id IS NOT NULL")->fetchAll();
            $best = null; $bestLen = 0;
            foreach ($rows as $row) {
                $mn = trim((string)$row['name']);
                if ($mn === '') continue;
                if (mb_strpos($name, $mn, 0, 'UTF-8') !== false && mb_strlen($mn, 'UTF-8') > $bestLen) {
                    $best = $row; $bestLen = mb_strlen($mn, 'UTF-8');
                }
            }
            if ($best) {
                $code = preg_replace('/[^A-Za-z0-9]/', '', (string)$best['slug']);
                if ($code !== '') return strtoupper(substr($code, 0, 4));
                $tr = faTranslit($best['name']);
                if ($tr !== '') return substr($tr, 0, 4);
            }
        }
    } catch (Throwable $e) {}
    return 'GEN';
}

function technicalNumberExists($code, $exceptProductId = 0) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE technical_number = ? AND id <> ?");
        $stmt->execute([$code, (int)$exceptProductId]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/* شمارهٔ فنی خودکار: «اختصار قطعه - اختصار مدل - شمارهٔ یونیک»
   مثال: دیسک ترمز جلو توسان → DSK-TUCS-933 */
function generateTechnicalNumber($productName, $categoryIds = [], $exceptProductId = 0) {
    $part   = partCodeFromName($productName);
    $model  = modelCodeFor($categoryIds, $productName);
    $prefix = $part . '-' . $model;

    for ($try = 0; $try < 40; $try++) {
        $code = $prefix . '-' . random_int(100, 999);
        if (!technicalNumberExists($code, $exceptProductId)) return $code;
    }
    for ($try = 0; $try < 60; $try++) {
        $code = $prefix . '-' . random_int(1000, 99999);
        if (!technicalNumberExists($code, $exceptProductId)) return $code;
    }
    return $prefix . '-' . random_int(100000, 999999);
}

/* ===================== امتیاز ستاره‌ای · نظرات · پرسش‌وپاسخ =====================
   جدول‌ها: product_reviews (نظر + امتیاز ۱..۵) و product_qa (نخ‌دار: parent_id
   خالی = پرسش، مقداردار = پاسخ به همان پرسش). فقط ردیف‌های approved عمومی‌اند. */

/* میانگین و تعداد نظرهای تأییدشدهٔ همه محصولات — یک کوئری برای کل درخواست.
   کارت‌های فهرست (تا ۲۴ کارت در هر صفحه) از همین کش می‌خوانند تا به‌جای
   ۲۴ کوئری فقط یکی اجرا شود. */
function ratingsAll() {
    if (array_key_exists('__ratings_all', $GLOBALS)) return $GLOBALS['__ratings_all'];
    $map = [];
    if (reviewsReady()) {
        global $pdo;
        try {
            $rows = $pdo->query("SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS cnt
                                 FROM product_reviews WHERE status = 'approved' GROUP BY product_id")->fetchAll();
            foreach ($rows as $r) {
                $map[(int)$r['product_id']] = [round((float)$r['avg_rating'], 2), (int)$r['cnt']];
            }
        } catch (Throwable $e) { $map = []; }
    }
    return $GLOBALS['__ratings_all'] = $map;
}

/* [میانگین, تعداد] برای یک محصول — بدون نظر تأییدشده: [0, 0] */
function getProductRating($productId) {
    $all = ratingsAll();
    return $all[(int)$productId] ?? [0.0, 0];
}

/* پنج ستاره در دو لایه: پایهٔ خاکستری + لایهٔ رنگی بریده‌شده به درصد میانگین.
   با این روش «نیم‌ستاره» دقیق (مثلا ۴.۳ → ۸۶٪) نمایش داده می‌شود و به آیکن
   جداگانهٔ نیم‌ستاره نیازی نیست.
   $count = null → فقط ستاره‌ها، بدون متن. */
function ratingStars($avg, $count = null, $class = '') {
    $avg = (float)$avg;
    if ($avg < 0) $avg = 0;
    if ($avg > 5) $avg = 5;
    $row = str_repeat(icon('star'), 5);

    $out = '<span class="rstars' . ($class !== '' ? ' ' . $class : '') . '" role="img"'
         . ' aria-label="' . h(number_format($avg, 1) . ' از ۵') . '">'
         . '<span class="rstars-base">' . $row . '</span>'
         . '<span class="rstars-fill" style="width:' . round($avg / 5 * 100, 1) . '%">' . $row . '</span>'
         . '</span>';

    if ($count !== null) {
        $out .= (int)$count > 0
            ? '<span class="rstars-meta"><b>' . number_format($avg, 1) . '</b> از ۵ · ' . (int)$count . ' نظر</span>'
            : '<span class="rstars-meta rstars-none">هنوز نظری ثبت نشده</span>';
    }
    return $out;
}

/* نسخهٔ فشرده برای کارت‌های فهرست — محصول بی‌نظر چیزی نشان نمی‌دهد */
function productCardStars($productId) {
    list($avg, $cnt) = getProductRating($productId);
    if ($cnt < 1) return '';
    return '<div class="pc-stars">' . ratingStars($avg) . '<span class="pc-stars-n">(' . $cnt . ')</span></div>';
}

/* نظرهای یک محصول. $status = 'approved' برای نمایش عمومی، '' = همه (پنل ادمین) */
function getProductReviews($productId, $status = 'approved') {
    global $pdo;
    if (!reviewsReady()) return [];
    try {
        $sql = "SELECT * FROM product_reviews WHERE product_id = ?";
        $params = [(int)$productId];
        if ($status !== '') { $sql .= " AND status = ?"; $params[] = $status; }
        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* پرسش‌های تأییدشدهٔ یک محصول، هر پرسش با کلید 'answers'.
   پاسخ تأییدشده‌ای که پرسشش تأیید نشده باشد نمایش داده نمی‌شود (درست است). */
function getProductQa($productId) {
    global $pdo;
    if (!reviewsReady()) return [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM product_qa
            WHERE product_id = ? AND status = 'approved' ORDER BY id");
        $stmt->execute([(int)$productId]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) { return []; }

    $questions = [];
    $answers = [];
    foreach ($rows as $r) {
        if ($r['parent_id'] === null || (int)$r['parent_id'] === 0) {
            $r['answers'] = [];
            $questions[(int)$r['id']] = $r;
        } else {
            $answers[(int)$r['parent_id']][] = $r;
        }
    }
    foreach ($answers as $qid => $list) {
        if (isset($questions[$qid])) $questions[$qid]['answers'] = $list;
    }
    /* پرسش تازه‌تر بالاتر؛ پاسخ‌ها به ترتیب زمانی می‌مانند */
    return array_reverse(array_values($questions), false);
}

/* نام نمایشی نویسندهٔ نظر/پرسش/پاسخ */
function reviewAuthor($row) {
    if (!empty($row['is_admin'])) return 'فروشگاه';
    $n = trim((string)($row['author_name'] ?? ''));
    return $n !== '' ? $n : 'مشتری';
}

/* تعداد ردیف‌های در انتظار تأیید (نشان کنار منوی ادمین) */
function pendingReviewsCount() {
    global $pdo;
    if (!reviewsReady()) return 0;
    try {
        $a = (int)$pdo->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'")->fetchColumn();
        $b = (int)$pdo->query("SELECT COUNT(*) FROM product_qa WHERE status = 'pending'")->fetchColumn();
        return $a + $b;
    } catch (Throwable $e) { return 0; }
}

/* لایهٔ درگاه پرداخت — در انتها لود می‌شود چون از h()/icon()/getSetting()/httpPostJson() استفاده می‌کند.
   این تنها نقطهٔ اتصال است؛ هم سایت و هم پنل ادمین از همین‌جا به توابع payment* دسترسی دارند. */
require_once __DIR__ . '/payment.php';

/* لایهٔ روش‌های ارسال — مثل payment.php در انتها لود می‌شود چون به
   getSettingRaw()/faToLatinDigits()/formatPrice() نیاز دارد. */
require_once __DIR__ . '/shipping.php';

/* لایهٔ مالیات — به dbHasColumn() (بالای همین فایل) نیاز دارد. */
require_once __DIR__ . '/tax.php';

/* نگهبان مالکیت سبد، یک‌بار در هر درخواست. همین‌جا صدا زده می‌شود چون تنها
   جایی است که همه صفحه‌ها (سایت، پنل ادمین، اسکریپت‌های یک‌بارمصرف) از آن
   می‌گذرند و هیچ خروجی‌ای هم پیش‌تر تولید نشده. اگر نشست فعال نباشد (اجرای
   خط فرمان) کاری نمی‌کند. */
if (session_status() === PHP_SESSION_ACTIVE) cartOwnerSync();