<?php
require_once 'includes/header.php';

$q = trim($_GET['q'] ?? '');
$isNew      = isset($_GET['new']);
$isFeatured = isset($_GET['featured']);
$isSale     = isset($_GET['sale']);
$isListMode = $isNew || $isFeatured || $isSale; // حالت‌هایی که نوار جستجو ندارند
$newDays = 7; // «محصولات جدید» = کالاهای اضافه‌شده در ۷ روز اخیر

$products = [];
$pageTitle = '';
$pageIcon = '';
$pageNote = '';
$emptyText = 'نتیجه‌ای یافت نشد.';
$emptyIcon = icon('search');

if ($isNew) {
    $products = getProducts(['new_within_days' => $newDays]);
    $pageIcon = icon('sparkles');
    $pageTitle = 'محصولات جدید';
    $pageNote = 'کالاهای اضافه‌شده در ' . $newDays . ' روز اخیر';
    $emptyText = 'فعلاً محصول جدیدی ثبت نشده است.';
    $emptyIcon = icon('sparkles');
} elseif ($isFeatured) {
    $products = getBestSellerProducts(48);
    $pageIcon = icon('star');
    $pageTitle = 'پرفروش‌ها';
    $pageNote = 'پرفروش‌ترین کالاها بر اساس تعداد فروش';
    $emptyText = 'هنوز فروشی ثبت نشده است.';
    $emptyIcon = icon('star');
} elseif ($isSale) {
    $products = getProducts(['on_sale' => 1]);
    $pageIcon = icon('percent');
    $pageTitle = 'تخفیف‌ها';
    $pageNote = 'کالاهای دارای تخفیف';
    $emptyText = 'در حال حاضر کالای تخفیف‌داری موجود نیست.';
    $emptyIcon = icon('percent');
} elseif ($q !== '') {
    $filters = ['search' => $q];
    if (!empty($_GET['brand'])) {
        $filters['brand_id'] = (int)$_GET['brand'];
    }
    $products = getProducts($filters);
}

/* کارت محصول (هم‌شکل با سایر صفحات، با پشتیبانی از تخفیف) */
function searchProductCard($p) {
    ob_start(); ?>
    <div class="product-card">
        <a href="product.php?id=<?= $p['id'] ?>" class="product-card-image">
            <?php if ($p['image']): ?>
            <img src="uploads/products/<?= h($p['image']) ?>" alt="<?= h($p['name']) ?>">
            <?php else: ?>
            <?= icon('cog') ?>
            <?php endif; ?>
        </a>
        <div class="product-card-body">
            <div class="product-card-title">
                <a href="product.php?id=<?= $p['id'] ?>"><?= h($p['name']) ?></a>
            </div>
            <div class="product-card-tech">شماره فنی: <?= h($p['technical_number']) ?></div>
            <?= productCardStars($p['id']) ?>
            <?= productCardPrices($p) ?>
            <div class="product-card-stock"><?= $p['stock'] > 0 ? 'موجود در انبار' : 'ناموجود' ?></div>
            <div class="product-card-actions">
                <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">مشاهده</a>
                <?php if ($p['stock'] > 0): ?>
                <button class="btn btn-primary btn-sm add-to-cart-btn" data-id="<?= $p['id'] ?>">افزودن به سبد</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>

<div class="container">
    <?php /* نوارِ جستجویِ خودِ این صفحه حذف شد — نوارِ جستجوی بالای سایت
            (includes/header.php، همیشه روی هر صفحه‌ای هست) کارش را می‌کند؛
            داشتنِ دوتایی‌اش زیرِ هم روی همین صفحه اضافه و گیج‌کننده بود
            (خواستهٔ کاربر). */ ?>

    <?php if ($isListMode): ?>
    <h1 class="page-title"><?= $pageIcon ?> <?= h($pageTitle) ?></h1>
    <div class="search-results-count"><?= h($pageNote) ?><?= $products ? ' — ' . count($products) . ' محصول' : '' ?></div>

    <?php if ($products): ?>
    <div class="product-grid">
        <?php foreach ($products as $p) echo searchProductCard($p); ?>
    </div>
    <?php else: ?>
    <div class="no-results">
        <div class="no-results-icon"><?= $emptyIcon ?></div>
        <p><?= h($emptyText) ?></p>
    </div>
    <?php endif; ?>

    <?php elseif ($q !== ''): ?>
    <div class="search-results-count"><?= count($products) ?> نتیجه برای «<?= h($q) ?>»</div>

    <?php if ($products): ?>
    <div class="product-grid">
        <?php foreach ($products as $p) echo searchProductCard($p); ?>
    </div>
    <?php else: ?>
    <div class="no-results">
        <div class="no-results-icon"><?= icon('search') ?></div>
        <p>نتیجه‌ای یافت نشد.</p>
        <p style="font-size:0.85rem;">عبارت دیگری را جستجو کنید.</p>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <?php /* بدون عبارت جستجو: مشتری از نوارِ بالای سایت جستجو می‌کند، اینجا
            فقط راهنمایی کوتاه است تا صفحه خالی نماند. */ ?>
    <div class="no-results">
        <div class="no-results-icon"><?= icon('search') ?></div>
        <p>عبارتی برای جستجو وارد نشده است.</p>
        <p style="font-size:0.85rem;">از نوار جستجوی بالای صفحه، نام قطعه یا شماره فنی را جستجو کنید.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
