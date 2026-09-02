<?php
require_once 'includes/header.php';

/* ==========================================================================
   صفحهٔ اصلی = فقط بنرها (طرح ثابت با ابعاد مشخص).
   منبع هر بنر به‌ترتیب اولویت:
     ۱) تصویر و لینک ثبت‌شده در پنل ادمین (جدول banners)
     ۲) فایل هم‌نام در uploads/banners/  (main.jpg, wide1.jpg, box1.jpg …)
     ۳) جای‌گیر خاکستری با ابعاد پیشنهادی (وقتی هیچ تصویری نباشد)
   ابعاد (پیکسل):  main 1200×420 | wide1/2 590×250 | box1..4 285×190
   ========================================================================== */

$main   = getMainBanner();
$smalls = getBanners('small');   // مرتب‌شده بر اساس sort_order

/* یک تصویر با نام پایه در uploads/banners/ پیدا کن (fallback فایلی). */
function findBannerFile($base) {
    $dir = __DIR__ . '/uploads/banners/';
    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
        if (is_file($dir . $base . '.' . $ext)) return 'uploads/banners/' . $base . '.' . $ext;
    }
    return '';
}

/* منبع تصویر: اول دیتابیس، بعد فایل هم‌نام. */
function bannerImg($dbImage, $base) {
    if ($dbImage && is_file(__DIR__ . '/uploads/banners/' . $dbImage)) {
        return 'uploads/banners/' . $dbImage;
    }
    return findBannerFile($base);
}

/* رندر یک بنر: تصویر واقعی (لینک‌دار) یا جای‌گیر خالی با ابعاد. */
function renderBanner($base, $w, $h, $label, $img, $href) {
    $ratio = 'aspect-ratio:' . $w . '/' . $h;
    if ($img) {
        $href = $href !== '' ? $href : '#';
        return '<a href="' . h($href) . '" class="banner" style="' . $ratio . '">'
             . '<img src="' . h($img) . '" alt="' . h($label) . '" class="banner-img">'
             . '</a>';
    }
    return '<div class="banner is-empty" style="' . $ratio . '">'
         . '<span class="banner-ph">'
         . '<span class="banner-ph-icon">' . icon('image') . '</span>'
         . '<span class="banner-ph-label">' . h($label) . '</span>'
         . '<span class="banner-ph-size">' . $w . ' &times; ' . $h . ' px</span>'
         . '<span class="banner-ph-file">uploads/banners/' . h($base) . '.jpg</span>'
         . '</span>'
         . '</div>';
}

/* ---------- دو بنر مجزا زیر بنر اصلی ---------- */

/* تصویر محصول (اگر فایلش موجود باشد) */
function productImgSrc($file) {
    if ($file && is_file(__DIR__ . '/uploads/products/' . $file)) {
        return 'uploads/products/' . $file;
    }
    return '';
}

/* بنر «تخفیف ویژه» — زنده از محصولی که در ادمین تیک فروش ویژه خورده.
   کارت سمت راست ردیف زیر بنر اصلی (در RTL اولین فرزند سمت راست است). */
function renderSpecialBanner($p) {
    if (!$p) return '';
    $img   = productImgSrc($p['image'] ?? '');
    $price = (int)($p['retail_price'] ?? 0);
    $off   = (int)($p['retail_discount'] ?? 0);
    $has   = hasDiscount($off);

    $out  = '<a href="product.php?id=' . (int)$p['id'] . '" class="promo-card promo-special">';
    $out .= '<span class="pc-glow" aria-hidden="true"></span>';
    $out .= '<span class="pc-media">';
    $out .= $img
          ? '<img src="' . h($img) . '" alt="' . h($p['name']) . '" loading="lazy">'
          : '<span class="pc-noimg">' . icon('image') . '</span>';
    if ($has) $out .= '<span class="pc-off">' . $off . '٪ تخفیف</span>';
    $out .= '</span>';

    $out .= '<span class="pc-body">';
    $out .= '<span class="pc-tag">' . icon('flame', 'ic-sm') . ' تخفیف ویژه</span>';
    $out .= '<span class="pc-title">' . h($p['name']) . '</span>';
    $out .= '<span class="pc-fill"></span>';
    if ($price > 0) {
        $out .= '<span class="pc-cap">' . icon('tag', 'ic-sm') . ($has ? ' قیمت با تخفیف' : ' قیمت') . '</span>';
        $out .= '<span class="pc-price">';
        if ($has) {
            $out .= '<s>' . number_format($price, 0, '.', ',') . '</s>';
            $out .= '<b>' . formatPriceUnit(discountedPrice($price, $off)) . '</b>';
        } else {
            $out .= '<b>' . formatPriceUnit($price) . '</b>';
        }
        $out .= '</span>';
    }
    $out .= '<span class="pc-cta">مشاهده و خرید' . icon('arrow-left', 'ic-sm') . '</span>';
    $out .= '</span></a>';
    return $out;
}

/* یک اسلاید از «آفر زمان‌دار»: تصویر + قیمت تخفیف‌دار + شمارش معکوس +
   نوار زمان باقی‌مانده. مقدار اولیهٔ شمارنده و نوار در سرور رندر می‌شود
   تا بدون JS هم چیزی دیده شود.
   آفر تمام‌شده (مهلت گذشته یا فروخته‌شده) از صفحه برداشته نمی‌شود؛ بنرش با
   نوشتهٔ «مهلت خرید تمام شد» و در صورت فروش، مهر «فروخته شد» در گوشهٔ چپ بالا
   می‌ماند و کلید خرید به «مشاهدهٔ محصول» تبدیل می‌شود.
   نکته: برچسب متنی «آفر زمان‌دار» به‌درخواست کاربر روی بنر نوشته نمی‌شود. */
function renderOfferBanner($o, $active = true) {
    if (!$o) return '';
    $img = (!empty($o['image']) && is_file(__DIR__ . '/uploads/banners/' . $o['image']))
         ? 'uploads/banners/' . $o['image']
         : productImgSrc($o['product_image'] ?? '');

    $title = trim((string)($o['title'] ?? ''));
    if ($title === '') $title = trim((string)($o['product_name'] ?? '')) ?: 'آفر ویژه';
    $sub  = trim((string)($o['subtitle'] ?? ''));
    $pid  = (int)($o['product_id'] ?? 0);
    $href = $pid > 0 ? 'product.php?id=' . $pid : 'shop.php';

    $price = (int)($o['product_price'] ?? 0);
    $off   = (int)($o['product_discount'] ?? 0);
    $has   = hasDiscount($off);

    $endTs   = !empty($o['end_at'])     ? strtotime($o['end_at'])     : 0;
    $startTs = !empty($o['created_at']) ? strtotime($o['created_at']) : 0;
    $left    = $endTs ? max(0, $endTs - time()) : 0;
    /* درصد زمان باقی‌مانده برای نوار — فقط وقتی بازهٔ آفر معلوم باشد */
    $span = ($endTs && $startTs && $endTs > $startTs) ? ($endTs - $startTs) : 0;
    $pct  = $span ? max(0, min(100, (int)round($left * 100 / $span))) : 0;

    $sold   = !empty($o['is_sold']);
    $expired = ($endTs && $left <= 0);
    $overNow = $sold || $expired;   /* آفر دیگر قابل خرید نیست */

    $d  = (int)floor($left / 86400);
    $hh = (int)floor(($left % 86400) / 3600);
    $mm = (int)floor(($left % 3600) / 60);
    $ss = (int)($left % 60);
    $pad = function ($n) { return str_pad((string)$n, 2, '0', STR_PAD_LEFT); };

    $out  = '<a href="' . h($href) . '" class="promo-card promo-offer promo-slide'
          . ($active ? ' is-active' : '') . ($overNow ? ' is-done' : '')
          . ($sold ? ' is-sold' : '') . '">';
    $out .= '<span class="pc-glow" aria-hidden="true"></span>';
    $out .= '<span class="pc-media">';
    $out .= $img
          ? '<img src="' . h($img) . '" alt="' . h($title) . '" loading="lazy">'
          : '<span class="pc-noimg">' . icon('image') . '</span>';
    if ($has) $out .= '<span class="pc-off">' . $off . '٪ تخفیف</span>';
    /* مهر «فروخته شد» — گوشهٔ چپ بالای تصویر (در RTL یعنی inset-inline-end) */
    if ($sold) $out .= '<span class="pc-sold">' . icon('check-circle', 'ic-sm') . ' فروخته شد</span>';
    $out .= '</span>';

    $out .= '<span class="pc-body">';
    $out .= '<span class="pc-tag">' . icon('clock', 'ic-sm') . ' فرصت محدود</span>';
    $out .= '<span class="pc-title">' . h($title) . '</span>';
    if ($sub !== '') $out .= '<span class="pc-sub">' . h($sub) . '</span>';
    if ($price > 0) {
        $out .= '<span class="pc-price">';
        if ($has) {
            $out .= '<s>' . number_format($price, 0, '.', ',') . '</s>';
            $out .= '<b>' . formatPriceUnit(discountedPrice($price, $off)) . '</b>';
        } else {
            $out .= '<b>' . formatPriceUnit($price) . '</b>';
        }
        $out .= '</span>';
    }
    $out .= '<span class="pc-fill"></span>';

    if ($endTs) {
        $out .= '<span class="pc-cap' . ($overNow ? ' is-hidden' : '') . '">'
              . icon('clock', 'ic-sm') . ' زمان باقی‌مانده</span>';
        /* بدون data-end، شمارندهٔ آفر تمام‌شده در جاوااسکریپت ردیابی نمی‌شود
           (پنهان است و چیزی برای شمردن ندارد). */
        $out .= '<span class="pc-count' . ($overNow ? ' is-over' : '') . '"'
              . ($overNow ? '' : ' data-end="' . $endTs . '"') . '>';
        foreach ([[$d, 'روز'], [$hh, 'ساعت'], [$mm, 'دقیقه'], [$ss, 'ثانیه']] as $i => $u) {
            $out .= '<span class="pc-cd' . ($i === 3 ? ' is-sec' : '') . '">'
                  . '<b>' . $pad($u[0]) . '</b><i>' . $u[1] . '</i></span>';
        }
        $out .= '</span>';
        if ($span) {
            $out .= '<span class="pc-bar" data-start="' . $startTs . '" data-end="' . $endTs . '">'
                  . '<i style="width:' . ($overNow ? 0 : $pct) . '%"></i></span>';
        }
        $out .= '<span class="pc-over">' . icon('alert', 'ic-sm') . ' مهلت خرید تمام شد</span>';
    } elseif ($sold) {
        /* آفر بی‌زمان که فروخته شده: بی‌شمارنده هم باید پیام تمام‌شدن را ببیند */
        $out .= '<span class="pc-over is-shown">' . icon('alert', 'ic-sm') . ' این آفر فروخته شد</span>';
    }
    $out .= '<span class="pc-cta">' . ($overNow ? 'مشاهدهٔ محصول' : 'مشاهده و خرید')
          . icon('arrow-left', 'ic-sm') . '</span>';
    $out .= '</span></a>';
    return $out;
}

/* اسلایدر آفرها: همهٔ آفرهای فعال روی هم قرار می‌گیرند و فقط اسلاید فعال
   دیده می‌شود؛ با ≥۲ آفر، خودکار و با بازهٔ تعیین‌شدهٔ ادمین می‌چرخد.
   بدون JS هم اسلاید اول (بر اساس ترتیب نمایش) دیده می‌شود. */
function renderOfferSlider($offers, $seconds) {
    if (!$offers) return '';
    $sec = max(2, (int)$seconds);
    $out = '<div class="promo-slider" data-ms="' . ($sec * 1000) . '" style="--promo-ms:' . $sec . 's">';
    foreach ($offers as $i => $o) {
        $out .= renderOfferBanner($o, $i === 0);
    }
    $n = count($offers);
    if ($n > 1) {
        $out .= '<div class="promo-dots" role="tablist" aria-label="آفرهای زمان‌دار">';
        for ($i = 0; $i < $n; $i++) {
            $out .= '<button type="button" class="promo-dot' . ($i === 0 ? ' is-active' : '') . '"'
                  . ' data-i="' . $i . '" role="tab" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"'
                  . ' aria-label="آفر ' . ($i + 1) . '"></button>';
        }
        $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
}

/* آفرها را به‌صورت گردشی بین اسلات‌های موجود پخش می‌کند تا هر اسلات
   اسلایدر مستقل خودش را داشته باشد (۵ آفر و ۳ اسلات ⇒ ۲/۲/۱). */
function splitOffers($offers, $slots) {
    $slots  = max(1, (int)$slots);
    $groups = [];
    foreach (array_values($offers) as $i => $o) {
        $groups[$i % $slots][] = $o;
    }
    ksort($groups);
    return $groups;
}

/* اسلات خالی ردیف — کادر خط‌چین، همان زبان بقیهٔ اسلات‌های این صفحه.
   ردیف همیشه سه‌تایی می‌ماند تا جمع عرض کارت‌ها اندازهٔ بنر اصلی بشود.
   راهنمای پرکردن فقط برای مدیر واردشده نوشته می‌شود، نه برای مشتری. */
function renderPromoPlaceholder() {
    $admin = function_exists('isLoggedIn') && isLoggedIn();
    $out  = '<div class="promo-card promo-empty">';
    $out .= '<span class="pc-media">' . icon('tag', 'ic-xl') . '</span>';
    $out .= '<span class="pc-body">';
    $out .= '<span class="pc-title">' . ($admin ? 'جای بنر آفر' : 'آفر بعدی به‌زودی') . '</span>';
    $out .= '<span class="pc-sub">' . ($admin
          ? 'از «مدیریت بنرها ← آفر زمان‌دار» پر می‌شود.'
          : 'این جایگاه به‌زودی با یک پیشنهاد ویژه پر می‌شود.') . '</span>';
    $out .= '<span class="pc-fill"></span>';
    $out .= '</span></div>';
    return $out;
}

$special  = getSpecialSaleProduct();
$offers   = getActiveTimedOffers(9);
$slideSec = offerSlideSeconds();

/* ---------- ردیف سه‌کارتی زیر بنر اصلی ----------
   همیشه سه اسلات رندر می‌شود تا جمع عرضشان دقیقا اندازهٔ بنر اصلی باشد.
   اگر محصولی تیک «فروش ویژه» داشته باشد، اسلات اول از آن اوست و آفرهای
   زمان‌دار بین اسلات‌های باقی‌مانده پخش می‌شوند؛ اسلات بی‌محتوا خط‌چین می‌شود. */
$promoSlots = 3;
$promoCards = [];
if ($special) $promoCards[] = renderSpecialBanner($special);
$freeSlots = $promoSlots - count($promoCards);
if ($freeSlots > 0 && $offers) {
    foreach (splitOffers($offers, min($freeSlots, count($offers))) as $grp) {
        $promoCards[] = renderOfferSlider($grp, $slideSec);
    }
}
while (count($promoCards) < $promoSlots) {
    $promoCards[] = renderPromoPlaceholder();
}

/* بنر اصلی (تمام‌عرض) */
$mainImg  = bannerImg($main['image'] ?? '', 'main');
$mainHref = ($main && !empty($main['link_url'])) ? $main['link_url'] : 'shop.php';

/* شش بنر کوچک از دیتابیس → به‌ترتیب روی اسلات‌های wide1,wide2,box1..box4 نگاشت می‌شوند */
$order = [
    ['base' => 'wide1', 'w' => 590, 'h' => 250, 'label' => 'بنر عریض ۱'],
    ['base' => 'wide2', 'w' => 590, 'h' => 250, 'label' => 'بنر عریض ۲'],
    ['base' => 'box1',  'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۱'],
    ['base' => 'box2',  'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۲'],
    ['base' => 'box3',  'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۳'],
    ['base' => 'box4',  'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۴'],
];
$slot = [];
foreach ($order as $i => $s) {
    $row = $smalls[$i] ?? null;
    $slot[$s['base']] = [
        'w'     => $s['w'],
        'h'     => $s['h'],
        'label' => $s['label'],
        'img'   => bannerImg($row['image'] ?? '', $s['base']),
        'href'  => $row['link_url'] ?? '',
    ];
}
?>

<?php
/* ==========================================================================
   لایهٔ تزیینی گوشه‌های صفحهٔ اصلی (پشت محتوا، z-index:-1).
   قابل فعال/غیرفعال‌سازی و انتخاب طرح از پنل مدیریت (تنظیمات سایت).
   طرح‌ها با SVG درون‌خطی ساخته می‌شوند تا به فایل تصویری نیاز نباشد.
   ========================================================================== */
if (getSettingRaw('home_decor_enabled', '1') === '1'):
    $decorStyle = getSetting('home_decor_style', 'auto');

    if (!function_exists('decorSvg')) {
        function decorGear($teeth = 12) {
            $t = '';
            for ($i = 0; $i < $teeth; $i++) {
                $ang = $i * (360 / $teeth);
                $t .= '<rect x="47" y="2" width="6" height="15" rx="2" transform="rotate(' . round($ang, 2) . ' 50 50)"/>';
            }
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="3">'
                 . '<circle cx="50" cy="50" r="30"/><circle cx="50" cy="50" r="12"/>'
                 . '<g fill="currentColor" stroke="none">' . $t . '</g></svg>';
        }
        function decorWheel() {
            $sp = '';
            for ($i = 0; $i < 5; $i++) {
                $sp .= '<line x1="50" y1="50" x2="50" y2="26" transform="rotate(' . ($i * 72) . ' 50 50)"/>';
            }
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="3">'
                 . '<circle cx="50" cy="50" r="40"/><circle cx="50" cy="50" r="31" stroke-dasharray="5 5"/>'
                 . '<circle cx="50" cy="50" r="22"/><g stroke-width="4">' . $sp . '</g>'
                 . '<circle cx="50" cy="50" r="6" fill="currentColor"/></svg>';
        }
        function decorNut() {
            $pts = [];
            for ($i = 0; $i < 6; $i++) {
                $a = deg2rad(60 * $i - 90);
                $pts[] = round(50 + 42 * cos($a), 1) . ',' . round(50 + 42 * sin($a), 1);
            }
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round">'
                 . '<polygon points="' . implode(' ', $pts) . '"/><circle cx="50" cy="50" r="19"/></svg>';
        }
        function decorPiston() {
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round">'
                 . '<rect x="28" y="12" width="44" height="30" rx="5"/>'
                 . '<line x1="30" y1="22" x2="70" y2="22"/><line x1="30" y1="29" x2="70" y2="29"/>'
                 . '<circle cx="50" cy="52" r="6"/><path d="M50 58 L42 88 M50 58 L58 88"/>'
                 . '<line x1="40" y1="88" x2="60" y2="88"/></svg>';
        }
        function decorSpring() {
            $p = '';
            for ($i = 0; $i < 7; $i++) {
                $y = 22 + $i * 9;
                $p .= '<path d="M30 ' . $y . ' C 46 ' . ($y - 6) . ', 54 ' . ($y + 15) . ', 70 ' . ($y + 9) . '"/>';
            }
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round">'
                 . '<line x1="24" y1="14" x2="76" y2="14"/>' . $p . '<line x1="24" y1="90" x2="76" y2="90"/></svg>';
        }
        function decorBolt() {
            $th = '';
            for ($i = 0; $i < 7; $i++) {
                $y = 34 + $i * 7;
                $th .= '<line x1="41" y1="' . $y . '" x2="59" y2="' . ($y + 4) . '"/>';
            }
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linejoin="round" stroke-linecap="round">'
                 . '<path d="M34 12 h32 v16 h-32 z"/><path d="M41 28 h18 v50 l-9 10 l-9 -10 z"/>'
                 . '<g stroke-width="2.2">' . $th . '</g></svg>';
        }
        function decorOil() {
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linejoin="round" stroke-linecap="round">'
                 . '<path d="M50 10 C 50 10, 78 46, 78 62 a28 28 0 0 1 -56 0 C 22 46, 50 10, 50 10 z"/>'
                 . '<path d="M38 60 a12 12 0 0 0 9 14" stroke-width="2.4"/></svg>';
        }
        function decorCircuit() {
            return '<svg viewBox="0 0 100 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">'
                 . '<path class="decor-trace" d="M4 24 H34 L48 38 H72 L88 24"/>'
                 . '<path class="decor-trace" d="M4 52 H26 L40 66 H64 L78 52 H96"/>'
                 . '<path class="decor-trace" d="M12 86 H44 L58 72 H90"/>'
                 . '<g fill="currentColor" stroke="none">'
                 . '<circle cx="34" cy="24" r="3.4"/><circle cx="72" cy="38" r="3.4"/><circle cx="26" cy="52" r="3.4"/>'
                 . '<circle cx="64" cy="66" r="3.4"/><circle cx="44" cy="86" r="3.4"/><circle cx="58" cy="72" r="3.4"/></g>'
                 . '<rect x="42" y="12" width="16" height="10" rx="2"/><rect x="66" y="78" width="18" height="10" rx="2"/></svg>';
        }
        /* ماشین نیم‌رخ؛ خط زمین روی y=100 است تا با نوار جاده هم‌تراز شود.
           چرخ‌ها در گروه .car-wheel هستند و با CSS دور محور خودشان می‌چرخند. */
        function decorCarWheel($cx) {
            return '<g transform="translate(' . $cx . ' 83)"><g class="car-wheel">'
                 . '<circle r="17" stroke-width="4"/><circle r="6.5" fill="currentColor" stroke="none"/>'
                 . '<g stroke-width="2.6"><line x1="0" y1="-12" x2="0" y2="12"/><line x1="-12" y1="0" x2="12" y2="0"/>'
                 . '<line x1="-8.5" y1="-8.5" x2="8.5" y2="8.5"/><line x1="-8.5" y1="8.5" x2="8.5" y2="-8.5"/></g>'
                 . '</g></g>';
        }
        function decorCar() {
            return '<svg viewBox="0 0 240 100" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round" stroke-linecap="round">'
                 . '<path d="M12 83 L12 65 C12 58 16 54 24 53 L60 50 C70 36 90 30 112 30 L146 30 C161 30 172 35 180 46 L200 52 L219 58 C226 60 230 64 230 70 L230 83"/>'
                 . '<path d="M12 83 L38 83 A20 20 0 0 0 78 83 L162 83 A20 20 0 0 0 202 83 L228 83"/>'
                 . '<path d="M68 50 C76 39 92 34 110 34 L110 49 Z" stroke-width="3"/>'
                 . '<path d="M118 34 L143 34 C153 34 161 37 167 45 L118 49 Z" stroke-width="3"/>'
                 . '<g stroke-width="3"><line x1="120" y1="58" x2="133" y2="58"/>'
                 . '<line x1="224" y1="63" x2="231" y2="63"/><line x1="13" y1="70" x2="20" y2="70"/></g>'
                 . decorCarWheel(58) . decorCarWheel(182)
                 . '</svg>';
        }
        function decorSvg($kind) {
            switch ($kind) {
                case 'wheel':   return decorWheel();
                case 'nut':     return decorNut();
                case 'piston':  return decorPiston();
                case 'spring':  return decorSpring();
                case 'bolt':    return decorBolt();
                case 'oil':     return decorOil();
                case 'circuit': return decorCircuit();
                default:        return decorGear();
            }
        }
    }

    /* هر طرح = مجموعهٔ ۴ گوشه + جلوه‌های افزودنی (ماشین، جاده، قطعات شناور، نئون، پیستون) */
    $decorStyles = [
        'gears'  => ['corners' => ['gear', 'gear', 'gear', 'gear']],
        'tire'   => ['corners' => ['wheel', 'wheel', 'wheel', 'wheel']],
        'tools'  => ['corners' => ['nut', 'gear', 'piston', 'nut']],
        'auto'   => ['corners' => ['gear', 'nut', 'wheel', 'piston']],
        'car'    => ['corners' => ['gear', 'wheel', 'wheel', 'gear'], 'car' => true],
        'road'   => ['corners' => ['wheel', 'nut', 'nut', 'wheel'], 'car' => true, 'road' => true],
        'engine' => ['corners' => ['piston', 'gear', 'gear', 'piston'], 'pump' => true],
        'neon'   => ['corners' => ['circuit', 'circuit', 'circuit', 'circuit'], 'neon' => true],
        'float'  => ['corners' => ['gear', 'gear', 'wheel', 'nut'], 'float' => true],
    ];
    $cfg = $decorStyles[$decorStyle] ?? $decorStyles['auto'];
    $decorSet = $cfg['corners'];
    $decorCorners = ['tl', 'tr', 'bl', 'br'];
    $decorFloats = ['bolt', 'spring', 'oil', 'nut', 'bolt', 'spring'];
?>
<div class="home-decor <?= !empty($cfg['neon']) ? 'decor-neon' : '' ?>" aria-hidden="true">
    <?php foreach ($decorCorners as $ci => $corner): $kind = $decorSet[$ci];
        $fx = '';
        if (!empty($cfg['pump']) && $kind === 'piston')      $fx = 'decor-pump';
        elseif ($kind === 'gear' || $kind === 'wheel')       $fx = ($ci % 2 === 0 ? 'decor-spin' : 'decor-spin-rev'); ?>
    <span class="decor-corner decor-<?= $corner ?> <?= $fx ?>"><?= decorSvg($kind) ?></span>
    <?php endforeach; ?>

    <?php if (!empty($cfg['road'])): ?>
    <span class="decor-road"><span class="decor-road-line"></span></span>
    <?php endif; ?>

    <?php if (!empty($cfg['car'])): ?>
    <span class="decor-car"><?= decorCar() ?></span>
    <?php endif; ?>

    <?php if (!empty($cfg['float'])): foreach ($decorFloats as $fi => $fkind): ?>
    <span class="decor-float decor-f<?= $fi + 1 ?>"><?= decorSvg($fkind) ?></span>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>

<div class="container banners-page">
    <div class="banners-wrap">

        <!-- بنر اصلی: تمام‌عرض کانتینر و دست‌نخورده -->
        <?= renderBanner('main', 1200, 420, 'بنر اصلی', $mainImg, $mainHref) ?>

        <!-- سه بنر مجزا زیر بنر اصلی: «تخفیف ویژه» (اگر محصولی تیک داشته
             باشد) و آفرهای زمان‌دار. مجموع عرض سه کارت دقیقا به اندازهٔ
             بنر اصلی است و فاصلهٔ بین‌شان آن‌ها را از هم جدا نگه می‌دارد.
             ردیف همیشه سه‌تایی رندر می‌شود؛ اسلات بی‌محتوا خط‌چین می‌ماند. -->
        <div class="promo-row">
            <?php foreach ($promoCards as $c) echo $c; ?>
        </div>

        <!-- دو بنر عریض -->
        <div class="banners-row row-2">
            <?php foreach (['wide1', 'wide2'] as $k): $b = $slot[$k]; ?>
            <?= renderBanner($k, $b['w'], $b['h'], $b['label'], $b['img'], $b['href']) ?>
            <?php endforeach; ?>
        </div>

        <!-- چهار بنر کوچک -->
        <div class="banners-row row-4">
            <?php foreach (['box1', 'box2', 'box3', 'box4'] as $k): $b = $slot[$k]; ?>
            <?= renderBanner($k, $b['w'], $b['h'], $b['label'], $b['img'], $b['href']) ?>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php if ($offers): ?>
<script>
/* آفرهای زمان‌دار: شمارش معکوس همهٔ اسلایدها + چرخش خودکار اسلایدر.
   بدون کتابخانه؛ مقصد هر شمارنده به‌صورت epoch در data-end است. */
(function () {
    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    /* ---------- شمارش معکوس: هر اسلاید شمارندهٔ خودش را دارد ---------- */
    var counts = [];
    var boxes  = document.querySelectorAll('.pc-count[data-end]');
    for (var i = 0; i < boxes.length; i++) {
        var cells = boxes[i].querySelectorAll('b');
        if (cells.length < 4) continue;
        var bar = boxes[i].parentNode.querySelector('.pc-bar[data-start]');
        var cap = boxes[i].previousElementSibling;
        counts.push({
            box:   boxes[i],
            cells: cells,
            end:   parseInt(boxes[i].getAttribute('data-end'), 10) * 1000,
            bar:   bar ? bar.querySelector('i') : null,
            start: bar ? parseInt(bar.getAttribute('data-start'), 10) * 1000 : 0,
            cap:   (cap && cap.className.indexOf('pc-cap') !== -1) ? cap : null
        });
    }

    var timer = null;
    function tick() {
        var now = new Date().getTime(), live = 0;
        for (var i = 0; i < counts.length; i++) {
            var c = counts[i];
            var left = Math.floor((c.end - now) / 1000);
            if (left <= 0) {
                c.box.classList.add('is-over');
                for (var j = 0; j < 4; j++) c.cells[j].textContent = '00';
                if (c.bar) c.bar.style.width = '0%';
                /* برچسب «زمان باقی‌مانده» پیش از شمارنده است و با انتخابگر
                   خواهر CSS قابل دسترسی نیست؛ پس همین‌جا پنهان می‌شود. */
                if (c.cap) c.cap.style.display = 'none';
                continue;
            }
            live++;
            c.cells[0].textContent = pad(Math.floor(left / 86400));
            c.cells[1].textContent = pad(Math.floor((left % 86400) / 3600));
            c.cells[2].textContent = pad(Math.floor((left % 3600) / 60));
            c.cells[3].textContent = pad(left % 60);
            /* نوار زمان: نسبت زمان باقی‌مانده به کل بازهٔ آفر */
            if (c.bar && c.start && c.end > c.start) {
                var p = (c.end - now) * 100 / (c.end - c.start);
                c.bar.style.width = Math.max(0, Math.min(100, p)).toFixed(2) + '%';
            }
        }
        if (!live && timer) { clearInterval(timer); timer = null; }
    }
    if (counts.length) { timer = setInterval(tick, 1000); tick(); }

    /* ---------- چرخش خودکار اسلایدرها ----------
       هر اسلات ردیف می‌تواند اسلایدر مستقل خودش باشد، پس روی همهٔ
       اسلایدرها حلقه می‌زنیم و هرکدام تایمر و وضعیت جدا می‌گیرد.
       اسلایدر تک‌اسلایدی نادیده گرفته می‌شود (چیزی برای چرخاندن ندارد). */
    var sliders = document.querySelectorAll('.promo-slider');
    for (var s = 0; s < sliders.length; s++) initSlider(sliders[s]);

    function initSlider(sl) {
        var slides = sl.querySelectorAll('.promo-slide');
        var dots   = sl.querySelectorAll('.promo-dot');
        if (slides.length < 2) return;

        var ms  = parseInt(sl.getAttribute('data-ms'), 10) || 6000;
        var cur = 0, spin = null;

        function show(n) {
            cur = (n + slides.length) % slides.length;
            for (var i = 0; i < slides.length; i++) {
                if (i === cur) slides[i].classList.add('is-active');
                else           slides[i].classList.remove('is-active');
                if (dots[i]) {
                    if (i === cur) dots[i].classList.add('is-active');
                    else           dots[i].classList.remove('is-active');
                    dots[i].setAttribute('aria-selected', i === cur ? 'true' : 'false');
                }
            }
        }
        function stop() { if (spin) { clearInterval(spin); spin = null; } }
        function play() { stop(); spin = setInterval(function () { show(cur + 1); }, ms); }

        for (var k = 0; k < dots.length; k++) {
            (function (n) {
                dots[n].addEventListener('click', function (e) {
                    e.preventDefault();
                    if (n !== cur) show(n);
                    play();
                });
            })(k);
        }
        /* با نگه‌داشتن موس روی اسلایدر چرخش می‌ایستد تا کاربر فرصت خواندن داشته
           باشد؛ در تب پنهان هم بی‌جهت نمی‌چرخد. */
        sl.addEventListener('mouseenter', stop);
        sl.addEventListener('mouseleave', play);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop(); else play();
        });
        play();
    }
})();
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
