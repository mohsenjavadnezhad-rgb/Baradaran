<?php
require_once 'includes/header.php';

/* ==========================================================================
   صفحهٔ دسته‌بندی قطعات (part_categories).
     parts.php               → فهرست همهٔ سرشاخه‌ها
     parts.php?cat=<سرشاخه>  → زیرشاخه‌ها + همین‌جا مرحلهٔ برند/سال، سپس محصولات
     parts.php?cat=<زیرشاخه> → همین مرحلهٔ برند/سال برای محصولات آن زیرشاخه
   ---------------------------------------------------------------------
   مرحلهٔ برند/سال (خواستهٔ کاربر): بعد از انتخاب یک دستهٔ قطعه، اول باید
   برند خودرو انتخاب شود (کاشی‌های بزرگ)؛ با انتخاب برند، یک انتخابگر
   بزرگ «سال تولید» کنارش ظاهر می‌شود. سال تولید اختیاری است — همان لحظه که
   برند انتخاب شده (بدون انتخاب سال) همهٔ محصولات آن برند دیده می‌شوند؛
   دکمهٔ «نمایش همهٔ سال‌ها» هم برای برگشتن از یک سال انتخاب‌شده به همین
   حالت است. محصولی که سالی برایش ثبت نشده («از/تا» خالی)، برای همهٔ
   سال‌ها مناسب حساب می‌شود (productYearReady()/getProducts() را ببینید).
   ========================================================================== */

$catId   = (int)($_GET['cat'] ?? 0);
$brandId = (int)($_GET['brand'] ?? 0);
$year    = (int)($_GET['year'] ?? 0);

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

$yearOptions = productYearReady() ? productYearOptions() : [];
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
   که در همهٔ لینک‌های این بخش باید بماند. */
function renderBrandYearStep($allBrands, $selectedBrand, $brandId, $year, $yearOptions, $baseQs) {
    ?>
    <div class="pby-box">
        <?php if (!$selectedBrand): ?>
        <div class="pby-title"><?= icon('cog', 'ic-sm') ?> اول برند خودروتان را انتخاب کنید</div>
        <div class="pby-brands">
            <?php foreach ($allBrands as $b):
                $logoFile = 'assets/images/brands/' . $b['slug'] . '.svg';
                $logoSrc = file_exists(__DIR__ . '/' . $logoFile) ? $logoFile : '';
            ?>
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
                <?php $logoFile = 'assets/images/brands/' . $selectedBrand['slug'] . '.svg';
                      $logoSrc = file_exists(__DIR__ . '/' . $logoFile) ? $logoFile : ''; ?>
                <?php if ($logoSrc): ?><img src="<?= $logoSrc ?>" alt="" class="pby-brand-logo"><?php else: ?><?= icon('cog') ?><?php endif; ?>
                <b><?= h($selectedBrand['name']) ?></b>
                <a href="parts.php?<?= $baseQs ?>" class="pby-change"><?= icon('refresh', 'ic-sm') ?> تغییر برند</a>
            </div>

            <?php /* انتخابگر سال تولید — «کلید بزرگ و زیبا» (خواستهٔ کاربر):
                    select بزرگ‌رنگی که با تغییر خودش ارسال می‌شود (progressive
                    enhancement با جاوااسکریپت پایین)، به‌همراه دکمهٔ صریح
                    «اعمال» برای حالت بدون جاوااسکریپت. */ ?>
            <?php if ($yearOptions): ?>
            <form method="GET" action="parts.php" class="pby-yearform" id="pbyYearForm">
                <?php foreach (explode('&', $baseQs) as $kv): if ($kv === '') continue; list($k, $v) = array_pad(explode('=', $kv, 2), 2, ''); ?>
                <input type="hidden" name="<?= h($k) ?>" value="<?= h(urldecode($v)) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="brand" value="<?= (int)$brandId ?>">
                <label for="pbyYearSel" class="pby-yearlabel"><?= icon('calendar', 'ic-sm') ?> سال تولید</label>
                <select name="year" id="pbyYearSel" class="pby-yearselect">
                    <option value="0" <?= $year === 0 ? 'selected' : '' ?>>همهٔ سال‌ها</option>
                    <?php foreach ($yearOptions as $y): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary pby-yearbtn"><?= icon('check', 'ic-sm') ?> اعمال</button>
                <?php if ($year): ?>
                <a href="parts.php?<?= $baseQs ?>&brand=<?= (int)$brandId ?>" class="pby-allyears"><?= icon('layers', 'ic-sm') ?> نمایش همهٔ سال‌ها</a>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
    (function(){
        var sel = document.getElementById('pbyYearSel');
        var frm = document.getElementById('pbyYearForm');
        if (sel && frm) sel.addEventListener('change', function(){ frm.submit(); });
    })();
    </script>
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

    <?php renderBrandYearStep($allBrands, $selectedBrand, $brandId, $year, $yearOptions, $baseQs); ?>

    <?php if ($brandId): ?>
        <?php if ($products): ?>
        <div class="parts-section-title">محصولات این دسته (<?= count($products) ?>)</div>
        <div class="product-grid">
            <?php foreach ($products as $p) echo partsProductCard($p); ?>
        </div>
        <?php else: ?>
        <div class="no-results"><div class="no-results-icon"><?= icon('package') ?></div><p>محصولی برای همین برند<?= $year ? ' و سال ' . $year : '' ?> در این دسته یافت نشد.</p></div>
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
        if ($year) $pf['year'] = $year;
        $products = getProducts($pf);
        if (!$products && !$year) {
            // اگر محصولی مستقیما به این زیرشاخه وصل نشده بود، جست‌وجوی نامی به‌عنوان جایگزین (فقط بدون فیلتر سال، تا معنایش عوض نشود)
            $pf2 = ['search' => $current['name'], 'brand_id' => $brandId];
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
        <a href="parts.php?cat=<?= $s['id'] ?><?= $brandId ? '&brand=' . $brandId : '' ?><?= $year ? '&year=' . $year : '' ?>" class="brand-tag <?= $s['id'] == $current['id'] ? 'active' : '' ?>"><?= h($s['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php renderBrandYearStep($allBrands, $selectedBrand, $brandId, $year, $yearOptions, $baseQs); ?>

    <?php if ($brandId): ?>
        <?php if ($products): ?>
        <div class="search-results-count" style="margin-bottom:1rem;"><?= count($products) ?> محصول</div>
        <div class="product-grid">
            <?php foreach ($products as $p) echo partsProductCard($p); ?>
        </div>
        <?php else: ?>
        <div class="no-results"><div class="no-results-icon"><?= icon('package') ?></div><p>محصولی برای همین برند<?= $year ? ' و سال ' . $year : '' ?> در این دسته یافت نشد.</p></div>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
