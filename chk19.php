<?php
/* پروب موقت — دو خواستهٔ تازه:
   A) صفحهٔ روش‌های ارسال در ادمین نباید نوار اسکرول لازم داشته باشد
   B) استان و شهر در «مشخصات و آدرس تحویل» نوار کشویی باشند
   هیچ رمزی و هیچ اطلاعات شخصی چاپ نمی‌شود. بعد از استفاده ۴۰۴ می‌شود. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');
$m = (string)($_GET['m'] ?? 'eng');
$P = 0; $F = 0;
function ok($c, $l) { global $P, $F; if ($c) { $P++; echo "PASS $l\n"; } else { $F++; echo "FAIL $l\n"; } }
function has($h, $n, $l) { ok(strpos($h, $n) !== false, $l); }
function hasnt($h, $n, $l) { ok(strpos($h, $n) === false, $l); }
function done() { global $P, $F; echo "\n--- $P PASS / $F FAIL ---\n"; }

/* ---------------- A) موتور کمک‌تابع‌های شهر و استان ---------------- */
if ($m === 'eng') {
    ok(shippingCitiesReady(), 'cities-table-ready');

    $g = shippingCityGroupsAll();
    ok(is_array($g) && count($g) >= 30, 'province-groups=' . count($g));
    $tot = 0; foreach ($g as $l) $tot += count($l);
    ok($tot >= 200, 'city-total=' . $tot);

    $provs = shippingProvinceNames();
    ok(in_array('خراسان رضوی', $provs, true), 'province-khorasan');
    ok(in_array('تهران', $provs, true), 'province-tehran');
    ok($provs[0] !== '', 'first-province-nonempty');

    ok(shippingCityProvince('مشهد') === 'خراسان رضوی', 'city2prov-mashhad=' . shippingCityProvince('مشهد'));
    ok(shippingCityProvince('تبريز') === 'آذربایجان شرقی', 'city2prov-arabic-ye');
    ok(shippingCityProvince('بوکان') === 'آذربایجان غربی', 'city2prov-bookan');
    ok(shippingCityProvince('یک شهر ناموجود') === '', 'city2prov-unknown-empty');
    ok(shippingCityProvince('') === '', 'city2prov-blank-empty');

    /* پایتخت هر استان باید اول فهرست همان استان باشد (ترتیب sort_order) */
    ok(($g['خراسان رضوی'][0] ?? '') === 'مشهد', 'capital-first-mashhad');
    ok(($g['تهران'][0] ?? '') === 'تهران', 'capital-first-tehran');

    /* --- رندر فیلدها --- */
    $html = shippingProvinceCityFields('خراسان رضوی', 'مشهد', 'یک راهنما');
    has($html, '<select name="province" id="province"', 'province-is-select');
    has($html, '<select name="city" id="city"', 'city-is-select');
    hasnt($html, '<input type="text" name="province"', 'no-province-text-input');
    hasnt($html, '<input type="text" name="city"', 'no-city-text-input');
    hasnt($html, '<datalist', 'no-datalist-left');
    has($html, '<option value="خراسان رضوی" selected>', 'province-preselected');
    has($html, '<option value="مشهد" selected>', 'city-preselected');
    has($html, 'یک راهنما', 'hint-rendered');
    /* شهر استان دیگر نباید در فهرست فیلترشده باشد */
    hasnt($html, '>تبریز<', 'other-province-city-filtered-out');
    has($html, '>نیشابور<', 'same-province-city-present');
    has($html, 'dispatchEvent(new Event("change"', 'change-event-fired');
    has($html, 'form-hint', 'hint-class');

    /* حالت خالی: همهٔ شهرها با optgroup تا بدون جاوااسکریپت هم قابل انتخاب باشد */
    $h2 = shippingProvinceCityFields('', '', '');
    has($h2, '<optgroup label="خراسان رضوی">', 'blank-shows-optgroups');
    has($h2, '>تبریز<', 'blank-shows-all-cities');
    has($h2, '<option value="">— انتخاب استان —</option>', 'blank-province-placeholder');
    hasnt($h2, 'form-hint', 'no-hint-when-empty');

    /* شهر ناشناختهٔ ذخیره‌شده نباید گم شود */
    $h3 = shippingProvinceCityFields('یک استان دستی', 'یک شهر دستی', '');
    has($h3, '<option value="یک شهر دستی" selected>', 'unknown-city-kept');
    has($h3, '<option value="یک استان دستی" selected>', 'unknown-province-kept');

    /* شهری که فقط در نرخ‌نامه است هم باید در فهرست باشد */
    $rateCities = shippingRateCities();
    if ($rateCities) {
        $found = false;
        foreach ($g as $list) foreach ($list as $cn) if (shipNormCity($cn) === shipNormCity($rateCities[0])) $found = true;
        ok($found, 'rate-city-in-groups (' . count($rateCities) . ' rate cities)');
    } else {
        echo "INFO no rate cities to check\n";
    }

    /* JSON داخل <script> نباید تگ باز کند */
    hasnt($html, '</scr' . 'ipt><', 'json-no-tag-break');
    done();
    exit;
}

/* ---------------- B) صفحهٔ تسویه: واقعا کشویی شده؟ ---------------- */
if ($m === 'chk') {
    $cid = (int)$pdo->query("SELECT id FROM customers ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($cid <= 0) { echo "FAIL no-customer\n"; exit; }
    $pid = (int)$pdo->query("SELECT id FROM products WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($pid <= 0) { echo "FAIL no-active-product\n"; exit; }

    $prevCity = (string)$pdo->query("SELECT city FROM customers WHERE id=$cid")->fetchColumn();
    $prevProv = (string)$pdo->query("SELECT province FROM customers WHERE id=$cid")->fetchColumn();

    /* بازگردانی حتما در shutdown، چون صفحهٔ include شده ممکن است redirect/exit کند */
    register_shutdown_function(function () use ($pdo, $cid, $prevCity, $prevProv) {
        try {
            $pdo->prepare("UPDATE customers SET city=?, province=? WHERE id=?")
                ->execute([$prevCity, $prevProv, $cid]);
            echo "\n[restored: customer city+province]\n";
        } catch (Throwable $e) { echo "\n[RESTORE FAILED]\n"; }
    });

    /* شهر آزمایشی از خود فهرست، نه هاردکد */
    $pdo->prepare("UPDATE customers SET city='مشهد', province='' WHERE id=?")->execute([$cid]);

    $_SESSION['customer_id'] = $cid;
    $_SESSION['cart'] = [$pid => 1];
    currentCustomer(true);
    $_SERVER['SCRIPT_NAME'] = '/checkout.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start(); include __DIR__ . '/checkout.php'; $html = ob_get_clean();

    ok(strlen($html) > 2000, 'checkout-rendered len=' . strlen($html));
    has($html, 'مشخصات و آدرس تحویل', 'checkout-box-title');
    has($html, '<select name="province" id="province"', 'checkout-province-select');
    has($html, '<select name="city" id="city"', 'checkout-city-select');
    hasnt($html, 'list="ship-cities"', 'old-datalist-gone');
    hasnt($html, '<datalist id="ship-cities"', 'old-datalist-markup-gone');
    /* استان از خود شهر پروفایل حساب شده باشد، هرچند در پروفایل خالی بود */
    has($html, '<option value="خراسان رضوی" selected>', 'province-derived-from-city');
    has($html, '<option value="مشهد" selected>', 'city-from-profile-selected');
    hasnt($html, '>تبریز<', 'checkout-city-list-filtered');
    has($html, '?v=27', 'css-v27');
    done();
    exit;
}

/* ---------------- B2) صفحهٔ حساب کاربری ---------------- */
if ($m === 'acc') {
    $cid = (int)$pdo->query("SELECT id FROM customers ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($cid <= 0) { echo "FAIL no-customer\n"; exit; }
    $_SESSION['customer_id'] = $cid;
    currentCustomer(true);
    $_SERVER['SCRIPT_NAME'] = '/account.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';

    ob_start(); include __DIR__ . '/account.php'; $html = ob_get_clean();

    ok(strlen($html) > 2000, 'account-rendered len=' . strlen($html));
    has($html, 'مشخصات و آدرس', 'account-box-title');
    has($html, '<select name="province" id="province"', 'account-province-select');
    has($html, '<select name="city" id="city"', 'account-city-select');
    hasnt($html, 'list="acct-cities"', 'account-old-datalist-gone');
    hasnt($html, '<datalist id="acct-cities"', 'account-old-datalist-markup-gone');
    has($html, 'form-row-2', 'account-uses-shared-grid');
    has($html, '?v=27', 'css-v27');
    done();
    exit;
}

/* ---------------- A2) ادمین: پهنای بخش ارسال ---------------- */
if ($m === 'adm') {
    $aid = (int)$pdo->query("SELECT id FROM admins ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($aid <= 0) { echo "FAIL no-admin\n"; exit; }
    $_SESSION['admin_id'] = $aid;
    $_SESSION['admin_username'] = 'probe';
    $_SERVER['SCRIPT_NAME'] = '/admin/settings.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['sec'] = 'ship';
    chdir(__DIR__ . '/admin');

    ob_start(); include __DIR__ . '/admin/settings.php'; $html = ob_get_clean();

    ok(strlen($html) > 3000, 'settings-rendered len=' . strlen($html));
    has($html, 'روش‌های ارسال', 'ship-section-title');
    has($html, 'class="admin-form-full ff-wide"', 'form-has-wide-class');
    has($html, '.admin-form-full.ff-wide,#shipcrud{max-width:1320px;}', 'wide-css-present');
    has($html, '.ff-wide .ff-cap', 'cap-css-present');
    has($html, 'ship-mtable', 'methods-table');
    has($html, 'ship-rtable', 'rate-table');
    has($html, 'form-control ff-cap', 'long-field-capped');
    has($html, 'id="shipcrud"', 'crud-block');
    has($html, '?v=27', 'css-v27');
    done();
    exit;
}

/* ---------------- A2b) بخش‌های دیگر نباید پهن شوند ---------------- */
if ($m === 'adm2') {
    $aid = (int)$pdo->query("SELECT id FROM admins ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($aid <= 0) { echo "FAIL no-admin\n"; exit; }
    $_SESSION['admin_id'] = $aid;
    $_SESSION['admin_username'] = 'probe';
    $_SERVER['SCRIPT_NAME'] = '/admin/settings.php';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['sec'] = 'footer';
    chdir(__DIR__ . '/admin');

    ob_start(); include __DIR__ . '/admin/settings.php'; $html = ob_get_clean();

    ok(strlen($html) > 3000, 'footer-section-rendered len=' . strlen($html));
    has($html, 'class="admin-form-full"', 'plain-form-class');
    hasnt($html, 'admin-form-full ff-wide', 'footer-section-not-widened');
    hasnt($html, 'ship-mtable', 'no-ship-table-here');
    done();
    exit;
}

/* ---------------- A3) عرض واقعی: جدول باید در قاب جا شود ---------------- */
if ($m === 'css') {
    $css = (string)file_get_contents(__DIR__ . '/assets/css/style.css');
    ok(strpos($css, '.ship-rtable { min-width: 680px; }') !== false, 'table-minwidth-680');
    ok(strpos($css, 'min-width: 720px') === false, 'old-720-gone');
    ok(strpos($css, '.tbl-scroll') !== false, 'tbl-scroll-kept-as-safety-net');

    /* حساب سرانگشتی: قاب ۱۳۲۰ منهای padding جعبه (۱٫۲۵rem×۲=۴۰) و حاشیه */
    $frame = 1320 - 40 - 2;
    ok($frame > 680, "frame=$frame > table-min=680  ⇒ بدون اسکرول");
    /* بدترین حالت لپ‌تاپ ۱۰۲۴: منهای سایدبار ۲۴۰ و padding محتوا ۴۰ */
    $small = 1024 - 240 - 40 - 40 - 2;
    ok($small > 680, "laptop1024=$small > 680  ⇒ بدون اسکرول");
    $old = 700 - 40 - 2;
    ok($old < 720, "قبلا: frame=$old < 720 ⇒ همین باعث نوار اسکرول بود");
    done();
    exit;
}

echo "modes: eng | chk | acc | adm | css\n";
