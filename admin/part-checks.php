<?php
/* صفِ بررسیِ «عکس نمونهٔ قطعه» — سمتِ ادمین.
   کارشناس عکس‌های مشتری را می‌بیند، با کالای سبد مقایسه می‌کند و در همین‌جا هم
   «مطابقت قطعه» را تأیید/رد می‌کند و هم «موجودی» را — خواستهٔ مدیر: «بعد ادمین
   که عکس رو تایید کرد همونجا تایید موجودی رو ببینه».
   POST به الگوی PRG است و پیش از include قالب انجام می‌شود. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$ready = partChecksReady();
$tab   = (string)($_GET['tab'] ?? 'pending');
if (!in_array($tab, ['pending', 'approved', 'rejected', 'all'], true)) $tab = 'pending';

/* ---------- ثبتِ داوری ---------- */
if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['pc_id'] ?? 0);
    $act  = (string)($_POST['pc_do'] ?? '');
    $note = mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 255);
    $sOk  = !empty($_POST['stock_ok']) ? 1 : 0;
    $sNo  = mb_substr(trim((string)($_POST['stock_note'] ?? '')), 0, 160);
    $back = 'part-checks.php?tab=' . urlencode($tab);

    if ($id > 0 && in_array($act, ['approve', 'reject'], true)) {
        try {
            $pdo->prepare("UPDATE part_checks
                              SET status = ?, admin_note = ?, stock_ok = ?, stock_note = ?, reviewed_at = NOW()
                            WHERE id = ?")
                ->execute([$act === 'approve' ? 'approved' : 'rejected', $note, $sOk, $sNo, $id]);
            header('Location: ' . $back . '&msg=' . ($act === 'approve' ? 'ok' : 'no') . '#pc' . $id);
            exit;
        } catch (Throwable $e) {
            header('Location: ' . $back . '&msg=err'); exit;
        }
    }
    /* برگرداندن به «در انتظار» تا اگر اشتباه داوری شد قابل اصلاح باشد */
    if ($id > 0 && $act === 'reopen') {
        try {
            $pdo->prepare("UPDATE part_checks SET status = 'pending', reviewed_at = NULL WHERE id = ?")->execute([$id]);
            header('Location: ' . $back . '&msg=re#pc' . $id); exit;
        } catch (Throwable $e) {
            header('Location: ' . $back . '&msg=err'); exit;
        }
    }
    header('Location: ' . $back); exit;
}

/* ---------- خواندنِ فهرست ---------- */
$rows = []; $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
if ($ready) {
    try {
        foreach ($pdo->query("SELECT status, COUNT(*) n FROM part_checks GROUP BY status") as $r) {
            $counts[$r['status']] = (int)$r['n'];
            $counts['all'] += (int)$r['n'];
        }
        $sql = "SELECT pc.*, p.name product_name, p.image product_image, p.technical_number, p.stock,
                       cu.full_name, cu.mobile, cu.city
                  FROM part_checks pc
                  LEFT JOIN products  p  ON p.id  = pc.product_id
                  LEFT JOIN customers cu ON cu.id = pc.customer_id";
        if ($tab !== 'all') $sql .= " WHERE pc.status = ?";
        $sql .= " ORDER BY pc.id DESC LIMIT 200";
        $st = $pdo->prepare($sql);
        $st->execute($tab !== 'all' ? [$tab] : []);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { $rows = []; }
}

/* عکس‌های همهٔ ردیف‌ها در یک کوئری */
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
    عکس‌ها را با کالای سبد خرید مشتری مقایسه کنید. با <b>تأیید</b>، کلیدِ «ثبت سفارش و پرداخت» برای
    مشتری باز می‌شود؛ تیکِ <b>موجودی</b> و یادداشتِ شما هم همان‌جا به او نشان داده می‌شود.
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
            <div class="pcadm-review-title"><?= icon('shield-check', 'ic-sm') ?> داوری این درخواست</div>

            <div class="pcadm-stock">
                <label class="pcadm-check">
                    <input type="checkbox" name="stock_ok" value="1" checked>
                    <?= icon('package', 'ic-sm') ?> موجودی کالا را تأیید می‌کنم
                </label>
                <input type="text" name="stock_note" class="form-control" maxlength="160" style="flex:1 1 12rem;min-width:0;"
                       placeholder="توضیح موجودی (اختیاری) — مثلاً: ۲ عدد موجود، برای شما کنار گذاشته شد">
            </div>

            <div class="pcadm-row">
                <input type="text" name="admin_note" class="form-control" maxlength="255"
                       placeholder="یادداشت برای مشتری (اختیاری) — در صورتِ رد، دلیل را این‌جا بنویسید">
            </div>

            <div class="pcadm-acts">
                <button type="submit" name="pc_do" value="approve" class="btn btn-success btn-sm">
                    <?= icon('check-circle', 'ic-sm') ?> تأیید مطابقت و موجودی
                </button>
                <button type="submit" name="pc_do" value="reject" class="btn btn-danger btn-sm"
                        onclick="return confirm('این درخواست «تأیید نشد» ثبت شود؟ توضیح شما به مشتری نشان داده می‌شود.');">
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
                <?php if ($st === 'approved'): ?>
                <div class="pchk-confirm-row <?= !empty($r['stock_ok']) ? 'is-ok' : 'is-soft' ?>">
                    <?= icon(!empty($r['stock_ok']) ? 'package' : 'clock', 'ic-sm') ?>
                    <b>موجودی:</b> <?= !empty($r['stock_ok']) ? 'تأیید شد' : 'تأیید نشد' ?>
                    <?php if (trim((string)$r['stock_note']) !== ''): ?>
                    <span class="pchk-confirm-note">— <?= h((string)$r['stock_note']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
