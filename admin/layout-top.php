<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$currentPage = basename($_SERVER['SCRIPT_NAME']);
/* صفحات فرزند، آیتم منوی والد را فعال نگه می‌دارند */
$menuAliases = ['customer-detail.php' => 'customers.php', 'product-edit.php' => 'products.php', 'order-detail.php' => 'orders.php'];
if (isset($menuAliases[$currentPage])) $currentPage = $menuAliases[$currentPage];
$adminName = $_SESSION['admin_username'] ?? 'admin';

/* $iconName = نام آیکون از includes/icons.php (نه ایموجی)
   $badge > 0 → شمارندهٔ قرمز کنار عنوان (مثل «در انتظار تأیید») */
function adminMenuLink($href, $iconName, $label, $current, $badge = 0) {
    $active = ($current === $href) ? 'active' : '';
    $ic = icon($iconName);
    $bg = (int)$badge > 0 ? "<span class='am-badge'>" . (int)$badge . "</span>" : '';
    return "<a href='$href' class='am-link $active'><span class='am-icon'>$ic</span><span>$label</span>$bg</a>";
}

$partCatCount = $pdo->query("SELECT COUNT(*) FROM part_categories")->fetchColumn();
$brandCount = $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NULL")->fetchColumn();
$modelCount = $pdo->query("SELECT COUNT(*) FROM categories WHERE parent_id IS NOT NULL")->fetchColumn();
$ordersCount = getOrdersCount();
$productsCount = getProductsCount();
$pendingCount = getPendingOrdersCount();
$reviewPending = pendingReviewsCount();
$pchkPending = partCheckPendingCount();
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پنل مدیریت - <?= h(SITE_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/style.css?v=61">
<style>@font-face{font-family:Peyda;src:url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff2') format('woff2'),url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff') format('woff');}
.admin-shell{display:flex;min-height:100vh;background:var(--bg-primary);}
.admin-sidebar{width:240px;background:var(--bg-secondary);border-left:1px solid var(--border-color);display:flex;flex-direction:column;flex-shrink:0;position:sticky;top:0;height:100vh;overflow-y:auto;}
.as-logo{padding:1.25rem;border-bottom:2px solid var(--red-primary);text-align:center;}
.as-logo a{color:var(--red-primary);font-size:1.1rem;font-weight:bold;text-decoration:none;}
.as-logo small{display:block;color:var(--text-muted);font-size:0.7rem;margin-top:2px;}
.as-nav{flex:1;padding:0.75rem;}
.as-nav-label{color:var(--text-muted);font-size:0.65rem;text-transform:uppercase;letter-spacing:1px;padding:0.75rem 0.75rem 0.35rem;border-top:1px solid var(--border-color);margin-top:0.35rem;}
.as-nav-label:first-of-type{border-top:none;margin-top:0;}
.am-link{display:flex;align-items:center;gap:0.6rem;padding:0.55rem 0.75rem;color:var(--text-secondary);text-decoration:none;border-radius:var(--radius-sm);font-size:0.82rem;transition:all 0.15s;margin-bottom:1px;}
.am-link:hover{background:rgba(220,38,38,0.08);color:var(--text-primary);}
.am-link.active{background:var(--red-primary);color:#fff;}
.am-icon{font-size:1rem;width:20px;flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;}
.am-icon>.ic{width:1.15rem;height:1.15rem;vertical-align:0;}
.am-badge{margin-inline-start:auto;background:var(--red-primary);color:#fff;font-size:0.68rem;font-weight:700;min-width:1.25rem;text-align:center;padding:0.05rem 0.35rem;border-radius:999px;line-height:1.5;}
.am-link.active .am-badge{background:#fff;color:var(--red-primary);}
.am-link.active .am-icon>.ic{color:#fff;}
/* «تنظیمات سایت» به‌صورت نوار کشویی: کلیک روی عنوان، زیرشاخه‌ها را باز/بسته می‌کند.
   با details/summary انجام شده تا بدون یک خط جاوااسکریپت کار کند و روی صفحهٔ
   تنظیمات هم از قبل باز باشد. */
.am-group{margin-bottom:1px;}
.am-group>summary{list-style:none;cursor:pointer;}
.am-group>summary::-webkit-details-marker{display:none;}
.am-group>summary::marker{content:'';}
.am-caret{margin-inline-start:auto;display:inline-flex;flex-shrink:0;transition:transform 0.18s;}
.am-caret>.ic{width:0.85rem;height:0.85rem;}
.am-group[open]>summary .am-caret{transform:rotate(180deg);}
.am-group>summary.is-cur{background:rgba(220,38,38,0.10);color:var(--text-primary);}
.am-sub{margin:2px 0 0.4rem;padding-inline-start:0.55rem;border-inline-start:1px solid var(--border-color);}
.am-sublink{font-size:0.78rem;padding:0.42rem 0.6rem;}
.am-sublink .am-icon{width:17px;}
.am-sublink .am-icon>.ic{width:1rem;height:1rem;}
.as-footer{padding:0.75rem;border-top:1px solid var(--border-color);text-align:center;}
.as-footer span{color:var(--text-muted);font-size:0.7rem;}
.as-footer a{color:var(--red-primary);font-size:0.75rem;text-decoration:none;display:block;margin-top:2px;}
.admin-main{flex:1;min-width:0;}
.admin-topbar{background:var(--bg-secondary);border-bottom:1px solid var(--border-color);padding:0.6rem 1.25rem;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:50;}
.at-title{color:var(--text-primary);font-size:0.95rem;font-weight:600;}
.at-actions{display:flex;align-items:center;gap:0.75rem;}
.at-actions .btn{font-size:0.78rem;padding:0.35rem 0.75rem;}
.admin-content{padding:1.25rem;}
.dash-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;margin-bottom:1.5rem;}
.dc{border-radius:var(--radius);padding:1.25rem;display:flex;flex-direction:column;gap:0.5rem;}
.dc-red{background:linear-gradient(135deg,rgba(220,38,38,0.15),rgba(220,38,38,0.05));border:1px solid rgba(220,38,38,0.2);}
.dc-blue{background:linear-gradient(135deg,rgba(37,99,235,0.12),rgba(37,99,235,0.04));border:1px solid rgba(37,99,235,0.2);}
.dc-green{background:linear-gradient(135deg,rgba(16,185,129,0.12),rgba(16,185,129,0.04));border:1px solid rgba(16,185,129,0.2);}
.dc-purple{background:linear-gradient(135deg,rgba(124,58,237,0.12),rgba(124,58,237,0.04));border:1px solid rgba(124,58,237,0.2);}
.dc-orange{background:linear-gradient(135deg,rgba(249,115,22,0.12),rgba(249,115,22,0.04));border:1px solid rgba(249,115,22,0.2);}
.dc-gold{background:linear-gradient(135deg,rgba(234,179,8,0.12),rgba(234,179,8,0.04));border:1px solid rgba(234,179,8,0.2);}
.dc-val{font-size:1.8rem;font-weight:bold;}
.dc-red .dc-val{color:var(--red-light);}
.dc-blue .dc-val{color:#3B82F6;}
.dc-green .dc-val{color:var(--green);}
.dc-purple .dc-val{color:#A78BFA;}
.dc-orange .dc-val{color:#FB923C;}
.dc-gold .dc-val{color:#FBBF24;}
.dc-lbl{color:var(--text-muted);font-size:0.78rem;}
.dash-grid-2{display:grid;grid-template-columns:2fr 1fr;gap:1rem;}
.dg-box{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius);overflow:hidden;}
.dg-box-hd{padding:0.75rem 1rem;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:0.75rem;}
.dg-box-hd h3{font-size:0.85rem;color:var(--text-primary);display:flex;align-items:center;gap:0.4rem;}
/* عنوان جعبه وقتی آیکون و متن مستقیما داخل .dg-box-hd هستند (بدون h3) */
.dg-hd-t{display:flex;align-items:center;gap:0.4rem;font-size:0.85rem;font-weight:600;color:var(--text-primary);}
.dg-box-hd a{font-size:0.72rem;color:var(--red-primary);text-decoration:none;}
.dg-box-bd{padding:0.5rem;}
.admin-form-full{max-width:700px;}
/* بخش «روش‌های ارسال» دو جدول پهن دارد (min-width:680px در style.css) که در قاب
   ۷۰۰ پیکسلی جا نمی‌شد و نوار اسکرول افقی می‌ساخت. فقط همین بخش تا ۱۳۲۰ پیکسل به
   چپ کشیده می‌شود تا جدول‌ها بی‌اسکرول جا شوند؛ فیلدهای تک‌خطی همین بخش با
   ff-cap کوتاه نگه داشته می‌شوند تا کشیده و بی‌قواره نشوند. */
.admin-form-full.ff-wide,#shipcrud,#ratecrud{max-width:1320px;}
.ff-wide .ff-cap,#shipcrud .ff-cap{max-width:640px;}
/* تب‌های مشتری/همکار + جمع‌بندی */
.cust-tabs{display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;margin-bottom:1rem;}
.cust-tab{display:inline-flex;align-items:center;gap:0.4rem;padding:0.45rem 0.85rem;border:1px solid var(--border-color);border-radius:999px;color:var(--text-secondary);text-decoration:none;font-size:0.82rem;transition:all 0.15s;}
.cust-tab:hover{border-color:var(--red-primary);color:var(--text-primary);}
.cust-tab.active{background:var(--red-primary);border-color:var(--red-primary);color:#fff;}
.cust-tab-n{background:rgba(0,0,0,0.25);border-radius:999px;padding:0 0.4rem;font-size:0.72rem;}
.cust-search{display:flex;gap:0.35rem;margin-right:auto;}
.cust-search .form-control{width:230px;font-size:0.8rem;padding:0.4rem 0.6rem;}
.cust-sum{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;margin-bottom:1rem;}
.cust-sum>div{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius);padding:0.7rem 0.9rem;display:flex;flex-direction:column;gap:0.2rem;}
.cust-sum span{color:var(--text-muted);font-size:0.72rem;}
.cust-sum b{color:var(--text-primary);font-size:1rem;}
.cust-info th{width:150px;color:var(--text-muted);font-weight:400;font-size:0.8rem;}
/* نمودار SVG درون‌خطی */
.chart-wrap{width:100%;overflow-x:auto;overflow-y:hidden;padding-bottom:0.25rem;}
.chart-wrap .sales-chart{min-width:560px;display:block;}
.chart-wrap .sales-chart text{font-family:inherit;}
.chart-empty{padding:2rem 1rem;text-align:center;color:var(--text-muted);font-size:0.85rem;}
.badge-partner,.badge-retail,.badge-pending{display:inline-block;font-size:0.7rem;font-weight:600;padding:0.15rem 0.5rem;border-radius:999px;white-space:nowrap;}
.badge-partner{background:rgba(34,197,94,0.15);color:#4ADE80;border:1px solid rgba(34,197,94,0.35);}
.badge-retail{background:rgba(148,163,184,0.15);color:#CBD5E1;border:1px solid rgba(148,163,184,0.3);}
.badge-pending{background:rgba(234,179,8,0.15);color:#FBBF24;border:1px solid rgba(234,179,8,0.35);}
@media(max-width:768px){.admin-shell{flex-direction:column;}.admin-sidebar{width:100%;height:auto;position:static;}.dash-grid-2{grid-template-columns:1fr;}.cust-search{width:100%;margin-right:0;}.cust-search .form-control{flex:1;width:auto;}}
</style>
</head>
<body>
<div class="admin-shell">
<aside class="admin-sidebar">
<div class="as-logo"><a href="index.php"><?= h(SITE_NAME) ?></a><small>پنل مدیریت</small></div>
<nav class="as-nav">
<div class="as-nav-label">اصلی</div>
<?= adminMenuLink('index.php', 'dashboard', 'داشبورد', $currentPage) ?>
<?= adminMenuLink('../shop.php', 'store', 'مشاهده فروشگاه', '') ?>
<div class="as-nav-label">مدیریت</div>
<?= adminMenuLink('products.php', 'package', 'محصولات', $currentPage) ?>
<?= adminMenuLink('categories.php', 'layers', 'برندها و مدل‌ها', $currentPage) ?>
<?= adminMenuLink('part-categories.php', 'cog', 'دسته‌بندی قطعات', $currentPage) ?>
<?= adminMenuLink('banners.php', 'image', 'بنرها', $currentPage) ?>
<?= adminMenuLink('menus.php', 'menu', 'منوهای سایت', $currentPage) ?>
<?= adminMenuLink('orders.php', 'clipboard-list', 'سفارشات', $currentPage) ?>
<?= adminMenuLink('customers.php', 'users', 'مشتریان و همکاران', $currentPage) ?>
<?= adminMenuLink('partner-settlements.php', 'scale', 'تسویهٔ همکاران', $currentPage) ?>
<?= adminMenuLink('reviews.php', 'message', 'نظرات و پرسش‌ها', $currentPage, $reviewPending) ?>
<?= adminMenuLink('part-checks.php', 'camera', 'بررسی عکس قطعه', $currentPage, $pchkPending) ?>
<?php
/* تنظیمات سایت: والد کشویی با چهار زیرشاخهٔ مجزا (هرکدام صفحهٔ خودش را دارد) */
$asSecs   = settingsSections();
$asOnSet  = ($currentPage === 'settings.php');
$asCurSec = settingsSectionKey($_GET['sec'] ?? '');
?>
<details class="am-group" <?= $asOnSet ? 'open' : '' ?>>
<summary class="am-link am-parent<?= $asOnSet ? ' is-cur' : '' ?>"><span class="am-icon"><?= icon('gear') ?></span><span>تنظیمات سایت</span><span class="am-caret"><?= icon('chevron-down') ?></span></summary>
<div class="am-sub">
<?php foreach ($asSecs as $asK => $asD): ?>
<a href="settings.php?sec=<?= h($asK) ?>" class="am-link am-sublink<?= ($asOnSet && $asCurSec === $asK) ? ' active' : '' ?>"><span class="am-icon"><?= icon($asD['icon']) ?></span><span><?= h($asD['label']) ?></span></a>
<?php endforeach; ?>
</div>
</details>
<div class="as-nav-label">حساب کاربری</div>
<a href="logout.php" class="am-link"><span class="am-icon"><?= icon('logout') ?></span><span>خروج</span></a>
</nav>
<div class="as-footer"><span>کاربر: <?= h($adminName) ?></span><a href="../shop.php" target="_blank">مشاهده فروشگاه &#8598;</a></div>
</aside>
<main class="admin-main">
<div class="admin-topbar">
<div class="at-title"><?= h(SITE_NAME) ?> / پنل مدیریت</div>
<div class="at-actions"><a href="../shop.php" target="_blank" class="btn btn-secondary btn-sm">&#8598; فروشگاه</a></div>
</div>
<div class="admin-content">