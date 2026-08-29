<?php
require_once 'includes/header.php';

$brands = getAllBrands();
$filters = [];
$selectedBrandId = 0;

if (!empty($_GET['brand'])) {
    $selectedBrandId = (int)$_GET['brand'];
    $filters['brand_id'] = $selectedBrandId;
}

$products = getProducts($filters);

$selectedBrandName = '';
$subcats = [];
if ($selectedBrandId) {
    foreach ($brands as $b) {
        if ($b['id'] == $selectedBrandId) { $selectedBrandName = $b['name']; break; }
    }
    $subcats = getSubCategories($selectedBrandId);
}
?>

<div class="container">
    <h1 class="page-title">قطعات خودرو</h1>

    <div class="brand-stage" id="brandStage">
        <div class="brand-tags-wrap ben-<?= h(brandEnMode()) ?> <?= $selectedBrandId ? 'is-faded' : '' ?>" id="brandTagsWrap">
            <?php foreach ($brands as $i => $brand): ?>
            <a href="shop.php?brand=<?= $brand['id'] ?>"
               class="brand-tag <?= $selectedBrandId == $brand['id'] ? 'active is-sticky' : '' ?>"
               data-id="<?= $brand['id'] ?>"
               data-index="<?= $i ?>">
                <span class="brand-fa"><?= h($brand['name']) ?></span>
                <span class="brand-en"><?= h(str_replace('-', ' ', $brand['slug'])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="models-reveal <?= $selectedBrandId ? 'is-open' : '' ?>" id="modelsReveal">
            <div class="models-head">
                <button class="brand-tag brand-tag--back" id="backBtn">&#8592; همه برندها</button>
                <span class="brand-tag active models-title" id="modelsTitle"><?= h($selectedBrandName) ?></span>
            </div>
            <div class="models-list" id="modelsList">
                <?php foreach ($subcats as $sc): ?>
                <a href="category.php?cat=<?= $sc['id'] ?>" class="brand-tag brand-tag--model">
                    <?= h($sc['name']) ?>
                </a>
                <?php endforeach; ?>
                <?php if ($selectedBrandId && empty($subcats)): ?>
                <span style="color:var(--text-muted);font-size:0.85rem;">مدلی برای این برند تعریف نشده است.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($products): ?>
    <div class="product-grid">
        <?php foreach ($products as $p): ?>
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
                <div class="product-card-stock">
                    <?= $p['stock'] > 0 ? 'موجود در انبار' : 'ناموجود' ?>
                </div>
                <div class="product-card-actions">
                    <a href="product.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">مشاهده</a>
                    <?php if ($p['stock'] > 0): ?>
                    <button class="btn btn-primary btn-sm add-to-cart-btn" data-id="<?= $p['id'] ?>">افزودن به سبد</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="no-results">
        <div class="no-results-icon"><?= icon('search') ?></div>
        <p>محصولی یافت نشد.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
