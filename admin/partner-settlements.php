<?php
/* پیگیریِ تسویهٔ همکاران — یک گزارشِ مجزا: کدام همکار، چند فاکتورِ بی‌تسویه
   دارد، جمعِ بدهی‌اش چقدر است، و قرار است با چه روشی تسویه کند (چک، اول ماه،
   کارت‌به‌کارتِ در انتظار تأیید، پرداخت در محل، یا آنلاینِ ناموفق). هر همکار
   یک ردیفِ قابل‌بازشدن است؛ زیرش فهرستِ خودِ فاکتورهای بدهکارش. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$payOn     = paymentReady();
$hasPnrCol = dbHasColumn('customers', 'customer_type');

$groups = [];      // customer_id => ['customer' => row, 'orders' => [...], 'debt' => int]
$grandDebt = 0;
$methodTotals = []; // payment_method => ['count'=>n, 'debt'=>n]

if ($payOn && $hasPnrCol) {
    try {
        $rows = $pdo->query("
            SELECT o.*, c.full_name, c.mobile AS cust_mobile, c.partner_company, c.partner_status
            FROM orders o
            JOIN customers c ON c.id = o.customer_id
            WHERE c.customer_type = 'partner'
              AND o.status <> 'cancelled'
              AND o.payment_status IN ('unpaid', 'pending', 'failed')
            ORDER BY c.full_name, o.created_at DESC
        ")->fetchAll();
    } catch (Throwable $e) { $rows = []; }

    foreach ($rows as $o) {
        $cid = (int)$o['customer_id'];
        $debt = max(0, (int)$o['total_amount'] - (int)($o['paid_amount'] ?? 0));
        if (!isset($groups[$cid])) {
            $groups[$cid] = [
                'customer' => [
                    'id' => $cid, 'full_name' => $o['full_name'], 'mobile' => $o['cust_mobile'],
                    'partner_company' => $o['partner_company'], 'partner_status' => $o['partner_status'],
                ],
                'orders' => [], 'debt' => 0,
            ];
        }
        $groups[$cid]['orders'][] = $o;
        $groups[$cid]['debt'] += $debt;
        $grandDebt += $debt;

        $pm = (string)($o['payment_method'] ?? 'cod');
        if (!isset($methodTotals[$pm])) $methodTotals[$pm] = ['count' => 0, 'debt' => 0];
        $methodTotals[$pm]['count']++;
        $methodTotals[$pm]['debt'] += $debt;
    }
    /* بدهکارترین همکار اول دیده شود */
    uasort($groups, function ($a, $b) { return $b['debt'] <=> $a['debt']; });
}

require_once __DIR__ . '/layout-top.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
    <h2 style="font-size:1rem;color:var(--text-primary);"><?= icon('scale', 'ic-sm') ?> پیگیری تسویهٔ همکاران</h2>
    <a href="customers.php?type=partner" class="btn btn-secondary btn-sm">فهرست همکاران</a>
</div>

<?php if (!$payOn || !$hasPnrCol): ?>
<div class="flash flash-error" style="margin-bottom:1rem;">
    <?= icon('alert', 'ic-sm') ?>
    <?= !$payOn ? 'ستون‌های پرداخت هنوز ساخته نشده‌اند.' : 'ستون‌های حساب همکار هنوز ساخته نشده‌اند.' ?>
</div>
<?php else: ?>

<p style="font-size:0.8rem;color:var(--text-muted);line-height:1.95;margin-bottom:1.25rem;">
    این صفحه فقط سفارش‌های همکاران را نشان می‌دهد که هنوز <b>پرداخت‌نشده</b>، <b>در انتظار</b> یا <b>ناموفق</b> هستند
    (سفارش‌های لغوشده یا پرداخت‌شده اینجا نمی‌آیند). برای هر همکار، مبلغِ بدهی و روشِ تسویه‌ای که انتخاب کرده مشخص است؛
    برای چک، وضعیتِ «دریافت چک» هم همین‌جا دیده می‌شود.
</p>

<div class="dash-cards" style="margin-bottom:1.5rem;">
    <div class="dc dc-red">
        <span class="dc-lbl">جمع کل بدهیِ همکاران</span>
        <span class="dc-val"><?= formatPrice($grandDebt) ?></span>
    </div>
    <div class="dc dc-orange">
        <span class="dc-lbl">همکارانِ بدهکار</span>
        <span class="dc-val"><?= number_format(count($groups)) ?> نفر</span>
    </div>
    <div class="dc dc-gold">
        <span class="dc-lbl">فاکتورهایِ بی‌تسویه</span>
        <span class="dc-val"><?= number_format(array_sum(array_map(function ($g) { return count($g['orders']); }, $groups))) ?> فاکتور</span>
    </div>
</div>

<?php if ($methodTotals): ?>
<div class="dg-box" style="margin-bottom:1.5rem;">
    <div class="dg-box-hd"><h3><?= icon('credit-card', 'ic-sm') ?> تفکیک بر اساس روشِ تسویه</h3></div>
    <div class="dg-box-bd" style="padding:0.75rem 1rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0.6rem;">
            <?php foreach ($methodTotals as $pm => $mt): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:0.55rem 0.75rem;">
                <span style="display:flex;align-items:center;gap:0.35rem;font-size:0.82rem;color:var(--text-secondary);"><?= icon(paymentIcon($pm), 'ic-sm') ?> <?= h(paymentLabel($pm)) ?> <span style="color:var(--text-muted);font-size:0.72rem;">(<?= $mt['count'] ?>)</span></span>
                <b style="font-size:0.82rem;color:var(--text-primary);"><?= formatPrice($mt['debt']) ?></b>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$groups): ?>
<div class="no-results" style="padding:2rem 1rem;">
    <div class="no-results-icon"><?= icon('check-circle') ?></div>
    <p style="font-size:0.95rem;">هیچ همکاری در حال حاضر بدهیِ بازی ندارد.</p>
</div>
<?php else: ?>

<div class="dg-box">
    <div class="dg-box-hd"><h3><?= icon('users', 'ic-sm') ?> همکارانِ بدهکار (<?= count($groups) ?>)</h3></div>
    <div class="dg-box-bd" style="padding:0;">
        <?php foreach ($groups as $g): $cu = $g['customer']; $pid = 'pset-' . (int)$cu['id']; ?>
        <div class="pset-row">
            <input type="checkbox" id="<?= $pid ?>" class="pset-toggle" hidden>
            <div class="pset-sum-wrap">
                <label for="<?= $pid ?>" class="pset-sum">
                    <span class="pset-name">
                        <?= icon('user', 'ic-sm') ?> <b><?= h($cu['full_name'] ?: 'بدون نام') ?></b>
                        <?php if (trim((string)($cu['partner_company'] ?? '')) !== ''): ?>
                        <span class="pset-company"><?= h($cu['partner_company']) ?></span>
                        <?php endif; ?>
                        <span class="badge-<?= $cu['partner_status'] === 'approved' ? 'partner' : 'pending' ?>"><?= $cu['partner_status'] === 'approved' ? 'همکار تأییدشده' : 'در انتظار تأیید' ?></span>
                    </span>
                    <span class="pset-mobile" dir="ltr"><?= h($cu['mobile']) ?></span>
                    <span class="pset-count"><?= count($g['orders']) ?> فاکتور</span>
                    <span class="pset-debt"><?= formatPrice($g['debt']) ?></span>
                    <span class="pset-caret"><?= icon('chevron-down', 'ic-sm') ?></span>
                </label>
                <a href="customer-detail.php?id=<?= (int)$cu['id'] ?>" class="btn btn-secondary btn-sm">پروندهٔ همکار</a>
            </div>

            <div class="pset-body">
            <table class="admin-table" style="margin:0 0 0.75rem;">
                <thead>
                    <tr>
                        <th>شماره سفارش</th>
                        <th>تاریخ</th>
                        <th>روش تسویه</th>
                        <th>وضعیت</th>
                        <th>مبلغ بدهی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($g['orders'] as $o):
                        $pm = (string)($o['payment_method'] ?? 'cod');
                        $ps = (string)($o['payment_status'] ?? 'unpaid');
                        $odebt = max(0, (int)$o['total_amount'] - (int)($o['paid_amount'] ?? 0));
                    ?>
                    <tr>
                        <td dir="ltr"><?= h(orderNumber($o)) ?></td>
                        <td><?= h(jDate($o['created_at'], true)) ?></td>
                        <td><?= icon(paymentIcon($pm), 'ic-sm') ?> <?= h(paymentLabel($pm)) ?></td>
                        <td>
                            <?= paymentStatusBadgeFor($ps, $pm) ?>
                            <?php if ($pm === 'cheque' && function_exists('paymentChequeReady') && paymentChequeReady()): ?>
                                <?php if (!empty($o['cheque_received_at'])): ?>
                                <div style="color:#4ADE80;font-size:0.7rem;margin-top:2px;"><?= icon('check-circle', 'ic-sm') ?> چک دریافت شد</div>
                                <?php elseif (trim((string)($o['cheque_number'] ?? '')) !== ''): ?>
                                <div style="color:#FBBF24;font-size:0.7rem;margin-top:2px;"><?= icon('clock', 'ic-sm') ?> چک ثبت شد — دریافت نشده</div>
                                <?php else: ?>
                                <div style="color:var(--text-muted);font-size:0.7rem;margin-top:2px;">چک هنوز ثبت نشده</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><b><?= formatPrice($odebt) ?></b></td>
                        <td>
                            <a href="order-detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm">جزئیات</a>
                            <a href="invoice.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm" target="_blank"><?= icon('printer', 'ic-sm') ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/layout-bottom.php'; ?>
