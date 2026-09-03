<?php
require_once 'includes/header.php';

/* ==========================================================================
   صفحهٔ دسته‌بندی قطعات (part_categories).
     parts.php               → فهرست همه سرشاخه‌ها
     parts.php?cat=<سرشاخه>  → زیرشاخه‌ها + همین‌جا مرحلهٔ برند/سال، سپس محصولات
     parts.php?cat=<زیرشاخه> → همین مرحلهٔ برند/سال برای محصولات آن زیرشاخه
   ---------------------------------------------------------------------
   مرحله برند/مدل/سال (خواسته کاربر): بعد از انتخاب یک دسته قطعه، اول باید
   برند خودرو انتخاب شود (کاشی‌های بزرگ)؛ با انتخاب برند، چیپ‌های بزرگِ
   «مدل خودرو» (زیرمجموعهٔ همان برند در سیستمِ دستهٔ برند/مدل — همان
   getSubCategories() که shop.php هم استفاده می‌کند) و «سال تولید» کنارش
   ظاهر می‌شوند (نه نوار کشویی — خواسته صریح کاربر). هر دو اختیاری‌اند —
   همان لحظه که برند انتخاب شده (بدون انتخابِ مدل/سال) همه محصولات آن برند
   دیده می‌شوند؛ چیپ‌های «همهٔ مدل‌ها»/«همه سال‌ها» هم برای برگشتن از یک
   انتخابِ قبلی به همین حالت‌اند. محصولی که سالی برایش ثبت نشده («از/تا»
   خالی)، برای همه سال‌ها مناسب حساب می‌شود
   (productYearReady()/getProducts() را ببینید).
   ========================================================================== */

$catId   = (int)($_GET['cat'] ?? 0);
$brandId = (int)($_GET['brand'] ?? 0);
$year    = (int)($_GET['year'] ?? 0);
$modelId = (int)($_GET['model'] ?? 0);

$current = null;
if ($catId) {
    $st = $pdo->prepare("SELECT * FROM part_categories WHERE id = ?");
    $st->execute([$catId]);
    $current = $st->fetch();
}

/* برند معتبر است؟ (ورودی کاربر قابل جعل است) */
$allBrands = getAllBrands();
$selectedBrand = null;
if ($brandId) {
    foreach ($allBrands as $b) { if ((int)$b['id'] === $brandId) { $selectedBrand = $b; break; } }
    if (!$selectedBrand) $brandId = 0;
}

/* مدلِ زیرمجموعهٔ همین برند (خواستهٔ کاربر: «وقتی پژو رو انتخاب می‌کنم
   مدل‌های زیرمجموعه‌اش رو نشون بده — ۲۰۰۸/۲۰۶/۲۰۷/...»). دقیقاً همان
   getSubCategories() که shop.php برای مدل‌های زیر یک برند استفاده می‌کند؛
   اختیاری است، مثل سال — انتخاب‌نکردنش یعنی همهٔ مدل‌های آن برند دیده
   می‌شوند (همان قاعدهٔ «هیچ‌وقت با انتخاب‌نکردن، چیزی خالی نشود»). */
$subModels = $selectedBrand ? getSubCategories($brandId) : [];
$modelName = '';
if ($modelId) {
    foreach ($subModels as $sm) { if ((int)$sm['id'] === $modelId) { $modelName = $sm['name']; break; } }
    if ($modelName === '') $modelId = 0;
}

$yearOptions = (productYearReady() && productYearEnabled()) ? productYearOptions() : [];
if ($year && !in_array($year, $yearOptions, true)) $year = 0;

/* کارت محصول (هم‌شکل با فروشگاه) */
function partsProductCard($p) {
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

/* مرحلهٔ برند + سال — یک تابع مشترک چون هم صفحهٔ سرشاخه و هم صفحهٔ زیرشاخه
   دقیقا همین قدم را لازم دارند. $baseQs = پارامترهای ثابت صفحه (cat=...)
   که در همه لینک‌های این بخش باید بماند. */
function renderBrandYearStep($allBrands, $selectedBrand, $brandId, $modelId, $subModels, $year, $yearOptions, $baseQs) {
    /* لینک‌های چیپِ مدل/سال باید همیشه انتخابِ دیگری (سال وقتی مدل عوض
       می‌شود، مدل وقتی سال عوض می‌شود) را نگه دارند — وگرنه انتخابِ یکی،
       آن یکی را بی‌دلیل پاک می‌کرد. */
    $yearQs  = $year  ? '&year=' . (int)$year   : '';
    $modelQs = $modelId ? '&model=' . (int)$modelId : '';
    ?>
    <div class="pby-box">
        <?php if (!$selectedBrand): ?>
        <div class="pby-title"><?= icon('cog', 'ic-sm') ?> اول برند خودروتان را انتخاب کنید</div>
        <div class="pby-brands">
            <?php foreach ($allBrands as $b): $logoSrc = brandLogoSrc($b); ?>
            <a href="parts.php?<?= $baseQs ?>&brand=<?= (int)$b['id'] ?>" class="pby-brand-tile">
                <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" alt="" class="pby-brand-logo"><?php else: ?><?= icon('cog') ?><?php endif; ?>
                <span><?= h($b['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$allBrands): ?>
        <p style="color:var(--text-muted);font-size:0.85rem;">هنوز برندی تعریف نشده است.</p>
        <?php endif; ?>

        <?php else: ?>
        <div class="pby-selrow">
            <div class="pby-selbrand">
                <?php $logoSrc = brandLogoSrc($selectedBrand); ?>
                <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" alt="" class="pby-brand-logo"><?php else: ?><?= icon('cog') ?><?php endif; ?>
                <b><?= h($selectedBrand['name']) ?></b>
                <a href="parts.php?<?= $baseQs ?>" class="pby-change"><?= icon('refresh', 'ic-sm') ?> تغییر برند</a>
            </div>

            <?php /* انتخابگرِ مدل — زیرمجموعهٔ همان برند (خواستهٔ کاربر: بعد از
                    انتخابِ برند، مدل‌هایش نشان داده شود، مثلاً پژو ۲۰۰۸/۲۰۶/...).
                    اختیاری است، دقیقاً هم‌قاعدهٔ سال: چیپِ «همهٔ مدل‌ها» همیشه
                    اول است، هم برای انتخابِ صریح هم برایِ برگشتن از یک مدلِ
                    انتخاب‌شده. سال (اگر انتخاب شده) در همهٔ لینک‌ها حفظ می‌شود. */ ?>
            <?php if ($subModels): ?>
            <div class="pby-models">
                <div class="pby-modelslabel"><?= icon('cog', 'ic-sm') ?> مدل خودرو</div>
                <div class="pby-yearchips">
                    <a href="parts.php?<?= $baseQs ?>&brand=<?= (int)$brandId ?><?= $yearQs ?>" class="pby-yearchip <?= $modelId === 0 ? 'is-on' : '' ?>">همهٔ مدل‌ها</a>
                    <?php foreach ($subModels as $sm): ?>
                    <a href="parts.php?<?= $baseQs ?>&brand=<?= (int)$brandId ?>&model=<?= (int)$sm['id'] ?><?= $yearQs ?>" class="pby-yearchip <?= $modelId === (int)$sm['id'] ? 'is-on' : '' ?>"><?= h($sm['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php /* انتخابگر سال تولید — به‌جای نوار کشویی (select)، چیپ‌های
                    بزرگ کلیک‌پذیر (خواستهٔ کاربر: «نوار کشویی نباشه، یک طرح
                    زیباتر»)؛ هر چیپ خودش یک لینک ساده است، پس بدون جاوااسکریپت
                    هم کامل کار می‌کند. چیپ «همه سال‌ها» همیشه اول است، هم برای
                    انتخاب صریح و هم برای برگشتن از یک سال انتخاب‌شده. */ ?>
            <?php if ($yearOptions): ?>
            <div class="pby-years">
                <div class="pby-yearslabel"><?= icon('calendar', 'ic-sm') ?> سال تولید</div>
                <div class="pby-yearchips">
                    <a href="parts.php?<?= $baseQs ?>&brand=<?= (int)$brandId ?><?= $modelQs ?>" class="pby-yearchip <?= $year === 0 ? 'is-on' : '' ?>">همه سال‌ها</a>
                    <?php foreach ($yearOptions as $y): ?>
                    <a href="parts.php?<?= $baseQs ?>&brand=<?= (int)$brandId ?>&year=<?= $y ?><?= $modelQs ?>" class="pby-yearchip <?= $year === $y ? 'is-on' : '' ?>"><?= $y ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<div class="container">

<?php if (!$current): ?>
    <?php /* --- فهرست سرشاخه‌ها (بدون تغییر) --- */ ?>
    <h1 class="page-title">دسته‌بندی قطعات</h1>
    <?php $tree = getPartCategoriesTree(); ?>
    <?php if ($tree): ?>
    <div class="parts-cat-grid parts-cat-grid--top">
        <?php foreach ($tree as $g): ?>
        <a href="parts.php?cat=<?= $g['parent']['id'] ?>" class="parts-cat-tile">
            <span class="parts-cat-tile-name"><?= h($g['parent']['name']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="no-results"><div class="no-results-icon"><?= icon('cog') ?></div><p>دسته‌بندی‌ای تعریف نشده است.</p></div>
    <?php endif; ?>

<?php elseif (empty($current['parent_id'])): ?>
    <?php
    /* --- صفحهٔ سرشاخه: زیرشاخه‌ها + مرحلهٔ برند/سال + محصولات --- */
    $ch = $pdo->prepare("SELECT * FROM part_categories WHERE parent_id = ? ORDER BY sort_order, name");
    $ch->execute([$current['id']]);
    $children = $ch->fetchAll();

    $ids = array_merge([(int)$current['id']], array_map('intval', array_column($children, 'id')));
    $counts = [];
    $inList = implode(',', $ids);
    if ($inList) {
        foreach ($pdo->query("SELECT part_category_id, COUNT(*) AS c FROM products WHERE is_active = 1 AND part_category_id IN ($inList) GROUP BY part_category_id") as $r) {
            $counts[(int)$r['part_category_id']] = (int)$r['c'];
        }
    }

    $baseQs = 'cat=' . (int)$current['id'];
    $products = [];
    if ($brandId) {
        $pf = ['part_category_ids' => $ids, 'brand_id' => $brandId];
        if ($modelId) $pf['category_id'] = $modelId;
        if ($year) $pf['year'] = $year;
        $products = getProducts($pf);
    }
    ?>
    <div class="parts-crumb"><a href="parts.php">دسته‌بندی قطعات</a> &laquo; <?= h($current['name']) ?></div>
    <h1 class="page-title"><?= h($current['name']) ?></h1>

    <?php if ($children): ?>
    <div class="parts-cat-grid">
        <?php foreach ($children as $c): $n = $counts[(int)$c['id']] ?? 0; ?>
        <a href="parts.php?cat=<?= $c['id'] ?>" class="parts-cat-tile">
            <span class="parts-cat-tile-name"><?= h($c['name']) ?></span>
            <?php if ($n): ?><span class="parts-cat-tile-count"><?= $n ?> کالا</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php renderBrandYearStep($allBrands, $selectedBrand, $brandId, $modelId, $subModels, $year, $yearOptions, $baseQs); ?>

    <?php if ($brandId): ?>
        <?php if ($products): ?>
        <div class="parts-section-title">محصولات این دسته (<?= count($products) ?>)</div>
        <div class="product-grid">
            <?php foreach ($products as $p) echo partsProductCard($p); ?>
        </div>
        <?php else: ?>
        <div class="no-results"><div class="no-results-icon"><?= icon('package') ?></div><p>محصولی برای همین برند<?= $modelName !== '' ? ' — ' . h($modelName) : '' ?><?= $year ? ' و سال ' . $year : '' ?> در این دسته یافت نشد.</p></div>
        <?php endif; ?>
    <?php endif; ?>

<?php else: ?>
    <?php
    /* --- صفحهٔ زیرشاخه: چیپس هم‌گروه‌ها + مرحلهٔ برند/سال + محصولات --- */
    $pRow = $pdo->prepare("SELECT * FROM part_categories WHERE id = ?");
    $pRow->execute([(int)$current['parent_id']]);
    $parentRow = $pRow->fetch();

    $sib = $pdo->prepare("SELECT * FROM part_categories WHERE parent_id = ? ORDER BY sort_order, name");
    $sib->execute([(int)$current['parent_id']]);
    $siblings = $sib->fetchAll();

    $baseQs = 'cat=' . (int)$current['id'];
    $products = [];
    if ($brandId) {
        $pf = ['part_category_id' => (int)$current['id'], 'brand_id' => $brandId];
        if ($modelId) $pf['category_id'] = $modelId;
        if ($year) $pf['year'] = $year;
        $products = getProducts($pf);
        if (!$products) {
            /* اگر محصولی مستقیما به این زیرشاخه وصل نشده بود، جست‌وجوی نامی
               جایگزین می‌شود — فیلتر سال هم اینجا اعمال می‌شود (خواستهٔ کاربر:
               نتیجه با انتخاب سال نباید ناگهان خالی شود، چون هر دو مسیر باید
               یک قاعدهٔ یکسان داشته باشند). فیلترِ مدل عمداً در این مسیرِ
               جایگزین (جست‌وجوی نامی) نمی‌آید — «category_id» روی جدولِ
               product_categories کار می‌کند، نه روی نتیجهٔ LIKE. */
            $pf2 = ['search' => $current['name'], 'brand_id' => $brandId];
            if ($year) $pf2['year'] = $year;
            $products = getProducts($pf2);
        }
    }
    ?>
    <div class="parts-crumb">
        <a href="parts.php">دسته‌بندی قطعات</a>
        <?php if ($parentRow): ?> &laquo; <a href="parts.php?cat=<?= $parentRow['id'] ?>"><?= h($parentRow['name']) ?></a><?php endif; ?>
        &laquo; <?= h($current['name']) ?>
    </div>
    <h1 class="page-title"><?= h($current['name']) ?></h1>

    <?php if ($siblings): ?>
    <div class="subcat-filter">
        <?php foreach ($siblings as $s): ?>
        <a href="parts.php?cat=<?= $s['id'] ?><?= $brandId ? '&brand=' . $brandId : '' ?><?= $modelId ? '&model=' . $modelId : '' ?><?= $year ? '&year=' . $year : '' ?>" class="brand-tag <?= $s['id'] == $current['id'] ? 'active' : '' ?>"><?= h($s['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php renderBrandYearStep($allBrands, $selectedBrand, $brandId, $modelId, $subModels, $year, $yearOptions, $baseQs); ?>

    <?php if ($brandId): ?>
        <?php if ($products): ?>
        <div class="search-results-count" style="margin-bottom:1rem;"><?= count($products) ?> محصول</div>
        <div class="product-grid">
            <?php foreach ($products as $p) echo partsProductCard($p); ?>
        </div>
        <?php else: ?>
        <div class="no-results"><div class="no-results-icon"><?= icon('package') ?></div><p>محصولی برای همین برند<?= $modelName !== '' ? ' — ' . h($modelName) : '' ?><?= $year ? ' و سال ' . $year : '' ?> در این دسته یافت نشد.</p></div>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
