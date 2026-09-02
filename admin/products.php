<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$search = $_GET['q'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * ITEMS_PER_PAGE;

$specialOn = specialSaleReady();
/* کلید «آفر» کنار هر محصول — میانبر به فرم «آفر جدید» در
   admin/banners.php#offers با همین محصول از پیش انتخاب‌شده. */
$offersOn  = timedOffersReady();

/* بازگشت به همان صفحه/جستجو (PRG) */
function productsSelfUrl($search, $page) {
    $qs = [];
    if ($search !== '') $qs['q'] = $search;
    if ($page > 1)      $qs['page'] = $page;
    return 'products.php' . ($qs ? '?' . http_build_query($qs) : '');
}

/* تیک «فروش ویژه» — بنر «تخفیف ویژه» در ردیف زیر بنر اصلی از جدیدترین محصول تیک‌خورده ساخته می‌شود */
if ($specialOn && isset($_GET['special'])) {
    try {
        $pdo->prepare("UPDATE products SET is_special = 1 - is_special WHERE id = ?")
            ->execute([(int)$_GET['special']]);
    } catch (Throwable $e) { /* بی‌صدا — فهرست همچنان نمایش داده می‌شود */ }
    header('Location: ' . productsSelfUrl($search, $page));
    exit;
}

$sql = "FROM products WHERE is_active = 1";
$params = [];
if ($search) { $sql .= " AND (name LIKE ? OR technical_number LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$total = $pdo->query("SELECT COUNT(*) $sql")->fetchColumn();
$totalPages = ceil($total / ITEMS_PER_PAGE);

$stmt = $pdo->prepare("SELECT * $sql ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([...$params, ITEMS_PER_PAGE, $offset]);
$products = $stmt->fetchAll();

/* پایهٔ لینک توگل (با حفظ جستجو و شمارهٔ صفحه) — آماده برای چسباندن special=<id> */
$selfUrl = productsSelfUrl($search, $page);
$spBase  = $selfUrl . (strpos($selfUrl, '?') !== false ? '&' : '?');

require_once __DIR__ . '/layout-top.php';
?>

<div class="dash-cards" style="margin-bottom:1rem;">
  <div class="dc dc-red" style="padding:0.75rem;"><div class="dc-val" style="font-size:1.2rem;"><?= $total ?></div><div class="dc-lbl">محصول</div></div>
</div>

<form method="GET" style="display:flex;gap:0.5rem;margin-bottom:1rem;">
  <input type="text" name="q" value="<?= h($search) ?>" placeholder="جستجو در محصولات..." class="search-input" style="max-width:400px;">
  <button class="btn btn-primary btn-sm">جستجو</button>
  <?php if ($search): ?><a href="products.php" class="btn btn-secondary btn-sm">حذف فیلتر</a><?php endif; ?>
  <a href="product-edit.php" class="btn btn-primary btn-sm" style="margin-right:auto;">+ محصول جدید</a>
</form>

<?php if ($specialOn): ?>
<div class="flash" style="background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.3);color:var(--text-secondary);font-size:0.8rem;line-height:1.9;padding:0.6rem 0.8rem;border-radius:8px;margin-bottom:1rem;">
  <?= icon('flame', 'ic-sm') ?>
  با زدن تیک <b>فروش ویژه</b> روی یک محصول، بنر «تخفیف ویژه» در ردیف <b>زیر بنر اصلی</b> صفحهٔ اصلی
  به‌صورت خودکار از تصویر، نام و قیمت تخفیف‌دار همان محصول ساخته می‌شود.
  اگر چند محصول تیک داشته باشند، <b>جدیدترین</b> آن‌ها نمایش داده می‌شود.
</div>
<?php endif; ?>

<table class="admin-table">
<thead><tr>
  <th>#</th><th>تصویر</th><th>نام</th><th>شماره فنی</th><th>قیمت جزئی</th><th>قیمت کلی</th><th>موجودی</th><?php if ($specialOn): ?><th>فروش ویژه</th><?php endif; ?><?php if ($offersOn): ?><th>آفر</th><?php endif; ?><th>عملیات</th>
</tr></thead>
<tbody>
<?php foreach ($products as $p): ?>
<tr>
  <td><?= $p['id'] ?></td>
  <td><div style="width:40px;height:40px;background:var(--bg-input);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--text-muted);overflow:hidden;"><?= $p['image'] ? '<img src="../uploads/products/'.h($p['image']).'" style="width:100%;height:100%;object-fit:cover;">' : icon('cog', 'ic-lg') ?></div></td>
  <td><a href="product-edit.php?id=<?= $p['id'] ?>" style="color:var(--text-primary);"><?= h($p['name']) ?></a></td>
  <td><code style="font-size:0.75rem;"><?= h($p['technical_number']) ?></code></td>
  <td><?= formatPrice($p['retail_price']) ?></td>
  <td><?= formatPrice($p['wholesale_price']) ?></td>
  <td><?= $p['stock'] ?></td>
  <?php if ($specialOn): $isSp = !empty($p['is_special']); ?>
  <td>
    <a href="<?= h($spBase) ?>special=<?= $p['id'] ?>"
       class="btn btn-sm <?= $isSp ? 'btn-primary' : 'btn-secondary' ?>"
       title="<?= $isSp ? 'برداشتن از فروش ویژه' : 'قرار دادن در فروش ویژه' ?>">
      <?= icon($isSp ? 'flame' : 'tag', 'ic-sm') ?> <?= $isSp ? 'ویژه' : 'عادی' ?>
    </a>
  </td>
  <?php endif; ?>
  <?php if ($offersOn): ?>
  <td>
    <a href="banners.php?ofor=<?= $p['id'] ?>#offers" class="btn btn-secondary btn-sm" title="ساخت بنر آفر زمان‌دار برای این محصول">
      <?= icon('clock', 'ic-sm') ?> آفر
    </a>
  </td>
  <?php endif; ?>
  <td>
    <a href="product-edit.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">ویرایش</a>
    <a href="product-edit.php?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('حذف؟')">حذف</a>
  </td>
</tr>
<?php endforeach; ?>
<?php if (!$products): ?><tr><td colspan="<?= 8 + ($specialOn ? 1 : 0) + ($offersOn ? 1 : 0) ?>" style="text-align:center;padding:2rem;color:var(--text-muted);">محصولی یافت نشد</td></tr><?php endif; ?>
</tbody>
</table>

<?php if ($totalPages > 1): $maxShow = 10; $start = max(1, $page - floor($maxShow/2)); $end = min($totalPages, $start + $maxShow - 1); $start = max(1, $end - $maxShow + 1); ?>
<div class="pagination">
  <?php if ($start > 1): ?><a href="?<?= $search?"q=".urlencode($search).'&':'' ?>page=1">1</a><span>...</span><?php endif; ?>
  <?php for ($i=$start; $i<=$end; ++$i): ?>
  <?php if ($i==$page): ?><span class="current"><?=$i?></span><?php else: ?><a href="?<?= $search?"q=".urlencode($search).'&':'' ?>page=<?=$i?>"><?=$i?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($end < $totalPages): ?><span>...</span><a href="?<?= $search?"q=".urlencode($search).'&':'' ?>page=<?=$totalPages?>"><?=$totalPages?></a><?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php';