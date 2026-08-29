<?php
/* بررسی موقت — بنرهای زیرِ بنر اصلی + اسلایدرِ آفر زمان‌دار.
   یک‌بارمصرف و با کلید محافظت‌شده؛ پس از استفاده با _404stub.php بی‌اثر می‌شود. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
if (($_GET['key'] ?? '') !== 'c15promo4419') { http_response_code(404); exit('404'); }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

function say($k, $v) { echo str_pad($k, 42, '.') . ' ' . $v . "\n"; }
function yn($b) { return $b ? 'YES' : 'no'; }
function src($rel) { $p = __DIR__ . '/' . $rel; return is_file($p) ? (string)file_get_contents($p) : ''; }
function has($h, $n) { return strpos($h, $n) !== false; }

echo "=== توابع ===\n";
say('timedOffersReady()', yn(timedOffersReady()));
say('specialSaleReady()', yn(specialSaleReady()));
say('getActiveTimedOffers exists', yn(function_exists('getActiveTimedOffers')));
say('offerSlideSeconds exists', yn(function_exists('offerSlideSeconds')));

$offers = getActiveTimedOffers(8);
say('active offers', count($offers));
foreach ($offers as $i => $o) {
    say("  #$i id/sort/end", $o['id'] . ' / ' . $o['sort_order'] . ' / ' . ($o['end_at'] ?: '-'));
    say("  #$i product/price/disc", ($o['product_name'] ?? '-') . ' / ' . (int)($o['product_price'] ?? 0) . ' / ' . (int)($o['product_discount'] ?? 0) . '%');
    say("  #$i created_at (bar span)", $o['created_at'] ?: 'NULL');
}
say('legacy getActiveTimedOffer()', $offers ? 'id=' . (getActiveTimedOffer()['id'] ?? '?') : 'null (ok)');
$sp = getSpecialSaleProduct();
say('special sale product', $sp ? ('id=' . $sp['id'] . ' — ' . $sp['name']) : 'none (بنر تخفیف ویژه رندر نمی‌شود)');

echo "\n=== بازهٔ چرخش (settings) ===\n";
$before = getSettingRaw('offer_slide_seconds', '(absent)');
say('current value', $before);
say('offerSlideSeconds()', offerSlideSeconds());
/* رفت‌وبرگشتِ نوشتن — همان مسیری که فرم ادمین استفاده می‌کند */
setSetting('offer_slide_seconds', '9');  getAllSettings(true);
say('after write 9', offerSlideSeconds() === 9 ? 'YES (9)' : 'no (' . offerSlideSeconds() . ')');
setSetting('offer_slide_seconds', '999'); getAllSettings(true);
say('clamp 999 -> 60', offerSlideSeconds() === 60 ? 'YES' : 'no (' . offerSlideSeconds() . ')');
setSetting('offer_slide_seconds', '0');   getAllSettings(true);
say('clamp 0 -> 2', offerSlideSeconds() === 2 ? 'YES' : 'no (' . offerSlideSeconds() . ')');
$restore = ($before === '(absent)' || $before === '') ? '6' : $before;
setSetting('offer_slide_seconds', $restore); getAllSettings(true);
say('restored to', offerSlideSeconds());

echo "\n=== صفحهٔ اصلی (banners.php) ===\n";
$b = src('banners.php');
say('has .promo-row', yn(has($b, 'class="promo-row"')));
say('has renderOfferSlider()', yn(has($b, 'function renderOfferSlider')));
say('main banner full width', yn(has($b, "renderBanner('main', 1200, 420")));
say('NO banner-hero-row', yn(!has($b, 'banner-hero-row')));
say('NO hero-main', yn(!has($b, 'hero-main')));
say('NO side-banner', yn(!has($b, 'side-banner')));
say('NO literal آفر زمان‌دار on card', yn(!has($b, "' آفر زمان‌دار<")));

echo "\n=== CSS ===\n";
$c = src('assets/css/style.css');
say('.promo-row', yn(has($c, '.promo-row {')));
say('.promo-card', yn(has($c, '.promo-card {')));
say('slider hide rule is child sel', yn(has($c, '.promo-slider > .promo-slide { display: none; }')));
say('.pc-count / .pc-cd / .pc-bar', yn(has($c, '.pc-count {') && has($c, '.pc-cd {') && has($c, '.pc-bar {')));
say('NO .side-banner', yn(!has($c, '.side-banner')));
say('NO 1560px gutter block', yn(!has($c, 'min-width: 1560px')));
say('.pc-stars intact (cards)', yn(has($c, '.pc-stars {')));

echo "\n=== ادمین ===\n";
$ab = src('admin/banners.php');
say('offer_slide_seconds input', yn(has($ab, 'name="offer_slide_seconds"')));
say('save_offer_slide handler', yn(has($ab, "isset(\$_POST['save_offer_slide'])")));
say('save_offer_slide button', yn(has($ab, 'name="save_offer_slide"')));
say('omsg slide message', yn(has($ab, "'slide' =>")));
say('heading updated', yn(has($ab, 'آفر زمان‌دار (بنرِ زیرِ بنر اصلی)')));
say('NO عمودی/چپ wording', yn(!has($ab, 'بنر عمودی') && !has($ab, 'سمت <b>چپِ</b>')));
$ap = src('admin/products.php');
say('products.php hint updated', yn(has($ap, 'زیرِ بنر اصلی') && !has($ap, 'بنر عمودی')));
$pe = src('admin/product-edit.php');
say('product-edit hint updated', yn(has($pe, 'زیرِ بنر اصلی') && !has($pe, 'بنر عمودی')));

echo "\n=== نسخهٔ فایل‌های استاتیک ===\n";
foreach (['includes/header.php', 'includes/footer.php', 'admin/layout-top.php', 'admin/orders.php',
          'admin/order-detail.php', 'admin/login.php', 'payment-start.php', 'payment-callback.php',
          'payment-gateway-sim.php'] as $f) {
    $s = src($f);
    say($f, (has($s, '?v=20') ? 'v20 YES' : 'v20 no') . (has($s, '?v=19') ? ' — هنوز v19 دارد!' : ''));
}

echo "\nDONE\n";
