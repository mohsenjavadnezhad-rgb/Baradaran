<?php
/* فاکتور چاپی یک سفارش — برای مدیر.
   بر پایهٔ همان قالب preinvoice.php ساخته شده، با این تفاوت که داده‌ها از
   خود سفارش ثبت‌شده می‌آید (نه سبد خرید)، و روش ارسال/پرداخت هم روی برگه است.
   عمدا includes/header.php لود نمی‌شود (header به‌صورت سراسری handleCartAction
   را اجرا می‌کند) و برای همین این صفحه هیچ چیز از سبد خود مدیر را دست نمی‌زند. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$st->execute([$id]);
$order = $st->fetch();

if (!$order) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<meta charset="utf-8"><div style="font:14px tahoma;direction:rtl;text-align:center;padding:3rem;">'
       . 'سفارش یافت نشد. <a href="orders.php">بازگشت به سفارشات</a></div>';
    exit;
}

/* شمارهٔ فنی در order_items ذخیره نمی‌شود، پس از خود محصول خوانده می‌شود */
$st = $pdo->prepare("SELECT oi.*, p.technical_number
                     FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
                     WHERE oi.order_id = ? ORDER BY oi.id");
$st->execute([$id]);
$items = $st->fetchAll();

/* مشخصات حساب مشتری (اگر سفارش به حسابی وصل باشد) — برای کد پستی و نوع مشتری */
$cust = null;
if (!empty($order['customer_id'])) {
    $st = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $st->execute([(int)$order['customer_id']]);
    $cust = $st->fetch() ?: null;
}

$payOn  = paymentReady();
$shipOn = shippingReady();

$shipCost   = $shipOn ? (int)($order['shipping_cost'] ?? 0) : 0;
$shipMethod = $shipOn ? trim((string)($order['shipping_method'] ?? '')) : '';
$taxTotal   = (int)($order['tax_total'] ?? 0);
$goodsTotal = max(0, (int)$order['total_amount'] - $shipCost - $taxTotal);

$payMethod = $payOn ? (string)($order['payment_method'] ?? 'cod') : '';
$payStatus = $payOn ? (string)($order['payment_status'] ?? 'unpaid') : '';
$isPaid    = ($payStatus === 'paid');

/* شمارهٔ فاکتور — پایدار و یکتا برای هر سفارش */
$fNo = 'F-' . date('ymd', strtotime($order['created_at'])) . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);

$phones  = contactNumbers('contact_phones',  'contact_phone');
$mobiles = contactNumbers('contact_mobiles', 'contact_mobile');
$email   = getSetting('contact_email', '');
$address = getSetting('contact_address', '');

$totalQty = 0;
foreach ($items as $it) $totalQty += (int)$it['quantity'];

$stLabels = orderStatusLabels();
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>فاکتور <?= h($fNo) ?> — <?= h(SITE_NAME) ?></title>
<style>
@font-face{font-family:Peyda;src:url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff2') format('woff2'),url('https://cdn.mxit.ir/fonts/Peyda/Peyda.woff') format('woff');font-display:swap}
*{margin:0;padding:0;box-sizing:border-box}
<?= iconBaseCss() ?>
body{font-family:Peyda,Tahoma,Arial,sans-serif;background:#EEF1F5;color:#111827;line-height:1.7;padding:1.5rem 1rem;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.pi-toolbar{max-width:820px;margin:0 auto 1rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center}
.pi-btn{display:inline-flex;align-items:center;gap:0.4rem;padding:0.55rem 1.1rem;border:1px solid #D1D5DB;border-radius:8px;background:#fff;color:#111827;font-family:inherit;font-size:0.9rem;font-weight:600;cursor:pointer;text-decoration:none}
.pi-btn:hover{background:#F3F4F6}
.pi-btn-primary{background:#DC2626;border-color:#DC2626;color:#fff}
.pi-btn-primary:hover{background:#B91C1C}
.pi-back{margin-right:auto;color:#374151;font-size:0.85rem;text-decoration:none}
.pi-tip{max-width:820px;margin:0 auto 1rem;background:#FEF3C7;border:1px solid #FDE68A;color:#92400E;border-radius:8px;padding:0.6rem 0.9rem;font-size:0.8rem}
.pi-sheet{max-width:820px;margin:0 auto;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:1.75rem;position:relative;overflow:hidden}
.pi-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;border-bottom:2px solid #DC2626;padding-bottom:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.pi-brand{font-size:1.5rem;font-weight:800;color:#DC2626}
.pi-brand small{display:block;font-size:0.78rem;font-weight:400;color:#6B7280;margin-top:0.2rem}
.pi-meta{text-align:left;font-size:0.82rem;color:#374151}
.pi-meta div{white-space:nowrap}
.pi-meta b{color:#111827}
.pi-title{display:inline-block;background:#FEE2E2;color:#991B1B;border-radius:6px;padding:0.2rem 0.7rem;font-size:0.9rem;font-weight:700;margin-bottom:0.5rem}
.pi-parties{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
.pi-party{border:1px solid #E5E7EB;border-radius:8px;padding:0.75rem 0.9rem;font-size:0.82rem}
.pi-party h3{font-size:0.78rem;color:#6B7280;font-weight:600;margin-bottom:0.4rem}
.pi-party p{margin:0.1rem 0;color:#374151}
.pi-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:0.5rem;margin-bottom:1.25rem}
.pi-info div{border:1px solid #E5E7EB;border-radius:8px;padding:0.5rem 0.7rem;font-size:0.8rem;color:#374151}
.pi-info span{display:block;font-size:0.72rem;color:#6B7280;margin-bottom:0.1rem}
.pi-info b{color:#111827}
table.pi-tbl{width:100%;border-collapse:collapse;font-size:0.85rem}
table.pi-tbl th{background:#F3F4F6;color:#374151;font-size:0.78rem;font-weight:700;padding:0.55rem 0.5rem;border:1px solid #E5E7EB;text-align:right}
table.pi-tbl td{padding:0.55rem 0.5rem;border:1px solid #E5E7EB;vertical-align:middle}
table.pi-tbl td.num{white-space:nowrap;font-variant-numeric:tabular-nums}
.pi-pt{display:inline-block;font-size:0.7rem;padding:0.1rem 0.4rem;border-radius:4px;margin-right:0.3rem}
.pi-pt.wholesale{background:#DCFCE7;color:#166534}
.pi-pt.retail{background:#FEE2E2;color:#991B1B}
.pi-tech{font-size:0.72rem;color:#6B7280;direction:ltr;text-align:right}
.pi-foot{display:flex;justify-content:space-between;gap:1rem;margin-top:1.25rem;flex-wrap:wrap}
.pi-notes{flex:1;min-width:240px;font-size:0.75rem;color:#6B7280;line-height:1.9}
.pi-totals{min-width:270px}
.pi-totals div{display:flex;justify-content:space-between;gap:1rem;padding:0.4rem 0.6rem;font-size:0.88rem}
.pi-totals .pi-total{background:#FEE2E2;color:#991B1B;font-weight:800;font-size:1rem;border-radius:6px;margin-top:0.3rem}
.pi-sign{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:2rem;font-size:0.78rem;color:#6B7280}
.pi-sign div{border-top:1px dashed #9CA3AF;padding-top:0.5rem;text-align:center}
.tmn{font-size:0.8em;color:#6B7280;margin-right:0.4rem}
.pi-paid{position:absolute;top:4.5rem;left:2rem;transform:rotate(-14deg);border:3px solid #16A34A;color:#16A34A;border-radius:10px;padding:0.2rem 0.9rem;font-size:1.15rem;font-weight:800;letter-spacing:1px;opacity:0.75}
.pi-paid.is-unpaid{border-color:#DC2626;color:#DC2626}
@media (max-width:640px){.pi-parties{grid-template-columns:1fr}.pi-sheet{padding:1rem}.pi-paid{top:auto;bottom:1rem;left:1rem;font-size:0.9rem}}
@media print{
  body{background:#fff;padding:0}
  .pi-toolbar,.pi-tip{display:none !important}
  .pi-sheet{max-width:none;border:none;border-radius:0;padding:0;margin:0}
  table.pi-tbl{font-size:11pt}
  table.pi-tbl th{background:#F3F4F6 !important}
  tr{page-break-inside:avoid}
  @page{size:A4;margin:12mm}
}
</style>
</head>
<body>

<div class="pi-toolbar">
  <button type="button" class="pi-btn pi-btn-primary" onclick="window.print()"><?= icon('printer') ?>چاپ فاکتور</button>
  <button type="button" class="pi-btn" onclick="window.print()"><?= icon('file-down') ?>خروجی PDF</button>
  <a href="order-detail.php?id=<?= $id ?>" class="pi-btn">جزئیات سفارش</a>
  <a href="orders.php" class="pi-back">بازگشت به فهرست سفارشات &#8592;</a>
</div>

<div class="pi-tip">
  برای گرفتن <b>PDF</b>: روی «خروجی PDF» بزنید و در پنجرهٔ چاپ، مقصد (Destination) را روی
  <b dir="ltr">Save as PDF</b> بگذارید و ذخیره کنید.
</div>

<div class="pi-sheet">
  <?php if ($payOn): ?>
  <div class="pi-paid<?= $isPaid ? '' : ' is-unpaid' ?>"><?= $isPaid ? 'پرداخت شد' : 'پرداخت‌نشده' ?></div>
  <?php endif; ?>

  <div class="pi-head">
    <div>
      <div class="pi-title">فاکتور فروش</div>
      <div class="pi-brand"><?= h(SITE_NAME) ?><small>فروش قطعات یدکی خودرو</small></div>
    </div>
    <div class="pi-meta">
      <div>شمارهٔ فاکتور: <b dir="ltr"><?= h($fNo) ?></b></div>
      <div>شمارهٔ سفارش: <b dir="ltr"><?= h(orderNumber($order)) ?></b></div>
      <div>تاریخ سفارش: <b><?= h(jDateLong($order['created_at'])) ?></b></div>
      <div>ساعت: <b dir="ltr"><?= date('H:i', strtotime($order['created_at'])) ?></b></div>
    </div>
  </div>

  <div class="pi-parties">
    <div class="pi-party">
      <h3>فروشنده</h3>
      <p><b><?= h(SITE_NAME) ?></b></p>
      <?php foreach ($phones as $ph):  ?><p>تلفن: <span dir="ltr"><?= h($ph) ?></span></p><?php endforeach; ?>
      <?php foreach ($mobiles as $mb): ?><p>موبایل: <span dir="ltr"><?= h($mb) ?></span></p><?php endforeach; ?>
      <?php if ($email !== ''):   ?><p>ایمیل: <span dir="ltr"><?= h($email) ?></span></p><?php endif; ?>
      <?php if ($address !== ''): ?><p>آدرس: <?= h($address) ?></p><?php endif; ?>
    </div>
    <div class="pi-party">
      <h3>خریدار</h3>
      <p><b><?= h(trim((string)$order['customer_name']) !== '' ? $order['customer_name'] : 'مشتری') ?></b>
        <?php if ($cust && ($cust['customer_type'] ?? 'retail') === 'partner'): ?>(همکار)<?php endif; ?></p>
      <?php if ($cust && trim((string)($cust['partner_company'] ?? '')) !== ''): ?>
      <p><?= h($cust['partner_company']) ?></p>
      <?php endif; ?>
      <p>موبایل: <span dir="ltr"><?= h($order['customer_mobile']) ?></span></p>
      <?php if (trim((string)$order['customer_address']) !== ''): ?>
      <p>آدرس: <?= nl2br(h($order['customer_address'])) ?></p>
      <?php endif; ?>
      <?php if ($cust && trim((string)($cust['postal_code'] ?? '')) !== ''): ?>
      <p>کد پستی: <span dir="ltr"><?= h($cust['postal_code']) ?></span></p>
      <?php endif; ?>
      <?php if (!$cust): ?><p style="color:#6B7280;">(سفارش مهمان — بدون حساب کاربری)</p><?php endif; ?>
    </div>
  </div>

  <div class="pi-info">
    <div><span>وضعیت سفارش</span><b><?= h($stLabels[(string)$order['status']] ?? (string)$order['status']) ?></b></div>
    <?php if ($payOn): ?>
    <div><span>روش پرداخت</span><b><?= h(paymentLabel($payMethod)) ?></b> — <?= h(paymentStatusLabelFor($payStatus, $payMethod)) ?></div>
    <?php endif; ?>
    <?php if ($shipOn): ?>
    <div><span>روش ارسال</span><b><?= $shipMethod !== '' ? h(shippingLabel($shipMethod)) : 'انتخاب‌نشده' ?></b> — <?= h(shippingCostText($shipCost, $shipMethod)) ?></div>
    <?php endif; ?>
    <?php if ($payOn && !empty($order['payment_ref'])): ?>
    <div><span>شمارهٔ پیگیری پرداخت</span><b dir="ltr"><?= h($order['payment_ref']) ?></b></div>
    <?php endif; ?>
    <?php if ($payOn && !empty($order['paid_at'])): ?>
    <div><span>زمان پرداخت</span><b><?= h(jDate($order['paid_at'], true)) ?></b></div>
    <?php endif; ?>
    <?php if (!empty($order['post_tracking_code'])): ?>
    <div><span>کد رهگیری پست</span><b dir="ltr"><?= h($order['post_tracking_code']) ?></b></div>
    <?php endif; ?>
  </div>

  <?php /* ستون مالیات فقط وقتی روی جدول اضافه می‌شود که واقعا چیزی برای
          نشان دادن باشد — سفارش‌های قدیمی‌تر (پیش از ستون‌های مالیات) یا
          بدون هیچ قلم مالیات‌دار، بدون یک ستون خالی گمراه‌کننده می‌مانند. */
        $rowsHaveTax = false;
        foreach ($items as $item) { if ((int)($item['tax_amount'] ?? 0) > 0) { $rowsHaveTax = true; break; } }
  ?>
  <table class="pi-tbl">
    <thead>
      <tr>
        <th style="width:34px;">#</th>
        <th>شرح کالا</th>
        <th style="width:110px;">شماره فنی</th>
        <th style="width:130px;">قیمت واحد</th>
        <th style="width:60px;">تعداد</th>
        <?php if ($rowsHaveTax): ?><th style="width:110px;">مالیات</th><?php endif; ?>
        <th style="width:140px;">مبلغ کل</th>
      </tr>
    </thead>
    <tbody>
      <?php $i = 1; foreach ($items as $item): ?>
      <tr>
        <td class="num"><?= $i++ ?></td>
        <td>
          <?= h($item['product_name']) ?>
          <span class="pi-pt <?= h($item['price_type']) ?>"><?= $item['price_type'] === 'wholesale' ? 'قیمت کلی' : 'قیمت جزئی' ?></span>
        </td>
        <td class="pi-tech"><?= h((string)($item['technical_number'] ?? '')) ?></td>
        <td class="num"><?= number_format($item['price'], 0, '.', ',') ?><span class="tmn">تومان</span></td>
        <td class="num"><?= (int)$item['quantity'] ?></td>
        <?php if ($rowsHaveTax):
              $itTax = (int)($item['tax_amount'] ?? 0);
              $itTaxPct = (float)($item['tax_percent'] ?? 0); ?>
        <td class="num"><?php if ($itTax > 0): ?><?= number_format($itTax, 0, '.', ',') ?><span class="tmn">تومان</span><br><span style="color:#6B7280;font-size:0.72rem;">(<?= rtrim(rtrim(number_format($itTaxPct, 2, '.', ''), '0'), '.') ?>٪)</span><?php else: ?>—<?php endif; ?></td>
        <?php endif; ?>
        <td class="num"><b><?= number_format($item['subtotal'], 0, '.', ',') ?></b><span class="tmn">تومان</span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
      <tr><td colspan="<?= $rowsHaveTax ? 7 : 6 ?>" style="text-align:center;color:#6B7280;">این سفارش قلمی ندارد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pi-foot">
    <div class="pi-notes">
      <?php if (trim((string)$order['notes']) !== ''): ?>
      <b style="color:#374151;">توضیحات مشتری:</b> <?= nl2br(h($order['notes'])) ?><br>
      <?php endif; ?>
      • «قیمت کلی» با رسیدن تعداد هر کالا به حد تعیین‌شدهٔ همان کالا به‌صورت خودکار اعمال شده است.<br>
      • مبالغ به <b>تومان</b> است.<br>
      • این برگه از پنل مدیریت و بر پایهٔ سفارش ثبت‌شدهٔ شمارهٔ <b dir="ltr">#<?= $id ?></b> صادر شده است.
    </div>
    <div class="pi-totals">
      <div><span>تعداد اقلام</span><b><?= number_format(count($items)) ?> قلم</b></div>
      <div><span>تعداد کل</span><b><?= number_format($totalQty) ?> عدد</b></div>
      <div><span>جمع کالاها</span><b><?= number_format($goodsTotal, 0, '.', ',') ?><span class="tmn">تومان</span></b></div>
      <?php if ($taxTotal > 0): ?>
      <div><span>جمع مالیات</span><b><?= number_format($taxTotal, 0, '.', ',') ?><span class="tmn">تومان</span></b></div>
      <?php endif; ?>
      <?php if ($shipOn): ?>
      <div><span>هزینهٔ ارسال</span><b><?= $shipCost > 0 ? number_format($shipCost, 0, '.', ',') . '<span class="tmn">تومان</span>' : h(shippingCostText($shipCost, $shipMethod)) ?></b></div>
      <?php endif; ?>
      <div class="pi-total"><span>مبلغ قابل پرداخت</span><span><?= number_format($order['total_amount'], 0, '.', ',') ?><span class="tmn">تومان</span></span></div>
      <?php if ($payOn && (int)($order['paid_amount'] ?? 0) > 0): ?>
      <div><span>پرداخت‌شده</span><b style="color:#166534;"><?= number_format((int)$order['paid_amount'], 0, '.', ',') ?><span class="tmn">تومان</span></b></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="pi-sign">
    <div>امضا و مهر فروشنده</div>
    <div>امضای خریدار</div>
  </div>
</div>

</body>
</html>
