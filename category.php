<?php
require_once 'includes/header.php';

$catId = (int)($_GET['cat'] ?? 0);
$brandId = (int)($_GET['brand'] ?? 0);

// When only cat is given, derive brand from parent_id
if ($catId && !$brandId) {
    $stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
    $stmt->execute([$catId]);
    $parent = $stmt->fetch();
    if ($parent && $parent['parent_id']) {
        $brandId = (int)$parent['parent_id'];
    }
}

$filters = [];
$title = 'دسته‌بندی محصولات';
$subcats = [];
$brands = getAllBrands();

if ($catId) {
    $filters['category_id'] = $catId;
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$catId]);
    $cat = $stmt->fetch();
    if ($cat) $title = $cat['name'];
}

if ($brandId) {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$brandId]);
    $brand = $stmt->fetch();
    $subcats = getSubCategories($brandId);
}

$products = getProducts($filters);
?>

<div class="container">
    <?php /* خواستهٔ کاربر (۲۰۲۶-۰۹-۰۵): «وقتی مدل خودرو رو انتخاب می‌کنم کلید
            همه برندها رو هم بذار که بتونم برگردم» — این صفحه (بعد از انتخاب
            یک مدل خاص) تا اینجا هیچ راهی به فهرست کامل برندهای shop.php
            نداشت، فقط بین مدل‌های همان برند (subcat-filter پایین‌تر) جابه‌جا
            می‌شد. همان کلاس بصری دکمهٔ «همه برندها»ی shop.php این‌جا هم
            استفاده شد تا هم‌شکل باشند. */ ?>
    <?php if ($brandId): ?>
    <div style="margin-bottom:1rem;">
        <a href="shop.php" class="brand-tag brand-tag--back">&#8592; همه برندها</a>
    </div>
    <?php endif; ?>

    <div class="category-header">
        <h1 class="page-title" style="margin-bottom:0;"><?= h($title) ?></h1>
    </div>

    <?php if ($subcats): ?>
    <div class="subcat-filter">
        <?php foreach ($subcats as $sc): ?>
        <a href="category.php?cat=<?= $sc['id'] ?>" class="brand-tag <?= $catId == $sc['id'] ? 'active' : '' ?>">
            <?= h($sc['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($products): ?>
    <div class="search-results-count" style="margin-bottom:1rem;"><?= count($products) ?> محصول</div>
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
        <div class="no-results-icon"><?= icon('package') ?></div>
        <p>محصولی در این دسته‌بندی یافت نشد.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>