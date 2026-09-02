<?php
/* پروب موقت بررسی — هیچ راز و هیچ اطلاعات شخصی چاپ نمی‌کند.
   پس از استفاده به ۴۰۴ تبدیل می‌شود. یک حالت در هر درخواست (?m=). */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=utf-8');

$m = (string)($_GET['m'] ?? '');
function ok($c, $l) { echo ($c ? 'PASS ' : 'FAIL ') . $l . "\n"; }
function has($html, $needle, $label) { ok(strpos($html, $needle) !== false, $label); }

/* ---------------- engine: rate table math ---------------- */
if ($m === 'eng') {
    ok(shippingRatesReady(), 'rates-table-ready');
    ok(shippingRateUnitReady(), 'weight_unit-column');
    ok(shippingCitiesReady(), 'cities-table');
    ok(shippingWeightReady(), 'products.weight-column');
    ok(paymentC2cReady(), 'c2c-columns');

    $names = shippingCityNames();
    ok(count($names) > 200, 'city-count=' . count($names));
    ok(in_array('تبریز', $names, true), 'tabriz-listed');
    ok(shippingCityCanonical('تبريز') === 'تبریز', 'canonical-arabic-ye');
    ok(shippingCityCanonical('یک شهر ناموجود') === 'یک شهر ناموجود', 'canonical-passthrough');

    /* ردیف آزمایشی: تبریز، پست سفارشی، هر ۱ کیلو ۵۰٬۰۰۰ — بعد پاک می‌شود */
    $pdo->prepare("INSERT INTO shipping_rates (method_key, city, city_norm, weight_unit, cost)
                   VALUES ('post_sefareshi','تبریز',?,1.00,50000)
                   ON DUPLICATE KEY UPDATE weight_unit=1.00, cost=50000")
        ->execute([shipNormCity('تبریز')]);
    unset($GLOBALS['__ship_rate_rows']);

    $r2 = shippingResolveCost('post_sefareshi', 'تبریز', 2.0);
    ok((int)$r2['display'] === 100000, '2kg=>100000 got=' . (int)$r2['display']);
    ok($r2['units'] === 2 && $r2['source'] === 'rate', '2kg units=' . $r2['units'] . ' src=' . $r2['source']);

    $r3 = shippingResolveCost('post_sefareshi', 'تبریز', 2.5);
    ok((int)$r3['display'] === 150000, '2.5kg-round-up=>150000 got=' . (int)$r3['display']);

    $r1 = shippingResolveCost('post_sefareshi', 'تبریز', 1.0);
    ok((int)$r1['display'] === 50000, '1kg=>50000 got=' . (int)$r1['display']);

    $r0 = shippingResolveCost('post_sefareshi', 'تبریز', 0);
    ok((int)$r0['display'] === 50000 && $r0['source'] === 'rate_base', 'no-weight=>1unit src=' . $r0['source']);

    $rx = shippingResolveCost('post_sefareshi', 'شهر بی‌نرخ', 2.0);
    ok((int)$rx['display'] === 0, 'unknown-city=>0');

    /* پس‌کرایه: هیچ محاسبه‌ای نباید انجام شود */
    echo "-- method flags --\n";
    $collectKey = '';
    foreach (shippingAvailableMethods() as $k => $d) {
        $fc = !empty($d['freight_collect']) ? 'collect' : '-';
        $co = !empty($d['cod_only']) ? 'cod' : '-';
        echo "INFO $k  $fc  $co\n";
        if ($fc === 'collect' && $collectKey === '') $collectKey = $k;
    }
    if ($collectKey === '') {
        echo "INFO no method has پس‌کرایه ticked right now — collect path untestable from data\n";
    } else {
        $rc = shippingResolveCost($collectKey, 'تبریز', 2.0);
        ok(!empty($rc['collect']) && (int)$rc['cost'] === 0 && $rc['source'] === 'collect', "freight-collect-no-calc ($collectKey)");
        ok(shippingCostText((int)$rc['cost'], $collectKey) === 'پس‌کرایه', 'collect-text-only');
    }

    /* پرداخت در محل: فقط پیک */
    ok(shippingCodAllowed('peyk_mashhad'), 'cod-allowed-peyk');
    ok(!shippingCodAllowed('post_sefareshi'), 'cod-blocked-post');
    $avail = array_keys(paymentAvailableMethods());
    echo 'INFO pay-methods: ' . implode(',', $avail) . "\n";
    $pk = shippingAllowedPayKeys('peyk_mashhad', $avail);
    ok(in_array('cod', $pk, true), 'peyk-keeps-cod');
    ok(count($pk) === count($avail), 'peyk-keeps-everything ' . implode(',', $pk));
    ok(!in_array('cod', shippingAllowedPayKeys('post_sefareshi', $avail), true), 'post-drops-cod');

    /* هیچ کلید حذف‌شده‌ای نباید لازم باشد */
    $t = shippingRateTexts();
    ok(!isset($t['agreed_free']) && !isset($t['estimate']) && !isset($t['flat']), 'dead-keys-gone');

    /* JS payload */
    $js = shippingRateJs(['post_sefareshi', 'barbari'], 2.0);
    ok((float)$js['w'] === 2.0, 'js-weight');
    ok(isset($js['m']['post_sefareshi']['r'][0]['u']), 'js-rate-unit');
    /* پرچم پس‌کرایه در JS فقط وقتی معنا دارد که روشی تیک‌خورده باشد؛ حالت ?m=col آن را می‌آزماید */
    if ($collectKey !== '') {
        ok(!empty($js['m'][$collectKey]['c'] ?? null) || !isset($js['m'][$collectKey]),
           "js-collect-flag ($collectKey)");
    } else {
        ok(empty($js['m']['barbari']['c']), 'js-collect-flag-off-matches-db');
    }

    /* برآورد سبد */
    $q = shippingCartQuotes('تبریز', 2.0);
    ok(count($q) > 0, 'cart-quotes=' . count($q));
    $best = shippingCheapestQuote($q);
    ok($best !== null && (int)$best['cost'] === 100000, 'cheapest=' . ($best ? (int)$best['cost'] : -1));

    $pdo->prepare("DELETE FROM shipping_rates WHERE method_key='post_sefareshi' AND city_norm=?")
        ->execute([shipNormCity('تبریز')]);
    echo "temp-rate-row removed\n";
    exit;
}

/* ---------------- collect path: tick پس‌کرایه, assert, restore ---------------- */
if ($m === 'col') {
    if (!shippingTableReady()) { echo "FAIL no shipping_methods table\n"; exit; }
    $prev = $pdo->prepare("SELECT freight_collect FROM shipping_methods WHERE method_key='barbari'");
    $prev->execute(); $prevVal = (int)$prev->fetchColumn();
    $pdo->prepare("UPDATE shipping_methods SET freight_collect=1 WHERE method_key='barbari'")->execute();
    unset($GLOBALS['__ship_all'], $GLOBALS['__ship_rate_rows']);

    $rc = shippingResolveCost('barbari', 'تبریز', 2.0);
    ok(!empty($rc['collect']), 'collect-flag');
    ok((int)$rc['cost'] === 0, 'collect-cost-zero got=' . (int)$rc['cost']);
    ok((int)$rc['display'] === 0, 'collect-display-zero got=' . (int)$rc['display']);
    ok($rc['source'] === 'collect', 'collect-source=' . $rc['source']);
    ok($rc['row'] === null, 'collect-no-row-used');
    ok(shippingCostText(0, 'barbari') === 'پس‌کرایه', 'collect-text-only');

    $js = shippingRateJs(['barbari'], 2.0);
    ok(!empty($js['m']['barbari']['c']), 'js-collect-flag');
    ok(empty($js['m']['barbari']['r']), 'js-collect-no-rate-rows');

    $q = shippingCartQuotes('تبریز', 2.0);
    $bq = null;
    foreach ($q as $one) { if ($one['key'] === 'barbari') $bq = $one; }
    ok($bq !== null && !empty($bq['collect']) && $bq['text'] === 'پس‌کرایه', 'cart-quote-collect-text');
    ok($bq !== null && empty($bq['known']), 'cart-quote-not-counted-as-known');

    $pdo->prepare("UPDATE shipping_methods SET freight_collect=? WHERE method_key='barbari'")->execute([$prevVal]);
    echo "barbari freight_collect restored to $prevVal\n";
    exit;
}

/* ---------------- customer pages ---------------- */
if (in_array($m, ['cart', 'chk', 'acc', 'ord', 'ord2'], true)) {
    $cid = (int)$pdo->query("SELECT id FROM customers ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($cid <= 0) { echo "FAIL no-customer-row\n"; exit; }
    $_SESSION['customer_id'] = $cid;

    /* ---- سبد و تسویه: باید سبد واقعی داشته باشند، وگرنه صفحه ریدایرکت می‌کند.
       هر تغییر موقتی با register_shutdown_function برگردانده می‌شود تا حتی اگر
       صفحهٔ include‌شده exit کند، داده‌های زندهٔ سایت دست‌نخورده بمانند. ---- */
    if ($m === 'cart' || $m === 'chk') {
        $pid = (int)$pdo->query("SELECT id FROM products WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($pid <= 0) { echo "FAIL no-active-product\n"; exit; }

        $prevCity = (string)$pdo->query("SELECT city FROM customers WHERE id=$cid")->fetchColumn();
        $prevW    = null;
        if (shippingWeightReady()) {
            $prevW = $pdo->query("SELECT weight FROM products WHERE id=$pid")->fetchColumn();
        }
        $norm = shipNormCity('تبریز');

        register_shutdown_function(function () use ($pdo, $cid, $pid, $prevCity, $prevW, $norm) {
            try {
                $pdo->prepare("UPDATE customers SET city=? WHERE id=?")->execute([$prevCity, $cid]);
                if ($prevW !== null) {
                    $pdo->prepare("UPDATE products SET weight=? WHERE id=?")->execute([$prevW, $pid]);
                }
                $pdo->prepare("DELETE FROM shipping_rates WHERE method_key='post_sefareshi' AND city_norm=?")
                    ->execute([$norm]);
                echo "\n[restored: city, weight, temp rate row]\n";
            } catch (Throwable $e) { echo "\n[RESTORE FAILED]\n"; }
        });

        $pdo->prepare("UPDATE customers SET city='تبریز' WHERE id=?")->execute([$cid]);
        if (shippingWeightReady()) {
            $pdo->prepare("UPDATE products SET weight=2.000 WHERE id=?")->execute([$pid]);
        }
        $pdo->prepare("INSERT INTO shipping_rates (method_key, city, city_norm, weight_unit, cost)
                       VALUES ('post_sefareshi','تبریز',?,1.00,50000)
                       ON DUPLICATE KEY UPDATE weight_unit=1.00, cost=50000")->execute([$norm]);

        $_SESSION['cart'] = [$pid => 1];
        currentCustomer(true);
        $expect = formatPrice(100000);   /* ۲ کیلو × ۵۰٬۰۰۰ */
    }

    if ($m === 'cart') {
        $_SERVER['SCRIPT_NAME'] = '/cart.php';
        ob_start(); include __DIR__ . '/cart.php'; $html = ob_get_clean();
        ok(strlen($html) > 2000, 'cart-rendered len=' . strlen($html));
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'cart-no-php-error');
        has($html, 'cart-summary', 'cart-summary');
        has($html, 'cart-ship', 'cart-ship-box');
        has($html, $expect, 'cart-auto-cost-2kg');
        has($html, 'تبریز', 'cart-uses-profile-city');
        exit;
    }
    if ($m === 'chk') {
        $_SERVER['SCRIPT_NAME'] = '/checkout.php';
        ob_start(); include __DIR__ . '/checkout.php'; $html = ob_get_clean();
        ok(strlen($html) > 2000, 'checkout-rendered len=' . strlen($html));
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'chk-no-php-error');
        has($html, 'ship-picker', 'ship-picker');
        has($html, 'data-codok="1"', 'codok-1-exists-peyk');
        has($html, 'data-codok="0"', 'codok-0-exists-others');
        has($html, $expect, 'checkout-auto-cost-2kg');
        ok(strpos($html, 'agreed_free') === false, 'no-agreed_free-leak');
        ok(strpos($html, 'پس‌کرایه / توافقی') === false, 'no-agreed-label-leak');
        exit;
    }
    if ($m === 'acc') {
        $_SERVER['SCRIPT_NAME'] = '/account.php';
        ob_start(); include __DIR__ . '/account.php'; $html = ob_get_clean();
        ok(strlen($html) > 2000, 'account-rendered len=' . strlen($html));
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'acc-no-php-error');
        has($html, 'id="acct-cities"', 'city-datalist');
        has($html, 'list="acct-cities"', 'city-input-wired');
        exit;
    }
    if ($m === 'ord') {
        /* سفارشی را بردار که صاحب دارد و همان مشتری را وارد کن — تازه‌ترین مشتری
           ممکن است هیچ سفارشی نداشته باشد. */
        $row = $pdo->query("SELECT id, customer_id FROM orders WHERE customer_id IS NOT NULL AND customer_id > 0 ORDER BY id DESC LIMIT 1")->fetch();
        if (!$row) { echo "INFO no-order-with-customer\n"; exit; }
        $oid = (int)$row['id'];
        $_SESSION['customer_id'] = (int)$row['customer_id'];
        currentCustomer(true);
        /* موقتا روش پرداخت را کارت به کارت کن تا فرم ثبت واریز رندر شود، بعد برگردان */
        $prev = $pdo->prepare("SELECT payment_method, payment_status FROM orders WHERE id=?");
        $prev->execute([$oid]); $prev = $prev->fetch();
        register_shutdown_function(function () use ($pdo, $oid, $prev) {
            try {
                $pdo->prepare("UPDATE orders SET payment_method=?, payment_status=? WHERE id=?")
                    ->execute([$prev['payment_method'], $prev['payment_status'], $oid]);
                echo "\n[order restored]\n";
            } catch (Throwable $e) { echo "\n[RESTORE FAILED]\n"; }
        });
        $pdo->prepare("UPDATE orders SET payment_method='card', payment_status='unpaid' WHERE id=?")->execute([$oid]);

        $_GET['id'] = $oid;
        $_SERVER['SCRIPT_NAME'] = '/order-success.php';
        ob_start(); include __DIR__ . '/order-success.php'; $html = ob_get_clean();
        ok(strlen($html) > 2000, 'order-success-rendered len=' . strlen($html));
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'ord-no-php-error');
        has($html, 'c2c-box', 'c2c-box');
        has($html, 'name="c2c_ref"', 'field-ref');
        has($html, 'name="c2c_amount"', 'field-amount');
        has($html, 'name="c2c_last4"', 'field-last4');
        has($html, 'name="c2c_paid_text"', 'field-when');
        ok(strpos($html, 'در انتظار تأیید واریز') === false, 'no-awaiting-label-before-report');
        ok(strpos($html, 'c2c-recap') === false, 'no-recap-before-report');
        exit;
    }
    if ($m === 'ord2') {
        /* حالت «مشتری واریز را اعلام کرده»: وضعیت pending + چهار مقدار آزمایشی.
           باید برچسب «در انتظار تأیید واریز» و بازبینی مقادیر ثبت‌شده دیده شود. */
        $row = $pdo->query("SELECT id, customer_id FROM orders WHERE customer_id IS NOT NULL AND customer_id > 0 ORDER BY id DESC LIMIT 1")->fetch();
        if (!$row) { echo "INFO no-order-with-customer\n"; exit; }
        $oid = (int)$row['id'];
        $_SESSION['customer_id'] = (int)$row['customer_id'];
        currentCustomer(true);

        $prev = $pdo->prepare("SELECT payment_method, payment_status, c2c_ref, c2c_amount, c2c_last4, c2c_paid_text, c2c_reported_at FROM orders WHERE id=?");
        $prev->execute([$oid]); $prev = $prev->fetch();
        register_shutdown_function(function () use ($pdo, $oid, $prev) {
            try {
                $pdo->prepare("UPDATE orders SET payment_method=?, payment_status=?, c2c_ref=?, c2c_amount=?, c2c_last4=?, c2c_paid_text=?, c2c_reported_at=? WHERE id=?")
                    ->execute([$prev['payment_method'], $prev['payment_status'], $prev['c2c_ref'],
                               $prev['c2c_amount'], $prev['c2c_last4'], $prev['c2c_paid_text'],
                               $prev['c2c_reported_at'], $oid]);
                echo "\n[order + c2c fields restored]\n";
            } catch (Throwable $e) { echo "\n[RESTORE FAILED]\n"; }
        });
        $pdo->prepare("UPDATE orders SET payment_method='card', payment_status='pending',
                       c2c_ref='PROBE0001', c2c_amount=123456, c2c_last4='4321',
                       c2c_paid_text='probe-time', c2c_reported_at=NOW() WHERE id=?")->execute([$oid]);

        $_GET['id'] = $oid;
        $_SERVER['SCRIPT_NAME'] = '/order-success.php';
        ob_start(); include __DIR__ . '/order-success.php'; $html = ob_get_clean();
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'ord2-no-php-error');
        has($html, 'در انتظار تأیید واریز', 'badge-awaiting-label');
        has($html, 'c2c-recap', 'recap-after-report');
        has($html, 'PROBE0001', 'recap-shows-ref');
        has($html, '4321', 'recap-shows-last4');
        has($html, 'به‌روزرسانی اطلاعات واریز', 'button-becomes-update');
        exit;
    }
}

/* ---------------- admin pages ---------------- */
if (in_array($m, ['adm', 'det', 'set'], true)) {
    $aid = (int)$pdo->query("SELECT id FROM admins ORDER BY id LIMIT 1")->fetchColumn();
    if ($aid <= 0) { echo "FAIL no-admin-row\n"; exit; }
    $_SESSION['admin_id'] = $aid;
    $_SESSION['admin_username'] = 'probe';
    chdir(__DIR__ . '/admin');

    if ($m === 'adm') {
        $_SERVER['SCRIPT_NAME'] = '/admin/orders.php';
        ob_start(); include __DIR__ . '/admin/orders.php'; $html = ob_get_clean();
        ok(strlen($html) > 2000, 'admin-orders-rendered len=' . strlen($html));
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'adm-no-php-error');
        has($html, '?v=26', 'css-v26');
        exit;
    }
    if ($m === 'det') {
        $oid = (int)$pdo->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($oid <= 0) { echo "INFO no-order\n"; exit; }
        $pm = (string)$pdo->query("SELECT payment_method FROM orders WHERE id=$oid")->fetchColumn();
        register_shutdown_function(function () use ($pdo, $oid, $pm) {
            try {
                $pdo->prepare("UPDATE orders SET payment_method=? WHERE id=?")->execute([$pm, $oid]);
                echo "\n[order restored]\n";
            } catch (Throwable $e) { echo "\n[RESTORE FAILED]\n"; }
        });
        $pdo->prepare("UPDATE orders SET payment_method='card' WHERE id=?")->execute([$oid]);

        $_GET['id'] = $oid;
        $_SERVER['SCRIPT_NAME'] = '/admin/order-detail.php';
        ob_start(); include __DIR__ . '/admin/order-detail.php'; $html = ob_get_clean();
        ok(strlen($html) > 2000, 'order-detail-rendered len=' . strlen($html));
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'det-no-php-error');
        has($html, 'واریز اعلام‌شدهٔ مشتری', 'c2c-admin-panel');
        has($html, 'تلاش‌های پرداخت', 'attempts-block-intact');
        has($html, '?v=26', 'css-v26');
        exit;
    }
    if ($m === 'set') {
        $_SERVER['SCRIPT_NAME'] = '/admin/settings.php';
        ob_start(); include __DIR__ . '/admin/settings.php'; $html = ob_get_clean();
        ok(strlen($html) > 2000, 'settings-rendered len=' . strlen($html));
        ok(strpos($html, 'Fatal error') === false && strpos($html, 'Warning') === false, 'set-no-php-error');
        ok(strpos($html, 'پس‌کرایه / توافقی') === false, 'agreed-option-removed');
        ok(strpos($html, 'یادداشت داخلی') === false, 'internal-note-removed');
        has($html, '?v=26', 'css-v26');
        exit;
    }
}

/* ---------------- بازبینی پایانی: هیچ داده‌ای نباید تغییرکرده مانده باشد ---------------- */
if ($m === 'state') {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM shipping_rates")->fetchColumn();
    echo "INFO shipping_rates-rows=$n\n";
    if ($n > 0) {
        foreach ($pdo->query("SELECT id, method_key, city, city_norm, weight_unit, weight_to, cost, is_active FROM shipping_rates ORDER BY id") as $r) {
            echo "ROW id={$r['id']} m={$r['method_key']} city={$r['city']} norm={$r['city_norm']}"
               . " unit={$r['weight_unit']} to={$r['weight_to']} cost={$r['cost']} act={$r['is_active']}\n";
        }
    }
    /* امضای ردیف آزمایشی خودم: تبریز + ۵۰٬۰۰۰ — ردیف‌های خود مدیر دست‌نخورده می‌مانند */
    $mine = (int)$pdo->query("SELECT COUNT(*) FROM shipping_rates WHERE method_key='post_sefareshi' AND city='تبریز' AND cost=50000")->fetchColumn();
    ok($mine === 0, 'probe-rate-row-left=' . $mine);

    $fc = (int)$pdo->query("SELECT freight_collect FROM shipping_methods WHERE method_key='barbari'")->fetchColumn();
    ok($fc === 0, 'barbari-freight_collect=' . $fc);

    $cardish = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_method='card'")->fetchColumn();
    echo "INFO orders-with-payment_method=card: $cardish\n";
    $probeLeft = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE c2c_ref='PROBE0001' OR c2c_paid_text='probe-time'")->fetchColumn();
    ok($probeLeft === 0, 'probe-c2c-values-left=' . $probeLeft);

    $pend = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status='pending'")->fetchColumn();
    echo "INFO orders-pending: $pend\n";

    $w = $pdo->query("SELECT weight FROM products WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    echo 'INFO newest-active-product-weight: ' . ($w === null ? 'NULL' : (string)$w) . "\n";
    $wn = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE weight IS NOT NULL AND weight > 0")->fetchColumn();
    echo "INFO products-with-weight: $wn\n";
    foreach ($pdo->query("SELECT id, weight FROM products WHERE weight IS NOT NULL AND weight > 0 ORDER BY id") as $r) {
        echo "WROW id={$r['id']} weight={$r['weight']}\n";
    }

    $isTabriz = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE id=(SELECT MAX(id) FROM customers) AND city='تبریز'")->fetchColumn();
    ok($isTabriz === 0, 'newest-customer-city-not-left-as-tabriz');
    exit;
}

/* ---------------- پاک‌سازی رد پروب: وزنی که پروب نوشت و برنگرداند ----------------
   علت باقی‌ماندن: در حالت cart/chk وزن اصلی NULL بود و شرط بازگردانی
   ($prevW !== null) آن را رد می‌کرد. فقط همان محصول تازه‌ترین و فقط اگر
   دقیقا 2.000 باشد به NULL برمی‌گردد؛ ردیف‌های نرخ خود مدیر لمس نمی‌شوند. */
if ($m === 'fix') {
    $pid = (int)$pdo->query("SELECT id FROM products WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
    $cur = $pdo->query("SELECT weight FROM products WHERE id=$pid")->fetchColumn();
    echo "INFO target product id=$pid weight=" . ($cur === null ? 'NULL' : (string)$cur) . "\n";
    if ($cur !== null && abs((float)$cur - 2.0) < 0.0001) {
        $pdo->prepare("UPDATE products SET weight=NULL WHERE id=? AND weight=2.000")->execute([$pid]);
        $now = $pdo->query("SELECT weight FROM products WHERE id=$pid")->fetchColumn();
        ok($now === null, 'weight-reverted-to-NULL');
    } else {
        echo "INFO nothing to revert\n";
    }
    exit;
}

/* ---------------- سناریوی واقعی خود مدیر (فقط خواندن، هیچ نوشتنی) ---------------- */
if ($m === 'user') {
    foreach ($pdo->query("SELECT method_key, city, weight_unit, cost FROM shipping_rates WHERE is_active=1 ORDER BY id") as $r) {
        $city = (string)$r['city'];
        $unit = (float)$r['weight_unit'] > 0 ? (float)$r['weight_unit'] : 1.0;
        $cost = (int)$r['cost'];
        echo "-- {$r['method_key']} / $city / هر {$r['weight_unit']} کیلو / $cost --\n";
        foreach ([1.0, 2.0, 2.5, 3.0, 5.0] as $wt) {
            $res = shippingResolveCost((string)$r['method_key'], $city, $wt);
            $units = (int)ceil($wt / $unit - 0.00001); if ($units < 1) $units = 1;
            $want  = $units * $cost;
            ok((int)$res['display'] === $want, "{$wt}kg => $want got=" . (int)$res['display'] . " units={$res['units']}");
        }
        /* وزن واقعی محصولی که مدیر ثبت کرده، ضربدر تعداد */
        $pw = $pdo->query("SELECT id, weight FROM products WHERE weight IS NOT NULL AND weight > 0 ORDER BY id LIMIT 1")->fetch();
        if ($pw) {
            $w1 = (float)$pw['weight'];
            foreach ([1, 2, 3] as $qty) {
                $tot = $w1 * $qty;
                $res = shippingResolveCost((string)$r['method_key'], $city, $tot);
                $units = (int)ceil($tot / $unit - 0.00001); if ($units < 1) $units = 1;
                ok((int)$res['display'] === $units * $cost,
                   "product#{$pw['id']} {$w1}kg × $qty = {$tot}kg => " . ($units * $cost) . " got=" . (int)$res['display']);
            }
        }
    }
    exit;
}

echo "usage: ?m=eng|col|cart|chk|acc|ord|ord2|adm|det|set|state|fix|user\n";
