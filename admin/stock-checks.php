<?php
/* صف «تأیید موجودی» — سمت ادمین. ۲۰۲۶-۰۹-۰۳ (خواستهٔ کاربر): از صف «بررسی
   عکس نمونهٔ قطعه» (part-checks.php) کاملا جدا شده تا یک همکار دیگر بتواند
   مستقل فقط همین را ببیند و تأیید کند — بدون نیاز به دیدن عکس مشتری، و بدون
   اثرگذاری روی مطابقت قطعه (و برعکس). همه ردیف‌های part_checks اینجا دیده
   می‌شوند (چه از part-check.php آمده باشند چه از stockCheckEnsureRow() —
   یعنی حالت «فقط موجودی» که بررسی عکس اصلا خاموش است)، چون در هر دو حالت
   موجودی باید همین‌جا سنجیده شود.
   ستون‌های مستقل: stock_status/stock_reviewed_at/stock_admin_note — هرگز به
   admin_note/status (مال صف عکس) دست نمی‌زنند و برعکس.
   POST به الگوی PRG است و پیش از include قالب انجام می‌شود. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$ready = partCheckStockSplitReady();
$tab   = (string)($_GET['tab'] ?? 'pending');
if (!in_array($tab, ['pending', 'approved', 'rejected', 'all'], true)) $tab = 'pending';

/* ---------- ثبت داوری ---------- */
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['pc_id'] ?? 0);
    $act   = (string)($_POST['pc_do'] ?? '');
    $sNote = mb_substr(trim((string)($_POST['stock_note'] ?? '')), 0, 160);
    $aNote = mb_substr(trim((string)($_POST['stock_admin_note'] ?? '')), 0, 255);
    $back  = 'stock-checks.php?tab=' . urlencode($tab);

    if ($id > 0 && in_array($act, ['approve', 'reject'], true)) {
        $newStatus = $act === 'approve' ? 'approved' : 'rejected';
        try {
            $pdo->prepare("UPDATE part_checks
                              SET stock_status = ?, stock_note = ?, stock_admin_note = ?,
                                  stock_ok = ?, stock_reviewed_at = NOW()
                            WHERE id = ?")
                ->execute([$newStatus, $sNote, $aNote, $newStatus === 'approved' ? 1 : 0, $id]);
            header('Location: ' . $back . '&msg=' . ($act === 'approve' ? 'ok' : 'no') . '#sc' . $id);
            exit;
        } catch (Throwable $e) {
            header('Location: ' . $back . '&msg=err'); exit;
        }
    }
    /* برگرداندن به «در انتظار» تا اگر اشتباه داوری شد قابل اصلاح باشد */
    if ($id > 0 && $act === 'reopen') {
        try {
            $pdo->prepare("UPDATE part_checks SET stock_status = 'pending', stock_ok = 0, stock_reviewed_at = NULL WHERE id = ?")->execute([$id]);
            header('Location: ' . $back . '&msg=re#sc' . $id); exit;
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
        foreach ($pdo->query("SELECT stock_status, COUNT(*) n FROM part_checks GROUP BY stock_status") as $r) {
            $counts[$r['stock_status']] = (int)$r['n'];
            $counts['all'] += (int)$r['n'];
        }
        $sql = "SELECT pc.*, p.name product_name, p.technical_number, p.stock,
                       cu.full_name, cu.mobile, cu.city
                  FROM part_checks pc
                  LEFT JOIN products  p  ON p.id  = pc.product_id
                  LEFT JOIN customers cu ON cu.id = pc.customer_id";
        if ($tab !== 'all') $sql .= " WHERE pc.stock_status = ?";
        $sql .= " ORDER BY pc.id DESC LIMIT 200";
        $st = $pdo->prepare($sql);
        $st->execute($tab !== 'all' ? [$tab] : []);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { $rows = []; }
}

$msg  = (string)($_GET['msg'] ?? '');
$tabs = ['pending' => 'در انتظار بررسی', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'all' => 'همه'];

require_once __DIR__ . '/layout-top.php';
?>
<div class="admin-header">
    <h2 style="color:var(--text-primary);"><?= icon('shield-check', 'ic-sm') ?> تأیید موجودی</h2>
    <div style="display:flex;gap:0.5rem;">
        <a href="part-checks.php" class="btn btn-secondary btn-sm"><?= icon('camera', 'ic-sm') ?> بررسی عکس قطعه</a>
        <a href="orders.php" class="btn btn-secondary btn-sm">سفارشات</a>
    </div>
</div>

<?php if (!$ready): ?>
<div class="flash flash-error">
    <?= icon('alert', 'ic-sm') ?> ستون‌های این بخش ساخته نشده‌اند. یک‌بار فایل <b>migrate-partcheck-split.php</b> را اجرا کنید
    (پیش از آن، <b>migrate-partcheck.php</b> باید اجرا شده باشد).
</div>
<?php else: ?>

<?php if ($msg === 'ok'): ?><div class="flash flash-success"><?= icon('check-circle', 'ic-sm') ?> موجودی تأیید شد؛ اگر بررسی عکس هم فعال بود و تأیید شده، مشتری می‌تواند سفارش را ثبت کند.</div><?php endif; ?>
<?php if ($msg === 'no'): ?><div class="flash flash-success"><?= icon('info', 'ic-sm') ?> به‌عنوان «موجودی ندارد» ثبت شد و توضیح شما به مشتری نشان داده می‌شود.</div><?php endif; ?>
<?php if ($msg === 're'): ?><div class="flash flash-success"><?= icon('refresh', 'ic-sm') ?> به حالت «در انتظار بررسی» برگشت.</div><?php endif; ?>
<?php if ($msg === 'err'): ?><div class="flash flash-error"><?= icon('alert', 'ic-sm') ?> ثبت انجام نشد. دوباره تلاش کنید.</div><?php endif; ?>

<p style="color:var(--text-muted);font-size:0.83rem;line-height:1.9;margin-bottom:1rem;">
    <?= icon('info', 'ic-sm') ?>
    موجودی انبار کالای درخواستی مشتری را بررسی کنید و <b>موجودی</b> را تأیید/رد کنید — کاری به
    عکس یا مطابقت قطعه ندارید، آن مستقل و در صفحهٔ <a href="part-checks.php">بررسی عکس قطعه</a>
    بررسی می‌شود. مشتری وقتی می‌تواند سفارش را ثبت کند که هر دو مرحلهٔ فعال (هرکدام روشن باشد)
    تأیید شده باشد.
</p>

<div class="pcadm-tabs">
    <?php foreach ($tabs as $k => $lbl): ?>
    <a href="stock-checks.php?tab=<?= h($k) ?>" class="pcadm-tab<?= $tab === $k ? ' is-on' : '' ?>">
        <?= h($lbl) ?> <span class="pcadm-tab-n"><?= (int)$counts[$k] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
<div class="pcadm-empty"><?= icon('shield-check') ?><br>در این دسته درخواستی نیست.</div>
<?php else: ?>
<div class="pcadm-list">
    <?php foreach ($rows as $r):
        $ss    = (string)$r['stock_status'];
        $badge = ['pending' => 'is-wait', 'approved' => 'is-ok', 'rejected' => 'is-no'][$ss] ?? 'is-wait';
        $bIcon = ['pending' => 'clock', 'approved' => 'check-circle', 'rejected' => 'x-circle'][$ss] ?? 'clock';
        $ps    = (string)$r['status'];
        $psBadge = ['pending' => 'is-wait', 'approved' => 'is-ok', 'rejected' => 'is-no'][$ps] ?? 'is-wait';
        $psIcon  = ['pending' => 'clock', 'approved' => 'check-circle', 'rejected' => 'x-circle'][$ps] ?? 'clock';
    ?>
    <div class="pcadm-card is-<?= h($ss) ?>" id="sc<?= (int)$r['id'] ?>">
        <div class="pcadm-top">
            <span class="pcadm-id">#<?= (int)$r['id'] ?></span>
            <span class="pchk-badge <?= $badge ?>"><?= icon($bIcon, 'ic-sm') ?> <?= h(partCheckStatusLabel($ss)) ?></span>
            <?php if (!empty($r['order_id'])): ?>
            <a href="order-detail.php?id=<?= (int)$r['order_id'] ?>" class="btn btn-secondary btn-sm">
                <?= icon('clipboard-list', 'ic-sm') ?> سفارش #<?= (int)$r['order_id'] ?>
            </a>
            <?php else: ?>
            <span class="badge-pending">هنوز سفارشی ثبت نشده</span>
            <?php endif; ?>
            <span class="pchk-when"><?= icon('calendar', 'ic-sm') ?> <?= h(jDate($r['created_at'], true)) ?></span>
        </div>

        <?php if (empty($r['photo_required'])): ?>
        <p class="pchk-sent-line"><?= icon('info', 'ic-sm') ?> این درخواست <b>فقط برای موجودی</b> ساخته شده — بررسی عکس برایش فعال نیست.</p>
        <?php else: ?>
        <p class="pchk-sent-line">
            <?= icon('camera', 'ic-sm') ?> وضعیت مستقل <b>مطابقت قطعه</b>:
            <span class="pchk-badge <?= $psBadge ?>"><?= icon($psIcon, 'ic-sm') ?> <?= h(partCheckStatusLabel($ps)) ?></span>
            — <a href="part-checks.php?tab=all#pc<?= (int)$r['id'] ?>">در صفحهٔ «بررسی عکس قطعه»</a>
        </p>
        <?php endif; ?>

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

        <?php if ($ss === 'pending'): ?>
        <form method="POST" action="stock-checks.php?tab=<?= h($tab) ?>" class="pcadm-review">
            <input type="hidden" name="pc_id" value="<?= (int)$r['id'] ?>">
            <div class="pcadm-review-title"><?= icon('shield-check', 'ic-sm') ?> داوری موجودی</div>

            <div class="pcadm-row">
                <input type="text" name="stock_note" class="form-control" maxlength="160"
                       placeholder="توضیح موجودی (اختیاری) — مثلا: ۲ عدد موجود، برای شما کنار گذاشته شد">
            </div>
            <div class="pcadm-row">
                <input type="text" name="stock_admin_note" class="form-control" maxlength="255"
                       placeholder="یادداشت برای مشتری (اختیاری) — در صورت نبود موجودی، دلیل را این‌جا بنویسید">
            </div>

            <div class="pcadm-acts">
                <button type="submit" name="pc_do" value="approve" class="btn btn-success btn-sm">
                    <?= icon('check-circle', 'ic-sm') ?> موجودی کالا را تأیید می‌کنم
                </button>
                <button type="submit" name="pc_do" value="reject" class="btn btn-danger btn-sm"
                        onclick="return confirm('این درخواست «موجودی ندارد» ثبت شود؟ توضیح شما به مشتری نشان داده می‌شود.');">
                    <?= icon('x-circle', 'ic-sm') ?> موجودی ندارد
                </button>
            </div>
        </form>
        <?php else: ?>
        <div class="pcadm-review">
            <div class="pchk-confirm">
                <div class="pchk-confirm-row <?= $ss === 'approved' ? 'is-ok' : '' ?>">
                    <?= icon($ss === 'approved' ? 'check-circle' : 'x-circle', 'ic-sm') ?>
                    <b>موجودی:</b> <?= h(partCheckStatusLabel($ss)) ?>
                    <?php if (!empty($r['stock_reviewed_at'])): ?>
                    <span class="pchk-confirm-note">— <?= h(jDate($r['stock_reviewed_at'], true)) ?></span>
                    <?php endif; ?>
                    <?php if (trim((string)$r['stock_note']) !== ''): ?>
                    <span class="pchk-confirm-note">— <?= h((string)$r['stock_note']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (trim((string)$r['stock_admin_note']) !== ''): ?>
            <p class="pchk-adminnote"><?= icon('message', 'ic-sm') ?> <b>یادداشت شما:</b> <?= nl2br(h((string)$r['stock_admin_note'])) ?></p>
            <?php endif; ?>
            <form method="POST" action="stock-checks.php?tab=<?= h($tab) ?>" class="pcadm-acts">
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
