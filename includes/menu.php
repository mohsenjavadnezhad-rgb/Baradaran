<?php
/* =====================================================================
   منوهای سایت (نوار بالای صفحه + «دسترسی سریع» فوتر) — قابل مدیریت از پنل
   ادمین (admin/menus.php): افزودن، ویرایش نام/آیکون، فعال/غیرفعال کردن،
   حذف و جابه‌جایی ترتیب.
   ---------------------------------------------------------------------
   هر آیتم یا «آزاد» است (item_key خالی: لینک ساده با برچسب/آدرس/آیکون
   دلخواه — کاملا حذف‌شدنی) یا «سیستمی» (item_key پر: مثل مگامنوی فروشگاه
   یا لینک حساب‌کاربری/ورود که آدرس یا محتوایش وابسته به منطق صفحه است و
   در includes/header.php و includes/footer.php ساخته می‌شود، نه از ستون
   url؛ فقط برچسب/آیکون/ترتیب/فعال‌بودنش از دیتابیس می‌آید و حذف واقعی
   ندارد — به‌جایش غیرفعال می‌شود).
   ===================================================================== */
require_once __DIR__ . '/db.php';

function menuReady() {
    return dbHasTable('site_menus');
}

/* چیدمان پیش‌فرض اولیهٔ سایت — هم برای بذر migrate-menus.php و هم برای
   نصب‌هایی که هنوز آن مهاجرت را اجرا نکرده‌اند (صفحه نباید قبل از مهاجرت
   خالی/خراب شود). */
function menuDefaults($group) {
    if ($group === 'footer') {
        return [
            ['item_key' => null,      'label' => 'خانه',          'url' => 'banners.php',           'icon' => 'home'],
            ['item_key' => null,      'label' => 'فروشگاه',        'url' => 'shop.php',               'icon' => 'store'],
            ['item_key' => null,      'label' => 'محصولات جدید',   'url' => 'search.php?new=1',       'icon' => 'sparkles'],
            ['item_key' => null,      'label' => 'تخفیف‌ها',       'url' => 'search.php?sale=1',      'icon' => 'percent'],
            ['item_key' => null,      'label' => 'سبد خرید',      'url' => 'cart.php',                'icon' => 'cart'],
            ['item_key' => 'account', 'label' => 'حساب کاربری',    'url' => null,                     'icon' => 'user'],
            ['item_key' => null,      'label' => 'شرایط و قوانین', 'url' => 'terms.php',               'icon' => 'clipboard-list'],
        ];
    }
    return [
        ['item_key' => null,        'label' => 'خانه',        'url' => 'banners.php',            'icon' => 'home'],
        ['item_key' => 'shop_mega', 'label' => 'فروشگاه',      'url' => 'parts.php',               'icon' => 'store'],
        ['item_key' => null,        'label' => 'قطعات خودرو',  'url' => 'shop.php',                 'icon' => 'cog'],
        ['item_key' => null,        'label' => 'محصولات جدید', 'url' => 'search.php?new=1',         'icon' => 'sparkles'],
        ['item_key' => null,        'label' => 'پرفروش‌ها',    'url' => 'search.php?featured=1',    'icon' => 'star'],
        ['item_key' => null,        'label' => 'تخفیف‌ها',     'url' => 'search.php?sale=1',        'icon' => 'percent'],
        ['item_key' => null,        'label' => 'سبد خرید',    'url' => 'cart.php',                  'icon' => 'cart'],
        ['item_key' => 'account',   'label' => 'حساب کاربری',  'url' => null,                       'icon' => 'user'],
    ];
}

/* آیتم‌های فعال یک منو، به ترتیب نمایش — برای رندر سمت سایت.
   قبل از مهاجرت (یا در صورت خطای غیرمنتظره) به چیدمان پیش‌فرض برمی‌گردد تا
   نوار بالای سایت/فوتر هرگز خالی نماند. */
function menuItems($group) {
    if (!menuReady()) return menuDefaults($group);
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM site_menus WHERE menu_group=? AND is_active=1 ORDER BY sort_order, id");
        $stmt->execute([$group]);
        return $stmt->fetchAll();
    } catch (Throwable $e) { return menuDefaults($group); }
}

/* همهٔ آیتم‌ها (غیرفعال‌ها هم) — برای فهرست پنل مدیریت */
function menuAllItems($group) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM site_menus WHERE menu_group=? ORDER BY sort_order, id");
        $stmt->execute([$group]);
        return $stmt->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* عدد ترتیب بعدی برای آیتم تازه (همیشه ته لیست) */
function menuNextOrder($group) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+10 FROM site_menus WHERE menu_group=?");
        $stmt->execute([$group]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 10; }
}

function menuGroupLabel($group) {
    return $group === 'footer' ? 'دسترسی سریع فوتر' : 'منوی بالای سایت';
}

/* آیکون‌های پیشنهادی برای انتخابگر آیکون در پنل مدیریت — زیرمجموعه‌ای از
   includes/icons.php که برای لینک منو معنادارند. */
function menuIconChoices() {
    return ['home', 'store', 'cog', 'sparkles', 'star', 'percent', 'cart', 'user', 'login',
            'clipboard-list', 'menu', 'grid', 'tag', 'info', 'mail', 'phone', 'map-pin',
            'truck', 'package', 'layers', 'image', 'message', 'help', 'external', 'globe',
            'award', 'flame', 'briefcase', 'bag', 'calendar', 'camera', 'chart', 'gear'];
}
