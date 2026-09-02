<?php
/* پیش‌فاکتور چاپی — صفحهٔ مستقل.
   عمدا includes/header.php را لود نمی‌کند، چون header به‌صورت سراسری
   handleCartAction() را اجرا می‌کند و POSTهای سبد دوباره پردازش می‌شوند. */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

$cartItems = getCartItems();
if (!$cartItems) { redirect('cart.php'); }

$cartTotal = getCartTotal();
$cartCount = getCartCount();
$cartTax   = itemsTaxTotal($cartItems);
$rowsHaveTax = $cartTax > 0;

$c = isCustomerLoggedIn() ? currentCustomer() : null;

/* شمارهٔ پیش‌فاکتور — پایدار برای یک سبد مشخص در یک روز */
$piNo = 'PF-' . date('ymd') . '-' . strtoupper(substr(md5(json_encode($_SESSION['cart'] ?? [])), 0, 5));

$phone   = getSetting('contact_phone', '');
$mobile  = getSetting('contact_mobile', '');
$email   = getSetting('contact_email', '');
$address = getSetting('contact_address', '');

$totalQty = 0;
foreach ($cartItems as $it) $totalQty += (int)$it['quantity'];
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پیش‌فاکتور <?= h($piNo) ?> — <?= h(SITE_NAME) ?></title>
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
.pi-sheet{max-width:820px;margin:0 auto;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:1.75rem}
.pi-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;border-bottom:2px solid #DC2626;padding-bottom:1rem;margin-bottom:1.25rem;flex-wrap:wrap}
.pi-brand{font-size:1.5rem;font-weight:800;color:#DC2626}
.pi-brand small{display:block;font-size:0.78rem;font-weight:400;color:#6B7280;margin-top:0.2rem}
.pi-meta{text-align:left;font-size:0.82rem;color:#374151}
.pi-meta div{white-space:nowrap}
.pi-meta b{color:#111827}
.pi-title{display:inline-block;background:#FEE2E2;color:#991B1B;border-radius:6px;padding:0.2rem 0.7rem;font-size:0.9rem;font-weight:700;margin-bottom:0.5rem}
.pi-parties{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem}
.pi-party{border:1px solid #E5E7EB;border-radius:8px;padding:0.75rem 0.9rem;font-size:0.82rem}
.pi-party h3{font-size:0.78rem;color:#6B7280;font-weight:600;margin-bottom:0.4rem}
.pi-party p{margin:0.1rem 0;color:#374151}
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
@media (max-width:640px){.pi-parties{grid-template-columns:1fr}.pi-sheet{padding:1rem}}
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
  <button type="button" class="pi-btn pi-btn-primary" onclick="window.print()"><?= icon('printer') ?>چاپ پیش‌فاکتور</button>
  <button type="button" class="pi-btn" onclick="window.print()"><?= icon('file-down') ?>خروجی PDF</button>
  <a href="cart.php" class="pi-btn">بازگشت به سبد خرید</a>
  <a href="checkout.php" class="pi-back">ادامه ثبت سفارش &#8592;</a>
</div>

<div class="pi-tip">
  برای گرفتن <b>PDF</b>: روی «خروجی PDF» بزنید و در پنجرهٔ چاپ، مقصد (Destination) را روی
  <b dir="ltr">Save as PDF</b> بگذارید و ذخیره کنید.
</div>

<div class="pi-sheet">
  <div class="pi-head">
    <div>
      <div class="pi-title">پیش‌فاکتور فروش</div>
      <div class="pi-brand"><?= h(SITE_NAME) ?><small>فروش قطعات یدکی خودرو</small></div>
    </div>
    <div class="pi-meta">
      <div>شماره: <b dir="ltr"><?= h($piNo) ?></b></div>
      <div>تاریخ: <b><?= h(jDateLong(date('Y-m-d H:i:s'))) ?></b></div>
      <div>ساعت: <b dir="ltr"><?= date('H:i') ?></b></div>
    </div>
  </div>

  <div class="pi-parties">
    <div class="pi-party">
      <h3>فروشنده</h3>
      <p><b><?= h(SITE_NAME) ?></b></p>
      <?php if ($phone !== ''):   ?><p>تلفن: <span dir="ltr"><?= h($phone) ?></span></p><?php endif; ?>
      <?php if ($mobile !== ''):  ?><p>موبایل: <span dir="ltr"><?= h($mobile) ?></span></p><?php endif; ?>
      <?php if ($email !== ''):   ?><p>ایمیل: <span dir="ltr"><?= h($email) ?></span></p><?php endif; ?>
      <?php if ($address !== ''): ?><p>آدرس: <?= h($address) ?></p><?php endif; ?>
    </div>
    <div class="pi-party">
      <h3>خریدار</h3>
      <?php if ($c): ?>
      <p><b><?= h(trim((string)$c['full_name']) !== '' ? $c['full_name'] : 'مشتری') ?></b>
        <?php if (($c['customer_type'] ?? 'retail') === 'partner'): ?>(همکار)<?php endif; ?></p>
      <?php if (trim((string)($c['partner_company'] ?? '')) !== ''): ?><p><?= h($c['partner_company']) ?></p><?php endif; ?>
      <p>موبایل: <span dir="ltr"><?= h($c['mobile']) ?></span></p>
      <?php if (trim((string)($c['address'] ?? '')) !== ''): ?>
      <p>آدرس: <?= h(trim(($c['province'] ?? '') . ' ' . ($c['city'] ?? '') . ' — ' . $c['address'], ' —')) ?></p>
      <?php endif; ?>
      <?php if (trim((string)($c['postal_code'] ?? '')) !== ''): ?><p>کد پستی: <span dir="ltr"><?= h($c['postal_code']) ?></span></p><?php endif; ?>
      <?php else: ?>
      <p style="color:#6B7280;">مهمان — برای درج مشخصات خود
        <a href="login.php?return=preinvoice.php" style="color:#DC2626;">وارد حساب</a> شوید.</p>
      <?php endif; ?>
    </div>
  </div>

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
      <?php $i = 1; foreach ($cartItems as $item): $p = $item['product']; ?>
      <tr>
        <td class="num"><?= $i++ ?></td>
        <td>
          <?= h($p['name']) ?>
          <span class="pi-pt <?= h($item['price_type']) ?>"><?= $item['price_type'] === 'wholesale' ? 'قیمت کلی' : 'قیمت جزئی' ?></span>
        </td>
        <td class="pi-tech"><?= h($p['technical_number']) ?></td>
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
    </tbody>
  </table>

  <div class="pi-foot">
    <div class="pi-notes">
      • این برگه <b>پیش‌فاکتور</b> است و سند فروش قطعی محسوب نمی‌شود.<br>
      • قیمت‌ها بر اساس موجودی و نرخ روز است و تا زمان تأیید سفارش قابل تغییر می‌باشد.<br>
      • «قیمت کلی» با رسیدن تعداد هر کالا به حد تعیین‌شدهٔ همان کالا به‌صورت خودکار اعمال می‌شود.<br>
      • هزینهٔ ارسال در این برگه محاسبه نشده است.
    </div>
    <div class="pi-totals">
      <div><span>تعداد اقلام</span><b><?= number_format(count($cartItems)) ?> قلم</b></div>
      <div><span>تعداد کل</span><b><?= number_format($totalQty) ?> عدد</b></div>
      <?php if ($cartTax > 0): ?>
      <div><span>جمع مالیات</span><b><?= number_format($cartTax, 0, '.', ',') ?><span class="tmn">تومان</span></b></div>
      <?php endif; ?>
      <div class="pi-total"><span>مبلغ قابل پرداخت</span><span><?= number_format($cartTotal + $cartTax, 0, '.', ',') ?><span class="tmn">تومان</span></span></div>
    </div>
  </div>

  <div class="pi-sign">
    <div>امضا و مهر فروشنده</div>
    <div>امضای خریدار</div>
  </div>
</div>

</body>
</html>
