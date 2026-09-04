<?php
/* =====================================================================
   لایهٔ روش‌های ارسال — انتخاب مشتری در صفحهٔ تسویه
   ---------------------------------------------------------------------
   طراحی:
   • فهرست روش‌ها در جدول `shipping_methods` است، پس مدیر می‌تواند روش
     جدید اضافه کند یا روشی را حذف کند بدون هیچ تغییری در کد. اگر آن جدول
     ساخته نشده باشد (پیش از اجرای مهاجرت) همان هشت روش پیش‌فرض کد با
     همان کلیدهای تنظیمات قبلی کار می‌کنند تا صفحات خطا ندهند.
   • هزینهٔ ارسال به «مبلغ قابل پرداخت» اضافه می‌شود تا درگاه پرداخت
     مجموع کالا + ارسال را بگیرد.
   • «پس‌کرایه» (تیک freight_collect روی روش) یعنی محاسبه‌ای انجام نمی‌شود:
     هیچ رقمی به سفارش اضافه نمی‌شود و به مشتری فقط «پس‌کرایه» نشان داده
     می‌شود (خواستهٔ صریح مدیر — نه برآورد، نه رقم راهنما).
   • «نرخ‌نامه» (جدول `shipping_rates`): برای هر روش، برای هر شهر یک ردیف
     «هر چند کیلوگرم، چند تومان». هزینه = تعداد واحدهای وزنی سبد (رند به
     بالا) × قیمت هر واحد. مثال: تبریز، پست، هر ۱ کیلو ۵۰٬۰۰۰ تومان و سبد
     ۳ کیلویی ⇒ ۱۵۰٬۰۰۰ تومان. اگر برای شهر مشتری نرخی ثبت نشده باشد
     هیچ رقمی اضافه نمی‌شود و می‌گوییم «پس از ثبت سفارش هماهنگ می‌شود».
   • «پیک مشهد» همیشه در فهرست می‌ماند و هیچ‌وقت پنهان نمی‌شود، ولی از
     ۱۴۰۵/۰۶/۰۳ اگر شهر پروفایل مشتری خوانده شود و مشهد نباشد، گزینه
     **غیرفعال** نشان داده می‌شود (خواستهٔ مدیر: «وقتی لوکیشنم غیر از مشهده باید
     گزینه ارسال با پیک(مشهد) برایم غیر فعال نشون داده بشه») — قاعده‌اش در
     shippingCityBlocked است و شهر محدودیت از نشان خود روش خوانده می‌شود.
     محافظ سر جایش است: شهر خالی یا ناشناخته چیزی را مسدود نمی‌کند، پس
     «مشهد مقدس»/«Mashhad»/غلط تایپی هیچ سفارشی را نمی‌بندد. نرخ‌نامه هم همین
     قاعده را دارد: نخواندن شهر یعنی «هزینه را نمی‌دانیم»، نه «ممنوع است».
   ===================================================================== */
require_once __DIR__ . '/db.php';

/* ---------- روش‌های پیش‌فرض (فقط تا وقتی جدول ساخته نشده) ---------- */
/* label: نام روش | icon: نام آیکون از iconLib() | hint: توضیح کمکی
   badge / badge_short : یادآوری محدودیت. badge_short برای کارت‌های فشردهٔ صفحهٔ
   تسویه است (فضای یک ستون از دو ستون) و badge برای جاهای عریض مثل پنل ادمین.
   cod_only : روی این روش ارسال «پرداخت در محل» مجاز است (و روی بقیهٔ روش‌ها
   مجاز نیست) — تصمیم مدیر، shippingAllowedPayKeys. نام ستون از نسخهٔ قبل
   مانده؛ معنایش امروز «پرداخت در محل مجاز است» است، نه «فقط پرداخت در محل».
   همین آرایه بذر اولیهٔ جدول `shipping_methods` هم هست (migrate-shiprates.php). */
function shippingDefaultMethods() {
    return [
        'peyk_mashhad' => [
            'label' => 'ارسال با پیک (مشهد)',
            'icon'  => 'bike',
            'hint'  => 'فقط برای شهر مشهد — تحویل درون‌شهری در سریع‌ترین زمان.',
            'badge' => 'فقط برای شهر مشهد',
            'badge_short' => 'مشهد',
            'cod_only'    => true,
        ],
        'post_sefareshi' => [
            'label' => 'پست سفارشی',
            'icon'  => 'mail',
            'hint'  => 'ارسال با پست جمهوری اسلامی — اقتصادی، با کد رهگیری.',
        ],
        'post_pishtaz' => [
            'label' => 'پست پیشتاز',
            'icon'  => 'send',
            'hint'  => 'ارسال سریع پست با کد رهگیری.',
        ],
        'barbari' => [
            'label' => 'باربری',
            'icon'  => 'truck',
            'hint'  => 'مناسب بارهای حجیم و سنگین.',
            'freight_collect' => true,
        ],
        'chapar' => [
            'label' => 'چاپار',
            'icon'  => 'package',
            'hint'  => 'ارسال با شرکت چاپار.',
        ],
        'tipax' => [
            'label' => 'تیپاکس',
            'icon'  => 'layers',
            'hint'  => 'ارسال با شرکت تیپاکس.',
        ],
        'digi_express' => [
            'label' => 'دیجی اکسپرس',
            'icon'  => 'store',
            'hint'  => 'ارسال با سرویس دیجی اکسپرس.',
        ],
        'post_havaei' => [
            'label' => 'پست هوایی',
            'icon'  => 'plane',
            'hint'  => 'سریع‌ترین روش پستی برای شهرهای دور.',
        ],
    ];
}

/* آیا جدول روش‌ها ساخته شده؟ (مثل paymentReady/shippingReady کش می‌شود) */
function shippingTableReady() {
    if (isset($GLOBALS['__ship_tbl'])) return $GLOBALS['__ship_tbl'];
    $GLOBALS['__ship_tbl'] = dbHasTable('shipping_methods');
    return $GLOBALS['__ship_tbl'];
}

/* آیا جدول نرخ‌نامه ساخته شده؟ */
function shippingRatesReady() {
    if (isset($GLOBALS['__ship_rates'])) return $GLOBALS['__ship_rates'];
    $GLOBALS['__ship_rates'] = dbHasTable('shipping_rates');
    return $GLOBALS['__ship_rates'];
}

/* آیا ستون وزن روی محصولات ساخته شده؟ (وزن اختیاری است) */
function shippingWeightReady() {
    if (isset($GLOBALS['__ship_weight'])) return $GLOBALS['__ship_weight'];
    $GLOBALS['__ship_weight'] = dbHasColumn('products', 'weight');
    return $GLOBALS['__ship_weight'];
}

/* آیا نرخ‌نامه «واحد وزن» دارد؟ (نرخ به‌ازای هر چند کیلو، جای پله‌ای قبلی) */
function shippingRateUnitReady() {
    if (isset($GLOBALS['__ship_unit'])) return $GLOBALS['__ship_unit'];
    $GLOBALS['__ship_unit'] = shippingRatesReady() && dbHasColumn('shipping_rates', 'weight_unit');
    return $GLOBALS['__ship_unit'];
}

/* آیا جدول شهرها ساخته شده؟ */
function shippingCitiesReady() {
    if (isset($GLOBALS['__ship_cities'])) return $GLOBALS['__ship_cities'];
    $GLOBALS['__ship_cities'] = dbHasTable('cities');
    return $GLOBALS['__ship_cities'];
}

/* ---------- فهرست شهرها ----------
   یک‌بار در هر درخواست خوانده و کش می‌شود؛ انتخابگر ادمین، انتخابگر شهر در
   پروفایل و datalist صفحهٔ تسویه همه از همین می‌خوانند. */
function shippingCityRows($refresh = false) {
    if (!$refresh && isset($GLOBALS['__ship_city_rows'])) return $GLOBALS['__ship_city_rows'];
    $rows = [];
    if (shippingCitiesReady()) {
        global $pdo;
        try {
            $rows = $pdo->query("SELECT id, name, name_norm, province FROM cities
                                 WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
        } catch (Throwable $e) { $rows = []; }
    }
    $GLOBALS['__ship_city_rows'] = $rows;
    return $rows;
}

/* شهرها گروه‌بندی‌شده بر اساس استان — برای <optgroup> در انتخابگرها */
function shippingCityGroups() {
    $out = [];
    foreach (shippingCityRows() as $r) {
        $p = trim((string)$r['province']);
        if ($p === '') $p = 'سایر';
        $out[$p][] = (string)$r['name'];
    }
    return $out;
}

/* نام همه شهرها (فهرست تخت) — شهرهای نرخ‌نامه هم اضافه می‌شوند تا اگر مدیر
   شهری را دستی در نرخ‌نامه نوشته باشد، در انتخابگرها گم نشود. */
function shippingCityNames() {
    $out = [];
    foreach (shippingCityRows() as $r) $out[(string)$r['name']] = true;
    foreach (shippingRateCities() as $c) { $c = trim((string)$c); if ($c !== '') $out[$c] = true; }
    return array_keys($out);
}

/* نام رسمی شهر از روی چیزی که کاربر نوشته (برای یکدست‌سازی پروفایل و سفارش).
   اگر نخواند، همان ورودی برمی‌گردد — هیچ‌وقت ورودی کاربر دور انداخته نمی‌شود. */
function shippingCityCanonical($city) {
    $n = shipNormCity($city);
    if ($n === '') return trim((string)$city);
    foreach (shippingCityRows() as $r) {
        if ((string)$r['name_norm'] === $n) return (string)$r['name'];
    }
    return trim((string)$city);
}

/* همان shippingCityGroups() ولی شهرهایی که فقط در نرخ‌نامه ثبت شده‌اند هم زیر
   «سایر» اضافه می‌شوند، تا شهر دستی مدیر از انتخابگرها جا نماند (همان کاری که
   shippingCityNames() برای فهرست تخت می‌کند). */
function shippingCityGroupsAll() {
    $groups = shippingCityGroups();
    $known  = [];
    foreach ($groups as $list) {
        foreach ($list as $cn) $known[shipNormCity($cn)] = true;
    }
    foreach (shippingRateCities() as $rc) {
        $rc = trim((string)$rc);
        if ($rc === '') continue;
        $n = shipNormCity($rc);
        if ($n === '' || isset($known[$n])) continue;
        $known[$n] = true;
        $groups['سایر'][] = $rc;
    }
    return $groups;
}

/* نام استان‌ها به ترتیب خود جدول شهرها (پایتخت هر استان اول آمده است) */
function shippingProvinceNames() {
    return array_keys(shippingCityGroupsAll());
}

/* استان یک شهر، با همان مقایسهٔ ساده‌گیر shipNormCity. اگر شهر شناخته نشود ''
   برمی‌گردد تا فرم مقدار قبلی خود کاربر را دور نریزد. */
function shippingCityProvince($city) {
    $n = shipNormCity($city);
    if ($n === '') return '';
    foreach (shippingCityRows() as $r) {
        if ((string)$r['name_norm'] === $n) {
            $p = trim((string)$r['province']);
            return $p === '' ? 'سایر' : $p;
        }
    }
    foreach (shippingCityGroupsAll() as $p => $list) {
        foreach ($list as $cn) if (shipNormCity($cn) === $n) return $p;
    }
    return '';
}

/* ---------- انتخابگر دوسطحی «استان ← شهر» برای فرم‌های مشتری ----------
   خواستهٔ کاربر: «اسم استان و شهر رو هم به صورت نوار کشویی بهم نشون بده».
   با انتخاب استان، فهرست شهر همان استان بازسازی می‌شود. دو نکتهٔ مهم:
   ۱) فهرست اولیه را *سرور* رندر می‌کند، پس بدون جاوااسکریپت هم درست است
      (وقتی استانی انتخاب نشده، همه شهرها با optgroup استان دیده می‌شوند).
   ۲) اگر جدول شهرها ساخته نشده باشد، به همان دو فیلد متنی قبلی برمی‌گردد
      تا صفحه هرگز بی‌فیلد نماند.
   شهر ذخیره‌شده‌ای که در فهرست نیست، به‌عنوان گزینهٔ اول نگه داشته می‌شود تا
   ذخیرهٔ دوبارهٔ فرم آدرس مشتری را پاک نکند. */
function shippingProvinceCityFields($province, $city, $hint = '') {
    $city     = trim((string)$city);
    $province = trim((string)$province);
    $groups   = shippingCityGroupsAll();

    if (!$groups) {
        return '<div class="form-group"><label for="province">استان</label>'
             . '<input type="text" name="province" id="province" class="form-control" value="' . h($province) . '"></div>'
             . '<div class="form-group"><label for="city">شهر</label>'
             . '<input type="text" name="city" id="city" class="form-control" value="' . h($city) . '"></div>';
    }

    /* شهر شناخته‌شده حرف آخر را می‌زند: استان از خود شهر گرفته می‌شود تا
       فهرست فیلترشده همیشه شهر ذخیره‌شده را داشته باشد. */
    $cityProv = shippingCityProvince($city);
    $provSel  = $cityProv !== '' ? $cityProv : $province;
    $provs    = array_keys($groups);
    $cityNorm = shipNormCity($city);

    $out  = '<div class="form-group"><label for="province">استان</label>';
    $out .= '<select name="province" id="province" class="form-control">';
    $out .= '<option value="">— انتخاب استان —</option>';
    foreach ($provs as $p) {
        $out .= '<option value="' . h($p) . '"' . ($p === $provSel ? ' selected' : '') . '>' . h($p) . '</option>';
    }
    if ($provSel !== '' && !in_array($provSel, $provs, true)) {
        $out .= '<option value="' . h($provSel) . '" selected>' . h($provSel) . '</option>';
    }
    $out .= '</select></div>';

    $out .= '<div class="form-group"><label for="city">شهر</label>';
    $out .= '<select name="city" id="city" class="form-control">';
    $out .= '<option value="">— انتخاب شهر —</option>';
    if ($city !== '' && $cityProv === '') {
        $out .= '<option value="' . h($city) . '" selected>' . h($city) . '</option>';
    }
    if ($provSel !== '' && isset($groups[$provSel])) {
        foreach ($groups[$provSel] as $cn) {
            $out .= '<option value="' . h($cn) . '"' . (shipNormCity($cn) === $cityNorm ? ' selected' : '') . '>' . h($cn) . '</option>';
        }
    } else {
        foreach ($groups as $p => $list) {
            $out .= '<optgroup label="' . h($p) . '">';
            foreach ($list as $cn) {
                $out .= '<option value="' . h($cn) . '"' . (shipNormCity($cn) === $cityNorm ? ' selected' : '') . '>' . h($cn) . '</option>';
            }
            $out .= '</optgroup>';
        }
    }
    $out .= '</select>';
    if ($hint !== '') {
        $out .= '<small class="form-hint">' . icon('map-pin', 'ic-sm') . ' ' . h($hint) . '</small>';
    }
    $out .= '</div>';

    /* بازسازی فهرست شهر با تغییر استان. innerHTML بازسازی می‌شود (نه
       option.hidden) چون پنهان‌کردن گزینه در همه مرورگرها یکسان نیست.
       صفحهٔ تسویه با change روی #city هزینهٔ ارسال را زنده حساب می‌کند، پس
       بعد از بازسازی، change دستی و bubbling شلیک می‌شود. */
    $json = json_encode($groups, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $out .= '<script>(function(){var G=' . $json . ';'
          . 'var p=document.getElementById("province"),c=document.getElementById("city");'
          . 'if(!p||!c||!c.options)return;'
          . 'function e(t){return String(t).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}'
          . 'function o(l,k){var s="",i;for(i=0;i<l.length;i++){s+="<option value=\\""+e(l[i])+"\\""+(l[i]===k?" selected":"")+">"+e(l[i])+"</option>";}return s;}'
          . 'p.addEventListener("change",function(){'
          . 'var k=c.value,h="<option value=\\"\\">— انتخاب شهر —</option>",g;'
          . 'if(p.value&&G[p.value]){h+=o(G[p.value],k);}'
          . 'else{for(g in G){if(G.hasOwnProperty(g)){h+="<optgroup label=\\""+e(g)+"\\">"+o(G[g],k)+"</optgroup>";}}}'
          . 'c.innerHTML=h;'
          . 'c.dispatchEvent(new Event("change",{bubbles:true}));'
          . '});})();</script>';

    return $out;
}

/* ---------- فهرست روش‌ها ----------
   یک‌بار از دیتابیس خوانده و در $GLOBALS کش می‌شود. کلید آرایه = method_key
   و شکل هر عضو همان شکل قبلی است تا همه مصرف‌کننده‌ها (صفحهٔ تسویه، پنل
   ادمین، سفارش‌های قدیمی) بدون تغییر کار کنند.
   شامل روش‌های حذف‌شده هم هست: سفارش‌های قدیمی باید نام روش خودشان را
   نشان بدهند، حتی اگر مدیر آن روش را حذف کرده باشد. */
function shippingAllMethods($refresh = false) {
    if (!$refresh && isset($GLOBALS['__ship_all'])) return $GLOBALS['__ship_all'];

    if (!shippingTableReady()) {
        /* حالت پیش از مهاجرت: تعریف از کد، فعال/هزینه/توضیح از settings */
        $out = [];
        foreach (shippingDefaultMethods() as $k => $d) {
            $raw = preg_replace('/\D+/', '', faToLatinDigits((string)getSettingRaw('ship_cost_' . $k, '0')));
            $out[$k] = [
                'label'           => $d['label'],
                'icon'            => $d['icon'],
                'hint'            => (string)($d['hint'] ?? ''),
                'badge'           => (string)($d['badge'] ?? ''),
                'badge_short'     => (string)($d['badge_short'] ?? ''),
                'cod_only'        => !empty($d['cod_only']),
                'freight_collect' => !empty($d['freight_collect']),
                'cost'            => $raw === '' ? 0 : (int)$raw,
                'is_active'       => getSettingRaw('ship_enable_' . $k, '1') === '1',
                'is_deleted'      => false,
            ];
        }
        $GLOBALS['__ship_all'] = $out;
        return $out;
    }

    global $pdo;
    $out = [];
    try {
        $rows = $pdo->query("SELECT * FROM shipping_methods ORDER BY sort_order ASC, id ASC")->fetchAll();
        foreach ($rows as $r) {
            $out[(string)$r['method_key']] = [
                'label'           => (string)$r['label'],
                'icon'            => (string)($r['icon'] !== '' ? $r['icon'] : 'truck'),
                'hint'            => (string)($r['hint'] ?? ''),
                'badge'           => (string)($r['badge'] ?? ''),
                'badge_short'     => (string)($r['badge_short'] ?? ''),
                'cod_only'        => (int)$r['cod_only'] === 1,
                'freight_collect' => (int)$r['freight_collect'] === 1,
                'cost'            => (int)$r['cost'],
                'is_active'       => (int)$r['is_active'] === 1,
                'is_deleted'      => (int)$r['is_deleted'] === 1,
            ];
        }
    } catch (Throwable $e) { $out = []; }
    $GLOBALS['__ship_all'] = $out;
    return $out;
}

/* روش‌های قابل مدیریت (حذف‌شده‌ها کنار گذاشته می‌شوند) — همان چیزی که
   پنل ادمین و انتخابگرها باید ببینند. */
function shippingMethods() {
    $out = [];
    foreach (shippingAllMethods() as $k => $d) {
        if (empty($d['is_deleted'])) $out[$k] = $d;
    }
    return $out;
}

/* تعریف یک روش — شامل روش‌های حذف‌شده، تا برچسب سفارش‌های قدیمی نشکند */
function shippingMethodDef($key) {
    $all = shippingAllMethods();
    return $all[$key] ?? null;
}

function shippingLabel($key) {
    $d = shippingMethodDef($key);
    return $d ? $d['label'] : ($key === '' ? 'انتخاب نشده' : (string)$key);
}

function shippingIcon($key) {
    $d = shippingMethodDef($key);
    return $d ? $d['icon'] : 'truck';
}

/* توضیح روش: متن مدیر (اگر نوشته شده باشد) وگرنه توضیح خود روش.
   این متن فقط راهنمای پنل ادمین است؛ در صفحهٔ تسویه نمایش داده نمی‌شود
   (تصمیم کاربر: «اون قسمت … رو برای همه روش های ارسال بردار»). */
function shippingHint($key) {
    $d = shippingMethodDef($key);
    if (!$d) return '';
    $note = trim((string)getSettingRaw('ship_note_' . $key, ''));
    return $note !== '' ? $note : (string)($d['hint'] ?? '');
}

/* یادآوری محدودیت روش (مثل «فقط برای شهر مشهد») */
function shippingBadge($key) {
    $d = shippingMethodDef($key);
    return $d ? (string)($d['badge'] ?? '') : '';
}

/* همان یادآوری، کوتاه — برای کارت‌های فشردهٔ صفحهٔ تسویه.
   اگر نسخهٔ کوتاه تعریف نشده باشد همان متن کامل برمی‌گردد. */
function shippingBadgeShort($key) {
    $d = shippingMethodDef($key);
    if (!$d) return '';
    $short = trim((string)($d['badge_short'] ?? ''));
    return $short !== '' ? $short : (string)($d['badge'] ?? '');
}

/* آیا این روش «پس‌کرایه» است؟ یعنی کرایه هنگام تحویل پرداخت می‌شود و به
   مبلغ سفارش اضافه نمی‌شود (تیک خود روش در پنل ادمین). */
function shippingIsFreightCollect($key) {
    $d = shippingMethodDef($key);
    return $d ? !empty($d['freight_collect']) : false;
}

/* هزینهٔ ثابت قدیمی روش. از نسخهٔ «نرخ بر پایهٔ شهر و وزن» به بعد در محاسبه
   استفاده نمی‌شود (تنها منبع هزینه، نرخ‌نامه است) و از پنل ادمین هم برداشته
   شده؛ فقط برای سازگاری با فراخوان‌های قدیمی باقی مانده. */
function shippingCost($key) {
    $d = shippingMethodDef($key);
    return $d ? (int)($d['cost'] ?? 0) : 0;
}

/* متن هزینه برای نمایش — یک‌جا نگه داشته می‌شود تا همه‌جا یک‌شکل باشد.
   کلید روش اختیاری است ولی مهم: اگر روش «پس‌کرایه» باشد فقط همان کلمه
   نشان داده می‌شود و هیچ رقمی — حتی رقم ثبت‌شده — بیرون نمی‌آید.
   قالب جمله‌ها از shippingRateTexts() می‌آید تا اسکریپت صفحهٔ تسویه هم
   بتواند دقیقا همین متن را زنده بسازد. */
function shippingCostText($cost, $key = '') {
    $cost = (int)$cost;
    $t    = shippingRateTexts();
    if ($key !== '' && shippingIsFreightCollect($key)) return $t['collect_only'];
    return $cost > 0 ? formatPrice($cost) : $t['later'];
}

/* روش‌های فعال قابل نمایش در صفحهٔ تسویه.
   اگر مدیر همه روش‌ها را خاموش کند آرایهٔ خالی برمی‌گردد و صفحهٔ تسویه
   انتخابگر را نشان نمی‌دهد (سفارش بدون روش ارسال ثبت می‌شود). */
function shippingAvailableMethods() {
    $out = [];
    foreach (shippingMethods() as $k => $d) {
        if (!empty($d['is_active'])) $out[$k] = $d;
    }
    return $out;
}

/* آیا ستون‌های ارسال روی جدول orders ساخته شده‌اند؟
   (تا قبل از اجرای migrate-shipping.php صفحات نباید خطا بدهند) */
function shippingReady() {
    if (isset($GLOBALS['__ship_ready'])) return $GLOBALS['__ship_ready'];
    global $pdo;
    $ok = false;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = ?");
        $stmt->execute(['shipping_method']);
        $ok = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) { $ok = false; }
    $GLOBALS['__ship_ready'] = $ok;
    return $ok;
}

/* اعتبارسنجی ورودی کاربر: فقط یکی از روش‌های فعال پذیرفته می‌شود */
function shippingIsAvailable($key) {
    return $key !== '' && array_key_exists($key, shippingAvailableMethods());
}

/* ---------- نرخ‌نامه: شهر مقصد + وزن + هزینه ---------- */
/* نام شهر را برای مقایسه یکدست می‌کند: نیم‌فاصله/کشیده حذف، ی و ک عربی به
   فارسی، ارقام فارسی به لاتین، فاصله‌های اضافه حذف. مقایسه «شامل هم بودن»
   است تا «مشهد» با «مشهد مقدس» بخواند. */
function shipNormCity($s) {
    $s = faToLatinDigits((string)$s);
    $s = str_replace(["\xE2\x80\x8C", "\xE2\x80\x8D", "\xD9\x80"], '', $s); // ZWNJ / ZWJ / کشیده
    $s = str_replace(['ي', 'ك', 'ة', 'أ', 'إ', 'آ', 'ۀ', 'ﻻ'], ['ی', 'ک', 'ه', 'ا', 'ا', 'ا', 'ه', 'لا'], $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim(mb_strtolower($s, 'UTF-8'));
}

/* همه ردیف‌های نرخ‌نامهٔ یک روش (مرتب بر اساس شهر).
   در هر درخواست یک‌بار پرس‌وجو می‌شود: صفحهٔ تسویه برای هر روش چند بار سراغ
   همین فهرست می‌آید (کارت، محاسبه، دادهٔ اسکریپت). */
function shippingRates($key) {
    if (!shippingRatesReady() || $key === '') return [];
    if (isset($GLOBALS['__ship_rate_rows'][$key])) return $GLOBALS['__ship_rate_rows'][$key];
    global $pdo;
    $rows = [];
    try {
        $st = $pdo->prepare("SELECT * FROM shipping_rates WHERE method_key = ? ORDER BY city ASC, id ASC");
        $st->execute([$key]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { $rows = []; }
    $GLOBALS['__ship_rate_rows'][$key] = $rows;
    return $rows;
}

/* واحد وزن یک ردیف نرخ‌نامه به کیلوگرم: «هزینه به‌ازای هر چند کیلو».
   اگر ستون تازه ساخته نشده باشد از weight_to قدیمی و در نهایت ۱ کیلو
   استفاده می‌شود، تا صفحات پیش از مهاجرت هم درست کار کنند. */
function shipRateUnit($row) {
    $u = isset($row['weight_unit']) ? (float)$row['weight_unit'] : 0.0;
    if ($u <= 0 && isset($row['weight_to'])) $u = (float)$row['weight_to'];
    return $u > 0 ? $u : 1.0;
}

/* تعداد واحدهای وزنی، همیشه رند به بالا و حداقل یک واحد.
   ۲٫۵ کیلو با واحد ۱ ⇒ ۳ واحد (تصمیم مدیر: «رند به بالا»).
   ۰٫۰۰۰۰۱ خطای شناوری را می‌گیرد تا ۳ کیلو دقیق، ۴ واحد حساب نشود. */
function shippingRateUnits($weight, $unit) {
    $w = (float)$weight;
    $u = (float)$unit; if ($u <= 0) $u = 1.0;
    if ($w <= 0) return 1;
    $n = (int)ceil($w / $u - 0.00001);
    return $n < 1 ? 1 : $n;
}

/* ردیف‌های نرخ‌نامهٔ یک روش که با شهر نوشته‌شده می‌خوانند (فقط فعال‌ها).
   مقایسه دوطرفه است: «مشهد» ⇄ «مشهد مقدس». */
function shippingRatesForCity($key, $city) {
    $city = shipNormCity($city);
    if ($city === '') return [];
    $out = [];
    foreach (shippingRates($key) as $r) {
        if ((int)$r['is_active'] !== 1) continue;
        $rc = (string)$r['city_norm'];
        if ($rc === '') continue;
        if ($rc === $city)                        { $r['__exact'] = 1; $out[] = $r; }
        elseif (mb_strpos($city, $rc) !== false || mb_strpos($rc, $city) !== false)
                                                  { $r['__exact'] = 0; $out[] = $r; }
    }
    /* برابری کامل بر «شامل هم بودن» مقدم است: کسی که «قم» می‌نویسد نباید
       نرخ «قمصر» را بگیرد وقتی خود «قم» ثبت شده. بعد از آن، کوچک‌ترین واحد
       وزنی (دقیق‌ترین نرخ) اول می‌آید. */
    usort($out, function ($a, $b) {
        $e = ((int)$b['__exact'] <=> (int)$a['__exact']);
        if ($e !== 0) return $e;
        $u = (shipRateUnit($a) <=> shipRateUnit($b));
        return $u !== 0 ? $u : ((int)$a['id'] <=> (int)$b['id']);
    });
    return $out;
}

/* فهرست شهرهای نرخ‌نامه (برای datalist صفحهٔ تسویه) */
function shippingRateCities() {
    if (!shippingRatesReady()) return [];
    global $pdo;
    try {
        return $pdo->query("SELECT DISTINCT city FROM shipping_rates WHERE is_active = 1 AND city <> ''
                            ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { return []; }
}

/* وزن را بدون صفرهای اضافه نشان می‌دهد: 2.50 ⇒ 2.5 و 3.00 ⇒ 3 */
function shippingWeightText($w) {
    $s = rtrim(rtrim(number_format((float)$w, 2, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
}

/* وزن سفارش از روی سبد خرید (کیلوگرم). وزن محصول اختیاری است، پس اگر هیچ
   کالایی وزن نداشته باشد صفر برمی‌گردد و نرخ‌نامه سراغ «ردیف پایه» می‌رود.
   خروجی: [مجموع وزن, تعداد کالاهای بی‌وزن] */
function shippingCartWeight(array $items) {
    $sum = 0.0; $missing = 0;
    foreach ($items as $it) {
        $w = $it['product']['weight'] ?? null;
        $q = max(1, (int)($it['quantity'] ?? 1));
        if ($w === null || $w === '' || (float)$w <= 0) { $missing++; continue; }
        $sum += ((float)$w) * $q;
    }
    return [round($sum, 2), $missing];
}

/* ---------- محاسبهٔ نهایی هزینهٔ ارسال ----------
   ترتیب تصمیم:
   0) روش «پس‌کرایه» است ⇒ هیچ محاسبه‌ای انجام نمی‌شود، هیچ رقمی نمایش داده
      نمی‌شود و چیزی به سفارش اضافه نمی‌شود (خواستهٔ صریح مدیر).
   1) شهر با ردیفی از نرخ‌نامه خواند ⇒ هزینه = تعداد واحدهای وزنی (رند به بالا)
      × قیمت هر واحد همان ردیف.  (source = rate)
   2) شهر خواند ولی هیچ کالایی وزن ندارد ⇒ یک واحد حساب می‌شود. (rate_base)
   3) هیچ ردیفی نخواند ⇒ صفر و جملهٔ «پس از ثبت سفارش هماهنگ می‌شود». (none)
   خروجی همیشه آرایه است تا صفحهٔ تسویه دلیل را هم به مشتری بگوید:
   • cost/display مبلغی که به سفارش اضافه و به مشتری نشان داده می‌شود
   • source  collect | rate | rate_base | none
   • unit/units  واحد وزنی و تعدادش (برای جملهٔ توضیحی) */
function shippingResolveCost($key, $city = '', $weight = 0) {
    $res = [
        'cost'     => 0,
        'display'  => 0,
        'source'   => 'none',
        'row'      => null,   // ردیف نرخ‌نامهٔ منتخب
        'rows'     => [],     // ردیف‌های همان شهر
        'has_rows' => false,  // این روش اصلا نرخ‌نامه دارد؟
        'weight'   => (float)$weight,
        'unit'     => 0.0,
        'units'    => 0,
        'collect'  => false,  // پس‌کرایه است؟
    ];
    if ($key === '' || shippingMethodDef($key) === null) return $res;

    /* پس‌کرایه: کل فرآیند محاسبه غیرفعال است */
    if (shippingIsFreightCollect($key)) {
        $res['collect'] = true;
        $res['source']  = 'collect';
        return $res;
    }

    /* «نرخ‌نامه دارد» فقط ردیف‌های فعال شهردار را می‌شمارد — دقیقا همان
       ردیف‌هایی که به اسکریپت صفحهٔ تسویه هم داده می‌شوند، تا جملهٔ «برای این
       شهر نرخی ثبت نشده» در سرور و در مرورگر یکی باشد. */
    foreach (shippingRates($key) as $r0) {
        if ((int)$r0['is_active'] === 1 && (string)$r0['city_norm'] !== '') { $res['has_rows'] = true; break; }
    }
    $rows = shippingRatesForCity($key, $city);
    $res['rows'] = $rows;
    if (!$rows) return $res;

    $row   = $rows[0];
    $unit  = shipRateUnit($row);
    $units = shippingRateUnits($weight, $unit);
    $res['row']     = $row;
    $res['unit']    = $unit;
    $res['units']   = $units;
    $res['source']  = ((float)$weight > 0) ? 'rate' : 'rate_base';
    $res['display'] = (int)round($units * (float)$row['cost']);
    $res['cost']    = $res['display'];
    return $res;
}

/* همان محاسبه، فقط مبلغی که به سفارش اضافه می‌شود */
function shippingChargeCost($key, $city = '', $weight = 0) {
    $r = shippingResolveCost($key, $city, $weight);
    return (int)$r['cost'];
}

/* ---------- متن‌های نرخ‌نامه ----------
   در یک‌جا نگه داشته می‌شوند تا رندر سرور (که بدون جاوااسکریپت هم باید درست
   باشد) و اسکریپت زندهٔ صفحهٔ تسویه دقیقا یک جمله بگویند. */
function shippingRateTexts() {
    return [
        'collect'      => 'کرایهٔ این روش هنگام تحویل از شما دریافت می‌شود و به مبلغ سفارش اضافه نمی‌شود.',
        'rate'         => 'نرخ «{city}»: هر {u} کیلوگرم {p} — وزن سبد {w} کیلوگرم ⇒ {n} واحد ⇒ {c}',
        'rate_base'    => 'نرخ «{city}»: هر {u} کیلوگرم {p} — وزن کالاهای این سبد ثبت نشده، پس یک واحد حساب شد: {c}',
        'none'         => 'برای شهری که انتخاب کرده‌اید نرخی ثبت نشده؛ هزینهٔ ارسال پس از ثبت سفارش هماهنگ می‌شود.',
        'nocity'       => 'شهر مقصد را از فهرست انتخاب کنید تا هزینهٔ ارسال خودکار حساب شود.',
        'rowunit'      => 'هر {w} کیلوگرم',
        /* متن‌های shippingCostText — کارت‌های روش ارسال و ردیف خلاصهٔ سفارش */
        'collect_only' => 'پس‌کرایه',
        'later'        => 'پس از هماهنگی',
        'pick'         => '— پس از انتخاب روش ارسال',
    ];
}

/* جملهٔ توضیحی زیر انتخابگر ارسال برای روش انتخاب‌شده */
function shippingRateLine(array $res) {
    $t   = shippingRateTexts();
    $src = (string)($res['source'] ?? 'none');

    if (!empty($res['collect']) || $src === 'collect') return $t['collect'];

    if ($src === 'rate' || $src === 'rate_base') {
        $row = $res['row'] ?? [];
        return str_replace(
            ['{city}', '{u}', '{p}', '{w}', '{n}', '{c}'],
            [
                (string)($row['city'] ?? ''),
                shippingWeightText($res['unit'] ?? 1),
                formatPrice((int)($row['cost'] ?? 0)),
                shippingWeightText($res['weight'] ?? 0),
                (string)(int)($res['units'] ?? 1),
                formatPrice((int)($res['display'] ?? 0)),
            ],
            $src === 'rate' ? $t['rate'] : $t['rate_base']
        );
    }
    return !empty($res['has_rows']) ? $t['none'] : $t['nocity'];
}

/* ---------- هزینه‌های باربری (بخش جدا در تنظیمات) ---------- */
function shippingBarbariCost() {
    $raw = preg_replace('/\D+/', '', faToLatinDigits((string)getSettingRaw('ship_barbari_cost', '0')));
    return $raw === '' ? 0 : (int)$raw;
}

function shippingBarbariDesc() {
    return trim((string)getSettingRaw('ship_barbari_desc', ''));
}

/* آیا این روش «باربری» است؟ بخش «هزینه‌های باربری» تنظیمات فقط زیر همین روش
   به مشتری نشان داده می‌شود. کلید پیش‌فرض `barbari` است، ولی مدیر می‌تواند
   روش باربری دیگری هم بسازد، پس از نام روش هم شناسایی می‌شود. */
function shippingIsBarbari($key) {
    if ($key === '') return false;
    if (mb_strpos($key, 'barbari') !== false) return true;
    $d = shippingMethodDef($key);
    return $d ? (mb_strpos((string)$d['label'], 'باربری') !== false) : false;
}

/* ---------- دادهٔ محاسبهٔ زندهٔ صفحهٔ تسویه ----------
   برای هر روش: پس‌کرایه بودن (c)، باربری بودن (b) و ردیف‌های فعال نرخ‌نامه‌اش
   (r: شهر یکدست‌شده، نام شهر، واحد وزن، قیمت هر واحد). اسکریپت با همین داده
   وقتی مشتری شهر را عوض می‌کند همان کاری را می‌کند که shippingResolveCost()
   در سرور انجام می‌دهد، تا هر دو یک رقم بگویند. */
function shippingRateJs(array $keys, $weight = 0) {
    $out = ['w' => (float)$weight, 'm' => []];
    foreach ($keys as $k) {
        $rows = [];
        foreach (shippingRates($k) as $r) {
            if ((int)$r['is_active'] !== 1) continue;
            if ((string)$r['city_norm'] === '') continue;
            $rows[] = [
                'c' => (string)$r['city_norm'],
                'n' => (string)$r['city'],
                'u' => shipRateUnit($r),
                'p' => (int)$r['cost'],
            ];
        }
        usort($rows, function ($a, $b) { return ($a['u'] <=> $b['u']); });
        $out['m'][$k] = [
            'c' => shippingIsFreightCollect($k) ? 1 : 0,
            'b' => shippingIsBarbari($k) ? 1 : 0,
            'r' => $rows,
        ];
    }
    return $out;
}

/* ---------- قاعدهٔ «ارسال ↔ پرداخت» ---------- */
/* تصمیم مدیر: «پرداخت در محل» فقط برای ارسال با پیک معنا دارد، ولی روی پیک
   پرداخت اینترنتی هم باید باز باشد (ممکن است مشتری مشهدی آنلاین پرداخت کند).
   پس:
   • روش ارسال cod_only (پیک) ← همه روش‌های پرداخت، از جمله «پرداخت در محل»
   • هر روش ارسال دیگر         ← هر روشی به‌جز «پرداخت در محل»
   نام ستون cod_only از نسخهٔ قبل مانده؛ معنایش «پرداخت در محل مجاز است». */
function shippingCodAllowed($key) {
    $d = shippingMethodDef($key);
    return $d ? !empty($d['cod_only']) : false;
}

/* نام قبلی، برای سازگاری با فراخوان‌های موجود */
function shippingIsCodOnly($key) {
    return shippingCodAllowed($key);
}

/* روش‌های پرداخت مجاز برای یک روش ارسال.
   تا وقتی مشتری روش ارسالی انتخاب نکرده، همه مجاز است.
   نکتهٔ مهم: اگر این قاعده هیچ روش پرداختی باقی نگذارد (مثلا مدیر فقط
   «پرداخت در محل» را فعال کرده باشد) همان فهرست کامل برمی‌گردد، وگرنه
   سفارش‌گیری قفل می‌شد و مشتری هیچ راهی برای پرداخت نداشت. */
function shippingAllowedPayKeys($shipKey, array $payKeys) {
    if ($shipKey === '' || !$payKeys) return $payKeys;
    if (shippingCodAllowed($shipKey)) return $payKeys;
    $out = array_values(array_diff($payKeys, ['cod']));
    return $out ? $out : $payKeys;
}

/* پیام راهنمای همین قاعده. متن در یک‌جا نگه داشته می‌شود تا سرور (اعتبارسنجی و
   رندر اولیه) و اسکریپت صفحهٔ تسویه (به‌روزرسانی زنده) دقیقا یک جمله بگویند. */
function shippingPayRuleTemplates() {
    return [
        'cod'   => 'برای «{m}» هم «پرداخت در محل» و هم پرداخت اینترنتی امکان‌پذیر است.',
        'other' => 'برای «{m}» پرداخت در محل امکان‌پذیر نیست؛ مبلغ باید پیش از ارسال پرداخت شود.',
    ];
}

function shippingPayRuleNote($shipKey) {
    if ($shipKey === '') return '';
    $t = shippingPayRuleTemplates();
    return str_replace('{m}', shippingLabel($shipKey), shippingCodAllowed($shipKey) ? $t['cod'] : $t['other']);
}

/* توضیح «پس‌کرایه» در صفحهٔ ثبت سفارش.
   جای پیام قاعدهٔ پرداخت را گرفته است — تصمیم مدیر: «این مال اینه که مقصد مشهد
   نیست و نیازی نداره نشون بدی، فقط حالت پرداخت در محل غیر فعال باشه کافیه؛ فقط
   میتونی به جاش برای پس کرایه توضیح بدی که هزینه ارسال در زمان تحویل کالا از شما
   دریافت میشود». پس آن پیام دیگر رندر نمی‌شود (خود گزینهٔ کم‌رنگ و غیرفعال
   گویاست) و تنها همین جمله می‌آید، آن هم فقط روی روش‌های پس‌کرایه.
   shippingPayRuleNote بالا حذف نشده چون در اعتبارسنجی POST — جایی که واقعا
   خطایی رخ داده و باید دلیلش گفته شود — همچنان لازم است. */
function shippingCollectNote($shipKey) {
    if ($shipKey === '' || !shippingIsFreightCollect($shipKey)) return '';
    return 'ارسال با «' . shippingLabel($shipKey) . '» پس‌کرایه است: هزینهٔ ارسال هنگام تحویل کالا از شما دریافت می‌شود، پس به مبلغ قابل پرداخت این سفارش اضافه نشده است.';
}

/* ---------- برآورد ارسال برای صفحهٔ سبد خرید ----------
   سبد خرید هنوز روش ارسال ندارد، پس هزینهٔ همه روش‌های فعال با شهر پروفایل
   مشتری و وزن سبد حساب و کنار هم نشان داده می‌شود («هم در سبد خرید و هم در
   تسویه» — تصمیم مدیر). محاسبه دقیقا همان shippingResolveCost() است، پس
   رقم سبد با رقم تسویه یکی است. */
function shippingCartQuotes($city, $weight) {
    $out = [];
    foreach (shippingAvailableMethods() as $k => $d) {
        $res     = shippingResolveCost($k, $city, $weight);
        $blocked = shippingCityBlocked($k, $city);
        /* روش مسدود هیچ رقمی نمی‌گیرد: نه نرخی نشان داده می‌شود، نه در
           «کم‌ترین هزینه» می‌آید، نه به مبلغ سفارش راه دارد. جای رقم را همان
           نشان محدودیت («مشهد») می‌گیرد (shippingQuoteBadgeOnly) و خود ردیف
           کم‌رنگ و غیرفعال رندر می‌شود. */
        $known   = !$blocked && in_array($res['source'], ['rate', 'rate_base'], true);
        $collect = !$blocked && !empty($res['collect']);
        $out[] = [
            'key'     => $k,
            'label'   => (string)$d['label'],
            'icon'    => (string)$d['icon'],
            'badge'   => shippingBadgeShort($k),
            'collect' => $collect,
            'blocked' => $blocked,
            'limit'   => shippingMethodCityLimit($k),
            'known'   => $known,
            'cost'    => $blocked ? 0 : (int)$res['cost'],
            'text'    => $blocked
                            ? ''
                            : ($collect
                                ? shippingRateTexts()['collect_only']
                                : ($known ? formatPrice((int)$res['cost']) : shippingRateTexts()['later'])),
        ];
    }
    return $out;
}

/* کم‌ترین هزینهٔ ارسال معلوم (برای خط «از … تومان» در سبد). اگر هیچ روشی نرخ
   نداشته باشد null برمی‌گردد. */
function shippingCheapestQuote(array $quotes) {
    $best = null;
    foreach ($quotes as $q) {
        if (!empty($q['blocked'])) continue;   // روش غیرفعال نمی‌تواند «کم‌ترین هزینه» باشد
        if (empty($q['known'])) continue;
        if ($best === null || (int)$q['cost'] < (int)$best['cost']) $best = $q;
    }
    return $best;
}

/* جملهٔ «وزن این سبد …» — یک‌جا نگه داشته می‌شود تا سبد و تسویه یک متن بگویند */
function shippingWeightLine($weight, $missing) {
    $w = (float)$weight; $m = (int)$missing;
    if ($w <= 0) return 'وزن کالاهای این سبد ثبت نشده است؛ هزینهٔ ارسال بر پایهٔ یک واحد وزنی حساب می‌شود.';
    $s = 'وزن این سبد: ' . shippingWeightText($w) . ' کیلوگرم';
    if ($m > 0) $s .= ' (وزن ' . $m . ' کالا ثبت نشده و در این محاسبه نیامده است)';
    return $s;
}

/* ---------- روشی که به یک شهر محدود است («ارسال با پیک (مشهد)») ----------
   خواستهٔ مدیر: «وقتی لوکیشنم غیر از مشهده باید گزینه ارسال با پیک(مشهد) برایم
   غیر فعال نشون داده بشه». پس گزینه همچنان در فهرست دیده می‌شود — پنهان نمی‌شود
   — ولی انتخاب‌شدنی نیست (رادیوی داخلش disabled و در سرور هم رد می‌شود).
   شهر محدودیت از خود «نشان» روش خوانده می‌شود (badge_short و اگر نبود badge):
   ستون جداگانه‌ای برای شهر روی روش‌ها وجود ندارد و همین نشان تنها جایی است که
   مدیر محدودیت را می‌نویسد؛ پس روش پیک شهر دیگری هم که مدیر بسازد خودکار
   همین قاعده را می‌گیرد (مثل shippingIsBarbari که از نام روش تشخیص می‌دهد).
   واژه‌های عمومی نشان («فقط برای شهر …») کنار گذاشته می‌شوند و باقی‌مانده تنها
   وقتی «شهر» شمرده می‌شود که در فهرست شهرها باشد — پس نشانی مثل «ارسال سریع»
   هیچ روشی را محدود نمی‌کند.
   دو محافظ عمدا باقی مانده‌اند تا این قاعده هیچ سفارشی را قفل نکند:
   • شهر خالی یا ناشناخته هرگز مسدود نمی‌کند؛ نخواندن شهر یعنی «نمی‌دانیم»،
     نه «ممنوع» (همان قاعدهٔ نرخ‌نامه).
   • اگر تنها یک روش ارسال فعال باشد مسدود نمی‌شود، وگرنه سفارش‌گیری می‌خوابید
     (همان منطق «هیچ‌وقت قفل نکن» در shippingAllowedPayKeys). */
function shippingMethodCityLimit($key) {
    if ($key === '') return '';
    if (isset($GLOBALS['__ship_city_limit'][$key])) return $GLOBALS['__ship_city_limit'][$key];
    $txt = trim((string)shippingBadgeShort($key));
    if ($txt === '') $txt = trim((string)shippingBadge($key));
    $out = '';
    $t   = shipNormCity($txt);
    if ($t !== '') {
        $pad  = ' ' . $t . ' ';
        $best = '';
        foreach (shippingCityNames() as $cn) {
            $n = shipNormCity($cn);
            if ($n === '') continue;
            if ($n === $t) { $best = (string)$cn; break; }   // نشان دقیقا نام شهر است («مشهد»)
            /* نشان بلند («فقط برای شهر مشهد») نام شهر را در خود دارد. مقایسه با
               فاصله در دو طرف انجام می‌شود تا نیم‌کلمه شهر شمرده نشود («شهری»
               نباید «ری» بخواند و «رشتخوار» نباید «رشت» بخواند)؛ بلندترین نام
               خواننده برنده است تا شهر چندکلمه‌ای هم درست دربیاید. */
            if (mb_strpos($pad, ' ' . $n . ' ') !== false && mb_strlen($n) > mb_strlen(shipNormCity($best))) {
                $best = (string)$cn;
            }
        }
        $out = $best;
    }
    $GLOBALS['__ship_city_limit'][$key] = $out;
    return $out;
}

/* آیا این روش برای این شهر مسدود است؟ مقایسه دوطرفه است، مثل نرخ‌نامه:
   «مشهد» ⇄ «مشهد مقدس» یک شهر شمرده می‌شوند. */
function shippingCityBlocked($key, $city) {
    $limit = shippingMethodCityLimit($key);
    if ($limit === '') return false;
    $c = shipNormCity($city);
    if ($c === '') return false;
    $l = shipNormCity($limit);
    if ($l === '' || $c === $l) return false;
    if (mb_strpos($c, $l) !== false || mb_strpos($l, $c) !== false) return false;
    if (count(shippingAvailableMethods()) < 2) return false;
    return true;
}

/* آزمون عضویت در فهرست شهرها: نام رسمی شهر برمی‌گردد و اگر ناشناس بود ''.
   برخلاف shippingCityCanonical() که ورودی ناشناس را عینا پس می‌دهد (تا نوشتهٔ
   مشتری دور نریزد)، این یکی برای «آیا این تکه، نام شهر است؟» ساخته شده.
   نگاشت یکدست‌شده یک‌بار در هر درخواست ساخته می‌شود. */
function shippingCityKnown($city) {
    $n = shipNormCity($city);
    if ($n === '') return '';
    if (!isset($GLOBALS['__ship_city_norm'])) {
        $map = [];
        foreach (shippingCityNames() as $cn) {
            $k = shipNormCity($cn);
            if ($k !== '' && !isset($map[$k])) $map[$k] = (string)$cn;
        }
        $GLOBALS['__ship_city_norm'] = $map;
    }
    return $GLOBALS['__ship_city_norm'][$n] ?? '';
}

/* آیا این روش، پیک درون‌شهری است (نه پست/باربری)؟ سه نشانه، به همان ترتیب
   اعتماد: نشان شهری خود روش («مشهد») که shippingMethodCityLimit() درمی‌آورد،
   بعد کلید، بعد برچسب — دقیقا همان اصطلاح shippingIsBarbari()، تا اگر مدیر
   پیک شهر دیگری هم اضافه کرد بی‌نیاز از تغییر کد شناخته شود. */
function shippingIsCityCourier($key) {
    $key = (string)$key;
    if ($key === '') return false;
    if (shippingMethodCityLimit($key) !== '') return true;
    if (mb_strpos($key, 'peyk') !== false) return true;
    $d = shippingMethodDef($key);
    return $d ? (mb_strpos((string)$d['label'], 'پیک') !== false) : false;
}

/* آیا نشانی این شهر در محدودهٔ پیک این روش است؟ همان مقایسهٔ دوطرفهٔ
   shippingCityBlocked() ولی بدون گارد «کمتر از دو روش» — اینجا حرفی از
   مسدودکردن سفارش نیست، فقط تشخیص «درون‌شهری بودن مقصد» است. */
function shippingCityMatchesLimit($key, $city) {
    $l = shipNormCity(shippingMethodCityLimit($key));
    $c = shipNormCity($city);
    if ($l === '' || $c === '') return false;
    return $c === $l || mb_strpos($c, $l) !== false || mb_strpos($l, $c) !== false;
}

/* ---------- «پرداخت در محل» فقط برای شهر معین ----------
   خواستهٔ کاربر (۲۰۲۶-۰۹-۰۳): «به محض اینکه آدرسش رو خراسان رضوی و شهر مشهد
   ثبت کرد پرداخت در محل فعال بشه، هر شهری غیر از مشهد بود همون‌جا غیرفعال
   بشه» — این باید مستقیم به شهر واردشده در فرم ثبت سفارش گره بخورد (نه فقط
   روش ارسالی که در سبد خرید انتخاب شده)، چون مشتری می‌تواند شهر را در همین
   صفحه عوض کند.
   برخلاف shippingCityBlocked() که «نمی‌دانیم» را «مسدود نیست» می‌گیرد (برای
   اینکه سفارش‌گیری قفل نشود)، اینجا برعکس است: پول نقد دم‌در فقط با اطمینان
   از مقصد باز می‌شود، پس شهر خالی/نامعلوم هم مجاز شمرده نمی‌شود. */

/* شهری که «پرداخت در محل» به آن محدود است. از همان نشان شهری روش‌های
   cod_only می‌خواند (shippingMethodCityLimit) تا اگر مدیر این نشان را عوض
   کرد یا روش تازه‌ای با این تیک ساخت، کد تازه‌ای لازم نباشد. رشتهٔ خالی یعنی
   «بدون محدودیت» — یا اصلا روشی با این تیک نیست، یا آن روش خودش شهر خاصی
   نمی‌خواهد (مدیر cod_only را سراسری می‌خواهد). */
function shippingCodRequiredCity() {
    if (isset($GLOBALS['__ship_cod_city'])) return $GLOBALS['__ship_cod_city'];
    $out = ''; $any = false;
    foreach (shippingAllMethods() as $key => $d) {
        if (empty($d['cod_only'])) continue;
        $limit = shippingMethodCityLimit($key);
        if ($limit === '') { $any = false; break; }   // یک روش cod_only بی‌محدودیت ⇒ کل قاعده بی‌اثر
        $any = true;
        if ($out === '') $out = $limit;
    }
    return $GLOBALS['__ship_cod_city'] = ($any ? $out : '');
}

/* آیا «پرداخت در محل» برای این شهر مجاز است؟ checkout.php (سرور و اسکریپت
   زنده‌اش) هردو از همین می‌خوانند تا یک تصمیم بگیرند. */
function shippingCodCityAllowed($city) {
    $required = shippingCodRequiredCity();
    if ($required === '') return true;
    $c = shipNormCity($city);
    if ($c === '') return false;
    $l = shipNormCity($required);
    return $c === $l || mb_strpos($c, $l) !== false || mb_strpos($l, $c) !== false;
}

/* شهر مبنای این قاعده: شهر پروفایل مشتری واردشده. یک منبع تا صفحهٔ سبد،
   پاسخ AJAX و صفحهٔ تسویه هر سه یک تصمیم بگیرند. مهمان یا شهر ثبت‌نشده ⇒ ''
   یعنی «نمی‌دانیم» ⇒ هیچ روشی مسدود نمی‌شود. */
function shippingCustomerCity() {
    if (!function_exists('isCustomerLoggedIn') || !isCustomerLoggedIn()) return '';
    if (!function_exists('currentCustomer')) return '';
    $c = currentCustomer();
    return trim((string)($c['city'] ?? ''));
}

/* توضیح زیر فهرست سبد برای روش‌های مسدود — یک‌جا نگه داشته می‌شود تا رندر
   سرور و هر مصرف‌کنندهٔ دیگری یک جمله بگویند. خود ردیف کم‌رنگ و غیرفعال است؛
   این جمله می‌گوید «چرا». */
function shippingBlockedNote(array $quotes, $city) {
    $names = [];
    foreach ($quotes as $q) {
        if (!empty($q['blocked'])) $names[] = '«' . (string)$q['label'] . '»';
    }
    if (!$names) return '';
    $city = trim((string)$city);
    return implode(' و ', $names) . ' برای ' . ($city !== '' ? '«' . $city . '»' : 'شهر شما')
         . ' فعال نیست؛ روش دیگری را انتخاب کنید.';
}

/* ---------- روش ارسال انتخاب‌شده، در نشست ----------
   انتخاب روش ارسال از صفحهٔ تسویه به صفحهٔ سبد خرید منتقل شده (خواستهٔ مدیر:
   «امکان انتخاب رو همینجا بذار … که دیگه صفحه بعد فقط ثبت سفارش نهایی باشه»)،
   پس انتخاب باید از سبد تا ثبت سفارش زنده بماند. نشست تنها جای درست است:
   سمت سرور است و قابل دست‌کاری نیست، و با پاک‌شدن سبد پاک می‌شود.
   مقدار نشست هر بار اعتبارسنجی می‌شود تا روشی که مدیر بعدا خاموش یا حذف
   کرده به سفارش نرسد. */
function shippingSessionMethod() {
    $k = (string)($_SESSION['ship_method'] ?? '');
    if ($k === '') return '';
    $ok = shippingAvailableMethods();
    if (!isset($ok[$k])) return '';
    /* شهر پروفایل بعد از انتخاب عوض شده و این روش دیگر مجاز نیست (پیک مشهد
       برای مشتری تهرانی). با برگشتن '' صفحهٔ سبد دوباره «انتخاب کنید» می‌گوید
       و صفحهٔ تسویه به سبد برمی‌گرداند، تا سفارش با روش نامعتبر ثبت نشود. */
    if (shippingCityBlocked($k, shippingCustomerCity())) return '';
    return $k;
}

function shippingSetSessionMethod($key) {
    $key = trim((string)$key);
    $ok  = shippingAvailableMethods();
    /* بررسی سمت سرور: رادیوی روش مسدود در فرم disabled است، ولی بدون
       جاوااسکریپت یا با فرم دست‌کاری‌شده هم نباید در نشست بنشیند. */
    if ($key === '' || !isset($ok[$key]) || shippingCityBlocked($key, shippingCustomerCity())) {
        unset($_SESSION['ship_method']);
        return '';
    }
    $_SESSION['ship_method'] = $key;
    return $key;
}

function shippingClearSessionMethod() {
    unset($_SESSION['ship_method']);
}

/* ---------- خلاصهٔ ارسال صفحهٔ سبد خرید ----------
   یک‌جا ساخته می‌شود تا سه مصرف‌کننده‌اش هیچ‌وقت با هم اختلاف نداشته باشند:
   رندر اول cart.php، پاسخ AJAX هنگام تغییر تعداد، و بازبینی صفحهٔ تسویه.
   وزن سبد با تعداد عوض می‌شود («وزن و هزینه بر اساس تعداد در سبد من تغییر
   کنه»)، پس همه‌چیز — وزن، نرخ هر روش، کم‌ترین هزینه و مبلغ قابل پرداخت —
   هر بار از نو حساب می‌شود. شهر خالی هم پذیرفته است: فهرست روش‌ها می‌آید
   ولی بی‌رقم، تا مشتری بتواند روشش را همین‌جا انتخاب کند. */
function shippingCartSummary(array $cartItems, $city, $goodsTotal) {
    list($w, $missing) = shippingCartWeight($cartItems);
    $t      = shippingRateTexts();
    $quotes = shippingCartQuotes($city, $w);
    $best   = shippingCheapestQuote($quotes);
    $pick   = shippingSessionMethod();
    $res    = $pick !== '' ? shippingResolveCost($pick, $city, $w) : null;
    $cost   = $res ? (int)$res['cost'] : 0;

    if ($pick === '')                    $costText = $t['pick'];
    elseif ($res && !empty($res['collect'])) $costText = $t['collect_only'];
    elseif ($cost > 0)                   $costText = formatPrice($cost);
    else                                 $costText = $t['later'];

    return [
        'weight'      => $w,
        'missing'     => (int)$missing,
        'quotes'      => $quotes,
        'best'        => $best,
        'pick'        => $pick,
        'cost'        => $cost,
        'cost_text'   => $costText,
        'payable'     => (int)$goodsTotal + $cost,
        'weight_line' => shippingWeightLine($w, $missing),
    ];
}

/* آیا این ردیف برآورد باید فقط نشان «فقط مشهد» را نشان بدهد؟
   خواستهٔ مدیر: «اون قسمت ارسال با پیک (مشهد) که نوشته پس از هماهنگی اونو
   بردار و فقط بنویس فقط مشهد». پس وقتی روشی نرخ معلومی ندارد ولی نشان
   محدودیت دارد، همان نشان جای رقم را می‌گیرد. روش‌های «پس‌کرایه» از این قاعده
   بیرون‌اند، چون خود کلمهٔ «پس‌کرایه» اطلاع مفیدی است. */
function shippingQuoteBadgeOnly(array $q) {
    return empty($q['known']) && empty($q['collect']) && (string)$q['badge'] !== '';
}
