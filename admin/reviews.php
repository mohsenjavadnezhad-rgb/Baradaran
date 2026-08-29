<?php
/* ------------------------------------------------------------------
   مدیریت نظرها و پرسش‌وپاسخ محصولات
   ------------------------------------------------------------------
   نظر و پرسشِ مشتری به‌صورت pending ثبت می‌شود و تا تأیید این‌جا دیده
   نمی‌شود. پاسخ فروشگاه (این صفحه یا فرمِ صفحهٔ محصول برای ادمینِ
   واردشده) خودکار تأیید و منتشر می‌شود.
   ------------------------------------------------------------------ */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$ready = reviewsReady();

/* ---------- اکشن‌ها (PRG — پیش از هر خروجی) ---------- */
$msgMap = [
    'approved' => 'تأیید و منتشر شد.',
    'rejected' => 'رد شد و از سایت برداشته شد.',
    'pended'   => 'به صف انتظار برگشت.',
    'deleted'  => 'حذف شد.',
    'answered' => 'پاسخ فروشگاه ثبت و منتشر شد.',
    'empty'    => 'متن پاسخ خالی بود؛ چیزی ثبت نشد.',
];
$msg = isset($_GET['msg']) ? ($msgMap[$_GET['msg']] ?? '') : '';

/* t=r → جدول نظرها، t=q → جدول پرسش‌وپاسخ */
function reviewTable($t) { return $t === 'q' ? 'product_qa' : 'product_reviews'; }

if ($ready) {
    $t = ($_GET['t'] ?? 'r') === 'q' ? 'q' : 'r';
    $tbl = reviewTable($t);

    if (isset($_GET['set'], $_GET['id'])) {
        $to = $_GET['set'];
        if (in_array($to, ['approved', 'rejected', 'pending'], true)) {
            $pdo->prepare("UPDATE {$tbl} SET status = ? WHERE id = ?")->execute([$to, (int)$_GET['id']]);
            $codes = ['approved' => 'approved', 'rejected' => 'rejected', 'pending' => 'pended'];
            redirect('reviews.php?msg=' . $codes[$to]);
        }
        redirect('reviews.php');
    }

    if (isset($_GET['del'])) {
        $delId = (int)$_GET['del'];
        /* حذف یک پرسش، پاسخ‌هایش را هم بی‌صاحب نگذارد */
        if ($t === 'q') $pdo->prepare("DELETE FROM product_qa WHERE id = ? OR parent_id = ?")->execute([$delId, $delId]);
        else            $pdo->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$delId]);
        redirect('reviews.php?msg=deleted');
    }

    /* پاسخ رسمی فروشگاه به یک پرسش */
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['answer_to'])) {
        $parentId = (int)$_POST['answer_to'];
        $body = trim((string)($_POST['body'] ?? ''));
        if (function_exists('mb_substr')) $body = mb_substr($body, 0, 2000, 'UTF-8');
        else                              $body = substr($body, 0, 2000);

        if ($body === '') redirect('reviews.php?msg=empty#q' . $parentId);

        $st = $pdo->prepare("SELECT product_id FROM product_qa WHERE id = ? AND parent_id IS NULL");
        $st->execute([$parentId]);
        $qProduct = $st->fetchColumn();
        if ($qProduct !== false) {
            $pdo->prepare("INSERT INTO product_qa
                    (product_id, parent_id, customer_id, is_admin, author_name, body, status)
                    VALUES (?,?,NULL,1,'فروشگاه',?, 'approved')")
                ->execute([(int)$qProduct, $parentId, $body]);
            /* پرسشِ بی‌پاسخ با ثبت پاسخ، خودش هم منتشر شود */
            $pdo->prepare("UPDATE product_qa SET status = 'approved' WHERE id = ? AND status = 'pending'")
                ->execute([$parentId]);
        }
        redirect('reviews.php?msg=answered#q' . $parentId);
    }
}

/* ---------- داده‌ها ---------- */
$pendingReviews = $pendingQa = $approvedReviews = $threads = [];
$stats = ['pr' => 0, 'pq' => 0, 'ar' => 0, 'aq' => 0];

if ($ready) {
    $pendingReviews = $pdo->query("SELECT r.*, p.name AS product_name
        FROM product_reviews r LEFT JOIN products p ON p.id = r.product_id
        WHERE r.status = 'pending' ORDER BY r.id DESC")->fetchAll();

    $pendingQa = $pdo->query("SELECT q.*, p.name AS product_name
        FROM product_qa q LEFT JOIN products p ON p.id = q.product_id
        WHERE q.status = 'pending' ORDER BY q.id DESC")->fetchAll();

    $approvedReviews = $pdo->query("SELECT r.*, p.name AS product_name
        FROM product_reviews r LEFT JOIN products p ON p.id = r.product_id
        WHERE r.status = 'approved' ORDER BY r.id DESC LIMIT 60")->fetchAll();

    /* نخ‌های پرسش‌وپاسخ: پرسش‌های تأییدشده یا در انتظار + همهٔ پاسخ‌هایشان */
    $qs = $pdo->query("SELECT q.*, p.name AS product_name
        FROM product_qa q LEFT JOIN products p ON p.id = q.product_id
        WHERE q.parent_id IS NULL AND q.status <> 'rejected' ORDER BY q.id DESC LIMIT 40")->fetchAll();
    if ($qs) {
        foreach ($qs as $q) { $q['answers'] = []; $threads[(int)$q['id']] = $q; }
        $ids = implode(',', array_map('intval', array_keys($threads)));
        $ans = $pdo->query("SELECT * FROM product_qa WHERE parent_id IN ($ids) ORDER BY id")->fetchAll();
        foreach ($ans as $a) {
            $pid = (int)$a['parent_id'];
            if (isset($threads[$pid])) $threads[$pid]['answers'][] = $a;
        }
    }

    $stats = [
        'pr' => count($pendingReviews),
        'pq' => count($pendingQa),
        'ar' => (int)$pdo->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'approved'")->fetchColumn(),
        'aq' => (int)$pdo->query("SELECT COUNT(*) FROM product_qa WHERE status = 'approved'")->fetchColumn(),
    ];
}

/* برچسب نوع ردیف پرسش‌وپاسخ */
function qaKind($row) {
    if ($row['parent_id'] === null || (int)$row['parent_id'] === 0) return 'پرسش';
    return !empty($row['is_admin']) ? 'پاسخ فروشگاه' : 'پاسخ مشتری';
}

require_once __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="flash flash-success" style="margin:0 0 1rem;"><?= icon('check', 'ic-sm') ?> <?= h($msg) ?></div><?php endif; ?>

<?php if (!$ready): ?>
<div class="flash flash-error" style="margin-bottom:1rem;">
    جدول‌های <code>product_reviews</code> و <code>product_qa</code> ساخته نشده‌اند.
    ابتدا اسکریپت راه‌اندازی دیتابیس را یک‌بار اجرا کنید، سپس به این صفحه بازگردید.
</div>
<?php else: ?>

<div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
    <h2 style="font-size:1rem;color:var(--text-primary);"><?= icon('message', 'ic-sm') ?> نظرها و پرسش‌وپاسخ محصولات</h2>
    <span style="font-size:0.75rem;color:var(--text-muted);">نظر و پرسشِ مشتری تا تأیید شما در سایت دیده نمی‌شود.</span>
</div>

<div class="dash-cards" style="margin-bottom:1.25rem;">
    <div class="dc <?= $stats['pr'] ? 'dc-red' : '' ?>" style="padding:0.75rem;"><div class="dc-val" style="font-size:1.2rem;"><?= $stats['pr'] ?></div><div class="dc-lbl">نظر در انتظار</div></div>
    <div class="dc <?= $stats['pq'] ? 'dc-red' : '' ?>" style="padding:0.75rem;"><div class="dc-val" style="font-size:1.2rem;"><?= $stats['pq'] ?></div><div class="dc-lbl">پرسش/پاسخ در انتظار</div></div>
    <div class="dc" style="padding:0.75rem;"><div class="dc-val" style="font-size:1.2rem;"><?= $stats['ar'] ?></div><div class="dc-lbl">نظر منتشرشده</div></div>
    <div class="dc" style="padding:0.75rem;"><div class="dc-val" style="font-size:1.2rem;"><?= $stats['aq'] ?></div><div class="dc-lbl">پرسش/پاسخ منتشرشده</div></div>
</div>

<!-- ===================== صف تأیید ===================== -->
<div class="dg-box" style="margin-bottom:1.5rem;">
    <div class="dg-box-hd"><h3><?= icon('clock', 'ic-sm') ?> در انتظار تأیید (<?= $stats['pr'] + $stats['pq'] ?>)</h3></div>
    <div class="dg-box-bd">
        <?php if (!$pendingReviews && !$pendingQa): ?>
        <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:0.85rem;"><?= icon('check-circle', 'ic-sm') ?> صف خالی است.</div>
        <?php else: ?>
        <table class="admin-table">
        <thead><tr><th>#</th><th>نوع</th><th>محصول</th><th>فرستنده</th><th>محتوا</th><th>تاریخ</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($pendingReviews as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><span class="badge-pending">نظر</span></td>
            <td><a href="../product.php?id=<?= (int)$r['product_id'] ?>#reviews" target="_blank" style="color:var(--text-primary);"><?= h($r['product_name'] ?? '—') ?></a></td>
            <td><?= h(reviewAuthor($r)) ?></td>
            <td style="max-width:360px;">
                <div style="margin-bottom:0.3rem;"><?= ratingStars((int)$r['rating'], null, 'rstars-sm') ?> <span style="font-size:0.72rem;color:var(--text-muted);"><?= (int)$r['rating'] ?>/5</span></div>
                <div style="font-size:0.8rem;line-height:1.8;color:var(--text-secondary);"><?= nl2br(h($r['body'])) ?></div>
            </td>
            <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= h(jDate($r['created_at'], true)) ?></td>
            <td style="white-space:nowrap;">
                <a href="reviews.php?t=r&set=approved&id=<?= (int)$r['id'] ?>" class="btn btn-primary btn-sm"><?= icon('check', 'ic-sm') ?> تأیید</a>
                <a href="reviews.php?t=r&set=rejected&id=<?= (int)$r['id'] ?>" class="btn btn-secondary btn-sm"><?= icon('x', 'ic-sm') ?> رد</a>
                <a href="reviews.php?t=r&del=<?= (int)$r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('این نظر برای همیشه حذف شود؟')"><?= icon('trash', 'ic-sm') ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($pendingQa as $q): ?>
        <tr>
            <td><?= (int)$q['id'] ?></td>
            <td><span class="badge-retail"><?= h(qaKind($q)) ?></span></td>
            <td><a href="../product.php?id=<?= (int)$q['product_id'] ?>#qa" target="_blank" style="color:var(--text-primary);"><?= h($q['product_name'] ?? '—') ?></a></td>
            <td><?= h(reviewAuthor($q)) ?></td>
            <td style="max-width:360px;font-size:0.8rem;line-height:1.8;color:var(--text-secondary);"><?= nl2br(h($q['body'])) ?></td>
            <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= h(jDate($q['created_at'], true)) ?></td>
            <td style="white-space:nowrap;">
                <a href="reviews.php?t=q&set=approved&id=<?= (int)$q['id'] ?>" class="btn btn-primary btn-sm"><?= icon('check', 'ic-sm') ?> تأیید</a>
                <a href="reviews.php?t=q&set=rejected&id=<?= (int)$q['id'] ?>" class="btn btn-secondary btn-sm"><?= icon('x', 'ic-sm') ?> رد</a>
                <a href="reviews.php?t=q&del=<?= (int)$q['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('این ردیف (و پاسخ‌هایش) حذف شود؟')"><?= icon('trash', 'ic-sm') ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== نخ‌های پرسش‌وپاسخ ===================== -->
<div class="dg-box" style="margin-bottom:1.5rem;">
    <div class="dg-box-hd"><h3><?= icon('help', 'ic-sm') ?> پرسش‌ها و پاسخ فروشگاه</h3></div>
    <div class="dg-box-bd" style="padding:1rem;">
        <?php if (!$threads): ?>
        <div style="padding:1rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">پرسشی ثبت نشده است.</div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.9rem;">
            <?php foreach ($threads as $q): ?>
            <div class="qa-item" id="q<?= (int)$q['id'] ?>">
                <div class="qa-q">
                    <span class="qa-tag qa-tag-q">پرسش</span>
                    <div class="qa-q-main">
                        <div class="qa-meta">
                            <b><?= h(reviewAuthor($q)) ?></b> · <?= h(jDate($q['created_at'], true)) ?> ·
                            <a href="../product.php?id=<?= (int)$q['product_id'] ?>#qa" target="_blank"><?= h($q['product_name'] ?? '—') ?></a>
                            <?php if ($q['status'] === 'pending'): ?><span class="badge-pending">در انتظار تأیید</span><?php endif; ?>
                        </div>
                        <div class="qa-body"><?= nl2br(h($q['body'])) ?></div>
                    </div>
                </div>

                <?php foreach ($q['answers'] as $a): ?>
                <div class="qa-a<?= !empty($a['is_admin']) ? ' is-shop' : '' ?>">
                    <span class="qa-tag qa-tag-a"><?= icon('reply', 'ic-sm') ?> پاسخ</span>
                    <div class="qa-q-main">
                        <div class="qa-meta">
                            <b><?= h(reviewAuthor($a)) ?></b> · <?= h(jDate($a['created_at'], true)) ?>
                            <?php if ($a['status'] !== 'approved'): ?><span class="badge-pending"><?= $a['status'] === 'pending' ? 'در انتظار تأیید' : 'رد شده' ?></span><?php endif; ?>
                        </div>
                        <div class="qa-body"><?= nl2br(h($a['body'])) ?></div>
                        <div style="margin-top:0.35rem;">
                            <?php if ($a['status'] !== 'approved'): ?>
                            <a href="reviews.php?t=q&set=approved&id=<?= (int)$a['id'] ?>" class="btn btn-primary btn-sm"><?= icon('check', 'ic-sm') ?> تأیید</a>
                            <?php else: ?>
                            <a href="reviews.php?t=q&set=pending&id=<?= (int)$a['id'] ?>" class="btn btn-secondary btn-sm">لغو انتشار</a>
                            <?php endif; ?>
                            <a href="reviews.php?t=q&del=<?= (int)$a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('این پاسخ حذف شود؟')"><?= icon('trash', 'ic-sm') ?></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <form method="POST" action="reviews.php" style="margin-top:0.7rem;display:flex;flex-direction:column;gap:0.5rem;align-items:flex-start;">
                    <input type="hidden" name="answer_to" value="<?= (int)$q['id'] ?>">
                    <textarea name="body" class="form-control" rows="2" maxlength="2000" style="font-family:inherit;font-size:0.85rem;"
                              placeholder="پاسخ رسمی فروشگاه…" required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('send', 'ic-sm') ?> ثبت و انتشار پاسخ فروشگاه</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== نظرهای منتشرشده ===================== -->
<div class="dg-box">
    <div class="dg-box-hd"><h3><?= icon('star', 'ic-sm') ?> نظرهای منتشرشده</h3></div>
    <div class="dg-box-bd">
        <?php if (!$approvedReviews): ?>
        <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">نظر منتشرشده‌ای وجود ندارد.</div>
        <?php else: ?>
        <table class="admin-table">
        <thead><tr><th>#</th><th>محصول</th><th>فرستنده</th><th>امتیاز</th><th>متن</th><th>تاریخ</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($approvedReviews as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><a href="../product.php?id=<?= (int)$r['product_id'] ?>#reviews" target="_blank" style="color:var(--text-primary);"><?= h($r['product_name'] ?? '—') ?></a></td>
            <td><?= h(reviewAuthor($r)) ?></td>
            <td style="white-space:nowrap;"><?= ratingStars((int)$r['rating'], null, 'rstars-sm') ?></td>
            <td style="max-width:420px;font-size:0.8rem;line-height:1.8;color:var(--text-secondary);"><?= nl2br(h($r['body'])) ?></td>
            <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= h(jDate($r['created_at'])) ?></td>
            <td style="white-space:nowrap;">
                <a href="reviews.php?t=r&set=pending&id=<?= (int)$r['id'] ?>" class="btn btn-secondary btn-sm">لغو انتشار</a>
                <a href="reviews.php?t=r&del=<?= (int)$r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('این نظر برای همیشه حذف شود؟')"><?= icon('trash', 'ic-sm') ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php';
