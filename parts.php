<?php
require_once 'includes/header.php';

/* ==========================================================================
   صفحهٔ دسته‌بندی قطعات (part_categories).
     parts.php               → فهرست همهٔ سرشاخه‌ها
     parts.php?cat=<سرشاخه>  → زیرشاخه‌ها به‌صورت مرتب + محصولات همان دسته
     parts.php?cat=<زیرشاخه> → محصولات آن زیرشاخه (+ چیپس زیرشاخه‌های هم‌گروه)
   ========================================================================== */

$catId = (int)($_GET['cat'] ?? 0);

$current = null;
if ($catId) {
    $st = $pdo->prepare("SELECT * FROM part_categories WHERE id = ?");
    $st->execute([$catId]);
    $current = $st->fetch();
}

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
?>

<div class="container">

<?php if (!$current): ?>
    <?php /* --- فهرست سرشاخه‌ها --- */ ?>
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
    /* --- صفحهٔ سرشاخه: زیرشاخه‌ها + محصولات --- */
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
    $products = getProducts(['part_category_ids' => $ids]);
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

    <?php if ($products): ?>
    <div class="parts-section-title">محصولات این دسته (<?= count($products) ?>)</div>
    <div class="product-grid">
        <?php foreach ($products as $p) echo partsProductCard($p); ?>
    </div>
    <?php elseif (!$children): ?>
    <div class="no-results"><div class="no-results-icon"><?= icon('package') ?></div><p>محصولی در این دسته یافت نشد.</p></div>
    <?php endif; ?>

<?php else: ?>
    <?php
    /* --- صفحهٔ زیرشاخه: محصولات + چیپس هم‌گروه‌ها --- */
    $pRow = $pdo->prepare("SELECT * FROM part_categories WHERE id = ?");
    $pRow->execute([(int)$current['parent_id']]);
    $parentRow = $pRow->fetch();

    $sib = $pdo->prepare("SELECT * FROM part_categories WHERE parent_id = ? ORDER BY sort_order, name");
    $sib->execute([(int)$current['parent_id']]);
    $siblings = $sib->fetchAll();

    $products = getProducts(['part_category_id' => (int)$current['id']]);
    if (!$products) {
        // اگر محصولی مستقیما به این زیرشاخه وصل نشده بود، جست‌وجوی نامی به‌عنوان جایگزین
        $products = getProducts(['search' => $current['name']]);
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
        <a href="parts.php?cat=<?= $s['id'] ?>" class="brand-tag <?= $s['id'] == $current['id'] ? 'active' : '' ?>"><?= h($s['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($products): ?>
    <div class="search-results-count" style="margin-bottom:1rem;"><?= count($products) ?> محصول</div>
    <div class="product-grid">
        <?php foreach ($products as $p) echo partsProductCard($p); ?>
    </div>
    <?php else: ?>
    <div class="no-results"><div class="no-results-icon"><?= icon('package') ?></div><p>محصولی در این دسته یافت نشد.</p></div>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
