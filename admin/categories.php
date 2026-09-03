<?php
/* برندها و مدل‌ها (جدول categories، دو سطحی: parent_id NULL = برند).
   بازطراحی 2026-08-26: قبلا یک جدول تخت با همه برند+مدل‌ها ردیف‌به‌ردیف
   بود که با ده‌ها برند خیلی طولانی می‌شد (خواستهٔ کاربر: «طراحیشو تغییر بده»)
   و صفحهٔ مستقل خودش را داشت (نه layout-top.php)، پس با بقیهٔ پنل هم‌شکل
   نبود. حالا مثل admin/part-categories.php گروه‌بندی‌شده و جمع‌شونده است. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$msg = '';
$msgErr = false;

/* نمایش نام انگلیسی برند (اسلاگ) روی تگ‌های shop.php — [[batch9-fixes]] */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brand_en'])) {
    $mode = in_array($_POST['brand_en_mode'] ?? '', ['off', 'hover', 'always'], true) ? $_POST['brand_en_mode'] : 'off';
    setSetting('brand_en_mode', $mode);
    $msg = 'تنظیم نمایش نام انگلیسی ذخیره شد.';
}

/* لوگوی برند — فقط وقتی خود ردیف یک برند است (parent_id خالی)، چون در
   سایت فقط سطح برند لوگو نشان می‌دهد (header.php/parts.php را ببینید). */
function saveBrandLogoUpload($editId) {
    global $pdo;
    if (empty($_FILES['logo']['name']) || ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return;
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) return;
    if ($_FILES['logo']['size'] > 1024 * 1024) return; // ۱ مگابایت کافی است، لوگو کوچک است
    $dir = __DIR__ . '/../uploads/brands/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    /* هر فرمتی که آپلود شود (خواستهٔ کاربر: «خودت مرتبش کن») — به‌جز SVG
       (وکتور است، پردازشِ تصویرِ خام رویش معنا ندارد) — به یک PNGِ شفاف
       و trim‌شده تبدیل می‌شود؛ نمایشِ سایت فقط رویِ پس‌زمینهٔ شفاف درست کار
       می‌کند. اگر پردازش شکست بخورد (Imagick نبود، یا فایل غیرمنتظره)،
       خودِ فایلِ خام ذخیره می‌شود — بهتر از هیچ لوگو. */
    $finalExt = $ext;
    $saved = false;
    if ($ext !== 'svg' && extension_loaded('imagick')) {
        $normTmp = tempnam(sys_get_temp_dir(), 'logo');
        if (normalizeBrandLogo($_FILES['logo']['tmp_name'], $normTmp)) {
            $newName = 'brand' . (int)$editId . '_' . time() . '.png';
            $saved = @rename($normTmp, $dir . $newName);
            if (!$saved) { $saved = @copy($normTmp, $dir . $newName); @unlink($normTmp); }
            if ($saved) $finalExt = 'png';
        } else {
            @unlink($normTmp);
        }
    }

    if (!$saved) {
        $newName = 'brand' . (int)$editId . '_' . time() . '.' . $ext;
        $saved = move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $newName);
    }

    if ($saved) {
        /* tempnam()+rename() (مسیرِ نرمال‌سازی بالا) فایل را 0600 می‌سازد —
           فقطِ خودِ PHP می‌خواندش، nginx برایِ سرو کردنِ مستقیمِ فایل به آن
           دسترسی ندارد و 403 می‌دهد (باگی که واقعاً پیش آمد، همین‌جا رفع
           شد). move_uploaded_file() معمولاً 0644 می‌دهد، اما chmod این‌جا
           هم بی‌ضرر است. */
        @chmod($dir . $newName, 0644);
        $old = $pdo->prepare("SELECT logo FROM categories WHERE id = ?");
        $old->execute([$editId]);
        $oldFile = $old->fetchColumn();
        $pdo->prepare("UPDATE categories SET logo = ? WHERE id = ?")->execute([$newName, $editId]);
        if ($oldFile && $oldFile !== $newName && is_file($dir . $oldFile)) @unlink($dir . $oldFile);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $name = trim($_POST['name'] ?? '');
    $parentId = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $editId = (int)($_POST['edit_id'] ?? 0);
    $slug = slugify($name);

    if ($name !== '') {
        if ($editId > 0) {
            $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, parent_id=? WHERE id=?");
            $stmt->execute([$name, $slug, $parentId, $editId]);
            $msg = 'دسته‌بندی به‌روزرسانی شد.';
            if ($parentId === null && categoryLogoReady()) saveBrandLogoUpload($editId);
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $slug, $parentId]);
            $msg = 'دسته‌بندی اضافه شد.';
            if ($parentId === null && categoryLogoReady()) saveBrandLogoUpload((int)$pdo->lastInsertId());
        }
    }
}

/* حذف خود لوگو (برگشتن به آیکون عمومی یا فایل SVG قدیمی، اگر باشد) */
if (isset($_GET['dellogo']) && categoryLogoReady()) {
    $dlId = (int)$_GET['dellogo'];
    $old = $pdo->prepare("SELECT logo FROM categories WHERE id = ?");
    $old->execute([$dlId]);
    $oldFile = $old->fetchColumn();
    if ($oldFile) {
        $pdo->prepare("UPDATE categories SET logo = NULL WHERE id = ?")->execute([$dlId]);
        $path = __DIR__ . '/../uploads/brands/' . $oldFile;
        if (is_file($path)) @unlink($path);
        $msg = 'لوگو حذف شد.';
    }
    redirect('categories.php?edit=' . $dlId);
}

/* حذف — اگر برندی هنوز مدل دارد رد می‌شود (وگرنه مدل‌ها یتیم می‌مانند)،
   هم‌الگوی گارد part-categories.php ([[part-categories-admin-crud]]). */
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $chk = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id=?");
    $chk->execute([$delId]);
    if ((int)$chk->fetchColumn() > 0) {
        $msg = 'این برند هنوز مدل دارد؛ ابتدا مدل‌هایش را حذف یا به برند دیگری منتقل کنید.';
        $msgErr = true;
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$delId]);
        $msg = 'دسته‌بندی حذف شد.';
    }
}

/* حذفِ دسته‌جمعیِ همهٔ مدل‌های یک برند (خواستهٔ کاربر) — خودِ برند دست‌نخورده
   می‌ماند، فقط زیرمجموعه‌هایش پاک می‌شوند؛ ارتباطشان با محصولات هم پاک
   می‌شود (همان قاعدهٔ حذفِ تک‌مدل بالا، فقط یک‌جا برایِ همه). */
if (isset($_GET['delete_all_models'])) {
    $brandId = (int)$_GET['delete_all_models'];
    $mStmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id=?");
    $mStmt->execute([$brandId]);
    $modelIds = array_column($mStmt->fetchAll(), 'id');
    if ($modelIds) {
        $in = implode(',', array_map('intval', $modelIds));
        $pdo->exec("DELETE FROM product_categories WHERE category_id IN ($in)");
        $pdo->exec("DELETE FROM categories WHERE id IN ($in)");
        $msg = count($modelIds) . ' مدل حذف شد.';
    } else {
        $msg = 'این برند مدلی برای حذف نداشت.';
        $msgErr = true;
    }
}

$brands = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE category_id = ?");
$childStmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");

$tree = [];
foreach ($brands as $b) {
    $countStmt->execute([$b['id']]);
    $ownCount = (int)$countStmt->fetchColumn();
    $childStmt->execute([$b['id']]);
    $children = [];
    foreach ($childStmt->fetchAll() as $c) {
        $countStmt->execute([$c['id']]);
        $children[] = ['row' => $c, 'count' => (int)$countStmt->fetchColumn()];
    }
    $tree[] = ['row' => $b, 'own' => $ownCount, 'children' => $children];
}

$editCat = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCat = $stmt->fetch();
}
$newModelParent = 0;
if (!$editCat && isset($_GET['new_model'])) $newModelParent = (int)$_GET['new_model'];

require_once __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="flash <?= $msgErr ? 'flash-error' : 'flash-success' ?>" style="margin:0 0 1rem;"><?= h($msg) ?></div><?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
    <h2 style="font-size:1rem;color:var(--text-primary);"><?= icon('layers', 'ic-sm') ?> برندها و مدل‌ها</h2>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <?php /* ۲۰۲۶-۰۹-۰۳: فرم از قبل «برند جدید» را پشتیبانی می‌کرد (برند
                مادر خالی = برندِ تازه) اما هیچ دکمه‌ای به آن اشاره نمی‌کرد —
                فقط با بازکردنِ categories.php بدونِ هیچ کوئری‌ای دیده می‌شد.
                همان «+ مدل» کنارِ هر برند، این‌جا برایِ خودِ برند. */ ?>
        <a href="categories.php" class="btn btn-primary btn-sm"><?= icon('plus', 'ic-sm') ?> برند جدید</a>
        <a href="categories-report.php" target="_blank" class="btn btn-secondary btn-sm"><?= icon('printer', 'ic-sm') ?> چاپ / خروجی اکسل</a>
    </div>
</div>

<?php $benMode = brandEnMode(); ?>
<div class="dg-box" style="padding:0.85rem 1rem;margin-bottom:1rem;display:flex;align-items:center;flex-wrap:wrap;gap:0.6rem;">
    <span style="font-size:0.82rem;color:var(--text-secondary);"><?= icon('globe', 'ic-sm') ?> نمایش نام انگلیسی برند روی دکمه‌های صفحهٔ «قطعات خودرو»</span>
    <form method="POST" style="display:flex;align-items:center;gap:0.4rem;">
        <input type="hidden" name="save_brand_en" value="1">
        <select name="brand_en_mode" class="form-control" style="width:auto;font-size:0.8rem;padding:0.4rem 0.7rem;" onchange="this.form.submit()">
            <option value="off"    <?= $benMode === 'off'    ? 'selected' : '' ?>>نشان نده (پیش‌فرض)</option>
            <option value="hover"  <?= $benMode === 'hover'  ? 'selected' : '' ?>>فقط با نگه‌داشتن موس رویش</option>
            <option value="always" <?= $benMode === 'always' ? 'selected' : '' ?>>همیشه به‌جای فارسی نشان بده</option>
        </select>
        <noscript><button type="submit" class="btn btn-secondary btn-sm">ذخیره</button></noscript>
    </form>
    <span style="font-size:0.72rem;color:var(--text-muted);">حروف اول کلمات انگلیسی خودکار بزرگ نوشته می‌شود.</span>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:1rem;">
    <div>
        <div class="dg-box">
            <div class="dg-box-hd"><h3>
                <?php if ($editCat): ?>ویرایش «<?= h($editCat['name']) ?>»
                <?php elseif ($newModelParent): ?>مدل جدید — <?php $npRow = null; foreach ($brands as $b) { if ((int)$b['id'] === $newModelParent) { $npRow = $b; break; } } ?>«<?= h($npRow['name'] ?? '') ?>»
                <?php else: ?>برند یا مدل جدید
                <?php endif; ?>
            </h3></div>
            <div class="dg-box-bd" style="padding:1rem;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="edit_id" value="<?= $editCat['id'] ?? 0 ?>">
                    <div class="form-group">
                        <label for="name">نام</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?= h($editCat['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="parent_id">برند مادر</label>
                        <select name="parent_id" id="parent_id" class="form-control">
                            <option value="">-- برند اصلی (بدون والد) --</option>
                            <?php foreach ($brands as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ($editCat['parent_id'] ?? $newModelParent) == $b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">خالی بگذارید تا یک برند تازه بسازید؛ یک برند انتخاب کنید تا مدل همان برند شود.</small>
                    </div>

                    <?php /* لوگو فقط برای خود برند معنا دارد (نه مدل) — چون در سایت
                            فقط سطح برند لوگو نشان می‌دهد. فقط در حالت ویرایش یک
                            برند موجود نشان داده می‌شود (نه هنگام ساختن تازه)،
                            چون تا ردیف ساخته نشود جایی برای ذخیرهٔ فایل نیست. */ ?>
                    <?php if (categoryLogoReady() && $editCat && empty($editCat['parent_id'])): ?>
                    <div class="form-group">
                        <label for="logo">لوگوی برند</label>
                        <?php $curLogoSrc = brandLogoSrc($editCat); ?>
                        <?php if ($curLogoSrc): ?>
                        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem;">
                            <img src="../<?= h($curLogoSrc) ?>" alt="" style="width:44px;height:44px;object-fit:contain;background:var(--bg-input);border-radius:6px;padding:4px;">
                            <?php if (!empty($editCat['logo'])): ?>
                            <a href="?edit=<?= (int)$editCat['id'] ?>&dellogo=<?= (int)$editCat['id'] ?>" class="btn btn-danger btn-sm" data-confirm="لوگوی آپلودی حذف شود؟">حذف لوگو</a>
                            <?php else: ?>
                            <span style="font-size:0.7rem;color:var(--text-muted);">فایل قدیمی سایت (assets/images/brands) — نه از همین‌جا آپلودشده</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="logo" id="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                        <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">SVG، PNG، JPG یا WebP — تا ۱ مگابایت. لوگوی تازه، لوگوی قبلی را جایگزین می‌کند.</small>
                    </div>
                    <?php endif; ?>

                    <button type="submit" name="save_category" class="btn btn-primary btn-block"><?= $editCat ? 'به‌روزرسانی' : 'افزودن' ?></button>
                    <?php if ($editCat || $newModelParent): ?><a href="categories.php" class="btn btn-secondary btn-block" style="margin-top:0.5rem;">انصراف</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="dg-box">
        <div class="dg-box-hd"><h3>برندها (<?= count($tree) ?>)</h3></div>
        <div class="dg-box-bd" style="padding:0;">
            <?php /* ۲۰۲۶-۰۹-۰۳: همه بسته شروع می‌شوند (خواستهٔ کاربر: «به‌صورت
                    پیش‌فرض منوهاشو بسته نگه دار تا خودم باز کنم») — قبلاً
                    برندهایِ ≤۸ مدل خودکار باز بودند. */ ?>
            <?php foreach ($tree as $t): $bid = 'catbr-' . (int)$t['row']['id']; ?>
            <div class="pset-row">
                <input type="checkbox" id="<?= $bid ?>" class="pset-toggle" hidden>
                <div class="pset-sum-wrap">
                    <label for="<?= $bid ?>" class="pset-sum">
                        <span class="pset-name">
                            <?php $brLogo = brandLogoSrc($t['row']); ?>
                            <?php if ($brLogo): ?>
                            <img src="../<?= h($brLogo) ?>" alt="" style="width:22px;height:22px;object-fit:contain;">
                            <?php else: ?>
                            <?= icon('layers', 'ic-sm') ?>
                            <?php endif; ?>
                            <b><?= h($t['row']['name']) ?></b>
                            <span class="pset-count"><?= count($t['children']) ?> مدل · <?= $t['own'] ?> محصول مستقیم</span>
                        </span>
                        <span class="pset-caret"><?= icon('chevron-down', 'ic-sm') ?></span>
                    </label>
                    <a href="?edit=<?= (int)$t['row']['id'] ?>" class="btn btn-secondary btn-sm">ویرایش برند</a>
                    <a href="?new_model=<?= (int)$t['row']['id'] ?>" class="btn btn-secondary btn-sm">+ مدل</a>
                    <?php /* خواستهٔ کاربر: «تمامِ زیرمجموعه‌های یک برند رو یک‌جا حذف کنم» —
                            حذفِ دسته‌جمعیِ همهٔ مدل‌ها، بدونِ لمس‌کردنِ خودِ برند. */ ?>
                    <?php if ($t['children']): ?>
                    <a href="?delete_all_models=<?= (int)$t['row']['id'] ?>" class="btn btn-danger btn-sm"
                       data-confirm="همهٔ <?= count($t['children']) ?> مدلِ «<?= h($t['row']['name']) ?>» حذف شوند؟ ارتباط آن‌ها با محصولات هم حذف می‌شود.">حذف همهٔ مدل‌ها</a>
                    <?php endif; ?>
                    <a href="?delete=<?= (int)$t['row']['id'] ?>" class="btn btn-danger btn-sm"
                       <?= $t['children'] ? '' : 'data-confirm="این برند حذف شود؟"' ?>>حذف</a>
                </div>

                <div class="pset-body">
                    <?php if (!$t['children']): ?>
                    <p style="color:var(--text-muted);font-size:0.82rem;padding:0 0 0.75rem;">این برند هنوز مدلی ندارد.</p>
                    <?php else: ?>
                    <table class="admin-table" style="margin:0 0 0.75rem;">
                        <thead><tr><th>مدل</th><th style="width:140px;">تعداد محصول</th><th style="width:170px;">عملیات</th></tr></thead>
                        <tbody>
                            <?php foreach ($t['children'] as $c): ?>
                            <tr>
                                <td><?= h($c['row']['name']) ?></td>
                                <td><?= number_format($c['count']) ?></td>
                                <td>
                                    <a href="?edit=<?= (int)$c['row']['id'] ?>" class="btn btn-secondary btn-sm">ویرایش</a>
                                    <a href="?delete=<?= (int)$c['row']['id'] ?>" class="btn btn-danger btn-sm" data-confirm="این مدل حذف شود؟ ارتباط آن با محصولات هم حذف می‌شود.">حذف</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$tree): ?>
            <p style="color:var(--text-muted);font-size:0.85rem;padding:1rem;text-align:center;">هنوز برندی ثبت نشده است.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>
