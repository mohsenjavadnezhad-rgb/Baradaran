<?php
/* صف بررسی «عکس نمونهٔ قطعه» — سمت ادمین.
   کارشناس عکس‌ها را با کالای سبد مقایسه می‌کند و فقط «مطابقت قطعه» را
   تأیید/رد می‌کند. ۲۰۲۶-۰۹-۰۳ (خواستهٔ کاربر): «تأیید موجودی» از اینجا جدا
   شد — صف/تب/اقدام خودش را در admin/stock-checks.php دارد تا یک همکار
   دیگر بتواند مستقل همان را ببیند و تأیید کند، بدون نیاز به دیدن عکس‌ها یا
   اثرگذاری روی مطابقت قطعه (و برعکس). فقط ردیف‌هایی که واقعا عکس/رد-کردن
   دارند اینجا دیده می‌شوند (photo_required=1)؛ ردیف‌های خالص «فقط موجودی»
   (وقتی بررسی عکس خاموش است) هرگز اینجا نمی‌آیند.
   POST به الگوی PRG است و پیش از include قالب انجام می‌شود. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$ready      = partChecksReady();
$splitReady = partCheckStockSplitReady();
$tab   = (string)($_GET['tab'] ?? 'pending');
if (!in_array($tab, ['pending', 'approved', 'rejected', 'all'], true)) $tab = 'pending';

/* ---------- ثبت داوری ---------- */
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['pc_id'] ?? 0);
    $act  = (string)($_POST['pc_do'] ?? '');
    $note = mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 255);
    $back = 'part-checks.php?tab=' . urlencode($tab);

    if ($id > 0 && in_array($act, ['approve', 'reject'], true)) {
        try {
            /* دفاع دوم: حتی اگر یک لینک/فرم قدیمی برای ردیف «فقط موجودی»
               (photo_required=0) رسید، این صفحه هرگز آن را لمس نمی‌کند —
               فقط ردیف‌هایی که واقعا در همین صف دیده می‌شوند قابل داوری‌اند. */
            $sql = "UPDATE part_checks SET status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?";
            if ($splitReady) $sql .= " AND photo_required = 1";
            $pdo->prepare($sql)->execute([$act === 'approve' ? 'approved' : 'rejected', $note, $id]);
            header('Location: ' . $back . '&msg=' . ($act === 'approve' ? 'ok' : 'no') . '#pc' . $id);
            exit;
        } catch (Throwable $e) {
            header('Location: ' . $back . '&msg=err'); exit;
        }
    }
    /* برگرداندن به «در انتظار» تا اگر اشتباه داوری شد قابل اصلاح باشد */
    if ($id > 0 && $act === 'reopen') {
        try {
            $sql = "UPDATE part_checks SET status = 'pending', reviewed_at = NULL WHERE id = ?";
            if ($splitReady) $sql .= " AND photo_required = 1";
            $pdo->prepare($sql)->execute([$id]);
            header('Location: ' . $back . '&msg=re#pc' . $id); exit;
        } catch (Throwable $e) {
            header('Location: ' . $back . '&msg=err'); exit;
        }
    }
    header('Location: ' . $back); exit;
}

/* ---------- خواندن فهرست ---------- */
$rows = []; $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
if ($ready) {
    try {
        $cq = "SELECT status, COUNT(*) n FROM part_checks";
        if ($splitReady) $cq .= " WHERE photo_required = 1";
        $cq .= " GROUP BY status";
        foreach ($pdo->query($cq) as $r) {
            $counts[$r['status']] = (int)$r['n'];
            $counts['all'] += (int)$r['n'];
        }
        $sql = "SELECT pc.*, p.name product_name, p.image product_image, p.technical_number, p.stock,
                       cu.full_name, cu.mobile, cu.city
                  FROM part_checks pc
                  LEFT JOIN products  p  ON p.id  = pc.product_id
                  LEFT JOIN customers cu ON cu.id = pc.customer_id";
        $where = [];
        if ($splitReady) $where[] = "pc.photo_required = 1";
        if ($tab !== 'all') $where[] = "pc.status = ?";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY pc.id DESC LIMIT 200";
        $st = $pdo->prepare($sql);
        $st->execute($tab !== 'all' ? [$tab] : []);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { $rows = []; }
}

/* عکس‌های همه ردیف‌ها در یک کوئری */
$imgMap = [];
if ($rows) {
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT check_id, image FROM part_check_images
                              WHERE check_id IN ($in) ORDER BY sort_order, id");
        $st->execute($ids);
        foreach ($st->fetchAll() as $r) $imgMap[(int)$r['check_id']][] = (string)$r['image'];
    } catch (Throwable $e) {}
}

$msg = (string)($_GET['msg'] ?? '');
$tabs = ['pending' => 'در انتظار بررسی', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'all' => 'همه'];

require_once __DIR__ . '/layout-top.php';
?>
<?php /* ۲۰۲۶-۰۹-۰۳ (پیگیری، خواستهٔ کاربر): دکمهٔ «تأیید موجودی» از هدر این
        صفحه حذف شد — کاربر گزارش داد همین دکمه باعث می‌شد وسط بررسی عکس،
        کارشناس ناخواسته/گیج‌کننده برود موجودی را هم تأیید کند و به‌نظر
        برسد «تأیید عکس، موجودی را هم تأیید کرد». این دو صفحه واقعا باید
        کاملا مجزا بمانند؛ رفتن به تأیید موجودی از منوی کناری کافی است. */ ?>
<div class="admin-header">
    <h2 style="color:var(--text-primary);"><?= icon('camera', 'ic-sm') ?> بررسی عکس نمونهٔ قطعه</h2>
    <a href="orders.php" class="btn btn-secondary btn-sm">سفارشات</a>
</div>

<?php if (!$ready): ?>
<div class="flash flash-error">
    <?= icon('alert', 'ic-sm') ?> جدول‌های این بخش ساخته نشده‌اند. یک‌بار فایل <b>migrate-partcheck.php</b> را اجرا کنید.
</div>
<?php else: ?>

<?php if ($msg === 'ok'): ?><div class="flash flash-success"><?= icon('check-circle', 'ic-sm') ?> قطعه تأیید شد؛ مشتری می‌تواند سفارش را ثبت کند.</div><?php endif; ?>
<?php if ($msg === 'no'): ?><div class="flash flash-success"><?= icon('info', 'ic-sm') ?> به‌عنوان «تأیید نشد» ثبت شد و توضیح شما به مشتری نشان داده می‌شود.</div><?php endif; ?>
<?php if ($msg === 're'): ?><div class="flash flash-success"><?= icon('refresh', 'ic-sm') ?> به حالت «در انتظار بررسی» برگشت.</div><?php endif; ?>
<?php if ($msg === 'err'): ?><div class="flash flash-error"><?= icon('alert', 'ic-sm') ?> ثبت انجام نشد. دوباره تلاش کنید.</div><?php endif; ?>

<p style="color:var(--text-muted);font-size:0.83rem;line-height:1.9;margin-bottom:1rem;">
    <?= icon('info', 'ic-sm') ?>
    عکس‌ها را با کالای سبد خرید مشتری مقایسه کنید و فقط <b>مطابقت قطعه</b> را تأیید/رد کنید.
    «تأیید موجودی» کاملا جدا و در بخش دیگری از پنل بررسی می‌شود؛ اینجا کاری با آن ندارید.
</p>

<div class="pcadm-tabs">
    <?php foreach ($tabs as $k => $lbl): ?>
    <a href="part-checks.php?tab=<?= h($k) ?>" class="pcadm-tab<?= $tab === $k ? ' is-on' : '' ?>">
        <?= h($lbl) ?> <span class="pcadm-tab-n"><?= (int)$counts[$k] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
<div class="pcadm-empty"><?= icon('camera') ?><br>در این دسته درخواستی نیست.</div>
<?php else: ?>
<div class="pcadm-list">
    <?php foreach ($rows as $r):
        $st    = (string)$r['status'];
        $imgs  = $imgMap[(int)$r['id']] ?? [];
        $badge = ['pending' => 'is-wait', 'approved' => 'is-ok', 'rejected' => 'is-no'][$st] ?? 'is-wait';
        $bIcon = ['pending' => 'clock', 'approved' => 'check-circle', 'rejected' => 'x-circle'][$st] ?? 'clock';
    ?>
    <div class="pcadm-card is-<?= h($st) ?>" id="pc<?= (int)$r['id'] ?>">
        <div class="pcadm-top">
            <span class="pcadm-id">#<?= (int)$r['id'] ?></span>
            <span class="pchk-badge <?= $badge ?>"><?= icon($bIcon, 'ic-sm') ?> <?= h(partCheckStatusLabel($st)) ?></span>
            <?php if (!empty($r['order_id'])): ?>
            <a href="order-detail.php?id=<?= (int)$r['order_id'] ?>" class="btn btn-secondary btn-sm">
                <?= icon('clipboard-list', 'ic-sm') ?> سفارش #<?= (int)$r['order_id'] ?>
            </a>
            <?php else: ?>
            <span class="badge-pending">هنوز سفارشی ثبت نشده</span>
            <?php endif; ?>
            <span class="pchk-when"><?= icon('calendar', 'ic-sm') ?> <?= h(jDate($r['created_at'], true)) ?></span>
        </div>

        <div class="pcadm-grid">
            <div class="pcadm-f">
                <span>مشتری</span>
                <b><?= h((string)($r['full_name'] ?: '—')) ?></b>
                <?php if (!empty($r['customer_id'])): ?>
                — <a href="customer-detail.php?id=<?= (int)$r['customer_id'] ?>">پروندهٔ مشتری</a>
                <?php endif; ?>
                <?php if (trim((string)$r['mobile']) !== ''): ?>
                <br><a href="tel:<?= h((string)$r['mobile']) ?>" dir="ltr"><?= h((string)$r['mobile']) ?></a>
                <?php endif; ?>
                <?php if (trim((string)$r['city']) !== ''): ?> — <?= h((string)$r['city']) ?><?php endif; ?>
            </div>

            <div class="pcadm-f">
                <span>کالای سبد خرید</span>
                <?php if (!empty($r['product_id']) && $r['product_name'] !== null): ?>
                <a href="product-edit.php?id=<?= (int)$r['product_id'] ?>" target="_blank"><b><?= h((string)$r['product_name']) ?></b></a>
                <?php if (trim((string)$r['technical_number']) !== ''): ?>
                <br>شماره فنی: <span dir="ltr"><?= h((string)$r['technical_number']) ?></span>
                <?php endif; ?>
                <br>موجودی انبار: <b><?= (int)$r['stock'] ?></b> عدد
                <?php else: ?>
                <b>—</b> (کالا مشخص نشده یا حذف شده)
                <?php endif; ?>
            </div>

            <div class="pcadm-f">
                <span>خودروی مشتری</span>
                <b><?= trim((string)$r['car_info']) !== '' ? h((string)$r['car_info']) : '—' ?></b>
            </div>

            <div class="pcadm-f">
                <span>توضیح مشتری</span>
                <?= trim((string)$r['note']) !== '' ? nl2br(h((string)$r['note'])) : '—' ?>
            </div>
        </div>

        <?php if ($imgs): ?>
        <div class="pchk-sent-head"><?= icon('camera', 'ic-sm') ?> <?= count($imgs) ?> عکس ارسالی — برای اندازهٔ کامل روی هر عکس بزنید</div>
        <div class="pchk-thumbs">
            <?php foreach ($imgs as $i => $im): $src = '../uploads/partchecks/' . basename($im); ?>
            <a href="<?= h($src) ?>" target="_blank" rel="noopener" class="pchk-thumb" title="عکس <?= (int)$i + 1 ?>">
                <img src="<?= h($src) ?>" alt="عکس <?= (int)$i + 1 ?>" loading="lazy">
                <span class="pchk-thumb-n"><?= (int)$i + 1 ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="pchk-sent-line"><?= icon('alert', 'ic-sm') ?> عکسی برای این درخواست ثبت نشده است.</p>
        <?php endif; ?>

        <?php if ($st === 'pending'): ?>
        <form method="POST" action="part-checks.php?tab=<?= h($tab) ?>" class="pcadm-review">
            <input type="hidden" name="pc_id" value="<?= (int)$r['id'] ?>">
            <div class="pcadm-review-title"><?= icon('camera', 'ic-sm') ?> داوری مطابقت قطعه</div>

            <div class="pcadm-row">
                <input type="text" name="admin_note" class="form-control" maxlength="255"
                       placeholder="یادداشت برای مشتری (اختیاری) — در صورت رد، دلیل را این‌جا بنویسید">
            </div>

            <div class="pcadm-acts">
                <button type="submit" name="pc_do" value="approve" class="btn btn-success btn-sm">
                    <?= icon('check-circle', 'ic-sm') ?> تأیید مطابقت قطعه
                </button>
                <button type="submit" name="pc_do" value="reject" class="btn btn-danger btn-sm"
                        data-confirm="این درخواست «تأیید نشد» ثبت شود؟ توضیح شما به مشتری نشان داده می‌شود."
                        data-confirm-icon="alert" data-confirm-label="ثبت شود">
                    <?= icon('x-circle', 'ic-sm') ?> تأیید نشد
                </button>
            </div>
        </form>
        <?php else: ?>
        <div class="pcadm-review">
            <div class="pchk-confirm">
                <div class="pchk-confirm-row <?= $st === 'approved' ? 'is-ok' : '' ?>">
                    <?= icon($st === 'approved' ? 'check-circle' : 'x-circle', 'ic-sm') ?>
                    <b>مطابقت قطعه:</b> <?= h(partCheckStatusLabel($st)) ?>
                    <?php if (!empty($r['reviewed_at'])): ?>
                    <span class="pchk-confirm-note">— <?= h(jDate($r['reviewed_at'], true)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (trim((string)$r['admin_note']) !== ''): ?>
            <p class="pchk-adminnote"><?= icon('message', 'ic-sm') ?> <b>یادداشت شما:</b> <?= nl2br(h((string)$r['admin_note'])) ?></p>
            <?php endif; ?>
            <form method="POST" action="part-checks.php?tab=<?= h($tab) ?>" class="pcadm-acts">
                <input type="hidden" name="pc_id" value="<?= (int)$r['id'] ?>">
                <button type="submit" name="pc_do" value="reopen" class="btn btn-secondary btn-sm">
                    <?= icon('refresh', 'ic-sm') ?> بازگرداندن به «در انتظار بررسی»
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>
