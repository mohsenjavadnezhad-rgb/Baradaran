<?php
/* بازبینی موقت بخش ۱ (انتخاب روش ارسال در سبد). پس از استفاده به 404 تبدیل می‌شود.
   هیچ رازی و هیچ اطلاعات شخصی چاپ نمی‌شود؛ هر تغییر موقت دیتابیس در پایان برگردانده می‌شود. */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

$PASS = 0; $FAIL = 0;
function ok($c, $n) { global $PASS, $FAIL; if ($c) { $PASS++; echo "PASS $n\n"; } else { $FAIL++; echo "FAIL $n\n"; } }
function has($h, $s, $n) { ok(strpos($h, $s) !== false, $n); }
function non($h, $s, $n) { ok(strpos($h, $s) === false, $n); }

$m = $_GET['m'] ?? 'a';
echo "== mode $m ==\n";

/* کالای دارای وزن (تا مسیر «نرخ» فعال شود) */
$p = $pdo->query("SELECT id FROM products WHERE is_active=1 AND stock>2 AND weight IS NOT NULL AND weight>0 ORDER BY id DESC LIMIT 1")->fetch();
$weighed = !empty($p);
if (!$p) $p = $pdo->query("SELECT id FROM products WHERE is_active=1 AND stock>2 ORDER BY id DESC LIMIT 1")->fetch();
ok(!empty($p), 'product-found');
if (!$p) exit;
$pid = (int)$p['id'];
echo "product-has-weight: " . ($weighed ? 'yes' : 'no') . "\n";

/* یک ردیف نرخ‌نامهٔ فعال ⇒ شهر و روشی که رقم دارد */
$r = $pdo->query("SELECT method_key, city, cost FROM shipping_rates WHERE is_active=1 AND city_norm<>'' ORDER BY id ASC LIMIT 1")->fetch();
ok(!empty($r), 'rate-row-found');
if (!$r) exit;
$rateKey  = (string)$r['method_key'];
$rateCity = (string)$r['city'];

$cust = $pdo->query("SELECT id, city FROM customers ORDER BY id ASC LIMIT 1")->fetch();
ok(!empty($cust), 'customer-found');
if (!$cust) exit;
$cid = (int)$cust['id'];
$oldCity = (string)$cust['city'];

/* شهر مشتری موقتا همان شهر نرخ‌نامه می‌شود و در پایان برمی‌گردد */
register_shutdown_function(function () use ($pdo, $cid, $oldCity) {
    $pdo->prepare("UPDATE customers SET city=? WHERE id=?")->execute([$oldCity, $cid]);
    echo "RESTORED customer city\n";
});
$pdo->prepare("UPDATE customers SET city=? WHERE id=?")->execute([$rateCity, $cid]);

$_SESSION['customer_id'] = $cid;
$_SESSION['cart'] = [$pid => 3];
$_SERVER['SCRIPT_NAME']    = '/cart.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

if ($m === 'a' || $m === 'b') {
    if ($m === 'b') shippingSetSessionMethod($rateKey);
    else            shippingClearSessionMethod();

    ob_start();
    include __DIR__ . '/cart.php';
    $html = ob_get_clean();

    ok(strlen($html) > 2000, 'cart-rendered');
    non($html, 'Fatal error', 'no-fatal');
    non($html, 'Warning:', 'no-warning');
    non($html, 'Notice:', 'no-notice');
    non($html, 'Undefined', 'no-undefined-var');

    has($html, 'id="cart-ship-form"', 'ship-form');
    has($html, 'name="shipping_method"', 'ship-radios');
    has($html, 'class="cart-ship-rows"', 'ship-rows');
    has($html, 'csr-price', 'price-span');
    has($html, 'cart-sum-row is-pay', 'payable-row');
    has($html, 'cart-pay-val', 'payable-cell');
    has($html, 'cart-ship-val', 'shipcost-cell');
    has($html, 'مبلغ قابل پرداخت', 'payable-label');
    has($html, 'csr-badge', 'badge-yellow-class');
    has($html, 'is-nocost', 'badge-only-row');
    non($html, 'روش ارسال را در صفحهٔ تسویه انتخاب می‌کنید', 'old-sentence-gone');
    non($html, 'مبلغ کل:', 'old-total-line-gone');
    has($html, '?v=28', 'assets-v28');

    if ($m === 'a') {
        has($html, 'is-locked', 'next-locked-without-pick');
        has($html, 'ابتدا روش ارسال را انتخاب کنید', 'hint-text');
        non($html, 'id="cart-go-hint" hidden', 'hint-visible');
    } else {
        non($html, 'is-locked', 'next-unlocked-with-pick');
        has($html, 'id="cart-go-hint" hidden', 'hint-hidden');
        has($html, 'cart-ship-row is-on', 'picked-row-marked');
    }
}

if ($m === 'c') {
    $items = getCartItems();
    $total = getCartTotal();
    ok(count($items) === 1, 'cart-seeded');

    /* بی‌انتخاب: مبلغ قابل پرداخت = جمع کالاها */
    shippingClearSessionMethod();
    $s0 = shippingCartSummary($items, $rateCity, $total);
    ok($s0['pick'] === '', 'nopick-pick-empty');
    ok((int)$s0['payable'] === (int)$total, 'nopick-payable-eq-goods');
    ok($s0['cost_text'] === shippingRateTexts()['pick'], 'nopick-cost-text');

    /* با انتخاب: هزینه اضافه می‌شود */
    shippingSetSessionMethod($rateKey);
    $s1 = shippingCartSummary($items, $rateCity, $total);
    ok($s1['pick'] === $rateKey, 'pick-stored-in-session');
    ok((int)$s1['cost'] > 0, 'pick-cost-positive');
    ok((int)$s1['payable'] === (int)$total + (int)$s1['cost'], 'payable-eq-goods-plus-ship');
    echo "goods=" . (int)$total . "  ship=" . (int)$s1['cost'] . "  payable=" . (int)$s1['payable'] . "\n";

    /* وزن با تعداد عوض می‌شود ⇒ نرخ هم */
    $_SESSION['cart'] = [$pid => 6];
    $items6 = getCartItems();
    $s2 = shippingCartSummary($items6, $rateCity, getCartTotal());
    ok($s2['weight'] >= $s1['weight'], 'weight-grows-with-qty');
    echo "w3=" . $s1['weight'] . "  w6=" . $s2['weight'] . "  cost3=" . (int)$s1['cost'] . "  cost6=" . (int)$s2['cost'] . "\n";
    $_SESSION['cart'] = [$pid => 3];

    /* وضعیت AJAX که cart.js می‌خواند */
    $st = cartAjaxState($pid);
    ok(isset($st['ship']) && is_array($st['ship']), 'ajax-has-ship');
    if (!empty($st['ship'])) {
        foreach (['rows','pick','weight_line','best_html','cost_text','cost_soft','payable_html','ready'] as $k) {
            ok(array_key_exists($k, $st['ship']), 'ajax-key-' . $k);
        }
        ok($st['ship']['ready'] === true, 'ajax-ready-true');
        ok(count($st['ship']['rows']) > 0, 'ajax-rows');
        $j = json_encode($st, JSON_UNESCAPED_UNICODE);
        ok($j !== false, 'ajax-json-encodable');
    }

    /* «فقط مشهد»: پیک، بی‌نرخ برای این شهر ⇒ فقط نشان */
    $q = shippingCartQuotes($rateCity, 3.0);
    $seenBadgeOnly = false; $seenPriced = false;
    foreach ($q as $row) {
        if (shippingQuoteBadgeOnly($row)) $seenBadgeOnly = true;
        if (!empty($row['known'])) $seenPriced = true;
    }
    ok($seenBadgeOnly, 'badge-only-quote-exists');
    ok($seenPriced, 'priced-quote-exists');

    /* پاک‌شدن سبد باید انتخاب را هم پاک کند */
    shippingSetSessionMethod($rateKey);
    cartClear();
    ok(shippingSessionMethod() === '', 'cartClear-clears-pick');
    $_SESSION['cart'] = [$pid => 3];

    /* CSS: سبز رقم + زرد نشان + ردیف انتخاب‌شده */
    $css = file_get_contents(__DIR__ . '/assets/css/style.css');
    has($css, '.cart-ship-row em { font-style: normal; font-weight: 700; color: #4ADE80;', 'css-price-green');
    has($css, 'i.csr-badge', 'css-badge-yellow');
    has($css, '.cart-ship-row.is-on', 'css-row-selected');
    has($css, '.cart-sum-row.is-pay .cart-pay-val { color: #FBBF24;', 'css-payable-color');
    has($css, '.cart-go .btn.is-locked', 'css-locked-btn');
    ok(strpos($css, '.cart-ship-row.is-on') > strpos($css, '.cart-ship-row.is-best'), 'css-selected-after-best');

    $js = file_get_contents(__DIR__ . '/assets/js/cart.js');
    has($js, 'function applyShipState', 'js-painter');
    has($js, "getElementById('cart-ship-form')", 'js-ship-form-hook');
    has($js, "fd.append('action', 'ship')", 'js-ship-post');
    has($js, 'applyShipState(cart.ship)', 'js-called-on-qty-change');
}

echo "\n---- PASS=$PASS  FAIL=$FAIL ----\n";
