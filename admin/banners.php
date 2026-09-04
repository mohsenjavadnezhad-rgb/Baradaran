<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$msg = '';

/* آپلود تصویر بنر: در uploads/banners/ ذخیره می‌شود و نام فایل برگردانده می‌شود */
function saveBannerImage($field, $existing) {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return $existing;
    $dir = __DIR__ . '/../uploads/banners/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) return $existing;
    if ($_FILES[$field]['size'] > MAX_UPLOAD_SIZE) return $existing;
    $newName = time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $newName)) {
        if ($existing && file_exists($dir . $existing)) @unlink($dir . $existing);
        return $newName;
    }
    return $existing;
}

/* آیا جدول بنرها ساخته شده است؟ */
$tableExists = false;
try { $pdo->query("SELECT 1 FROM banners LIMIT 1"); $tableExists = true; } catch (Exception $e) { $tableExists = false; }

/* ===================== آفر زمان‌دار (بنر زیر بنر اصلی) =====================
   جدا از جدول banners؛ در timed_offers ذخیره می‌شود. صفحهٔ اصلی همه آفرهای
   فعال را به‌صورت یک اسلایدر خودچرخان با شمارش معکوس نمایش می‌دهد.
   PRG تا رفرش مرورگر آفر تکراری نسازد. */
$offersOn  = timedOffersReady();
$soldOn    = timedOffersSoldReady();   /* ستون is_sold ساخته شده؟ */
$offerErr  = '';
$offers    = [];
$offerProducts = [];
$oEdit     = null;                     /* آفر در حال ویرایش (با ?oedit=) */
$slideSec  = offerSlideSeconds();

$omsgMap = [
    'saved'   => 'آفر زمان‌دار ذخیره شد.',
    'updated' => 'آفر ویرایش شد.',
    'deleted' => 'آفر حذف شد.',
    'toggled' => 'وضعیت آفر تغییر کرد.',
    'sold'    => 'نشان «فروخته شد» تغییر کرد.',
    'slide'   => 'زمان چرخش اسلایدر آفرها ذخیره شد.',
];
if (isset($_GET['omsg']) && isset($omsgMap[$_GET['omsg']])) $msg = $omsgMap[$_GET['omsg']];

if ($offersOn) {
    /* بازهٔ چرخش اسلایدر (ثانیه) — در جدول settings ذخیره می‌شود */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_offer_slide'])) {
        $sec = (int) faToLatinDigits($_POST['offer_slide_seconds'] ?? '6');
        $sec = max(2, min(60, $sec));
        setSetting('offer_slide_seconds', (string)$sec);
        header('Location: banners.php?omsg=slide#offers');
        exit;
    }

    /* یک فرم برای «آفر جدید» و «ویرایش آفر»: با edit_id پرشده، UPDATE می‌شود.
       نکتهٔ مهم در ویرایش: مهلت تنها وقتی از نو حساب می‌شود که ادمین تیک
       «تمدید مهلت» را بزند؛ وگرنه اصلاح عنوان، شمارش معکوس را صفر می‌کرد. */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_offer'])) {
        $eid   = (int)($_POST['edit_id'] ?? 0);
        $pid   = (int)($_POST['product_id'] ?? 0);
        $oTit  = trim($_POST['offer_title'] ?? '');
        $oSub  = trim($_POST['offer_subtitle'] ?? '');
        $days  = max(0, (int)faToLatinDigits($_POST['offer_days'] ?? '0'));
        $hours = max(0, min(23, (int)faToLatinDigits($_POST['offer_hours'] ?? '0')));
        $sort  = (int)($_POST['offer_sort'] ?? 0);
        $act   = isset($_POST['offer_active']) ? 1 : 0;
        $sold  = isset($_POST['offer_sold']) ? 1 : 0;
        $renew = isset($_POST['offer_renew']);

        /* «قیمت» و «درصد تخفیف» مستقیما روی خود محصول ذخیره می‌شوند — بنر
           آفر قیمت/تخفیفش را همیشه زنده از products.retail_price/retail_discount
           می‌خواند (renderOfferBanner در banners.php سایت)، نه از یک ستون
           جداگانه در timed_offers. پس این‌جا فقط یک UPDATE کوتاه روی محصول
           است، نه بخشی از خود آفر. مقدار خالی/صفر یعنی ادمین دست نزده،
           پس قیمت فعلی دست‌نخورده می‌ماند (صفر کردن ناخواسته رخ نمی‌دهد). */
        $oPrice = (int)preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['offer_price'] ?? '')));
        $oDisc  = max(0, min(100, (int)faToLatinDigits((string)($_POST['offer_discount'] ?? '0'))));

        $cur = null;
        if ($eid > 0) {
            try {
                $st = $pdo->prepare("SELECT * FROM timed_offers WHERE id=?");
                $st->execute([$eid]);
                $cur = $st->fetch() ?: null;
            } catch (Throwable $e) {}
            if (!$cur) $eid = 0;   /* ردیف پاک شده — به‌جای خطا، آفر تازه ساخته می‌شود */
        }
        $fresh = ($eid === 0 || $renew);

        if ($pid <= 0) {
            $offerErr = 'انتخاب محصول الزامی است.';
        } elseif ($fresh && $days === 0 && $hours === 0) {
            $offerErr = 'مدت آفر باید بیشتر از صفر باشد (روز یا ساعت).';
        } else {
            $endAt = $fresh ? date('Y-m-d H:i:s', time() + $days * 86400 + $hours * 3600)
                            : ($cur['end_at'] ?? null);
            try {
                if ($oPrice > 0) {
                    $pdo->prepare("UPDATE products SET retail_price=?, retail_discount=? WHERE id=?")
                        ->execute([$oPrice, $oDisc, $pid]);
                }
                if ($eid > 0) {
                    $img  = saveBannerImage('offer_image', $cur['image'] ?? null);
                    $set  = "product_id=?, image=?, title=?, subtitle=?, end_at=?, sort_order=?, is_active=?";
                    $vals = [$pid, $img, $oTit, $oSub, $endAt, $sort, $act];
                    if ($soldOn) { $set .= ", is_sold=?"; $vals[] = $sold; }
                    $vals[] = $eid;
                    $pdo->prepare("UPDATE timed_offers SET $set WHERE id=?")->execute($vals);
                    header('Location: banners.php?omsg=updated#offers');
                } else {
                    $img  = saveBannerImage('offer_image', null);
                    $cols = "product_id, image, title, subtitle, end_at, sort_order, is_active";
                    $ph   = "?,?,?,?,?,?,?";
                    $vals = [$pid, $img, $oTit, $oSub, $endAt, $sort, $act];
                    if ($soldOn) { $cols .= ", is_sold"; $ph .= ",?"; $vals[] = $sold; }
                    $pdo->prepare("INSERT INTO timed_offers ($cols) VALUES ($ph)")->execute($vals);
                    header('Location: banners.php?omsg=saved#offers');
                }
                exit;
            } catch (Throwable $e) { $offerErr = 'خطای دیتابیس: ' . $e->getMessage(); }
        }
    }

    if (isset($_GET['odelete'])) {
        $oid = (int)$_GET['odelete'];
        try {
            $st = $pdo->prepare("SELECT image FROM timed_offers WHERE id=?"); $st->execute([$oid]);
            $row = $st->fetch();
            if ($row && $row['image']) {
                $f = __DIR__ . '/../uploads/banners/' . $row['image'];
                if (file_exists($f)) @unlink($f);
            }
            $pdo->prepare("DELETE FROM timed_offers WHERE id=?")->execute([$oid]);
        } catch (Throwable $e) {}
        header('Location: banners.php?omsg=deleted#offers');
        exit;
    }

    if (isset($_GET['otoggle'])) {
        try {
            $pdo->prepare("UPDATE timed_offers SET is_active = 1 - is_active WHERE id=?")
                ->execute([(int)$_GET['otoggle']]);
        } catch (Throwable $e) {}
        header('Location: banners.php?omsg=toggled#offers');
        exit;
    }

    /* کلید سریع «فروخته شد» — همان کاری که چک‌باکس فرم می‌کند، در یک کلیک */
    if (isset($_GET['osold']) && $soldOn) {
        try {
            $pdo->prepare("UPDATE timed_offers SET is_sold = 1 - is_sold WHERE id=?")
                ->execute([(int)$_GET['osold']]);
        } catch (Throwable $e) {}
        header('Location: banners.php?omsg=sold#offers');
        exit;
    }

    try {
        $offers = $pdo->query("SELECT o.*, p.name AS product_name, p.image AS product_image,
                   p.retail_price AS cur_price, p.retail_discount AS cur_discount
            FROM timed_offers o LEFT JOIN products p ON p.id = o.product_id
            ORDER BY o.is_active DESC, o.sort_order, o.id DESC")->fetchAll();
        $offerProducts = $pdo->query("SELECT id, name, technical_number, retail_price, retail_discount FROM products WHERE is_active = 1 ORDER BY name")->fetchAll();
    } catch (Throwable $e) { $offers = []; $offerProducts = []; }

    /* پیش‌پرکردن فرم برای ویرایش — از همان فهرست بالا خوانده می‌شود */
    if (isset($_GET['oedit'])) {
        $oeid = (int)$_GET['oedit'];
        foreach ($offers as $o) { if ((int)$o['id'] === $oeid) { $oEdit = $o; break; } }
    }

    /* میانبر «آفر» کنار محصول در admin/products.php: ?ofor=<id>#offers یعنی
       فرم «آفر جدید» با همین محصول از پیش انتخاب‌شده باز شود. فقط وقتی معنا
       دارد که در حال ویرایش آفر دیگری نباشیم. */
    $oForProductId = 0;
    if (!$oEdit && isset($_GET['ofor'])) $oForProductId = (int)$_GET['ofor'];

    /* اگر محصول همین آفر بعدا غیرفعال شده باشد، در $offerProducts (که فقط
       محصولات فعال را دارد) دیگر نیست — یعنی دراپ‌داون آن را نشان نمی‌دهد،
       فرم با محصول خالی ارسال می‌شود و «انتخاب محصول الزامی است» ذخیره را
       رد می‌کند؛ دقیقا همینجا «فعال‌سازی مجدد» بی‌صدا شکست می‌خورد. پس محصول
       خود آفر، حتی غیرفعال، همیشه به لیست اضافه می‌شود تا انتخاب‌شده بماند.
       همین برای محصول ?ofor= هم صدق می‌کند (شاید همان لحظه غیرفعال شده باشد). */
    $wantExtraId = 0;
    if ($oEdit && (int)$oEdit['product_id'] > 0) $wantExtraId = (int)$oEdit['product_id'];
    elseif ($oForProductId > 0) $wantExtraId = $oForProductId;

    if ($wantExtraId > 0) {
        $hasProd = false;
        foreach ($offerProducts as $op) { if ((int)$op['id'] === $wantExtraId) { $hasProd = true; break; } }
        if (!$hasProd) {
            try {
                $st = $pdo->prepare("SELECT id, name, technical_number, retail_price, retail_discount FROM products WHERE id = ?");
                $st->execute([$wantExtraId]);
                $extraProd = $st->fetch();
                if ($extraProd) {
                    $extraProd['name'] .= ' (غیرفعال)';
                    array_unshift($offerProducts, $extraProd);
                }
            } catch (Throwable $e) {}
        }
    }
}

if ($tableExists) {
    /* ذخیره‌ی بنر اصلی (یک ردیف position=main) */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_main'])) {
        $subtitle = trim($_POST['subtitle'] ?? '');
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $btnText  = trim($_POST['btn_text'] ?? '');
        $linkUrl  = trim($_POST['link_url'] ?? '');
        $active   = 1; // بنر اصلی همیشه نمایش داده می‌شود
        $existing = $pdo->query("SELECT * FROM banners WHERE position='main' ORDER BY id LIMIT 1")->fetch();
        $img      = saveBannerImage('image', $existing['image'] ?? null);
        if ($existing) {
            $pdo->prepare("UPDATE banners SET subtitle=?, title=?, description=?, btn_text=?, link_url=?, image=?, is_active=? WHERE id=?")
                ->execute([$subtitle, $title, $desc, $btnText, $linkUrl, $img, $active, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO banners (position, subtitle, title, description, btn_text, link_url, image, sort_order, is_active) VALUES ('main',?,?,?,?,?,?,0,?)")
                ->execute([$subtitle, $title, $desc, $btnText, $linkUrl, $img, $active]);
        }
        $msg = 'بنر اصلی ذخیره شد.';
    }

    /* افزودن/ویرایش بنر کوچک */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_small'])) {
        $editId    = (int)($_POST['edit_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $linkUrl   = trim($_POST['link_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active    = isset($_POST['is_active']) ? 1 : 0;
        if ($title !== '') {
            if ($editId) {
                $ex = $pdo->prepare("SELECT * FROM banners WHERE id=? AND position='small'"); $ex->execute([$editId]); $ex = $ex->fetch();
                $img = saveBannerImage('image', $ex['image'] ?? null);
                $pdo->prepare("UPDATE banners SET title=?, link_url=?, image=?, sort_order=?, is_active=? WHERE id=?")
                    ->execute([$title, $linkUrl, $img, $sortOrder, $active, $editId]);
                $msg = 'بنر کوچک به‌روز شد.';
            } else {
                $img = saveBannerImage('image', null);
                $pdo->prepare("INSERT INTO banners (position, title, link_url, image, sort_order, is_active) VALUES ('small',?,?,?,?,?)")
                    ->execute([$title, $linkUrl, $img, $sortOrder, $active]);
                $msg = 'بنر کوچک افزوده شد.';
            }
        }
    }

    /* حذف */
    if (isset($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        $b = $pdo->prepare("SELECT image FROM banners WHERE id=?"); $b->execute([$id]); $b = $b->fetch();
        if ($b && $b['image']) { $f = __DIR__ . '/../uploads/banners/' . $b['image']; if (file_exists($f)) @unlink($f); }
        $pdo->prepare("DELETE FROM banners WHERE id=?")->execute([$id]);
        $msg = 'حذف شد.';
    }

    /* فعال/غیرفعال */
    if (isset($_GET['toggle'])) {
        $pdo->prepare("UPDATE banners SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_GET['toggle']]);
        $msg = 'وضعیت تغییر کرد.';
    }

    $main   = $pdo->query("SELECT * FROM banners WHERE position='main' ORDER BY id LIMIT 1")->fetch();
    $smalls = $pdo->query("SELECT * FROM banners WHERE position='small' ORDER BY sort_order, id")->fetchAll();
    $editSmall = null;
    if (isset($_GET['edit'])) { $st = $pdo->prepare("SELECT * FROM banners WHERE id=? AND position='small'"); $st->execute([(int)$_GET['edit']]); $editSmall = $st->fetch(); }
}

require_once __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="flash flash-success" style="margin:0 0 1rem;"><?= h($msg) ?></div><?php endif; ?>

<?php if (!$tableExists): ?>
<div class="flash flash-error" style="margin-bottom:1rem;">
    جدول بنرها هنوز ساخته نشده است. ابتدا فایل
    <a href="../migration-banners.php" target="_blank" style="text-decoration:underline;">migration-banners.php</a>
    را یک‌بار اجرا کنید، سپس به این صفحه بازگردید.
</div>
<?php else: ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h2 style="font-size:1rem;color:var(--text-primary);">مدیریت بنرها</h2>
    <a href="../banners.php" target="_blank" class="btn btn-secondary btn-sm">&#8598; پیش‌نمایش صفحه‌ی بنرها</a>
</div>

<!-- بنر اصلی -->
<div class="dg-box" style="margin-bottom:1.5rem;">
    <div class="dg-box-hd"><h3>بنر اصلی (Hero)</h3></div>
    <div class="dg-box-bd" style="padding:1rem;">
        <form method="POST" enctype="multipart/form-data" class="admin-form-full">
            <div class="form-group"><label>متن بالای عنوان (Eyebrow)</label><input type="text" name="subtitle" class="form-control" value="<?= h($main['subtitle'] ?? '') ?>"></div>
            <div class="form-group"><label>عنوان اصلی</label><input type="text" name="title" class="form-control" value="<?= h($main['title'] ?? '') ?>"></div>
            <div class="form-group"><label>توضیحات</label><textarea name="description" class="form-control" rows="2"><?= h($main['description'] ?? '') ?></textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label>متن دکمه</label><input type="text" name="btn_text" class="form-control" value="<?= h($main['btn_text'] ?? '') ?>" placeholder="مشاهده فروشگاه"></div>
                <div class="form-group"><label>لینک دکمه (هر آدرسی)</label><input type="text" name="link_url" class="form-control" value="<?= h($main['link_url'] ?? '') ?>" placeholder="shop.php یا product.php?id=5 یا https://example.com"><small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">هر لینک داخلی (مثل shop.php یا category.php?cat=3) یا آدرس کامل بیرونی (https://...) مجاز است.</small></div>
            </div>
            <div class="form-group">
                <label>تصویر بنر (اختیاری)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <?php if (!empty($main['image'])): ?><div class="image-preview" style="margin-top:0.5rem;"><img src="../uploads/banners/<?= h($main['image']) ?>" style="max-height:120px;border-radius:8px;"></div><?php endif; ?>
            </div>
            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;font-size:0.8rem;color:var(--text-muted);">بنر اصلی همیشه در بالای صفحه نمایش داده می‌شود.</label>
            <button type="submit" name="save_main" class="btn btn-primary">ذخیره‌ی بنر اصلی</button>
        </form>
    </div>
</div>

<!-- بنرهای کوچک -->
<div style="display:grid;grid-template-columns:320px 1fr;gap:1rem;">
    <div>
        <div class="dg-box">
            <div class="dg-box-hd"><h3><?= $editSmall ? 'ویرایش بنر کوچک' : 'بنر کوچک جدید' ?></h3></div>
            <div class="dg-box-bd" style="padding:1rem;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="edit_id" value="<?= $editSmall['id'] ?? 0 ?>">
                    <div class="form-group"><label>عنوان</label><input type="text" name="title" class="form-control" value="<?= h($editSmall['title'] ?? '') ?>" required></div>
                    <div class="form-group"><label>لینک مقصد (هر آدرسی)</label><input type="text" name="link_url" class="form-control" value="<?= h($editSmall['link_url'] ?? '') ?>" placeholder="search.php?q=لنت ترمز یا https://example.com"><small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">کل بنر به این آدرس لینک می‌شود؛ هر لینک داخلی یا آدرس کامل (https://...) مجاز است.</small></div>
                    <div class="form-group"><label>ترتیب نمایش</label><input type="number" name="sort_order" class="form-control" value="<?= h($editSmall['sort_order'] ?? 0) ?>" style="width:120px;"></div>
                    <div class="form-group">
                        <label>تصویر (اختیاری)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($editSmall['image'])): ?><div class="image-preview" style="margin-top:0.5rem;"><img src="../uploads/banners/<?= h($editSmall['image']) ?>" style="max-height:90px;border-radius:8px;"></div><?php endif; ?>
                    </div>
                    <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;font-size:0.85rem;"><input type="checkbox" name="is_active" value="1" <?= (!$editSmall || $editSmall['is_active']) ? 'checked' : '' ?>> نمایش این بنر</label>
                    <button type="submit" name="save_small" class="btn btn-primary btn-block"><?= $editSmall ? 'به‌روزرسانی' : 'افزودن' ?></button>
                    <?php if ($editSmall): ?><a href="banners.php" class="btn btn-secondary btn-block" style="margin-top:0.5rem;">انصراف</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div>
        <div class="dg-box">
            <div class="dg-box-hd"><h3>بنرهای کوچک (<?= count($smalls) ?>)</h3></div>
            <div class="dg-box-bd" style="padding:0.5rem;">
                <?php if (!$smalls): ?>
                <p style="padding:1rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">هنوز بنر کوچکی اضافه نشده است.</p>
                <?php endif; ?>
                <?php foreach ($smalls as $s): ?>
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem;border-bottom:1px solid var(--border-color);<?= $s['is_active'] ? '' : 'opacity:0.5;' ?>">
                    <div style="width:64px;height:44px;border-radius:6px;flex-shrink:0;background:var(--bg-input) center/cover no-repeat;<?= $s['image'] ? "background-image:url('../uploads/banners/".h($s['image'])."')" : '' ?>;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:1.2rem;"><?= $s['image'] ? '' : icon('image', 'ic-lg') ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);"><?= h($s['title']) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);direction:ltr;text-align:right;"><?= h($s['link_url'] ?: '—') ?></div>
                    </div>
                    <span style="font-size:0.68rem;color:var(--text-muted);">#<?= (int)$s['sort_order'] ?></span>
                    <div style="display:flex;gap:0.25rem;">
                        <a href="?toggle=<?= $s['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;"><?= $s['is_active'] ? 'مخفی' : 'نمایش' ?></a>
                        <a href="?edit=<?= $s['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;">ویرایش</a>
                        <a href="?delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;" data-confirm="حذف شود؟">حذف</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- ===================== آفر زمان‌دار (بنر زیر بنر اصلی) ===================== -->
<?php if ($offersOn):
    /* چند آفر واقعا در اسلایدر صفحهٔ اصلی دیده می‌شود؟ آفر منقضی یا فروخته‌شده
       هم دیده می‌شود (بنر برداشته نمی‌شود، فقط پیام پایان روی آن می‌آید)،
       پس معیار فقط «فعال بودن» است. */
    $liveOffers = 0; $doneOffers = 0;
    foreach ($offers as $o) {
        if (!$o['is_active']) continue;
        $liveOffers++;
        $e = !empty($o['end_at']) ? strtotime($o['end_at']) : 0;
        if (($e && $e <= time()) || ($soldOn && !empty($o['is_sold']))) $doneOffers++;
    }
?>
<div id="offers" style="margin-top:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h2 style="font-size:1rem;color:var(--text-primary);"><?= icon('clock', 'ic-sm') ?> آفر زمان‌دار (بنر زیر بنر اصلی)</h2>
    </div>

    <?php if ($offerErr): ?><div class="flash flash-error" style="margin-bottom:1rem;"><?= h($offerErr) ?></div><?php endif; ?>

    <!-- بازهٔ چرخش اسلایدر -->
    <div class="dg-box" style="margin-bottom:1rem;">
        <div class="dg-box-hd"><h3><?= icon('refresh', 'ic-sm') ?> چرخش خودکار اسلایدر</h3></div>
        <div class="dg-box-bd" style="padding:1rem;">
            <form method="POST" action="banners.php#offers" style="display:flex;align-items:flex-end;gap:0.75rem;flex-wrap:wrap;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>هر چند ثانیه اسلاید عوض شود؟</label>
                    <input type="number" name="offer_slide_seconds" class="form-control" style="width:130px;"
                           value="<?= (int)$slideSec ?>" min="2" max="60" required>
                </div>
                <button type="submit" name="save_offer_slide" value="1" class="btn btn-primary">ذخیره</button>
                <p style="margin:0;font-size:0.75rem;color:var(--text-muted);line-height:1.9;flex:1 1 260px;">
                    از ۲ تا ۶۰ ثانیه. با <b>دو آفر فعال یا بیشتر</b>، بنر آفر در صفحهٔ اصلی به‌صورت خودکار و با همین بازه اسلاید می‌خورد
                    (با نگه‌داشتن نشانگر موس روی بنر، چرخش موقتا می‌ایستد).
                    در حال حاضر <b><?= $liveOffers ?></b> آفر در اسلایدر نمایش داده می‌شود
                    <?= $liveOffers < 2 ? '— با یک آفر، اسلایدر ثابت می‌ماند.' : '' ?>
                    <?php if ($doneOffers): ?>
                    <br><span style="color:#FBBF24;">توجه:</span> <b><?= $doneOffers ?></b> آفر مهلتش تمام شده یا فروخته شده — بنرش <b>برداشته نمی‌شود</b>،
                    فقط به انتهای اسلایدر می‌رود و روی آن «مهلت خرید تمام شد» یا مهر «فروخته شد» نمایش داده می‌شود.
                    <?php endif; ?>
                </p>
            </form>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:320px 1fr;gap:1rem;">
        <div>
            <?php
            /* یک فرم برای ساخت و ویرایش. در حالت ویرایش، مقدارها از $oEdit پیش‌پر
               می‌شوند و مهلت فقط با تیک «تمدید مهلت» از نو حساب می‌شود. */
            $isEdit  = (bool)$oEdit;
            $eEndTs  = ($isEdit && !empty($oEdit['end_at'])) ? strtotime($oEdit['end_at']) : 0;
            $eLeft   = $eEndTs ? max(0, $eEndTs - time()) : 0;
            /* «این آفر الان روی سایت زنده نیست» — سه دلیل ممکن، جدا از هم شمرده
               می‌شوند تا جعبهٔ توضیح پایین بگوید کدام‌یک اتفاق افتاده. آفر بی‌زمان
               (end_at NULL) منقضی حساب نمی‌شود. */
            $eExpired = $isEdit && $eEndTs > 0 && $eEndTs <= time();
            $eSold    = $isEdit && $soldOn && !empty($oEdit['is_sold']);
            $eHidden  = $isEdit && empty($oEdit['is_active']);
            $eDone    = $eExpired || $eSold || $eHidden;
            /* برای آفر تمام‌شده، روز/ساعت باقی‌مانده صفر است؛ صفر پیش‌پر کردن یعنی
               ادمین حتما باید عدد تازه بنویسد وگرنه فرم خطا می‌دهد. پس همان ۳ روز
               پیش‌فرض «آفر جدید» را می‌گذاریم تا یک کلیک ذخیره کافی باشد. */
            $eDays   = $isEdit ? ($eLeft > 0 ? (int)floor($eLeft / 86400) : 3) : 3;
            $eHours  = ($isEdit && $eLeft > 0) ? (int)floor(($eLeft % 86400) / 3600) : 0;
            $eImg    = ($isEdit && !empty($oEdit['image']) && file_exists(__DIR__ . '/../uploads/banners/' . $oEdit['image']))
                     ? '../uploads/banners/' . $oEdit['image'] : '';
            /* قیمت/تخفیف فعلی محصول برای پیش‌پرکردن کادرهای «قیمت» و «درصد
               تخفیف» — این دو مستقیما روی products.retail_price/retail_discount
               می‌نشینند (نه ستونی در timed_offers)، پس مقدار اولیه از همان‌جا
               خوانده می‌شود: در حالت ویرایش از join بالا (cur_price/cur_discount)،
               در حالت «آفر جدید برای محصول X» (?ofor=) از $offerProducts. */
            $eCurPrice = 0; $eCurDisc = 0;
            if ($isEdit) {
                $eCurPrice = (int)($oEdit['cur_price'] ?? 0);
                $eCurDisc  = (int)($oEdit['cur_discount'] ?? 0);
            } elseif ($oForProductId) {
                foreach ($offerProducts as $op) {
                    if ((int)$op['id'] === $oForProductId) { $eCurPrice = (int)$op['retail_price']; $eCurDisc = (int)$op['retail_discount']; break; }
                }
            }
            ?>
            <div class="dg-box" <?= $isEdit ? 'style="border-color:var(--red-primary);"' : '' ?>>
                <div class="dg-box-hd"><h3><?= $isEdit ? icon('edit', 'ic-sm') . ' ویرایش آفر #' . (int)$oEdit['id'] : 'آفر جدید' ?></h3></div>
                <div class="dg-box-bd" style="padding:1rem;">
                    <form method="POST" enctype="multipart/form-data" action="banners.php#offers">
                        <?php if ($isEdit): ?><input type="hidden" name="edit_id" value="<?= (int)$oEdit['id'] ?>"><?php endif; ?>
                        <?php if ($eDone): ?>
                        <?php /* آفر تمام‌شده: تیک‌های پایین از پیش طوری چیده شده‌اند که یک
                                «ذخیرهٔ تغییرات» آفر را برگرداند. اینجا صریح می‌نویسیم چه چیزی
                                عوض می‌شود تا هیچ تغییری پشت پرده نباشد. */ ?>
                        <div class="offer-back-box">
                            <b><?= icon('refresh', 'ic-sm') ?> این آفر الان روی سایت زنده نیست</b>
                            <span>دلیل:
                                <?php $rs = [];
                                      if ($eExpired) $rs[] = 'مهلتش تمام شده';
                                      if ($eSold)    $rs[] = 'نشان «فروخته شد» دارد';
                                      if ($eHidden)  $rs[] = 'تیک نمایش ندارد';
                                      echo h(implode(' + ', $rs)); ?>.
                            </span>
                            <span>تیک‌های پایین از پیش برای <b>برگرداندن آفر</b> چیده شده‌اند؛
                                کافی است روز تازه را ببینید و «ذخیرهٔ تغییرات» را بزنید. اگر
                                نمی‌خواهید برگردد، تیک‌ها را خودتان تغییر دهید.</span>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>محصول *</label>
                            <input type="text" id="offerProdFilter" class="form-control" placeholder="جست‌وجو در نام یا شمارهٔ فنی..." autocomplete="off" style="margin-bottom:0.4rem;">
                            <select name="product_id" id="offerProdSelect" class="form-control" required>
                                <option value="">-- انتخاب محصول --</option>
                                <?php foreach ($offerProducts as $op):
                                    $opTn = trim((string)($op['technical_number'] ?? ''));
                                    $opSelected = $isEdit ? ((int)$op['id'] === (int)$oEdit['product_id'])
                                                          : ($oForProductId > 0 && (int)$op['id'] === $oForProductId); ?>
                                <option value="<?= (int)$op['id'] ?>" <?= $opSelected ? 'selected' : '' ?>><?= h($op['name']) ?><?= $opTn !== '' ? ' — ' . h($opTn) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">
                                <span id="offerProdCount">همه <?= count($offerProducts) ?> محصول</span>
                                — می‌توانید چند کلمه را با فاصله بنویسید (مثلا «استارت 405»)؛ ارقام فارسی و لاتین یکی حساب می‌شوند.
                            </small>
                        </div>
                        <div class="form-group">
                            <label>عنوان بنر (اختیاری)</label>
                            <input type="text" name="offer_title" class="form-control" placeholder="خالی بگذارید تا نام محصول استفاده شود" value="<?= $isEdit ? h((string)$oEdit['title']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label>زیرعنوان (اختیاری)</label>
                            <input type="text" name="offer_subtitle" class="form-control" placeholder="مثال: تا پایان موجودی" value="<?= $isEdit ? h((string)$oEdit['subtitle']) : '' ?>">
                        </div>
                        <?php /* قیمت و درصد تخفیف — روی خود محصول ذخیره می‌شوند (بالاتر توضیح
                                داده شد)، پس روی همه آفرهای همان محصول و هرجای دیگر سایت هم اثر
                                می‌گذارند، نه فقط این بنر. با انتخاب محصول دیگر از فهرست بالا،
                                این دو کادر با قیمت/تخفیف همان محصول به‌روز می‌شوند (offerProdSelect
                                در پایین صفحه). */ ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                            <div class="form-group"><label>قیمت (تومان)</label><input type="text" name="offer_price" id="offerPrice" class="form-control" dir="ltr" inputmode="numeric" value="<?= $eCurPrice > 0 ? $eCurPrice : '' ?>" placeholder="خالی = بدون تغییر"></div>
                            <div class="form-group"><label>درصد تخفیف</label><input type="number" name="offer_discount" id="offerDiscount" class="form-control" value="<?= $eCurDisc ?>" min="0" max="100"></div>
                        </div>
                        <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:-0.4rem;margin-bottom:0.75rem;">
                            این دو مستقیما روی <b>قیمت و تخفیف خود محصول</b> ذخیره می‌شوند (همان‌طور که در فروشگاه دیده می‌شود)، نه فقط این بنر.
                        </small>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                            <div class="form-group"><label>شمارش معکوس (روز) <?= $isEdit ? '' : '*' ?></label><input type="number" name="offer_days" class="form-control" value="<?= $eDays ?>" min="0" max="365" <?= $isEdit ? '' : 'required' ?>></div>
                            <div class="form-group"><label>ساعت اضافه</label><input type="number" name="offer_hours" class="form-control" value="<?= $eHours ?>" min="0" max="23"></div>
                        </div>
                        <?php if ($isEdit): ?>
                        <label style="display:flex;align-items:flex-start;gap:0.5rem;margin-bottom:0.75rem;font-size:0.82rem;line-height:1.8;">
                            <input type="checkbox" name="offer_renew" value="1" style="margin-top:0.35rem;" <?= $eExpired ? 'checked' : '' ?>>
                            <span>تمدید مهلت — مهلت از <b>همین لحظه</b> با روز/ساعت بالا از نو حساب می‌شود.
                                <?php if ($eExpired): ?>
                                <br><span style="color:#FBBF24;font-size:0.74rem;">مهلت فعلی گذشته است (<?= h(jDate($oEdit['end_at'], true)) ?>)، پس این تیک از پیش خورده تا شمارش معکوس دوباره راه بیفتد.</span>
                                <?php else: ?>
                                <br><span style="color:var(--text-muted);font-size:0.74rem;">اگر تیک نزنید، مهلت فعلی دست‌نخورده می‌ماند (<?= $eEndTs ? h(jDate($oEdit['end_at'], true)) : 'بی‌زمان' ?>) و فقط بقیهٔ فیلدها ذخیره می‌شوند.</span>
                                <?php endif; ?></span>
                        </label>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>تصویر بنر (اختیاری)</label>
                            <input type="file" name="offer_image" class="form-control" accept="image/*">
                            <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">اگر تصویری آپلود نکنید، <?= $isEdit ? 'تصویر فعلی حفظ می‌شود' : 'تصویر خود محصول استفاده می‌شود' ?>. تصویر در یک قاب تقریبا مربع نمایش داده می‌شود و برای پرکردن قاب برش می‌خورد.</small>
                            <?php if ($eImg): ?><div class="image-preview" style="margin-top:0.5rem;"><img src="<?= h($eImg) ?>" style="max-height:90px;border-radius:8px;"></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>ترتیب نمایش</label>
                            <input type="number" name="offer_sort" class="form-control" value="<?= $isEdit ? (int)$oEdit['sort_order'] : 0 ?>" style="width:120px;">
                            <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">عدد کمتر = اسلاید جلوتر (آفر اول اسلایدر).</small>
                        </div>
                        <?php /* در حالت «تمام‌شده» این دو تیک برای برگرداندن آفر چیده می‌شوند:
                                نمایش روشن، فروخته‌شد خاموش. جعبهٔ بالای فرم همین را گفته است،
                                پس چیزی پشت پرده عوض نمی‌شود — وضعیت فرم دقیقا همان است که
                                ذخیره خواهد شد. («فروخته شد» خودش یکی از دلیل‌های زنده‌نبودن
                                است، پس شرط زیر برای آفر فروخته‌شده همیشه false می‌شود — عمدی
                                است تا فرم همیشه پیشنهاد «برش گردان» بدهد.) */ ?>
                        <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.6rem;font-size:0.85rem;"><input type="checkbox" name="offer_active" value="1" <?= (!$isEdit || $oEdit['is_active'] || $eDone) ? 'checked' : '' ?>> نمایش این آفر</label>
                        <?php if ($soldOn): ?>
                        <label style="display:flex;align-items:flex-start;gap:0.5rem;margin-bottom:1rem;font-size:0.85rem;line-height:1.8;">
                            <input type="checkbox" name="offer_sold" value="1" style="margin-top:0.35rem;" <?= ($eSold && !$eDone) ? 'checked' : '' ?>>
                            <span>فروخته شد
                                <?php if ($eSold): ?>
                                <br><span style="color:#FBBF24;font-size:0.74rem;">این آفر «فروخته شد» بود؛ تیک از پیش برداشته شده تا با ذخیره، مهر فروخته‌شد از بنر پاک شود. اگر می‌خواهید بماند، دوباره تیک بزنید.</span>
                                <?php else: ?>
                                <br><span style="color:var(--text-muted);font-size:0.74rem;">بنر روی صفحهٔ اصلی می‌ماند و مهر «فروخته شد» در گوشهٔ چپ بالای تصویر می‌خورد.</span>
                                <?php endif; ?></span>
                        </label>
                        <?php endif; ?>
                        <button type="submit" name="save_offer" value="1" class="btn btn-primary btn-block"><?= $isEdit ? 'ذخیرهٔ تغییرات' : 'ایجاد بنر آفر' ?></button>
                        <?php if ($isEdit): ?><a href="banners.php#offers" class="btn btn-secondary btn-block" style="margin-top:0.5rem;">لغو ویرایش</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div>
            <div class="dg-box">
                <div class="dg-box-hd"><h3>آفرها (<?= count($offers) ?>)</h3></div>
                <div class="dg-box-bd" style="padding:0.5rem;">
                    <?php if (!$offers): ?>
                    <p style="padding:1rem;text-align:center;color:var(--text-muted);font-size:0.85rem;line-height:1.9;">
                        هنوز آفری ساخته نشده است.<br>
                        آفرها <b>زیر بنر اصلی</b> صفحهٔ اصلی نمایش داده می‌شوند و اگر بیش از یکی فعال باشد، خودکار اسلاید می‌خورند.
                    </p>
                    <?php endif; ?>
                    <?php foreach ($offers as $o):
                        $endTs   = !empty($o['end_at']) ? strtotime($o['end_at']) : 0;
                        $left    = $endTs ? $endTs - time() : 0;
                        $expired = $endTs && $left <= 0;
                        $isSold  = $soldOn && !empty($o['is_sold']);
                        $isRow   = $oEdit && (int)$oEdit['id'] === (int)$o['id'];
                        $thumb   = '';
                        if ($o['image'] && file_exists(__DIR__ . '/../uploads/banners/' . $o['image'])) {
                            $thumb = '../uploads/banners/' . $o['image'];
                        } elseif (!empty($o['product_image']) && file_exists(__DIR__ . '/../uploads/products/' . $o['product_image'])) {
                            $thumb = '../uploads/products/' . $o['product_image'];
                        }
                    ?>
                    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0.5rem;border-bottom:1px solid var(--border-color);<?= (!$o['is_active'] || $expired || $isSold) ? 'opacity:0.6;' : '' ?><?= $isRow ? 'background:rgba(220,38,38,0.08);' : '' ?>">
                        <div style="width:44px;height:60px;border-radius:6px;flex-shrink:0;background:var(--bg-input) center/cover no-repeat;<?= $thumb ? "background-image:url('".h($thumb)."')" : '' ?>;display:flex;align-items:center;justify-content:center;color:var(--text-muted);"><?= $thumb ? '' : icon('image', 'ic-lg') ?></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary);">
                                <?= h($o['title'] ?: ($o['product_name'] ?: 'آفر')) ?>
                                <?php if ($isSold): ?><span style="font-size:0.65rem;font-weight:800;color:#FCA5A5;border:1px solid rgba(220,38,38,0.5);border-radius:4px;padding:0.05rem 0.3rem;margin-inline-start:0.3rem;">فروخته شد</span><?php endif; ?>
                            </div>
                            <?php if ($o['title'] && $o['product_name']): ?>
                            <div style="font-size:0.7rem;color:var(--text-muted);"><?= h($o['product_name']) ?></div>
                            <?php endif; ?>
                            <div style="font-size:0.72rem;margin-top:0.15rem;color:<?= $expired ? 'var(--red-light)' : '#FBBF24' ?>;">
                                <?php if (!$endTs): ?>
                                    بی‌زمان
                                <?php elseif ($expired): ?>
                                    منقضی شده — <?= h(jDate($o['end_at'], true)) ?>
                                <?php else: ?>
                                    <?= (int)floor($left / 86400) ?> روز و <?= (int)floor(($left % 86400) / 3600) ?> ساعت باقی مانده
                                    <span style="color:var(--text-muted);">(<?= h(jDate($o['end_at'], true)) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($o['is_active'] && ($expired || $isSold)): ?>
                            <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.15rem;">
                                بنر روی صفحهٔ اصلی می‌ماند و روی آن «<?= $isSold ? 'فروخته شد' : 'مهلت خرید تمام شد' ?>» نمایش داده می‌شود.
                            </div>
                            <?php endif; ?>
                        </div>
                        <span style="font-size:0.68rem;color:var(--text-muted);">#<?= (int)$o['sort_order'] ?></span>
                        <div style="display:flex;gap:0.25rem;flex-wrap:wrap;justify-content:flex-end;">
                            <?php /* آفر زنده‌نبوده: همان کلید ویرایش، ولی برجسته و با برچسبی که
                                    بگوید از آن راه برمی‌گردد (فرم ویرایش تیک‌ها را از پیش می‌چیند). */
                                  $deadRow = (!$o['is_active'] || $expired || $isSold); ?>
                            <a href="?oedit=<?= (int)$o['id'] ?>#offers" class="btn <?= $deadRow ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;"><?= $deadRow ? 'ویرایش و فعال‌سازی' : 'ویرایش' ?></a>
                            <?php if ($soldOn): ?>
                            <a href="?osold=<?= (int)$o['id'] ?>" class="btn <?= $isSold ? 'btn-secondary' : 'btn-primary' ?> btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;"><?= $isSold ? 'برگشت از فروش' : 'فروخته شد' ?></a>
                            <?php endif; ?>
                            <a href="?otoggle=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;"><?= $o['is_active'] ? 'مخفی' : 'نمایش' ?></a>
                            <a href="?odelete=<?= (int)$o['id'] ?>" class="btn btn-danger btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;" data-confirm="این آفر حذف شود؟">حذف</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* جست‌وجوی فهرست محصولات آفر (بدون کتابخانه). گزینه‌ها بازسازی می‌شوند
   چون display:none روی <option> در همه مرورگرها کار نمی‌کند.
   نکته‌های مهمی که قبلا اشتباه بود:
   • هیچ سقفی روی تعداد گزینه‌ها نیست. سقف قبلی (۳۰۰) باعث می‌شد با ۵۷۴ محصول،
     به‌محض تایپ‌کردن (و حتی پس از خالی‌کردن کادر) بقیهٔ محصول‌ها بی‌صدا غیب شوند.
   • تعداد نمایش‌داده‌شده همیشه زیر کادر نوشته می‌شود تا هیچ‌وقت بی‌صدا کم نشود.
   • متن جست‌وجو و نام محصول هر دو نرمال می‌شوند: ارقام فارسی/عربی → لاتین،
     ی/ك عربی → فارسی، نیم‌فاصله حذف. پس «۴۰۵» هم «405» را پیدا می‌کند.
   • چند کلمه با فاصله = همه باید باشند (ترتیبشان مهم نیست).
   • محصول انتخاب‌شده هرگز با ادامهٔ تایپ از فهرست نمی‌افتد. */
(function () {
    var box = document.getElementById('offerProdFilter');
    var sel = document.getElementById('offerProdSelect');
    var cnt = document.getElementById('offerProdCount');
    if (!box || !sel) return;

    var all = <?= json_encode(array_map(function ($p) {
        return ['i' => (int)$p['id'], 'n' => (string)$p['name'], 't' => trim((string)($p['technical_number'] ?? '')),
                'p' => (int)($p['retail_price'] ?? 0), 'd' => (int)($p['retail_discount'] ?? 0)];
    }, $offerProducts), JSON_UNESCAPED_UNICODE) ?>;
    var TOTAL = all.length;

    /* با انتخاب محصول دیگر از فهرست، کادرهای «قیمت» و «درصد تخفیف» با
       قیمت/تخفیف فعلی همان محصول پر می‌شوند — تا ادمین از قیمت واقعی که
       دارد تغییر می‌دهد آگاه باشد، نه یک عدد کهنه از محصول قبلی. */
    var priceEl = document.getElementById('offerPrice');
    var discEl  = document.getElementById('offerDiscount');
    function fillPriceFields(id) {
        if (!priceEl || !discEl) return;
        for (var i = 0; i < TOTAL; i++) {
            if (String(all[i].i) === String(id)) {
                priceEl.value = all[i].p > 0 ? all[i].p : '';
                discEl.value = all[i].d || 0;
                return;
            }
        }
    }
    sel.addEventListener('change', function () { fillPriceFields(sel.value); });

    function norm(s) {
        s = String(s).toLowerCase();
        s = s.replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 1776); });
        s = s.replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 1632); });
        s = s.replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/ة/g, 'ه').replace(/[أإآ]/g, 'ا');
        s = s.replace(/[\u200c\u200d\u0640]/g, '');   // نیم‌فاصله و کشیدگی
        return s.replace(/\s+/g, ' ').trim();
    }

    /* متن جست‌وجوی هر محصول یک‌بار ساخته می‌شود (نام + شمارهٔ فنی) */
    for (var i = 0; i < TOTAL; i++) all[i].s = norm(all[i].n + ' ' + all[i].t);

    function label(it) { return it.t ? (it.n + ' — ' + it.t) : it.n; }

    function mkOpt(it, keep) {
        var o = document.createElement('option');
        o.value = it.i;
        o.textContent = label(it);
        if (String(it.i) === keep) o.selected = true;
        return o;
    }

    function rebuild() {
        var toks = norm(box.value);
        toks = toks === '' ? [] : toks.split(' ');
        var keep = sel.value;

        var frag = document.createDocumentFragment();
        var first = document.createElement('option');
        first.value = '';
        first.textContent = '-- انتخاب محصول --';
        frag.appendChild(first);

        var shown = 0, keptShown = false;
        for (var i = 0; i < TOTAL; i++) {
            var hay = all[i].s, ok = true;
            for (var t = 0; t < toks.length; t++) {
                if (hay.indexOf(toks[t]) === -1) { ok = false; break; }
            }
            if (!ok) continue;
            frag.appendChild(mkOpt(all[i], keep));
            if (String(all[i].i) === keep) keptShown = true;
            shown++;
        }

        /* انتخاب فعلی حتی اگر با فیلتر نخواند، سر فهرست می‌ماند */
        if (keep !== '' && !keptShown) {
            for (var j = 0; j < TOTAL; j++) {
                if (String(all[j].i) === keep) {
                    frag.insertBefore(mkOpt(all[j], keep), first.nextSibling);
                    break;
                }
            }
        }

        sel.innerHTML = '';
        sel.appendChild(frag);
        if (keep !== '') sel.value = keep;

        if (cnt) {
            cnt.textContent = toks.length === 0
                ? ('همه ' + TOTAL + ' محصول')
                : (shown === 0 ? 'هیچ محصولی با این عبارت پیدا نشد' : (shown + ' محصول از ' + TOTAL));
        }
    }

    box.addEventListener('input', rebuild);
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php';
