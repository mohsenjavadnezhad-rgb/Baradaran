<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

if (isset($_GET['delete'])) { $id = (int)$_GET['delete']; $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$id]); redirect('products.php?deleted=1'); }

/* ===================== گالری تصاویر (کاروسل صفحهٔ محصول) =====================
   در جدول موجود product_images (image, sort_order) ذخیره می‌شود. */
define('GALLERY_MAX_UPLOAD', 12);   // حداکثر تصویر در هر بار آپلود

/* آپلود چند تصویر هم‌زمان → آرایهٔ نام فایل‌های ذخیره‌شده */
function saveGalleryUploads($field, $max = GALLERY_MAX_UPLOAD) {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'] ?? null)) return [];
    $dir = __DIR__ . '/../uploads/products/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $saved = [];
    $count = count($_FILES[$field]['name']);
    for ($i = 0; $i < $count && count($saved) < $max; $i++) {
        if (($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        if (($_FILES[$field]['size'][$i] ?? 0) > MAX_UPLOAD_SIZE) continue;
        $ext = strtolower(pathinfo($_FILES[$field]['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) continue;

        /* اگر چند فایل در همان ثانیه بیاید، نام تکراری نشود */
        do { $newName = time() . '_' . mt_rand(1000, 9999) . '.' . $ext; } while (is_file($dir . $newName));
        if (move_uploaded_file($_FILES[$field]['tmp_name'][$i], $dir . $newName)) $saved[] = $newName;
    }
    return $saved;
}

/* حذف فایل تصویر فقط وقتی هیچ محصول یا ردیف گالری دیگری به آن ارجاع ندارد */
function galleryUnlinkIfUnused($file) {
    global $pdo;
    if (!$file) return;
    foreach ([['product_images', 'image'], ['products', 'image']] as $t) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM {$t[0]} WHERE {$t[1]} = ?");
        $st->execute([$file]);
        if ((int)$st->fetchColumn() > 0) return;
    }
    $path = __DIR__ . '/../uploads/products/' . $file;
    if (is_file($path)) @unlink($path);
}

/* ترتیب فعلی تصاویر یک محصول (فقط idها، مرتب) */
function galleryOrder($productId) {
    global $pdo;
    $st = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
    $st->execute([$productId]);
    return array_map('intval', array_column($st->fetchAll(), 'id'));
}

/* شماره‌گذاری دوبارهٔ ۰..n-۱ روی همان ترتیب داده‌شده */
function galleryApplyOrder(array $ids) {
    global $pdo;
    $up = $pdo->prepare("UPDATE product_images SET sort_order = ? WHERE id = ?");
    foreach ($ids as $i => $rid) $up->execute([$i, $rid]);
}

/* یک تصویر را یک پله جلو (-۱) یا عقب (+۱) می‌برد */
function galleryMove($productId, $imgId, $delta) {
    $ids = galleryOrder($productId);
    $pos = array_search((int)$imgId, $ids, true);
    if ($pos === false) return;
    $new = $pos + $delta;
    if ($new < 0 || $new >= count($ids)) return;
    $tmp = $ids[$pos]; $ids[$pos] = $ids[$new]; $ids[$new] = $tmp;
    galleryApplyOrder($ids);
}

$id = (int)($_GET['id'] ?? 0); $isEdit = $id > 0; $product = null; $selectedCats = [];
$existingVariants = [];
if ($isEdit) {
    $product = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1"); $product->execute([$id]);
    $product = $product->fetch(); if (!$product) redirect('products.php');
    $scStmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?"); $scStmt->execute([$id]);
    $selectedCats = array_column($scStmt->fetchAll(), 'category_id');
    $existingVariants = getProductVariants($id);
}

/* اکشن‌های گالری (PRG — پیش از هر خروجی، تا ریدایرکت سالم بماند) */
$galleryImages = [];
$gmsgMap = ['deleted' => 'تصویر گالری حذف شد.', 'moved' => 'ترتیب تصاویر تغییر کرد.', 'added' => 'تصاویر گالری اضافه شدند.'];
$gmsg = isset($_GET['gmsg']) ? ($gmsgMap[$_GET['gmsg']] ?? '') : '';

if ($isEdit) {
    if (isset($_GET['gdel'])) {
        $gid = (int)$_GET['gdel'];
        $st = $pdo->prepare("SELECT image FROM product_images WHERE id = ? AND product_id = ?");
        $st->execute([$gid, $id]);
        $gfile = $st->fetchColumn();
        if ($gfile !== false) {
            $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?")->execute([$gid, $id]);
            galleryUnlinkIfUnused($gfile);
            galleryApplyOrder(galleryOrder($id));
        }
        redirect('product-edit.php?id=' . $id . '&gmsg=deleted#gallery');
    }
    if (isset($_GET['gmove'])) {
        galleryMove($id, (int)$_GET['gmove'], (($_GET['gdir'] ?? '') === 'back') ? 1 : -1);
        redirect('product-edit.php?id=' . $id . '&gmsg=moved#gallery');
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $techNum = trim($_POST['technical_number'] ?? ''); $desc = trim($_POST['description'] ?? '');
    $wholesaleMin = max(1, (int)($_POST['wholesale_min_qty'] ?? 5));
    $cats = $_POST['categories'] ?? []; $partCatId = (int)($_POST['part_category_id'] ?? 0) ?: null;

    $vCountries = $_POST['v_country'] ?? [];
    $vMakers = $_POST['v_maker'] ?? [];
    $vRetail = $_POST['v_retail'] ?? [];
    $vWhole = $_POST['v_whole'] ?? [];
    $vStock = $_POST['v_stock'] ?? [];
    $vRdisc = $_POST['v_rdisc'] ?? [];
    $vWdisc = $_POST['v_wdisc'] ?? [];

    // قیمت و تخفیف سطح محصول = آینهٔ واریانت اول (برای کارت‌ها و سبد خرید)
    $retailDisc = min(100, max(0, (int)($vRdisc[0] ?? 0)));
    $wholeDisc  = min(100, max(0, (int)($vWdisc[0] ?? 0)));

    // مالیات — مستقل از هر واریانت، سطح محصول (مثل تخفیف)
    $taxOn = taxReady();
    $taxEnabledIn = $taxOn && isset($_POST['tax_enabled']) ? 1 : 0;
    $taxPercentIn = $taxOn ? min(100, max(0, (float)faToLatinDigits((string)($_POST['tax_percent'] ?? '0')))) : 0;

    /* محصولِ عمومی/بدونِ‌نیاز — خواستهٔ کاربر: بعضی کالاها به هیچ برند/مدل/
       سالِ خاصی مقید نیستند. سه تیکِ مستقل، هرکدام همان‌جایی که برند/مدل/
       سال چک می‌شوند اثر می‌گذارد (getProducts()). سالِ تولید عمداً ستونِ
       تازه‌ای نگرفت چون خالی‌گذاشتنِ year_from/year_to همین حالا هم یعنی
       «برایِ همه سال‌ها» — فقط هنگامِ تیک‌خوردن، خودِ فیلدهایِ سال هم پاک
       می‌شوند تا حالتِ متناقض (هم تیک هم بازهٔ سال) پیش نیاید. */
    $univOn = productUniversalReady();
    $noBrandIn = $univOn && isset($_POST['no_brand_required']) ? 1 : 0;
    $noModelIn = $univOn && isset($_POST['no_model_required']) ? 1 : 0;
    $noYearIn  = $univOn && isset($_POST['no_year_required']) ? 1 : 0;

    if ($name === '') $error = 'نام محصول الزامی است.';
    if (count($vCountries) === 0) $error = 'حداقل یک واریانت باید تعریف شود.';

    /* شمارهٔ فنی: اگر خالی مانده باشد خودکار ساخته می‌شود (اختصار قطعه + مدل + شمارهٔ یونیک) */
    if (!$error && $techNum === '') {
        $techNum = generateTechnicalNumber($name, $cats, $isEdit ? $id : 0);
    }

    if (!$error) {
        $image = $product['image'] ?? null;
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['image']['size'] <= 2*1024*1024) {
                $newName = time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newName)) {
                    if ($image && file_exists($uploadDir . $image)) @unlink($uploadDir . $image);
                    $image = $newName;
                }
            }
        }

        /* تصاویر گالری پیش از تراکنش روی دیسک ذخیره می‌شوند (مثل تصویر اصلی)
           چون شمارهٔ محصول تازه پس از INSERT مشخص می‌شود. */
        $galleryNew = saveGalleryUploads('gallery');

        try { $pdo->beginTransaction();
            if ($isEdit) {
                $sql  = "UPDATE products SET name=?,technical_number=?,description=?,retail_price=?,wholesale_price=?,wholesale_min_qty=?,stock=?,image=?,part_category_id=?,retail_discount=?,wholesale_discount=?";
                $vals = [$name,$techNum,$desc,(int)($vRetail[0]??0),(int)($vWhole[0]??0),$wholesaleMin,0,$image,$partCatId,$retailDisc,$wholeDisc];
                if ($taxOn) { $sql .= ",tax_enabled=?,tax_percent=?"; $vals[] = $taxEnabledIn; $vals[] = $taxPercentIn; }
                $sql .= " WHERE id=?"; $vals[] = $id;
                $pdo->prepare($sql)->execute($vals);
                $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$id]);
                $pid = $id;
            } else {
                $cols = "name,technical_number,description,retail_price,wholesale_price,wholesale_min_qty,stock,image,part_category_id,retail_discount,wholesale_discount";
                $ph   = "?,?,?,?,?,?,?,?,?,?,?";
                $vals = [$name,$techNum,$desc,(int)($vRetail[0]??0),(int)($vWhole[0]??0),$wholesaleMin,0,$image,$partCatId,$retailDisc,$wholeDisc];
                if ($taxOn) { $cols .= ",tax_enabled,tax_percent"; $ph .= ",?,?"; $vals[] = $taxEnabledIn; $vals[] = $taxPercentIn; }
                $pdo->prepare("INSERT INTO products ($cols) VALUES ($ph)")->execute($vals);
                $pid = $pdo->lastInsertId();
            }

            $vStmt = $pdo->prepare("INSERT INTO product_variants (product_id, country, manufacturer, retail_price, wholesale_price, stock, retail_discount, wholesale_discount) VALUES (?,?,?,?,?,?,?,?)");
            $totalStock = 0;
            for ($i = 0; $i < count($vCountries); $i++) {
                $c = trim($vCountries[$i] ?? '');
                $m = trim($vMakers[$i] ?? '');
                if ($c === '' || $m === '') continue;
                $r = (int)($vRetail[$i] ?? 0);
                $w = (int)($vWhole[$i] ?? 0);
                $s = (int)($vStock[$i] ?? 0);
                $rd = min(100, max(0, (int)($vRdisc[$i] ?? 0)));
                $wd = min(100, max(0, (int)($vWdisc[$i] ?? 0)));
                $vStmt->execute([$pid, $c, $m, $r, $w, $s, $rd, $wd]);
                $totalStock += $s;
            }
            $pdo->prepare("UPDATE products SET stock = ?, retail_price = ?, wholesale_price = ? WHERE id = ?")
                ->execute([$totalStock, (int)($vRetail[0]??0), (int)($vWhole[0]??0), $pid]);

            /* وزن محصول (کیلوگرم) — اختیاری و فقط برای نرخ‌نامهٔ ارسال. خالی
               گذاشتنش NULL می‌شود و صفحهٔ تسویه سراغ «نرخ پایه»ی شهر می‌رود، پس
               لازم نیست وزن همه محصولات یک‌جا وارد شود. مثل is_special جدا و
               محافظت‌شده نوشته می‌شود تا پیش از اجرای مهاجرت خطا ندهد. */
            if (shippingWeightReady()) {
                $wRaw = trim(faToLatinDigits((string)($_POST['weight'] ?? '')));
                $wRaw = preg_replace('/[^0-9.]/', '', $wRaw);
                $pdo->prepare("UPDATE products SET weight = ? WHERE id = ?")
                    ->execute([($wRaw === '' || (float)$wRaw <= 0) ? null : (float)$wRaw, $pid]);
            }

            /* سال تولید خودرو (شمسی، از/تا) — اختیاری و جدا/محافظت‌شده مثل
               weight، تا پیش از اجرای مهاجرت خطا ندهد. خالی‌گذاشتن یعنی این
               قطعه محدودیت سالی ندارد (در فروشگاه برای همه سال‌ها می‌آید).
               فقط «از» پرشده باشد یعنی همان یک سال مشخص (از=تا). */
            if (productYearReady()) {
                if ($noYearIn) {
                    /* تیکِ «بدونِ نیاز به سالِ تولید» خورده — هر بازه‌ای هم
                       فرستاده شده باشد نادیده گرفته می‌شود تا حالتِ
                       متناقض (هم تیک، هم بازهٔ سال) ذخیره نشود. */
                    $yf = null; $yt = null;
                } else {
                    $yfRaw = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['year_from'] ?? '')));
                    $ytRaw = preg_replace('/\D+/', '', faToLatinDigits((string)($_POST['year_to'] ?? '')));
                    $yf = ($yfRaw !== '' && strlen($yfRaw) === 4) ? (int)$yfRaw : null;
                    $yt = ($ytRaw !== '' && strlen($ytRaw) === 4) ? (int)$ytRaw : ($yf !== null ? $yf : null);
                    if ($yf !== null && $yt !== null && $yt < $yf) { $tmp = $yf; $yf = $yt; $yt = $tmp; }
                }
                $pdo->prepare("UPDATE products SET year_from = ?, year_to = ? WHERE id = ?")
                    ->execute([$yf, $yt, $pid]);
            }

            /* سه تیکِ «بدونِ نیاز به برند/مدل/سال» — جدا و محافظت‌شده مثل
               weight/سال، تا پیش از اجرای مهاجرت خطا ندهد. */
            if ($univOn) {
                $pdo->prepare("UPDATE products SET no_brand_required=?, no_model_required=?, no_year_required=? WHERE id=?")
                    ->execute([$noBrandIn, $noModelIn, $noYearIn, $pid]);
            }

            /* درج تصاویر گالری در ادامهٔ ترتیب فعلی */
            if ($galleryNew) {
                $maxSort = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?");
                $maxSort->execute([$pid]);
                $next = (int)$maxSort->fetchColumn() + 1;
                $gs = $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?,?,?)");
                foreach ($galleryNew as $gf) { $gs->execute([$pid, $gf, $next]); $next++; }
            }

            if ($cats) { $cs = $pdo->prepare("INSERT INTO product_categories (product_id,category_id) VALUES (?,?)"); foreach ($cats as $cid) $cs->execute([$pid, (int)$cid]); }
            $pdo->commit();
            /* اگر تصویر گالری اضافه شده، به همان صفحه برگرد تا ادمین ترتیب را ببیند */
            if ($galleryNew) redirect('product-edit.php?id=' . (int)$pid . '&gmsg=added#gallery');
            redirect('products.php?'.($isEdit ? 'edited=1' : 'created=1'));
        } catch (Exception $e) { $pdo->rollBack(); $error = 'خطای دیتابیس: '.$e->getMessage(); }
    }
}

$allCategories = $pdo->query("SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON c.parent_id = p.id ORDER BY COALESCE(p.name,c.name), c.name")->fetchAll();

/* مدل‌های خودرو گروه‌بندی‌شده زیر برند خودشان — فهرست تخت «برند - مدل» خیلی
   شلوغ می‌شد. کوئری از قبل بر اساس نام برند و بعد نام مدل مرتب است، پس ترتیب
   کلیدها همان ترتیب الفبایی برندها می‌ماند. */
$postCats = $_POST['categories'] ?? $selectedCats;
$modelsByBrand = [];
foreach ($allCategories as $cat) {
    if (!$cat['parent_id']) continue;
    $bId = (int)$cat['parent_id'];
    if (!isset($modelsByBrand[$bId])) {
        $bName = (string)($cat['parent_name'] ?? '');
        $modelsByBrand[$bId] = ['name' => ($bName === '' ? 'سایر' : $bName), 'models' => [], 'sel' => 0];
    }
    $modelsByBrand[$bId]['models'][] = $cat;
    if (in_array($cat['id'], $postCats)) $modelsByBrand[$bId]['sel']++;
}
$modelTotal = 0;
$modelSel   = 0;
foreach ($modelsByBrand as $b) { $modelTotal += count($b['models']); $modelSel += $b['sel']; }

$partCats = getPartCategories();
$partParents = [];
foreach ($partCats as $c) { if (!$c['parent_id']) $partParents[] = $c; }
$countries = ['آلمان','ژاپن','کره جنوبی','چین','ایران','فرانسه','ایتالیا','آمریکا','انگلستان','تایوان','هند','ترکیه','اسپانیا','برزیل','تایلند','روسیه','هلند','سوئد','بلژیک','لهستان'];
$manufacturers = ['بوش (Bosch)','دنسو (Denso)','ساخت ایران','هیوندای موبیس','مان (Mann)','نگین (Negin)','ساچم (SACHS)','والئو (Valeo)','دلفی (Delphi)','فدرال موگول','زیمنس','کونتیننتال','TRW','ماهله (Mahle)','گیتس (Gates)','ایساکو','آمیکو','کروز (Cruze)','برمبو (Brembo)','KYB','لوكاس (Lucas)','SKF','ان تی ان (NTN)','پایا یدک','کروز ستاره'];

/* تصاویر فعلی گالری (برای فهرست/حذف/مرتب‌سازی) */
if ($isEdit) $galleryImages = getProductImages($id);

require_once __DIR__ . '/layout-top.php';
if ($gmsg): ?><div class="flash flash-success"><?= icon('check', 'ic-sm') ?> <?= h($gmsg) ?></div><?php endif;
if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>

<style>
.tech-row{display:flex;gap:0.4rem;align-items:stretch;}
.tech-row .form-control{flex:1;min-width:0;font-family:Consolas,monospace;letter-spacing:0.5px;}
.tech-row .btn{white-space:nowrap;flex-shrink:0;}
.tech-hint{font-size:0.7rem;color:var(--text-muted);margin-top:0.3rem;line-height:1.6;}
.tech-hint.is-auto{color:var(--green);}
.tech-hint.is-err{color:var(--red-light);}
/* گالری تصاویر محصول */
.gal-admin{display:flex;flex-wrap:wrap;gap:0.6rem;margin-top:0.7rem;}
.gal-item{width:112px;border:1px solid var(--border-color);border-radius:6px;background:var(--bg-secondary);overflow:hidden;}
.gal-item .gal-img{width:100%;height:82px;background:var(--bg-input);display:block;}
.gal-item .gal-img img{width:100%;height:100%;object-fit:cover;display:block;}
.gal-tools{display:flex;align-items:center;gap:0.15rem;padding:0.25rem;}
.gal-num{font-size:0.68rem;color:var(--text-muted);min-width:1.1rem;text-align:center;}
.gal-tools .btn{padding:0.12rem 0.3rem;font-size:0.66rem;line-height:1.5;}
.gal-first{font-size:0.62rem;color:var(--green);padding:0 0.25rem 0.25rem;}
/* ---------- انتخابگر مدل‌های خودرو ---------- */
.cm-total{font-size:0.7rem;font-weight:400;color:var(--text-muted);background:var(--bg-input);border:1px solid var(--border-color);border-radius:999px;padding:0.1rem 0.55rem;}
.cm-total.has-sel{color:#4ADE80;border-color:rgba(34,197,94,0.35);background:rgba(34,197,94,0.12);}
.cm-empty,.cm-hint{font-size:0.7rem;color:var(--text-muted);line-height:1.8;}
.cm-hint{margin-top:0.35rem;}
.cm-box{border:1px solid var(--border-color);border-radius:8px;background:var(--bg-secondary);overflow:hidden;}
.cm-bar{display:flex;flex-wrap:wrap;align-items:center;gap:0.35rem;padding:0.5rem;border-bottom:1px solid var(--border-color);background:rgba(0,0,0,0.15);}
.cm-search{position:relative;display:flex;align-items:center;gap:0.35rem;flex:1 1 200px;min-width:150px;background:var(--bg-input);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:0.25rem 0.5rem;color:var(--text-muted);}
.cm-search input{flex:1;min-width:0;background:none;border:0;outline:none;color:var(--text-primary);font-family:inherit;font-size:0.78rem;padding:0.15rem 0;}
.cm-x{background:none;border:0;color:var(--text-muted);cursor:pointer;font-size:1rem;line-height:1;padding:0 0.15rem;}
.cm-x:hover{color:var(--red-light);}
.cm-btn{background:var(--bg-input);border:1px solid var(--border-color);color:var(--text-secondary);border-radius:var(--radius-sm);font-family:inherit;font-size:0.7rem;padding:0.3rem 0.55rem;cursor:pointer;transition:all 0.15s;}
.cm-btn:hover{border-color:var(--red-primary);color:var(--text-primary);}
.cm-btn.is-on{background:var(--red-primary);border-color:var(--red-primary);color:#fff;}
.cm-btn.is-danger:hover{border-color:var(--red-light);color:var(--red-light);}
/* برچسب مدل‌های انتخاب‌شده — همیشه بالای کادر دیده می‌شوند */
.cm-chips{display:flex;flex-wrap:wrap;gap:0.3rem;padding:0.5rem;border-bottom:1px solid var(--border-color);}
.cm-chip{display:inline-flex;align-items:center;gap:0.3rem;background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.35);color:var(--text-primary);border-radius:999px;font-size:0.7rem;padding:0.15rem 0.25rem 0.15rem 0.55rem;}
.cm-chip b{font-weight:600;color:var(--text-muted);font-size:0.66rem;}
.cm-chip button{background:none;border:0;color:var(--red-light);cursor:pointer;font-size:0.85rem;line-height:1;padding:0 0.1rem;}
.cm-none{padding:0.75rem;font-size:0.75rem;color:var(--text-muted);text-align:center;}
.cm-groups{max-height:330px;overflow-y:auto;padding:0.4rem;}
.cm-brand{border:1px solid var(--border-color);border-radius:6px;background:rgba(0,0,0,0.12);margin-bottom:0.3rem;}
.cm-brand[hidden]{display:none;}
.cm-brand>summary{display:flex;align-items:center;gap:0.45rem;padding:0.45rem 0.6rem;cursor:pointer;list-style:none;font-size:0.8rem;color:var(--text-secondary);}
.cm-brand>summary::-webkit-details-marker{display:none;}
.cm-brand>summary::marker{content:'';}
.cm-brand>summary:hover{color:var(--text-primary);}
.cm-caret{display:inline-flex;flex-shrink:0;transition:transform 0.18s;}
.cm-caret>.ic{width:0.8rem;height:0.8rem;}
.cm-brand[open]>summary .cm-caret{transform:rotate(180deg);}
.cm-bname{font-weight:600;color:var(--text-primary);}
.cm-bnum{font-size:0.66rem;color:var(--text-muted);background:var(--bg-input);border-radius:999px;padding:0 0.4rem;}
.cm-bsel{font-size:0.66rem;font-weight:700;color:#fff;background:var(--red-primary);border-radius:999px;padding:0 0.4rem;}
.cm-ball{margin-inline-start:auto;font-size:0.68rem;color:var(--text-muted);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:0.05rem 0.4rem;}
.cm-ball:hover{border-color:var(--red-primary);color:var(--red-primary);}
.cm-models{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.15rem;padding:0.15rem 0.4rem 0.5rem;}
.cm-model{display:flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:var(--text-secondary);padding:0.28rem 0.4rem;border-radius:var(--radius-sm);cursor:pointer;min-width:0;}
.cm-model:hover{background:rgba(220,38,38,0.08);color:var(--text-primary);}
.cm-model[hidden]{display:none;}
.cm-model input{accent-color:var(--red-primary);flex-shrink:0;cursor:pointer;}
.cm-model span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cm-model.is-on{background:rgba(220,38,38,0.12);color:var(--text-primary);}
@media(max-width:768px){.cm-models{grid-template-columns:1fr 1fr;}.cm-groups{max-height:260px;}}
</style>

<form method="POST" enctype="multipart/form-data" id="productForm">
  <div class="form-group"><label>نام قطعه *</label><input type="text" name="name" id="prodNameInput" class="form-control" value="<?= h($_POST['name'] ?? $product['name'] ?? '') ?>" required></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="form-group">
      <label>شماره فنی <span style="color:var(--text-muted);font-weight:400;font-size:0.72rem;">(خالی بگذارید تا خودکار ساخته شود)</span></label>
      <div class="tech-row">
        <input type="text" name="technical_number" id="techNumInput" class="form-control" dir="ltr"
               placeholder="مثال: DSK-TUCS-933" autocomplete="off"
               value="<?= h($_POST['technical_number'] ?? $product['technical_number'] ?? '') ?>">
        <button type="button" class="btn btn-secondary btn-sm" id="techGenBtn" title="تولید شمارهٔ فنی جدید"><?= icon('refresh') ?>تولید خودکار</button>
      </div>
      <div class="tech-hint" id="techHint">پیشوند از نام قطعه و مدل خودرو ساخته می‌شود و شمارهٔ انتهایی یونیک است.</div>
    </div>
    <div class="form-group"><label>دسته بندی قطعه</label><select name="part_category_id" class="form-control"><option value="0">-- انتخاب --</option>
      <?php foreach ($partParents as $pp): ?><optgroup label="<?= h($pp['name']) ?>">
        <?php foreach ($partCats as $ch): if ($ch['parent_id'] != $pp['id']) continue; ?>
        <option value="<?=$ch['id']?>" <?= ($_POST['part_category_id'] ?? $product['part_category_id'] ?? '') == $ch['id'] ? 'selected' : '' ?>><?= h($ch['name']) ?></option>
        <?php endforeach; ?>
      </optgroup><?php endforeach; ?>
    </select></div>
  </div>
  <div class="form-group"><label>توضیحات</label><textarea name="description" class="form-control" rows="3"><?= h($_POST['description'] ?? $product['description'] ?? '') ?></textarea></div>
  <div class="form-group" style="display:flex;gap:1.25rem;flex-wrap:wrap;align-items:flex-start;">
    <div>
      <label>حداقل تعداد برای قیمت کلی</label>
      <input type="number" name="wholesale_min_qty" class="form-control" value="<?= h($_POST['wholesale_min_qty'] ?? $product['wholesale_min_qty'] ?? 5) ?>" min="1" style="width:200px;">
    </div>
    <?php if (shippingWeightReady()): ?>
    <?php /* وزن اختیاری است: فقط برای نرخ‌نامهٔ ارسال به کار می‌رود و می‌توان
            به‌تدریج پر کرد. خالی بودنش یعنی صفحهٔ تسویه سراغ نرخ پایهٔ شهر برود. */ ?>
    <div>
      <label>وزن (کیلوگرم) — اختیاری</label>
      <input type="text" name="weight" class="form-control" dir="ltr" inputmode="decimal"
             placeholder="مثلا 2.5"
             value="<?= h($_POST['weight'] ?? (isset($product['weight']) && $product['weight'] !== null ? shippingWeightText($product['weight']) : '')) ?>"
             style="width:200px;">
      <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.3rem;max-width:320px;line-height:1.7;">
        برای محاسبهٔ خودکار هزینهٔ ارسال از «نرخ‌نامه» (تنظیمات ← روش‌های ارسال). خالی بگذارید تا نرخ پایهٔ شهر در نظر گرفته شود.
      </div>
    </div>
    <?php endif; ?>
    <?php if (productYearReady() && productYearEnabled()): ?>
    <?php /* سال تولید خودرو (شمسی): در فروشگاه، مشتری بعد از انتخاب برند
            می‌تواند سال تولید خودروش را هم انتخاب کند تا فقط قطعات مناسب
            همان سال ببیند. خالی‌گذاشتن یعنی این قطعه برای همه سال‌ها مناسب
            است (خواستهٔ کاربر). */ ?>
    <div id="yearReqBox">
      <label>سال تولید خودرو (شمسی) — اختیاری</label>
      <div style="display:flex;align-items:center;gap:0.4rem;">
        <input type="text" name="year_from" class="form-control" dir="ltr" inputmode="numeric" maxlength="4"
               placeholder="از، مثلا 1390"
               value="<?= h($_POST['year_from'] ?? $product['year_from'] ?? '') ?>" style="width:110px;">
        <span style="color:var(--text-muted);">تا</span>
        <input type="text" name="year_to" class="form-control" dir="ltr" inputmode="numeric" maxlength="4"
               placeholder="تا، مثلا 1399"
               value="<?= h($_POST['year_to'] ?? $product['year_to'] ?? '') ?>" style="width:110px;">
      </div>
      <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.3rem;max-width:320px;line-height:1.7;">
        اگر این قطعه فقط برای یک سال مشخص است، فقط «از» را پر کنید. خالی‌گذاشتن هر دو یعنی محدودیت سالی ندارد و برای همه سال‌ها نشان داده می‌شود.
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if (productUniversalReady()): ?>
  <?php /* ۲۰۲۶-۰۹-۰۳: خواستهٔ کاربر — بعضی محصولات (لوازمِ عمومی) به هیچ
          برند/مدل/سالِ خاصی مقید نیستند. با هرکدام از این سه تیک، همان
          فیلترِ متناظر در فروشگاه/دسته‌بندیِ قطعات این محصول را همیشه
          نشان می‌دهد، صرف‌نظر از برند/مدل/سالی که مشتری انتخاب کرده. */ ?>
  <div id="univBox" style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1rem;margin-bottom:1rem;">
    <h3 style="font-size:0.9rem;color:var(--red-primary);margin-bottom:0.5rem;"><?= icon('globe', 'ic-sm') ?> محصولِ عمومی (بدونِ نیاز به برند/مدل/سالِ خودرو)</h3>
    <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.75rem;line-height:1.8;">
      برای کالاهایی که برای همهٔ خودروها مناسب‌اند — با هر تیک، همان بخش (برند/مدل/سال) دیگر برای این محصول لازم نیست و در هر جست‌وجو/فیلتری نشان داده می‌شود.
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;">
      <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.82rem;">
        <input type="checkbox" name="no_brand_required" id="no_brand_required" value="1"
               <?= !empty($product['no_brand_required']) || !empty($_POST['no_brand_required']) ? 'checked' : '' ?>
               style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
        بدونِ نیاز به برندِ خودرو
      </label>
      <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.82rem;">
        <input type="checkbox" name="no_model_required" id="no_model_required" value="1"
               <?= !empty($product['no_model_required']) || !empty($_POST['no_model_required']) ? 'checked' : '' ?>
               style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
        بدونِ نیاز به مدلِ خودرو
      </label>
      <?php if (productYearReady() && productYearEnabled()): ?>
      <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;font-size:0.82rem;">
        <input type="checkbox" name="no_year_required" id="no_year_required" value="1"
               <?= !empty($product['no_year_required']) || !empty($_POST['no_year_required']) ? 'checked' : '' ?>
               style="width:1.05rem;height:1.05rem;accent-color:var(--red-primary);">
        بدونِ نیاز به سالِ تولید
      </label>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1rem;margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
      <h3 style="font-size:0.9rem;color:var(--red-primary);">واریانت های محصول (کشور + شرکت سازنده)</h3>
      <button type="button" class="btn btn-primary btn-sm" onclick="addVariant()">+ افزودن واریانت</button>
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.6rem;">برای هر واریانت می‌توانید درصد تخفیف جزئی و کلی را جداگانه وارد کنید (۰ = بدون تخفیف). قیمت و تخفیف واریانت اول برای کارت محصولات و سبد خرید استفاده می‌شود.</div>
    <div id="variantsContainer">
      <?php
      $postVarCount = max(1, count($_POST['v_country'] ?? []), count($existingVariants));
      for ($i = 0; $i < $postVarCount; $i++):
          $vCountry = $_POST['v_country'][$i] ?? $existingVariants[$i]['country'] ?? '';
          $vMaker = $_POST['v_maker'][$i] ?? $existingVariants[$i]['manufacturer'] ?? '';
          $vRetail = $_POST['v_retail'][$i] ?? $existingVariants[$i]['retail_price'] ?? '';
          $vWhole = $_POST['v_whole'][$i] ?? $existingVariants[$i]['wholesale_price'] ?? '';
          $vStock = $_POST['v_stock'][$i] ?? $existingVariants[$i]['stock'] ?? '';
          $vRdisc = $_POST['v_rdisc'][$i] ?? $existingVariants[$i]['retail_discount'] ?? 0;
          $vWdisc = $_POST['v_wdisc'][$i] ?? $existingVariants[$i]['wholesale_discount'] ?? 0;
      ?>
      <div class="variant-row" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr .8fr .8fr .8fr 34px;gap:0.5rem;align-items:end;margin-bottom:0.5rem;padding:0.5rem;background:rgba(0,0,0,0.15);border-radius:6px;">
        <div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">کشور سازنده</label><select name="v_country[]" class="form-control" style="font-size:0.78rem;"><option value="">-- انتخاب --</option><?php foreach($countries as $c) echo '<option value="'.h($c).'"'.($vCountry===$c?' selected':'').'>'.h($c).'</option>'; ?></select></div>
        <div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">شرکت سازنده</label><select name="v_maker[]" class="form-control" style="font-size:0.78rem;"><option value="">-- انتخاب --</option><?php foreach($manufacturers as $m) echo '<option value="'.h($m).'"'.($vMaker===$m?' selected':'').'>'.h($m).'</option>'; ?></select></div>
        <div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">قیمت جزئی</label><input type="number" name="v_retail[]" class="form-control" value="<?= h($vRetail) ?>" style="font-size:0.78rem;" required></div>
        <div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">قیمت کلی</label><input type="number" name="v_whole[]" class="form-control" value="<?= h($vWhole) ?>" style="font-size:0.78rem;" required></div>
        <div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">موجودی</label><input type="number" name="v_stock[]" class="form-control" value="<?= h($vStock) ?>" style="font-size:0.78rem;" min="0"></div>
        <div class="form-group" style="margin:0;"><label style="font-size:0.72rem;" title="درصد تخفیف قیمت جزئی">تخفیف جزئی٪</label><input type="number" name="v_rdisc[]" class="form-control" value="<?= h($vRdisc) ?>" style="font-size:0.78rem;" min="0" max="100"></div>
        <div class="form-group" style="margin:0;"><label style="font-size:0.72rem;" title="درصد تخفیف قیمت کلی">تخفیف کلی٪</label><input type="number" name="v_wdisc[]" class="form-control" value="<?= h($vWdisc) ?>" style="font-size:0.78rem;" min="0" max="100"></div>
        <button type="button" class="btn btn-danger btn-sm" onclick="var r=this.parentNode;r.parentNode.removeChild(r)" style="margin-bottom:2px;padding:0.2rem 0.4rem;font-size:0.7rem;">X</button>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <?php if (taxReady()):
    /* برای چک‌باکس، بعد از یک POST ناموفق باید دقیقا همان چیزی که فرستاده شده
       نمایش داده شود (حتی «تیک نخورده») نه مقدار قدیمی $product — وگرنه با
       خطای اعتبارسنجی، برداشتن تیک توسط مدیر روی صفحه دیده نمی‌شود. */
    $taxChecked = $_SERVER['REQUEST_METHOD'] === 'POST' ? isset($_POST['tax_enabled']) : !empty($product['tax_enabled'] ?? 0);
  ?>
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:1rem;margin-bottom:1rem;">
    <h3 style="font-size:0.9rem;color:var(--red-primary);margin-bottom:0.6rem;">مالیات</h3>
    <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.6rem;font-size:0.85rem;">
      <input type="checkbox" name="tax_enabled" value="1" <?= $taxChecked ? 'checked' : '' ?>>
      مالیات روی این محصول اعمال شود
    </label>
    <div class="form-group" style="max-width:200px;">
      <label>درصد مالیات</label>
      <input type="number" name="tax_percent" class="form-control" dir="ltr" min="0" max="100" step="0.01"
             value="<?= h($_POST['tax_percent'] ?? $product['tax_percent'] ?? '9') ?>">
    </div>
    <div style="font-size:0.72rem;color:var(--text-muted);line-height:1.8;">
      اگر فعال باشد، این درصد روی قیمت این محصول در سبد خرید و تسویه‌حساب اضافه می‌شود و در فاکتور به‌صورت یک ردیف جدا نشان داده می‌شود.
    </div>
  </div>
  <?php endif; ?>

  <div class="form-group"><label>تصویر محصول</label><input type="file" name="image" class="form-control" accept="image/*"><?php $ci = $_POST['image_current'] ?? $product['image'] ?? null; if ($ci): ?><div class="image-preview"><img src="../uploads/products/<?=h($ci)?>"></div><?php endif; ?></div>

  <?php /* گالری کاروسل: تصویر اصلی بالای نوار بندانگشتی در صفحهٔ محصول نشان داده می‌شود */ ?>
  <div class="form-group" id="gallery">
    <label><?= icon('image', 'ic-sm') ?> تصاویر بیشتر (کاروسل صفحهٔ محصول)</label>
    <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.35rem;line-height:1.7;">
      می‌توانید چند تصویر را یک‌جا انتخاب کنید (حداکثر <?= GALLERY_MAX_UPLOAD ?> تصویر در هر بار، هر فایل تا ۲ مگابایت، فرمت jpg / jpeg / png / webp).
      این تصاویر در صفحهٔ محصول به‌صورت نوار بندانگشتی زیر تصویر اصلی می‌آیند و با کلیک، جای تصویر اصلی را می‌گیرند.
      <?php if (!$isEdit): ?><b>در محصول جدید، تصاویر هم‌زمان با ذخیرهٔ محصول ثبت می‌شوند.</b><?php endif; ?>
    </div>

    <?php if ($galleryImages): ?>
    <div class="gal-admin">
      <?php foreach ($galleryImages as $gi => $g): $gid = (int)$g['id']; ?>
      <div class="gal-item">
        <span class="gal-img"><img src="../uploads/products/<?= h($g['image']) ?>" alt=""></span>
        <div class="gal-tools">
          <span class="gal-num"><?= $gi + 1 ?></span>
          <a class="btn btn-secondary btn-sm" title="یک پله جلوتر"
             href="product-edit.php?id=<?= $id ?>&gmove=<?= $gid ?>&gdir=fwd#gallery"><?= icon('chevron-right', 'ic-sm') ?></a>
          <a class="btn btn-secondary btn-sm" title="یک پله عقب‌تر"
             href="product-edit.php?id=<?= $id ?>&gmove=<?= $gid ?>&gdir=back#gallery"><?= icon('chevron-left', 'ic-sm') ?></a>
          <a class="btn btn-danger btn-sm" title="حذف تصویر" onclick="return confirm('این تصویر حذف شود؟')"
             href="product-edit.php?id=<?= $id ?>&gdel=<?= $gid ?>#gallery"><?= icon('trash', 'ic-sm') ?></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="font-size:0.68rem;color:var(--text-muted);margin-top:0.4rem;">
      ترتیب بالا همان ترتیب نوار بندانگشتی است. تصویر اصلی محصول همیشه اولین بندانگشتی است.
    </div>
    <?php endif; ?>
  </div>

  <?php /* «فروش ویژه» (تیک is_special) از این فرم برداشته شد — خواستهٔ
          کاربر: «آفر» (پایین‌تر در products.php کنار همین محصول) همین کار
          را انجام می‌دهد و کافی است. */ ?>
  <?php /* انتخابگر مدل خودرو: گروه‌بندی زیر برند + جست‌وجو + نشان انتخاب‌شده‌ها.
           چک‌باکس‌ها همان name="categories[]" قبلی‌اند تا ذخیره و ساخت خودکار
           شمارهٔ فنی (که به رویداد change همین چک‌باکس‌ها گوش می‌دهد) دست‌نخورده بماند. */ ?>
  <div class="form-group" id="carmodels">
    <label style="display:flex;align-items:center;gap:0.45rem;flex-wrap:wrap;">
      <?= icon('layers', 'ic-sm') ?> مدل‌های خودرو
      <span class="cm-total" id="cmTotal" data-all="<?= (int)$modelTotal ?>"><?= (int)$modelSel ?> مدل انتخاب شده</span>
    </label>

    <?php if (!$modelsByBrand): ?>
    <div class="cm-empty">هنوز مدلی ثبت نشده است. از «برندها و مدل‌ها» برند و مدل اضافه کنید.</div>
    <?php else: ?>
    <div class="cm-box" id="cmBox">
      <div class="cm-bar">
        <span class="cm-search">
          <?= icon('search', 'ic-sm') ?>
          <input type="text" id="cmSearch" placeholder="جست‌وجوی برند یا مدل…" autocomplete="off">
          <button type="button" class="cm-x" id="cmClear" title="پاک‌کردن جست‌وجو" hidden>&times;</button>
        </span>
        <button type="button" class="cm-btn" data-cm="open">باز کردن همه</button>
        <button type="button" class="cm-btn" data-cm="close">بستن همه</button>
        <button type="button" class="cm-btn" data-cm="onlysel" id="cmOnlySel">فقط انتخاب‌شده‌ها</button>
        <button type="button" class="cm-btn is-danger" data-cm="clear">حذف انتخاب‌ها</button>
      </div>

      <div class="cm-chips" id="cmChips" hidden></div>
      <div class="cm-none" id="cmNoHit" hidden>موردی با این عبارت پیدا نشد.</div>

      <div class="cm-groups">
        <?php foreach ($modelsByBrand as $bId => $b): ?>
        <details class="cm-brand" data-brand="<?= h($b['name']) ?>" <?= $b['sel'] ? 'open' : '' ?>>
          <summary>
            <span class="cm-caret"><?= icon('chevron-down') ?></span>
            <span class="cm-bname"><?= h($b['name']) ?></span>
            <span class="cm-bnum"><?= count($b['models']) ?></span>
            <span class="cm-bsel" <?= $b['sel'] ? '' : 'hidden' ?>><?= (int)$b['sel'] ?></span>
            <span class="cm-ball" role="button" tabindex="0" data-all="<?= (int)$bId ?>">همه</span>
          </summary>
          <div class="cm-models">
            <?php foreach ($b['models'] as $m): ?>
            <label class="cm-model" data-name="<?= h($m['name']) ?>">
              <input type="checkbox" name="categories[]" value="<?= (int)$m['id'] ?>" <?= in_array($m['id'], $postCats) ? 'checked' : '' ?>>
              <span><?= h($m['name']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </details>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="cm-hint">برندها را باز کنید و مدل‌ها را تیک بزنید؛ یا نام برند/مدل را در کادر جست‌وجو بنویسید. مدل‌های انتخاب‌شده بالای کادر به‌صورت برچسب دیده می‌شوند و با × حذف می‌شوند.</div>
    <?php endif; ?>
  </div>
  <button type="submit" class="btn btn-primary"><?= $isEdit ? 'به روزرسانی محصول' : 'افزودن محصول' ?></button>
</form>

<script>
var variantCountries = <?= json_encode($countries, JSON_UNESCAPED_UNICODE) ?>;
var variantMakers = <?= json_encode($manufacturers, JSON_UNESCAPED_UNICODE) ?>;

/* ---------- شمارهٔ فنی خودکار ---------- */
(function(){
    var nameEl = document.getElementById('prodNameInput');
    var techEl = document.getElementById('techNumInput');
    var btn    = document.getElementById('techGenBtn');
    var hint   = document.getElementById('techHint');
    if (!nameEl || !techEl) return;

    var productId = <?= $isEdit ? (int)$id : 0 ?>;
    /* اگر فیلد از قبل پر است (ویرایش محصول) دست‌نویس تلقی می‌شود و بازنویسی نمی‌گردد */
    var isAuto = (techEl.value.trim() === '');
    var timer = null, busy = false;
    var ICON_CHECK = <?= json_encode(icon('check', 'ic-sm'), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

    function say(msg, cls){
        if (!hint) return;
        hint.className = 'tech-hint' + (cls ? ' ' + cls : '');
        hint.innerHTML = msg;
    }

    function selectedCats(){
        var out = [], boxes = document.querySelectorAll('input[name="categories[]"]:checked');
        for (var i = 0; i < boxes.length; i++) out.push(boxes[i].value);
        return out;
    }

    function fetchCode(force){
        var nm = nameEl.value.trim();
        if (nm === '') { if (force) say('ابتدا نام قطعه را وارد کنید.', 'is-err'); return; }
        if (!force && !isAuto) return;
        if (busy) return;
        busy = true;

        var body = 'name=' + encodeURIComponent(nm) + '&id=' + productId;
        selectedCats().forEach(function(c){ body += '&categories[]=' + encodeURIComponent(c); });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'tech-number.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) return;
            busy = false;
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) {}
            if (!res || !res.ok || !res.code) { say('تولید خودکار ناموفق بود؛ می‌توانید دستی وارد کنید.', 'is-err'); return; }
            techEl.value = res.code;
            isAuto = true;
            say(ICON_CHECK + ' شمارهٔ فنی خودکار ساخته شد (پیشوند <b dir="ltr">' + res.prefix + '</b>). قابل ویرایش است.', 'is-auto');
        };
        xhr.send(body);
    }

    function schedule(){
        if (!isAuto) return;
        if (timer) clearTimeout(timer);
        timer = setTimeout(function(){ fetchCode(false); }, 700);
    }

    nameEl.addEventListener('input', schedule);
    nameEl.addEventListener('change', schedule);

    /* تیک‌خوردن مدل خودرو پیشوند مدل را عوض می‌کند */
    document.addEventListener('change', function(e){
        if (e.target && e.target.name === 'categories[]') schedule();
    });

    /* هر تایپ دستی در فیلد، حالت خودکار را غیرفعال می‌کند */
    techEl.addEventListener('input', function(){
        isAuto = (techEl.value.trim() === '');
        if (!isAuto) say('شمارهٔ فنی دستی وارد شده است.', '');
        else say('خالی است — با نوشتن نام قطعه خودکار ساخته می‌شود.', '');
    });

    if (btn) btn.addEventListener('click', function(){ fetchCode(true); });

    /* محصول جدید با نام از قبل پرشده (بازگشت از خطا) */
    if (isAuto && nameEl.value.trim() !== '') fetchCode(false);
})();

/* ---------- انتخابگر مدل‌های خودرو (گروه‌بندی + جست‌وجو + برچسب انتخاب‌شده‌ها) ----------
   چک‌باکس‌ها دست‌نخورده‌اند؛ این کد فقط نمایش را مرتب می‌کند. هر تغییر برنامه‌ای
   یک رویداد change واقعی می‌فرستد تا سازندهٔ خودکار شمارهٔ فنی هم خبردار شود. */
(function(){
    var box = document.getElementById('cmBox');
    if (!box) return;

    var searchEl = document.getElementById('cmSearch'),
        clearEl  = document.getElementById('cmClear'),
        chipsEl  = document.getElementById('cmChips'),
        totalEl  = document.getElementById('cmTotal'),
        noHitEl  = document.getElementById('cmNoHit'),
        onlyBtn  = document.getElementById('cmOnlySel'),
        brands   = [].slice.call(box.querySelectorAll('.cm-brand')),
        onlySel  = false,
        tempView = false,
        savedOpen = null;

    /* یکسان‌سازی نویسه‌ها: ی/ي، ک/ك، ه/ة، ارقام فارسی و عربی، و حذف نیم‌فاصله
       تا جست‌وجو به شکل تایپ حساس نباشد. */
    function norm(s) {
        s = String(s == null ? '' : s);
        var fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩', out = '', i, ch, k;
        for (i = 0; i < s.length; i++) {
            ch = s.charAt(i);
            k = fa.indexOf(ch); if (k < 0) k = ar.indexOf(ch);
            if (k > -1) { out += k; continue; }
            if (ch === 'ي' || ch === 'ى') ch = 'ی';
            else if (ch === 'ك') ch = 'ک';
            else if (ch === 'ة' || ch === 'ۀ') ch = 'ه';
            else if (ch === '‌' || ch === '‎' || ch === '‏') continue;
            out += ch;
        }
        return out.toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function boxesOf(el) { return [].slice.call(el.querySelectorAll('input[name="categories[]"]')); }

    /* رویداد change واقعی → هم این ویجت و هم اسکریپت شمارهٔ فنی به‌روز می‌شوند */
    function fire(inp) { if (inp) inp.dispatchEvent(new Event('change', { bubbles: true })); }

    function buildChips() {
        if (!chipsEl) return 0;
        var html = '', n = 0;
        brands.forEach(function (brand) {
            var bn = brand.getAttribute('data-brand') || '';
            boxesOf(brand).forEach(function (b) {
                if (!b.checked) return;
                n++;
                var lb = b.closest('.cm-model');
                html += '<span class="cm-chip"><b>' + esc(bn) + '</b>' +
                        esc(lb ? lb.getAttribute('data-name') : '') +
                        '<button type="button" data-off="' + esc(b.value) + '" title="حذف این مدل">&times;</button></span>';
            });
        });
        chipsEl.innerHTML = html;
        chipsEl.hidden = (n === 0);
        return n;
    }

    function applyFilter() {
        var q = norm(searchEl ? searchEl.value : ''), anyHit = false;
        brands.forEach(function (brand) {
            var brandHit = (q !== '' && norm(brand.getAttribute('data-brand')).indexOf(q) > -1);
            var shown = 0;
            boxesOf(brand).forEach(function (b) {
                var lb = b.closest('.cm-model');
                if (!lb) return;
                var ok = true;
                if (onlySel && !b.checked) ok = false;
                if (ok && q !== '' && !brandHit && norm(lb.getAttribute('data-name')).indexOf(q) < 0) ok = false;
                lb.hidden = !ok;
                if (ok) shown++;
            });
            brand.hidden = (shown === 0);
            if (shown) { anyHit = true; if (q !== '' || onlySel) brand.open = true; }
        });
        if (noHitEl) noHitEl.hidden = anyHit;
        if (clearEl) clearEl.hidden = !(searchEl && searchEl.value !== '');
    }

    function refresh() {
        var total = 0;
        brands.forEach(function (brand) {
            var n = 0;
            boxesOf(brand).forEach(function (b) {
                var lb = b.closest('.cm-model');
                if (lb) lb.classList.toggle('is-on', b.checked);
                if (b.checked) n++;
            });
            total += n;
            var badge = brand.querySelector('.cm-bsel');
            if (badge) { badge.textContent = n; badge.hidden = (n === 0); }
        });
        if (totalEl) {
            totalEl.textContent = total === 0
                ? 'هیچ مدلی انتخاب نشده'
                : total + ' مدل از ' + totalEl.getAttribute('data-all') + ' انتخاب شده';
            totalEl.classList.toggle('has-sel', total > 0);
        }
        buildChips();
        applyFilter();
    }

    /* هنگام جست‌وجو گروه‌ها موقتا باز می‌شوند؛ با پاک‌شدن عبارت، وضعیت قبلی برمی‌گردد */
    function setTempView(on) {
        if (on && !tempView) { savedOpen = brands.map(function (b) { return b.open; }); tempView = true; }
        else if (!on && tempView) {
            if (savedOpen) brands.forEach(function (b, i) { b.open = savedOpen[i]; });
            tempView = false; savedOpen = null;
        }
    }

    function update() {
        setTempView((searchEl && searchEl.value.trim() !== '') || onlySel);
        applyFilter();
    }

    function toggleBrand(brand) {
        if (!brand) return;
        /* فقط مدل‌های دیده‌شده (نتیجهٔ جست‌وجو) تغییر می‌کنند */
        var vis = boxesOf(brand).filter(function (b) {
            var lb = b.closest('.cm-model');
            return lb && !lb.hidden;
        });
        if (!vis.length) return;
        var allOn = vis.every(function (b) { return b.checked; });
        vis.forEach(function (b) { b.checked = !allOn; });
        brand.open = true;
        fire(vis[0]);
    }

    function doAction(kind) {
        if (kind === 'open' || kind === 'close') {
            brands.forEach(function (b) { if (!b.hidden) b.open = (kind === 'open'); });
            if (tempView) savedOpen = brands.map(function (b) { return b.open; });
            return;
        }
        if (kind === 'onlysel') {
            onlySel = !onlySel;
            if (onlyBtn) onlyBtn.classList.toggle('is-on', onlySel);
            update();
            return;
        }
        if (kind === 'clear') {
            var on = [].slice.call(box.querySelectorAll('input[name="categories[]"]:checked'));
            if (!on.length) return;
            if (on.length > 1 && !confirm('انتخاب ' + on.length + ' مدل حذف شود؟')) return;
            on.forEach(function (b) { b.checked = false; });
            fire(on[0]);
        }
    }

    if (searchEl) {
        searchEl.addEventListener('input', update);
        /* Enter داخل کادر جست‌وجو نباید فرم محصول را ارسال کند */
        searchEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) e.preventDefault();
        });
    }
    if (clearEl) clearEl.addEventListener('click', function () {
        searchEl.value = ''; update(); searchEl.focus();
    });

    box.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;

        var off = t.closest('[data-off]');
        if (off) {
            e.preventDefault();
            var inp = box.querySelector('input[name="categories[]"][value="' + off.getAttribute('data-off') + '"]');
            if (inp) { inp.checked = false; fire(inp); }
            return;
        }
        /* «همه» در سر برند: نباید گروه را باز/بسته کند */
        var all = t.closest('.cm-ball');
        if (all) { e.preventDefault(); e.stopPropagation(); toggleBrand(all.closest('.cm-brand')); return; }

        var btn = t.closest('[data-cm]');
        if (btn) { e.preventDefault(); doAction(btn.getAttribute('data-cm')); }
    });

    /* دسترسی با کیبورد برای «همه» (span است، نه button — تا داخل summary مشکل نسازد) */
    box.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ' && e.keyCode !== 13 && e.keyCode !== 32) return;
        if (!e.target || !e.target.closest) return;
        var all = e.target.closest('.cm-ball');
        if (!all) return;
        e.preventDefault(); e.stopPropagation();
        toggleBrand(all.closest('.cm-brand'));
    });

    box.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'categories[]') refresh();
    });

    refresh();
})();

function addVariant() {
    var c = document.getElementById('variantsContainer');
    var row = document.createElement('div');
    row.className = 'variant-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr 1fr .8fr .8fr .8fr 34px;gap:0.5rem;align-items:end;margin-bottom:0.5rem;padding:0.5rem;background:rgba(0,0,0,0.15);border-radius:6px;';

    var co = '<option value="">-- انتخاب --</option>';
    variantCountries.forEach(function(cn){ co += '<option value="'+cn+'">'+cn+'</option>'; });
    var mo = '<option value="">-- انتخاب --</option>';
    variantMakers.forEach(function(mk){ mo += '<option value="'+mk+'">'+mk+'</option>'; });

    row.innerHTML =
        '<div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">کشور سازنده</label><select name="v_country[]" class="form-control" style="font-size:0.78rem;">'+co+'</select></div>' +
        '<div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">شرکت سازنده</label><select name="v_maker[]" class="form-control" style="font-size:0.78rem;">'+mo+'</select></div>' +
        '<div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">قیمت جزئی</label><input type="number" name="v_retail[]" class="form-control" value="" style="font-size:0.78rem;" required></div>' +
        '<div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">قیمت کلی</label><input type="number" name="v_whole[]" class="form-control" value="" style="font-size:0.78rem;" required></div>' +
        '<div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">موجودی</label><input type="number" name="v_stock[]" class="form-control" value="0" style="font-size:0.78rem;" min="0"></div>' +
        '<div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">تخفیف جزئی٪</label><input type="number" name="v_rdisc[]" class="form-control" value="0" style="font-size:0.78rem;" min="0" max="100"></div>' +
        '<div class="form-group" style="margin:0;"><label style="font-size:0.72rem;">تخفیف کلی٪</label><input type="number" name="v_wdisc[]" class="form-control" value="0" style="font-size:0.78rem;" min="0" max="100"></div>' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="var r=this.parentNode;r.parentNode.removeChild(r)" style="margin-bottom:2px;padding:0.2rem 0.4rem;font-size:0.7rem;">X</button>';
    c.appendChild(row);
}

/* خواستهٔ کاربر: «توی قسمتِ اضافه‌کردنِ محصول هم مدل‌های خودرو رو نیازی
   نباشه انتخاب کنم وقتی اون تیک‌ها رو زده باشم» — این‌جا فقط ظاهری کم‌رنگ
   می‌شود (نه غیرفعال) چون سرور از قبل هیچ‌کدام را الزامی نمی‌کند؛ هدف فقط
   نشان‌دادنِ واضح این است که دیگر لازم نیست. */
(function(){
    var noBrand = document.getElementById('no_brand_required');
    var noModel = document.getElementById('no_model_required');
    var noYear  = document.getElementById('no_year_required');
    var cmBox   = document.getElementById('cmBox');
    var yearBox = document.getElementById('yearReqBox');

    function fade(el, on) { if (el) el.style.opacity = on ? '0.4' : '1'; }
    function sync() {
        fade(cmBox, (noBrand && noBrand.checked) || (noModel && noModel.checked));
        fade(yearBox, noYear && noYear.checked);
    }
    [noBrand, noModel, noYear].forEach(function(el){ if (el) el.addEventListener('change', sync); });
    sync();
})();
</script>

<?php require_once __DIR__ . '/layout-bottom.php';