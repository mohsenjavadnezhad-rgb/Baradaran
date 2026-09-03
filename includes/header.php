<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cart-functions.php';
require_once __DIR__ . '/menu.php';

$cartAction = handleCartAction();
$cartCount = getCartCount();
$brands = getAllBrands();
$currentSearch = $_GET['q'] ?? '';
$partTree = getPartCategoriesTree();

$headerBrandId = (int)($_GET['brand'] ?? 0);
$headerBrandName = '';
$headerLogoSrc = '';
if ($headerBrandId) {
    foreach ($brands as $b) {
        if ($b['id'] == $headerBrandId) {
            $headerBrandName = $b['name'];
            $headerLogoSrc = brandLogoSrc($b);
            break;
        }
    }
}

function renderBrandsTree($brands) {
    $html = '';
    foreach ($brands as $brand) {
        $models = getSubCategories($brand['id']);
        $logoSrc = brandLogoSrc($brand);
        $html .= '<div class="mm-col">';
        $html .= '<a href="shop.php?brand=' . $brand['id'] . '" class="mm-brand-head">';
        if ($logoSrc) $html .= '<img src="' . $logoSrc . '" class="mm-brand-icon" alt="">';
        $html .= h($brand['name']) . '</a>';
        if ($models) {
            $html .= '<ul class="mm-sub-list">';
            foreach (array_slice($models, 0, 10) as $m) {
                $html .= '<li><a href="category.php?cat=' . $m['id'] . '">' . h($m['name']) . '</a></li>';
            }
            if (count($models) > 10) {
                $html .= '<li><a href="shop.php?brand=' . $brand['id'] . '" class="mm-more">+ ' . (count($models) - 10) . ' مدل دیگر</a></li>';
            }
            $html .= '</ul>';
        }
        $html .= '</div>';
    }
    return $html;
}
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=59">
    <style>
    @font-face{font-family:Peyda;src:url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff2') format('woff2'),url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff') format('woff')}
    .variant-section{margin-bottom:1rem;padding:0.75rem;background:var(--bg-primary);border:1px solid var(--border-color);border-radius:8px}
    .variant-section-title{font-size:0.82rem;font-weight:600;color:var(--red-light);margin-bottom:0.5rem;display:flex;align-items:center;gap:0.4rem}
    .variant-selects{display:flex;gap:0.75rem}
    .variant-selects label{display:flex;align-items:center;gap:0.35rem}
    /* رنگ آیکون منوهای برجسته */
    .nav-link .ic{opacity:.9}
    .nav-link:hover .ic{opacity:1}
    .logo .ic{color:var(--red-primary)}
    </style>
</head>
<body>
<header class="site-header">
    <div class="header-top">
        <div class="header-container">
            <div class="header-left-group">
                <?php if ($headerLogoSrc): ?>
                <a href="shop.php?brand=<?= $headerBrandId ?>" class="brand-logo-box">
                    <img src="<?= $headerLogoSrc ?>" alt="<?= h($headerBrandName) ?>" class="brand-logo-img">
                </a>
                <?php endif; ?>
                <a href="banners.php" class="logo">
                    <span class="logo-icon"><?= icon('cog') ?></span>
                    <span class="logo-text"><?= h(SITE_NAME) ?></span>
                </a>
            </div>

            <form action="search.php" method="GET" class="header-search">
                <input type="text" name="q" value="<?= h($currentSearch) ?>" placeholder="جستجوی قطعه یا شماره فنی..." class="search-input">
                <button type="submit" class="search-btn">جستجو</button>
            </form>

            <div class="header-actions">
                <?php if (isCustomerLoggedIn()): $cu = currentCustomer();
                    /* نوع حساب کنار اسم — خواستهٔ کاربر: «مشخص باشه با اکانت
                       همکار وارد شده یا مشتری». همان کلاس‌های badge-partner/
                       badge-retail/badge-pending که در فهرست مشتریان ادمین
                       هست، اینجا هم دوباره استفاده می‌شود (در style.css اند،
                       نه فقط inline layout-top، پس سمت سایت هم در دسترس‌اند). */
                    $cuIsPartner = $cu && (($cu['customer_type'] ?? 'retail') === 'partner');
                    $cuApproved  = $cuIsPartner && (($cu['partner_status'] ?? '') === 'approved');
                ?>
                <a href="account.php" class="account-link" title="حساب کاربری">
                    <span class="account-icon"><?= icon('user') ?></span>
                    <span class="account-name"><?= h($cu && trim((string)$cu['full_name']) !== '' ? $cu['full_name'] : 'حساب من') ?></span>
                    <?php if ($cuIsPartner): ?>
                    <span class="badge-<?= $cuApproved ? 'partner' : 'pending' ?>" style="margin-inline-start:0.3rem;"><?= $cuApproved ? 'همکار' : 'همکار (در انتظار تأیید)' ?></span>
                    <?php else: ?>
                    <span class="badge-retail" style="margin-inline-start:0.3rem;">مشتری</span>
                    <?php endif; ?>
                </a>
                <?php else: ?>
                <a href="login.php" class="account-link">
                    <span class="account-icon"><?= icon('login') ?></span>
                    <span class="account-name">ورود</span>
                </a>
                <?php endif; ?>
                <a href="cart.php" class="cart-link">
                    <span class="cart-icon"><?= icon('cart') ?></span>
                    <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <nav class="main-navbar">
        <div class="header-container">
            <?php /* آیتم‌های این نوار از پنل مدیریت (admin/menus.php) می‌آیند —
                    قابل افزودن/حذف/تغییرنام/غیرفعال‌کردن. دو آیتم «سیستمی»
                    (item_key پر) رفتار ویژه‌شان همین‌جا کد شده: مگامنوی
                    فروشگاه (محتوایش از $partTree می‌آید) و لینک حساب‌کاربری/
                    ورود (بسته به ورود مشتری عوض می‌شود)؛ بقیه لینک ساده‌اند. */ ?>
            <?php foreach (menuItems('main') as $mi): ?>
                <?php if ($mi['item_key'] === 'shop_mega'): ?>
            <div class="nav-dropdown nav-mega">
                <a href="parts.php" class="nav-link nav-dropdown-btn"><?= icon($mi['icon']) ?><?= h($mi['label']) ?></a>
                <div class="mega-menu">
                    <div class="mega-menu-inner">
                        <div class="mega-parts-grid">
                            <?php foreach ($partTree as $group): ?>
                            <div class="mm-col">
                                <a class="mm-part-head" href="parts.php?cat=<?= $group['parent']['id'] ?>"><?= h($group['parent']['name']) ?></a>
                                <ul class="mm-sub-list">
                                    <?php foreach ($group['children'] as $child): ?>
                                    <li><a href="parts.php?cat=<?= $child['id'] ?>"><?= h($child['name']) ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
                <?php elseif ($mi['item_key'] === 'account'): ?>
            <a href="<?= isCustomerLoggedIn() ? 'account.php' : 'login.php' ?>" class="nav-link"><?= icon(isCustomerLoggedIn() ? $mi['icon'] : 'login') ?><?= isCustomerLoggedIn() ? h($mi['label']) : 'ورود / ثبت‌نام' ?></a>
                <?php elseif ($mi['url'] !== null && $mi['url'] !== ''): ?>
            <a href="<?= h($mi['url']) ?>" class="nav-link"><?= icon($mi['icon']) ?><?= h($mi['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<main class="site-main">
<?php /* پیغام سبد خرید به‌صورت «توست» شناور (position:fixed) نمایش داده می‌شود.
         چون از جریان صفحه بیرون است، آمدن یا نیامدنش هیچ جابجایی/پرش در محتوا ایجاد نمی‌کند. */ ?>
<?php if (!empty($cartAction)): $ctOk = !empty($cartAction['success']); ?>
<div class="cart-toast <?= $ctOk ? 'is-ok' : 'is-err' ?>" id="cartToast" role="status" aria-live="polite">
    <span class="cart-toast-ic"><?= icon($ctOk ? 'check-circle' : 'alert') ?></span>
    <span class="cart-toast-msg"><?= h($cartAction['message']) ?></span>
    <button type="button" class="cart-toast-x" id="cartToastX" aria-label="بستن پیام"><?= icon('x') ?></button>
</div>
<script>
(function(){
    var t = document.getElementById('cartToast');
    if (!t) return;
    var hide = function(){ t.classList.add('is-hide'); };
    var x = document.getElementById('cartToastX');
    if (x) x.addEventListener('click', hide);
    setTimeout(hide, 4500);
})();
</script>
<?php endif; ?>