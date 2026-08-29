</main>
<?php
/* مقادیر فوتر از جدول تنظیمات (قابل ویرایش از پنل مدیریت) */
$fAbout    = getSetting('footer_about', 'فروشگاه اینترنتی لوازم یدکی خودرو - فروش کلی و جزئی');
$fCopy     = getSetting('footer_copyright', 'تمامی حقوق محفوظ است © ' . date('Y') . ' ' . SITE_NAME);
$cPhones   = contactNumbers('contact_phones', 'contact_phone');
$cMobiles  = contactNumbers('contact_mobiles', 'contact_mobile');
$cEmail    = getSetting('contact_email', '');
$cAddress  = getSetting('contact_address', '');
$cHours    = getSetting('working_hours', '');

/* شبکه‌های اجتماعی و پیام‌رسان‌ها (خارجی + داخلی) — ترتیب و نشانه‌ها */
/* لوگوهای برند به‌صورت SVG درون‌خطی (بدون فایل خارجی). رنگ از currentColor ارث می‌برد
   تا در حالت عادی خاکستری و هنگام hover به رنگ برند دربیاید.
   همهٔ ۸ لوگو نشان واقعی برند است (نه حرف/ایموجی).
   قاعدهٔ اندازه: viewBox هر SVG برابر «کرانهٔ محتوای خودش» تنظیم شده تا نشان لبه‌به‌لبه
   قاب را پر کند؛ در نتیجه هر ۸ کاشی از نظر چشمی هم‌اندازه دیده می‌شوند.
   نشان‌های تک‌رنگی که در اصل حفرهٔ سفید دارند (بله/گپ/روبیکا) با fill-rule="evenodd"
   ساخته شده‌اند: زیرمسیر بیرونی = بدنه، زیرمسیرهای درونی = حفره. */
$socialIcons = [
    'instagram' => '<svg class="social-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077"/></svg>',
    'telegram'  => '<svg class="social-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
    'whatsapp'  => '<svg class="social-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
    'twitter'   => '<svg class="social-ic" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z"/></svg>',
    /* ایتا: مسیر اصلی در فضای ۴۸ کشیده شده؛ viewBox روی کرانهٔ واقعی خودِ نشان (≈۴ تا ۴۴)
       تنظیم شده تا لبه‌به‌لبه شود (به‌جای scale که فقط قابِ خالی را کوچک می‌کرد).
       stroke-width متناسب با همین قاب انتخاب شده تا ضخامت رندرشده ≈۲px بماند. */
    'eitaa'     => '<svg class="social-ic" viewBox="1.7 2.1 44.3 44.3" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-width="4.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12.928 31.274c-2.198-1.34-3.801-3.232-3.82-6.206c-.042-6.88 10.703-12.049 16.364-11.642c3.714.267 6.662 2.559 6.856 4.374c.272 2.53-6.85 4.302-8.488 4.593c-3.588.636-7.535-.764-5.895-4.823c-3.971.365-5.88 6.262-2.64 8.635c-4.684 4.459-.8 11.088 2.727 11.712c.276-6.401 4.92-9.605 9.322-11.126c4.31-1.488 8.797-5.263 8.578-10.714c-.215-5.379-1.167-10.234-8.596-11.577C17.096 2.648 3.291 16.262 4.18 30.462c.478 7.626 7.05 13.875 16.218 13.154c12.583-.99 16.421-14.782 23.463-19.945"/><path d="M43.861 20.35c-5.553 2.614-12.91 17.209-23.86 13.724l-.791-.25"/></g></svg>',
    /* بله (Bale) — نشان رسمی ble.ir: دایرهٔ دُم‌دار + تیک درونی (تیک = حفره با evenodd) */
    'bale'      => '<svg class="social-ic" viewBox="7.12 2 25.96 25.96" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M7.12 14.98V3.42C7.12 2.36 8.25 1.67 9.19 2.16C10.13 2.65 12.5 4.46 12.5 4.46A12.98 12.98 0 1 1 7.12 14.98ZM27.67 13.32L20.14 20.86C19.64 21.35 18.99 21.6 18.35 21.6C17.7 21.6 17.05 21.35 16.56 20.86L12.52 16.82C11.53 15.83 11.53 14.23 12.52 13.24C13.51 12.25 15.11 12.25 16.1 13.24L18.35 15.48L24.09 9.74C25.08 8.75 26.69 8.75 27.67 9.74C28.66 10.73 28.66 12.33 27.67 13.32Z"/></svg>',
    /* گپ (Gap) — حبابِ گفتگوی دُم‌دار با سه نوار درونی (نوارها = حفره با evenodd) */
    'gap'       => '<svg class="social-ic" viewBox="34 28 444 444" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M457 344C478 372 478 430 468 462C430 456 385 458 350 451A222 222 0 1 1 457 344ZM282 148H338A24 24 0 0 1 338 196H282A24 24 0 0 1 282 148ZM173 230H343A25 25 0 0 1 343 280H173A25 25 0 0 1 173 230ZM171 310H231A25 25 0 0 1 231 360H171A25 25 0 0 1 171 310Z"/></svg>',
    /* روبیکا (Rubika) — نشان رسمی rubika.ir: مربعِ گِرد دُم‌دار + سه نقطه (نقطه‌ها = حفره با evenodd) */
    'rubika'    => '<svg class="social-ic" viewBox="-0.92 0 27.23 27.23" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M25.38 8.02C25.38 3.58 21.77 0 17.35 0H8.02C3.58 0 0 3.6 0 8.02V17.35C0 21.79 3.6 25.38 8.02 25.38H17.35C17.94 25.38 18.48 25.31 19.02 25.19L22.48 27.23C22.9 27.48 23.44 27.17 23.44 26.69L23.4 22.58C24.63 21.17 25.4 19.35 25.4 17.33V8.02H25.38ZM9 13.96C8.35 13.96 7.85 13.44 7.85 12.81C7.85 12.17 8.38 11.67 9 11.67C9.63 11.67 10.15 12.19 10.15 12.81C10.17 13.44 9.65 13.96 9 13.96ZM12.48 13.96C11.83 13.96 11.33 13.44 11.33 12.81C11.33 12.17 11.85 11.67 12.48 11.67C13.1 11.67 13.63 12.19 13.63 12.81C13.63 13.44 13.1 13.96 12.48 13.96ZM16.04 13.96C15.4 13.96 14.9 13.44 14.9 12.81C14.9 12.17 15.42 11.67 16.04 11.67C16.67 11.67 17.19 12.19 17.19 12.81C17.21 13.44 16.69 13.96 16.04 13.96Z"/></svg>',
];
$socialDefs = [
    'instagram' => ['label' => 'اینستاگرام',  'icon' => $socialIcons['instagram'], 'base' => 'https://instagram.com/'],
    'telegram'  => ['label' => 'تلگرام',      'icon' => $socialIcons['telegram'],  'base' => 'https://t.me/'],
    'whatsapp'  => ['label' => 'واتساپ',      'icon' => $socialIcons['whatsapp'],  'base' => 'https://wa.me/'],
    'twitter'   => ['label' => 'توییتر (X)',  'icon' => $socialIcons['twitter'],   'base' => 'https://twitter.com/'],
    'bale'      => ['label' => 'بله',         'icon' => $socialIcons['bale'],      'base' => 'https://ble.ir/'],
    'gap'       => ['label' => 'گپ',          'icon' => $socialIcons['gap'],       'base' => 'https://gap.im/'],
    'rubika'    => ['label' => 'روبیکا',       'icon' => $socialIcons['rubika'],    'base' => 'https://rubika.ir/'],
    'eitaa'     => ['label' => 'ایتا',         'icon' => $socialIcons['eitaa'],     'base' => 'https://eitaa.com/'],
];

/* آدرس نهایی: اگر مقدار کامل (https://) باشد همان استفاده می‌شود، وگرنه شناسه به base افزوده می‌شود */
function socialUrl($base, $val, $digitsOnly = false) {
    $val = trim($val);
    if ($val === '') return '';
    if (preg_match('#^https?://#i', $val)) return $val;
    $h = ltrim($val, '@/');
    if ($digitsOnly) $h = preg_replace('/\D+/', '', $h);
    return $base . $h;
}

$socialLinks = [];
foreach ($socialDefs as $key => $def) {
    $val = getSetting('social_' . $key, '');
    if ($val === '') continue;
    $socialLinks[$key] = [
        'label' => $def['label'],
        'icon'  => $def['icon'],
        'url'   => socialUrl($def['base'], $val, $key === 'whatsapp'),
    ];
}
$acctHref  = isCustomerLoggedIn() ? 'account.php' : 'login.php';
$acctLabel = isCustomerLoggedIn() ? 'حساب کاربری' : 'ورود / ثبت‌نام';
?>
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-col footer-about">
            <h4><span class="logo-icon"><?= icon('cog') ?></span> <?= h(SITE_NAME) ?></h4>
            <?php if ($fAbout !== ''): ?><p><?= nl2br(h($fAbout)) ?></p><?php endif; ?>
        </div>

        <div class="footer-col">
            <h4>دسترسی سریع</h4>
            <a href="banners.php"><?= icon('home') ?>خانه</a>
            <a href="shop.php"><?= icon('store') ?>فروشگاه</a>
            <a href="search.php?new=1"><?= icon('sparkles') ?>محصولات جدید</a>
            <a href="search.php?sale=1"><?= icon('percent') ?>تخفیف‌ها</a>
            <a href="cart.php"><?= icon('cart') ?>سبد خرید</a>
            <a href="<?= $acctHref ?>"><?= icon(isCustomerLoggedIn() ? 'user' : 'login') ?><?= h($acctLabel) ?></a>
            <a href="terms.php"><?= icon('clipboard-list') ?>شرایط و قوانین</a>
        </div>

        <div class="footer-col">
            <h4>تماس با ما</h4>
            <?php foreach ($cPhones as $ph): ?><p><?= icon('phone') ?>تلفن: <a href="tel:<?= h(telHref($ph)) ?>" dir="ltr" style="display:inline;"><?= h($ph) ?></a></p><?php endforeach; ?>
            <?php foreach ($cMobiles as $mb): ?><p><?= icon('mobile') ?>موبایل: <a href="tel:<?= h(telHref($mb)) ?>" dir="ltr" style="display:inline;"><?= h($mb) ?></a></p><?php endforeach; ?>
            <?php if ($cEmail !== ''): ?><p><?= icon('mail') ?><a href="mailto:<?= h($cEmail) ?>" style="display:inline;"><?= h($cEmail) ?></a></p><?php endif; ?>
            <?php if ($cAddress !== ''): ?><p><?= icon('map-pin') ?><span><?= nl2br(h($cAddress)) ?></span></p><?php endif; ?>
            <?php if ($cHours !== ''): ?><p class="footer-hours"><?= icon('clock') ?><span><?= nl2br(h($cHours)) ?></span></p><?php endif; ?>
        </div>

        <?php if ($socialLinks): ?>
        <div class="footer-col">
            <h4>ما را دنبال کنید</h4>
            <div class="footer-social">
                <?php foreach ($socialLinks as $key => $sl): ?>
                <a class="social-chip social-<?= $key ?>" href="<?= h($sl['url']) ?>" target="_blank" rel="noopener" title="<?= h($sl['label']) ?>" aria-label="<?= h($sl['label']) ?>"><?= $sl['icon'] ?><span class="social-sr"><?= h($sl['label']) ?></span></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="footer-bottom">
        <p><?= h($fCopy) ?></p>
    </div>
</footer>
<script src="assets/js/main.js?v=51"></script>
<script src="assets/js/cart.js?v=51"></script>
<script src="assets/js/search.js?v=51"></script>
<script>
/* «انتخاب فایل» سفارشی برای هر input[type=file].form-control که در سمتِ
   مشتری پیدا شود (همتای این اسکریپت در admin/layout-bottom.php) — رجوع کنید
   به کامنتِ .file-pick در assets/css/style.css. */
(function () {
    var UPLOAD_ICON = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2.5 15.5v3a2 2 0 0 0 2 2h15a2 2 0 0 0 2-2v-3"/><path d="m7 8.5 5-5 5 5"/><path d="M12 3.5v13"/></svg>';

    function enhance(input) {
        if (input.dataset.filePickDone) return;
        input.dataset.filePickDone = '1';

        var wrap = document.createElement('div');
        wrap.className = 'file-pick';
        input.parentNode.insertBefore(wrap, input);

        var box = document.createElement('div');
        box.className = 'file-pick-box';
        var defaultTxt = input.multiple ? 'عکس‌ها را آپلود کنید' : 'عکس را آپلود کنید';
        box.innerHTML = UPLOAD_ICON + '<span class="file-pick-txt">' + defaultTxt + '</span>';
        wrap.appendChild(box);
        wrap.appendChild(input);
        input.classList.add('file-pick-input');

        input.addEventListener('change', function () {
            var txt = box.querySelector('.file-pick-txt');
            if (!input.files || !input.files.length) {
                txt.textContent = defaultTxt;
                box.classList.remove('has-file');
                return;
            }
            txt.textContent = input.files.length > 1
                ? (input.files.length + ' فایل انتخاب شد')
                : input.files[0].name;
            box.classList.add('has-file');
        });
    }

    document.querySelectorAll('input[type="file"].form-control').forEach(enhance);
})();
</script>
</body>
</html>
