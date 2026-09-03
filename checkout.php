<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cart-functions.php';

requireCustomerLogin('checkout.php');

$cartItems = getCartItems();
$cartTotal = getCartTotal();
$taxTotal  = itemsTaxTotal($cartItems);

if (empty($cartItems)) {
    redirect('cart.php');
}

$c = currentCustomer();
$errors = [];
/* پس از ارسال عکس در part-check.php به اینجا می‌آید (?sent=1) — قبلا این
   تأییدیه در صفحهٔ جداگانهٔ stock-check.php نشان داده می‌شد؛ حالا همین‌جا،
   بالای گیت موجودی. */
$photosJustSent = $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['sent']);

/* ---------- گارد مرحلهٔ «بررسی عکس نمونهٔ قطعه» ----------
   اگر مشتری هنوز اقدامی نکرده (نه آپلود، نه رد کردن)، به part-check.php
   برمی‌گردد. فقط روی GET؛ چون اگر POST ثبت نهایی هم ریدایرکت شود، مشتری‌ای
   که همین حالا مرحله را گذرانده ولی نشستش لب‌مرزی است سفارشش را از دست
   می‌دهد.
   ۲۰۲۶-۰۹-۰۳: دیگر به stock-check.php ریدایرکت نمی‌شود (خواستهٔ کاربر) —
   وضعیت «در انتظار بررسی موجودی» حالا همین‌جا، زیر «مبلغ قابل پرداخت»،
   به‌صورت زنده نشان داده می‌شود؛ روش پرداخت هم تا تأیید قفل می‌ماند. */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pcGate = partCheckGateUrl($cartItems, $c);
    if ($pcGate === 'part-check.php') redirect($pcGate);
}

/* حالت «فقط تأیید موجودی» (۲۰۲۶-۰۹-۰۳، خواستهٔ کاربر): وقتی بررسی عکس
   خاموش است ولی تأیید موجودی روشن، مشتری هرگز به part-check.php فرستاده
   نمی‌شود (partCheckGateUrl() بالا در این حالت همیشه '' برمی‌گرداند) —
   به‌جایش همین‌جا یک ردیف بی‌صدا (بدون عکس) برایش ساخته می‌شود تا در صف
   ادمین بیفتد. */
if (!partCheckOn() && stockGateActive()) {
    stockCheckEnsureRow($cartItems, $c);
}

/* آیا هنوز منتظر تأیید موجودی هستیم؟ همان قاعدهٔ partCheckPassed() —
   چه با بررسی عکس چه بدونش. */
$stockPending = (partCheckOn() || stockGateActive()) && !partCheckPassed($cartItems, $c);

/* روش‌های ارسال فعال. انتخاب فقط از صفحهٔ سبد خرید می‌آید (در نشست سرور ذخیره
   شده) — تصمیم مدیر: «امکان انتخاب رو … از توی صفحه بعد ثبت سفارش بردار که دیگه
   صفحه بعد فقط ثبت سفارش نهایی باشه». پس این‌جا نه انتخابگری هست و نه فرم
   می‌تواند روش را عوض کند؛ اگر نشست انتخابی نداشت (نشست منقضی شده یا آدرس
   مستقیم تایپ شده) مشتری به سبد برمی‌گردد تا همان‌جا انتخاب کند. */
$shipOn      = shippingReady();
$shipMethods = $shipOn ? shippingAvailableMethods() : [];
$shipKeys    = array_keys($shipMethods);
$shipChosen  = shippingSessionMethod();
if (!in_array($shipChosen, $shipKeys, true)) $shipChosen = '';
if ($shipMethods && $shipChosen === '') {
    /* پیش از include هدر است، پس ریدایرکت ممکن است (config.php بافر خروجی ندارد) */
    redirect('cart.php?pick=ship');
}
$shipLabel = $shipChosen !== '' ? (string)($shipMethods[$shipChosen]['label'] ?? '') : '';
$shipBadge = $shipChosen !== '' ? shippingBadgeShort($shipChosen) : '';

/* ---------- نرخ‌نامه: شهر مقصد + وزن ⇒ هزینهٔ خودکار ----------
   شهر در اولین نمایش از پروفایل مشتری خوانده می‌شود (کسی که تبریز ثبت‌نام کرده
   بی‌آنکه چیزی بنویسد نرخ تبریز را می‌بیند) و وزن از مجموع وزن کالاهای سبد،
   ضرب در تعداد. هر ردیف نرخ‌نامه می‌گوید «هر {واحد} کیلوگرم، {مبلغ}»؛ وزن سبد
   بر واحد تقسیم و به بالا رند می‌شود. وزن محصول اختیاری است: اگر هیچ کالایی وزن
   نداشته باشد یک واحد حساب می‌شود. جملهٔ توضیحی نرخ («نرخ فلان‌شهر: هر ۱
   کیلوگرم …») این‌جا نمایش داده نمی‌شود (خواستهٔ مدیر) — تنها خود رقم می‌آید. */
$shipCityIn = trim((string)($_POST['city'] ?? $c['city'] ?? ''));
$shipWeight = shippingCartWeight($cartItems)[0];
$shipTexts  = shippingRateTexts();
/* فقط نرخ همان روش انتخاب‌شده به جاوااسکریپت می‌رود: با عوض‌شدن شهر باید رقم
   ارسال و مبلغ قابل پرداخت زنده به‌روز شوند، ولی روش دیگر عوض‌شدنی نیست. */
$shipJs     = shippingRateJs($shipChosen !== '' ? [$shipChosen] : [], $shipWeight);

/* هزینهٔ ارسال انتخاب فعلی. برای روش‌های «پس‌کرایه» هیچ محاسبه‌ای انجام
   نمی‌شود: نه رقمی نمایش داده می‌شود و نه چیزی به مبلغ سفارش اضافه می‌شود. */
$shipRes  = $shipChosen !== '' ? shippingResolveCost($shipChosen, $shipCityIn, $shipWeight) : null;
$shipCost = $shipRes ? (int)$shipRes['cost'] : 0;
$payable  = $cartTotal + $taxTotal + $shipCost;

/* روش‌های پرداخت فعال (از تنظیمات ادمین). پس از محاسبهٔ «مبلغ قابل پرداخت»
   خوانده می‌شود چون پرداخت اعتباری می‌تواند حد پایین مبلغ داشته باشد
   (pay_credit_min) و باید با همان رقمی سنجیده شود که مشتری می‌پردازد. */
$payMethods = paymentAvailableMethods($payable, isApprovedPartner($c));
$payKeys    = array_keys($payMethods);
$payDefault = $_POST['payment_method'] ?? ($payKeys[0] ?? 'cod');
if (!in_array($payDefault, $payKeys, true)) $payDefault = $payKeys[0] ?? 'cod';

/* هزینه‌های باربری (بخش جدای تنظیمات) — فقط زیر روش باربری نشان داده می‌شود */
$barCost = shippingBarbariCost();
$barDesc = shippingBarbariDesc();
$barHas  = ($barCost > 0 || $barDesc !== '');
$barNow  = $barHas && $shipChosen !== '' && shippingIsBarbari($shipChosen);

/* قاعدهٔ ارسال↔پرداخت (shippingAllowedPayKeys): «پرداخت در محل» فقط برای روشی
   که تیک آن را دارد (ارسال با پیک) مجاز است — و روی همان روش، پرداخت اینترنتی
   و کارت‌به‌کارت هم باز است، چون ممکن است مشتری مشهدی آنلاین پرداخت کند.
   گزینه‌های غیرمجاز در فرم disabled می‌شوند (پس اصلا ارسال نمی‌شوند) و در سرور
   هم دوباره بررسی می‌شود. */
$payAllowed = shippingAllowedPayKeys($shipChosen, $payKeys);
if (!in_array($payDefault, $payAllowed, true)) $payDefault = $payAllowed[0] ?? '';

/* ---------- کارت به کارت: ورودی‌های واریز روی همین صفحه ----------
   خواستهٔ مدیر: «یک گزینه کارت به کارت رو هم بذار که بتونه اطلاعات پرداخت رو
   وارد کنه … ۴ رقم آخر کارت، مبلغ و تاریخ». مقدارها پس از خطای فرم دوباره پر
   می‌شوند. مبلغ پیش‌فرض همان مبلغ قابل پرداخت و تاریخ و ساعت پیش‌فرض همین
   لحظه است، چون واریز معمولا همان لحظه انجام می‌شود؛ همه قابل ویرایش‌اند.
   تاریخ از تقویم شمسی همین صفحه انتخاب می‌شود و ساعت از دو انتخابگر «ساعت»
   و «دقیقه» (خواستهٔ مدیر: «تاریخ رو از روی تقویم فارسی بهم بده / و زمان رو هم
   با یک ساعت و دقیقه قابل انتخاب»).
   منطقهٔ زمانی صریحا تهران گرفته می‌شود: منطقهٔ زمانی سرور تنظیم نشده و
   پیش‌فرض ساعت نباید چند ساعت عقب‌تر از ساعت واقعی مشتری باشد. */
$c2cReady = (isset($payMethods['card']) && paymentC2cReady());
try { $c2cNow = new DateTime('now', new DateTimeZone('Asia/Tehran')); }
catch (Throwable $e) { $c2cNow = new DateTime(); }

/* تاریخ: ارقام فارسی به لاتین و یکدست‌کردن جداکننده. مشتری بی‌جاوااسکریپت که
   «۱۴۰۵/۶/۳» تایپ می‌کند باید همان شکلی ذخیره شود که تقویم می‌نویسد
   (۱۴۰۵/۰۶/۰۳) تا ادمین همه را یک‌جور ببیند. اگر الگو نخواند، دست‌نخورده
   می‌ماند — سرور فقط خالی‌نبودن را الزام می‌کند. */
$c2cDate = trim(faToLatinDigits((string)($_POST['c2c_date'] ?? '')));
if (preg_match('~^(\d{4})\s*[/\-.]\s*(\d{1,2})\s*[/\-.]\s*(\d{1,2})$~', $c2cDate, $mDate)) {
    $c2cDate = sprintf('%04d/%02d/%02d', (int)$mDate[1],
                       min(12, max(1, (int)$mDate[2])), min(31, max(1, (int)$mDate[3])));
}

/* ساعت و دقیقه دو انتخابگر جدا هستند (پس بدون جاوااسکریپت هم کار می‌کنند) و
   این‌جا به یک رشتهٔ «HH:MM» تبدیل می‌شوند تا با تاریخ در یک ستون ذخیره شود. */
$c2cH = ($_POST['c2c_time_h'] ?? '') === ''
        ? (int)$c2cNow->format('G')
        : (int)preg_replace('/\D+/', '', faToLatinDigits((string)$_POST['c2c_time_h']));
$c2cM = ($_POST['c2c_time_m'] ?? '') === ''
        ? (int)$c2cNow->format('i')
        : (int)preg_replace('/\D+/', '', faToLatinDigits((string)$_POST['c2c_time_m']));
if ($c2cH < 0 || $c2cH > 23) $c2cH = 0;
if ($c2cM < 0 || $c2cM > 59) $c2cM = 0;

$c2cIn = [
    'ref'    => trim((string)($_POST['c2c_ref'] ?? '')),
    'amount' => trim((string)($_POST['c2c_amount'] ?? '')),
    'last4'  => trim((string)($_POST['c2c_last4'] ?? '')),
    'date'   => $c2cDate,
    'time'   => sprintf('%02d:%02d', $c2cH, $c2cM),
];
if ($c2cIn['amount'] === '') $c2cIn['amount'] = (string)$payable;
if ($c2cIn['date'] === '')   $c2cIn['date']   = jDate($c2cNow->format('Y-m-d'));
$c2cIn['paid_text'] = trim($c2cIn['date'] . ' - ' . $c2cIn['time']);

/* ---------- چک: ورودی‌های ثبت چک روی همین صفحه ----------
   با تصمیم تازهٔ کاربر (۲۰۲۶-۰۸-۲۹) فرم دوباره برگشت — عینا به الگوی
   کارت‌به‌کارت بالا: بانک، سریال، تاریخ، مبلغ، در وجه و شناسهٔ صیاد چک همین‌جا
   گرفته می‌شود. مقدارها پس از خطای فرم دوباره پر می‌شوند؛ مبلغ پیش‌فرض همان
   مبلغ قابل پرداخت و تاریخ پیش‌فرض همین امروز است، همه قابل ویرایش‌اند.
   اصل چک هم‌چنان باید فیزیکی ارسال/تحویل شود (پیغامش در order-success.php
   با paymentChequeNoteText() نشان داده می‌شود) — این فرم فقط اطلاعات چک را
   از پیش می‌گیرد تا مدیر آن‌ها را با اصل رسیده مقایسه کند. */
$chqReady = (isset($payMethods['cheque']) && paymentChequeReady());

$chqDate = trim(faToLatinDigits((string)($_POST['chq_date'] ?? '')));
if (preg_match('~^(\d{4})\s*[/\-.]\s*(\d{1,2})\s*[/\-.]\s*(\d{1,2})$~', $chqDate, $mDate2)) {
    $chqDate = sprintf('%04d/%02d/%02d', (int)$mDate2[1],
                       min(12, max(1, (int)$mDate2[2])), min(31, max(1, (int)$mDate2[3])));
}

$chqIn = [
    'bank'   => trim((string)($_POST['chq_bank'] ?? '')),
    'number' => trim((string)($_POST['chq_number'] ?? '')),
    'date'   => $chqDate,
    'amount' => trim((string)($_POST['chq_amount'] ?? '')),
    'payee'  => trim((string)($_POST['chq_payee'] ?? '')),
    'sayad'  => trim((string)($_POST['chq_sayad'] ?? '')),
];
if ($chqIn['amount'] === '') $chqIn['amount'] = (string)$payable;
if ($chqIn['date'] === '')   $chqIn['date']   = jDate($c2cNow->format('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $name     = trim($_POST['customer_name'] ?? '');
    $mobile   = $c['mobile']; // شمارهٔ ثابت حساب کاربری
    $province = trim($_POST['province'] ?? '');
    /* نام رسمی شهر از فهرست شهرها؛ اگر نخواند همان چیزی که مشتری نوشته
       می‌ماند. یکدست‌شدن نام باعث می‌شود نرخ‌نامه و صفحهٔ سبد خرید بعدی هم
       همین شهر را بشناسند. */
    $city     = shippingCityCanonical(trim($_POST['city'] ?? ''));
    $address  = trim($_POST['customer_address'] ?? '');
    $postal   = trim(faToLatinDigits($_POST['postal_code'] ?? ''));
    $notes    = trim($_POST['notes'] ?? '');

    /* گیت «تأیید موجودی»: رادیوهای روش پرداخت در حالت انتظار disabled‌اند،
       ولی فرم دست‌کاری‌شده هم نباید بگذرد — سرور دوباره همان چیزی را
       می‌سنجد که بالا برای نمایش محاسبه شده بود. */
    if ($stockPending) {
        $errors[] = 'تا تأیید موجودی توسط کارشناسان ما، امکان ثبت سفارش نیست.';
    }

    /* روش پرداخت فقط از میان روش‌های فعال پذیرفته می‌شود (ورودی کاربر قابل جعل است) */
    $payMethod = (string)($_POST['payment_method'] ?? '');
    if (!in_array($payMethod, $payKeys, true)) $payMethod = $payKeys[0] ?? 'cod';

    /* کارت به کارت: اطلاعات واریز همین‌جا گرفته می‌شود (خواستهٔ مدیر) تا مشتری
       یک مرحلهٔ کمتر داشته باشد. اعتبار پیش از INSERT سنجیده می‌شود، وگرنه
       سفارشی ثبت می‌شد که اطلاعات واریزش ناقص است. */
    $c2cOn = ($payMethod === 'card' && paymentC2cReady());
    if ($c2cOn) {
        $c2cErr = paymentC2cClean($c2cIn)['error'];
        if ($c2cErr !== '') $errors[] = $c2cErr;
    }

    /* چک: اطلاعات چک هم همین‌جا گرفته می‌شود (تصمیم تازهٔ کاربر) — همان الگوی
       کارت‌به‌کارت بالا. اعتبار پیش از INSERT سنجیده می‌شود، وگرنه سفارشی ثبت
       می‌شد که اطلاعات چکش ناقص است. */
    $chqOn = ($payMethod === 'cheque' && paymentChequeReady());
    if ($chqOn) {
        $chqErr = paymentChequeClean($chqIn)['error'];
        if ($chqErr !== '') $errors[] = $chqErr;
    }

    /* روش ارسال هم فقط از میان روش‌های فعال؛ هزینه در سرور از نرخ‌نامه/تنظیمات
       محاسبه می‌شود، نه از فرم، تا کسی نتواند با دست‌کاری فرم هزینه را صفر کند.
       شهر و وزن همان چیزی است که ثبت می‌شود، پس رقم سفارش با رقمی که مشتری
       دیده یکی است. */
    $shipMethod = $shipChosen;
    $shipCost   = $shipMethod !== '' ? shippingChargeCost($shipMethod, $city, $shipWeight) : 0;
    $payable    = $cartTotal + $taxTotal + $shipCost;

    if ($name === '')    $errors[] = 'نام و نام خانوادگی الزامی است.';
    if ($address === '') $errors[] = 'آدرس الزامی است.';
    if ($postal === '')  $errors[] = 'کد پستی الزامی است.';
    if ($shipMethods && $shipMethod === '') $errors[] = 'روش ارسال را انتخاب کنید.';

    /* قاعدهٔ ارسال↔پرداخت دوباره در سرور بررسی می‌شود: گزینه‌های غیرمجاز در فرم
       disabled هستند، ولی بدون جاوااسکریپت یا با فرم دست‌کاری‌شده هم نباید
       ترکیب نامعتبر ثبت شود (مثلا پرداخت در محل برای ارسال پستی). */
    if ($shipMethod !== '' && !in_array($payMethod, shippingAllowedPayKeys($shipMethod, $payKeys), true)) {
        $errors[] = shippingPayRuleNote($shipMethod) . ' لطفا روش پرداخت را اصلاح کنید.';
    }

    /* روش محدود به یک شهر (پیک مشهد) با شهر همین فرم هم سنجیده می‌شود: شهر
       تحویل روی این صفحه قابل تغییر است و پیک شهری نباید به شهر دیگری برود.
       شهر خالی خطا نمی‌دهد (همان قاعدهٔ «نمی‌دانیم یعنی ممنوع نیست»). */
    if ($shipMethod !== '' && shippingCityBlocked($shipMethod, $city)) {
        $errors[] = '«' . shippingLabel($shipMethod) . '» برای شهر «' . $city
                  . '» فعال نیست؛ روش ارسال را در سبد خرید عوض کنید.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // تکمیل/به‌روزرسانی پروفایل مشتری
            $pdo->prepare("UPDATE customers SET full_name=?, province=?, city=?, address=?, postal_code=? WHERE id=?")
                ->execute([$name, $province, $city, $address, $postal, (int)$c['id']]);

            $fullAddress = trim(
                ($province !== '' ? $province . ' - ' : '') .
                ($city !== '' ? $city . ' - ' : '') .
                $address .
                ($postal !== '' ? ' - کدپستی: ' . $postal : '')
            );

            /* سفارش‌های آنلاین با وضعیت «در انتظار پرداخت» ثبت می‌شوند تا در بازگشت از درگاه تکمیل شوند.
               «پرداخت اول ماه» چیزی برای گزارش‌کردن از سمت همکار ندارد و «چک»
               اطلاعاتش را همین‌جا (پایین‌تر) ذخیره می‌کند، ولی هیچ‌کدام «پرداخت‌شده»
               نیستند، پس هر دو مستقیم «در انتظار» می‌شوند تا در صف پیگیری
               مدیر/تسویهٔ همکاران بیفتند. */
            $payStatus = paymentIsOnline($payMethod) ? 'pending' : (in_array($payMethod, ['partner_month', 'cheque'], true) ? 'pending' : 'unpaid');

            /* ستون‌ها بر اساس مهاجرت‌های اجرا‌شده ساخته می‌شوند تا صفحه روی
               نصب‌های قدیمی‌تر (بدون ستون‌های پرداخت یا ارسال) هم کار کند. */
            $cols = ['customer_id', 'customer_name', 'customer_mobile', 'customer_address', 'total_amount', 'status', 'notes'];
            $vals = [(int)$c['id'], $name, $mobile, $fullAddress, $payable, 'pending', $notes];
            if (paymentReady()) {
                $cols[] = 'payment_method';  $vals[] = $payMethod;
                $cols[] = 'payment_status';  $vals[] = $payStatus;
            }
            if ($shipOn) {
                $cols[] = 'shipping_method'; $vals[] = $shipMethod;
                $cols[] = 'shipping_cost';   $vals[] = $shipCost;
            }
            $taxItemsOn = taxItemsReady();
            if ($taxItemsOn) { $cols[] = 'tax_total'; $vals[] = $taxTotal; }
            $pdo->prepare("INSERT INTO orders (" . implode(', ', $cols) . ") VALUES ("
                          . implode(', ', array_fill(0, count($cols), '?')) . ")")->execute($vals);
            $orderId = $pdo->lastInsertId();

            /* ستون‌های مالیات روی order_items هم گارد می‌شوند تا بین آپلود کد و
               اجرای مهاجرت، ثبت سفارش 500 نگیرد — چیزی که بدون این گارد ممکن بود
               رخ بدهد چون این ستون‌ها تازه‌اند (طبق قاعدهٔ ترتیب دیپلوی). */
            $itemCols = "order_id, product_id, product_name, price, quantity, price_type, subtotal" . ($taxItemsOn ? ", tax_percent, tax_amount" : "");
            $itemPh   = "?, ?, ?, ?, ?, ?, ?" . ($taxItemsOn ? ", ?, ?" : "");
            $stmtItem = $pdo->prepare("INSERT INTO order_items ($itemCols) VALUES ($itemPh)");
            $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");

            foreach ($cartItems as $item) {
                $p = $item['product'];
                $itemVals = [$orderId, $p['id'], $p['name'], $item['price'], $item['quantity'], $item['price_type'], $item['subtotal']];
                if ($taxItemsOn) { $itemVals[] = $item['tax_percent']; $itemVals[] = $item['tax_amount']; }
                $stmtItem->execute($itemVals);
                $stmtStock->execute([$item['quantity'], $p['id'], $item['quantity']]);
            }

            /* اطلاعات واریز کارت‌به‌کارت همراه خود سفارش ثبت می‌شود و وضعیت
               پرداخت «در انتظار تأیید واریز» می‌ماند تا مدیر در جزئیات سفارش
               تأیید کند و بعد روند ارسال را شروع کند. */
            if ($c2cOn) {
                $c2cErr = paymentC2cSave($orderId, $c2cIn);
                if ($c2cErr !== '') throw new Exception($c2cErr);
            }

            /* اطلاعات چک هم همراه خود سفارش ذخیره می‌شود؛ وضعیت پرداخت
               «در انتظار» می‌ماند تا مدیر اصل چک رسیده را با این اطلاعات
               مقایسه و بعد «دریافت چک» را در جزئیات سفارش ثبت کند. */
            if ($chqOn) {
                $chqErr = paymentChequeSave($orderId, $chqIn);
                if ($chqErr !== '') throw new Exception($chqErr);
            }

            $pdo->commit();

            /* درخواست بررسی عکس (اگر بود) به همین سفارش گره می‌خورد تا ادمین آن
               را در «جزئیات سفارش» ببیند؛ نشانهٔ «رد کردن مرحله» هم پاک می‌شود. */
            partCheckAttachToOrder((int)$orderId, (int)$c['id']);

            /* «بررسی موجودی» اولین مرحلهٔ روند ارسال است و تا ثبت نشود هیچ
               پرداختی — حتی درگاه بانکی — باز نمی‌شود (خواستهٔ مدیر). اگر همین
               سفارش از «بررسی عکس نمونهٔ قطعه» با موجودی تأییدشده رد شده
               باشد، همین‌جا خودکار طی می‌شود؛ وگرنه مدیر باید در جزئیات
               سفارش تیک بزند و مشتری تا آن لحظه به صفحهٔ نتیجه (نه درگاه) می‌رود. */
            orderAutoPassStockCheck($orderId);

            cartClear();

            $stockUnlocked = true;
            if (trackStockReady()) {
                $stChk = $pdo->prepare("SELECT track_stock_at FROM orders WHERE id = ?");
                $stChk->execute([$orderId]);
                $stockUnlocked = (bool)$stChk->fetchColumn();
            }

            /* پرداخت آنلاین: مشتری به درگاه منتقل می‌شود — فقط اگر گیت موجودی باز باشد؛
               وگرنه به صفحهٔ نتیجه می‌رود که پیام «در انتظار بررسی موجودی» را
               نشان می‌دهد و دکمهٔ پرداخت را تا باز شدن گیت پنهان نگه می‌دارد.
               پرداخت در محل/کارت‌به‌کارت/چک همیشه مستقیم به همان صفحه می‌روند —
               این‌ها «پرداخت آنی» نیستند، بلکه اطلاعاتی‌اند که مدیر بعدا
               بررسی می‌کند، پس گزارش‌کردنشان به این گیت گره نمی‌خورد. */
            if ($stockUnlocked && paymentReady() && paymentIsOnline($payMethod)) {
                redirect('payment-start.php?order=' . $orderId);
            }
            redirect('order-success.php?id=' . $orderId);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'خطا در ثبت سفارش. لطفا دوباره تلاش کنید.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h1 class="page-title">ثبت سفارش</h1>

    <?php if ($errors): ?>
    <div class="flash flash-error">
        <?php foreach ($errors as $e): ?>
        <p><?= h($e) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="checkout-form">
        <?php /* data-goods شامل مالیات هم می‌شود (نه فقط جمع کالاها) چون جاوااسکریپت
                زیر همین صفحه با فرمول «goods + هزینهٔ ارسال» مبلغ قابل‌پرداخت را
                زنده حساب می‌کند و مالیات با تغییر شهر/روش ارسال عوض نمی‌شود؛
                ردیف «جمع کالاها»ی زیر همچنان فقط جمع خالص کالاها را نشان می‌دهد. */ ?>
        <div class="checkout-summary" data-goods="<?= (int)($cartTotal + $taxTotal) ?>">
            <?php foreach ($cartItems as $item): ?>
            <div class="checkout-summary-item">
                <span><?= h($item['product']['name']) ?> × <?= $item['quantity'] ?></span>
                <span><?= formatPrice($item['subtotal']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if ($shipMethods): ?>
            <div class="checkout-summary-item">
                <span>جمع کالاها</span>
                <span><?= formatPrice($cartTotal) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($taxTotal > 0): ?>
            <div class="checkout-summary-item">
                <span><?= icon('receipt', 'ic-sm') ?> مالیات</span>
                <span><?= formatPrice($taxTotal) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($shipMethods): ?>
            <?php /* روش ارسال در سبد انتخاب شده؛ این‌جا فقط نامش را با یک لینک
                    «تغییر» به سبد نشان می‌دهیم تا مشتری بداند رقم از کجا آمده. */ ?>
            <div class="checkout-summary-item">
                <span><?= icon('truck', 'ic-sm') ?> هزینهٔ ارسال
                    <?php if ($shipLabel !== ''): ?>
                    <i class="cs-name"><?= h($shipLabel) ?><?php if ($shipBadge !== ''): ?> <b><?= h($shipBadge) ?></b><?php endif; ?></i>
                    <a href="cart.php" class="cs-edit"><?= icon('refresh', 'ic-sm') ?> تغییر</a>
                    <?php endif; ?>
                </span>
                <span id="ship-cost-cell"><?= $shipRes === null ? h($shipTexts['pick']) : h(shippingCostText($shipRes['display'], $shipChosen)) ?></span>
            </div>
            <?php endif; ?>
            <div class="checkout-summary-item">
                <span>مبلغ قابل پرداخت</span>
                <span id="payable-cell"><?= formatPrice($payable) ?></span>
            </div>
        </div>

        <?php /* ---------- گیت «تأیید موجودی» — همین‌جا، زیر مبلغ قابل پرداخت ----------
                خواستهٔ کاربر (۲۰۲۶-۰۹-۰۳): به‌جای ریدایرکت به یک صفحهٔ جدا
                (stock-check.php، که همان روز کلا حذف شد چون دیگر اضافه بود)،
                همین کادر زیر خلاصهٔ سفارش نشان داده می‌شود؛ تا کارشناس تأیید
                نکند، روش پرداخت پایین‌تر قفل می‌ماند (چراغ زرد چشمک‌زن). با
                تأیید، همین کادر خودکار (بدون کاری از سمت مشتری) به چک سبز
                تبدیل و روش پرداخت باز می‌شود — همان الگوی poll ده‌ثانیه‌ای
                order-success.php. */ ?>
        <?php if ($photosJustSent): ?>
        <div class="flash flash-success"><?= icon('check-circle', 'ic-sm') ?> عکس‌های شما ثبت شد و برای بررسی به کارشناس ما رفت.</div>
        <?php endif; ?>
        <?php if ($stockPending): ?>
        <div class="checkout-stockgate" id="checkoutStockGate">
            <span class="pchk-badge is-wait pchk-badge-blink"><?= icon('clock', 'ic-sm') ?> در انتظار بررسی موجودی</span>
            <p><b>سفارش شما در انتظار «بررسی موجودی» است.</b> کارشناسان ما ابتدا موجودی کالا را بررسی می‌کنند؛
               به‌محض تأیید، امکان پرداخت (آنلاین یا دیگر روش‌ها) برای شما فعال می‌شود.</p>
        </div>
        <script>setTimeout(function () { location.reload(); }, 10000);</script>
        <?php elseif (partCheckOn() || stockGateActive()): ?>
        <div class="checkout-stockgate is-ok">
            <span class="pchk-badge is-ok"><?= icon('check-circle', 'ic-sm') ?> موجودی تأیید شد</span>
            <p>موجودی کالا تأیید شد؛ روش پرداخت را انتخاب و سفارش را ثبت کنید.</p>
        </div>
        <?php endif; ?>

        <?php /* چیدمان دوستونه (خواستهٔ کاربر): مشخصات و آدرس سمت راست، روش
                ارسال و پرداخت سمت چپ. در RTL اولین فرزند گرید سمت راست
                می‌نشیند، پس ستون مشخصات اول می‌آید. زیر ۸۶۰ پیکسل ستون‌ها
                روی هم می‌آیند (اول مشخصات، بعد ارسال و پرداخت). */ ?>
        <div class="checkout-cols">
            <div class="checkout-box">
                <div class="checkout-box-title"><?= icon('user') ?> مشخصات و آدرس تحویل</div>

                <div class="form-group">
                    <label>شماره موبایل (حساب شما)</label>
                    <input type="text" class="form-control" value="<?= h($c['mobile']) ?>" dir="ltr" readonly style="opacity:0.7;">
                </div>
                <div class="form-group">
                    <label for="customer_name">نام و نام خانوادگی *</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control"
                           value="<?= h($_POST['customer_name'] ?? $c['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-row-2">
                    <?php /* استان و شهر هر دو نوار کشویی‌اند (خواستهٔ کاربر) و به هم
                             وابسته: با انتخاب استان، فهرست شهر همان استان می‌شود.
                             شهر مبنای محاسبهٔ خودکار هزینهٔ ارسال است، پس انتخاب از
                             فهرست جلوی غلط‌های تایپی را می‌گیرد. */ ?>
                    <?= shippingProvinceCityFields(
                            $_POST['province'] ?? $c['province'] ?? '',
                            $shipCityIn,
                            'شهرتان را از فهرست انتخاب کنید تا هزینهٔ ارسال خودکار حساب شود.'
                        ) ?>
                </div>
                <div class="form-group">
                    <label for="customer_address">آدرس کامل *</label>
                    <textarea name="customer_address" id="customer_address" class="form-control" required><?= h($_POST['customer_address'] ?? $c['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="postal_code">کد پستی *</label>
                    <input type="text" name="postal_code" id="postal_code" class="form-control" dir="ltr" inputmode="numeric" value="<?= h($_POST['postal_code'] ?? $c['postal_code'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="notes">توضیحات سفارش</label>
                    <textarea name="notes" id="notes" class="form-control"><?= h($_POST['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="checkout-col-side">
                <?php if ($barNow): ?>
                <?php /* «هزینه‌های باربری» از تنظیمات ادمین — فقط اطلاع‌رسانی و فقط
                        وقتی روش ارسال انتخاب‌شده باربری است؛ مبلغی که به سفارش
                        اضافه می‌شود همان هزینهٔ خود روش است. */ ?>
                <div class="ship-barbari" id="ship-barbari">
                    <b><?= icon('truck', 'ic-sm') ?> هزینه‌های باربری</b>
                    <?php if ($barCost > 0): ?>
                    <span class="ship-barbari-cost"><?= h(formatPrice($barCost)) ?></span>
                    <?php endif; ?>
                    <?php if ($barDesc !== ''): ?>
                    <p><?= nl2br(h($barDesc)) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (paymentReady()): ?>
                <?php /* قاعدهٔ ارسال↔پرداخت: گزینه‌های ناسازگار با روش ارسالی که در
                        سبد انتخاب شده disabled می‌شوند (پس در POST نمی‌آیند) و کم‌رنگ
                        نشان داده می‌شوند. چون روش ارسال روی این صفحه عوض‌شدنی نیست،
                        همین حالت رندرشدهٔ سرور نهایی است و اسکریپتی آن را عوض
                        نمی‌کند. سرور در ثبت سفارش دوباره بررسی می‌کند. */ ?>
                <div class="pay-picker <?= $stockPending ? 'is-locked' : '' ?>" id="pay-picker">
                    <div class="pay-picker-title"><?= icon('credit-card') ?> روش پرداخت</div>
                    <?php if ($stockPending): ?>
                    <p class="pay-rule" id="pay-rule-stock">
                        <?= icon('clock', 'ic-sm') ?> <span>تا تأیید موجودی (کادر بالا)، انتخاب روش پرداخت قفل است.</span>
                    </p>
                    <?php endif; ?>
                    <?php /* پیام قاعدهٔ پرداخت («برای … پرداخت در محل امکان‌پذیر نیست»)
                            برداشته شد — تصمیم مدیر: خود گزینهٔ کم‌رنگ و غیرفعال کافی
                            است. جایش، فقط برای روش‌های پس‌کرایه، توضیح واقعا مفیدی
                            می‌آید: کرایه هنگام تحویل گرفته می‌شود و در این مبلغ نیست. */ ?>
                    <?php if (($collectNote = shippingCollectNote($shipChosen)) !== ''): ?>
                    <p class="pay-rule" id="pay-rule">
                        <?= icon('truck', 'ic-sm') ?> <span><?= h($collectNote) ?></span>
                    </p>
                    <?php endif; ?>
                    <?php foreach ($payMethods as $pk => $pd):
                        /* در حالت انتظار تأیید موجودی، همه گزینه‌ها هم‌الگوی
                           گزینهٔ ناسازگار با روش ارسال کم‌رنگ/غیرقابل‌کلیک
                           می‌شوند (خواستهٔ کاربر: «تمام مراحل روش پرداخت
                           غیرفعال باشه») — همان کلاس is-off، بدون CSS تازه. */
                        $pOff = !in_array($pk, $payAllowed, true) || $stockPending;
                    ?>
                    <label class="pay-opt <?= $payDefault === $pk ? 'is-on' : '' ?> <?= $pOff ? 'is-off' : '' ?>">
                        <input type="radio" name="payment_method" value="<?= h($pk) ?>"
                               <?= $payDefault === $pk ? 'checked' : '' ?> <?= $pOff ? 'disabled' : '' ?>>
                        <span class="pay-opt-ic"><?= icon($pd['icon']) ?></span>
                        <span class="pay-opt-body">
                            <b><?= h($pd['label']) ?></b>
                            <?php if ($pk === 'cod'): ?>
                            <small><?= h(getSetting('pay_cod_note', 'مبلغ سفارش هنگام تحویل کالا دریافت می‌شود.')) ?></small>
                            <?php elseif ($pk === 'card'): ?>
                            <?php /* کارت به کارت: شمارهٔ کارت مقصد همین‌جا نشان داده می‌شود تا
                                    مشتری پیش از ثبت سفارش واریز کند و بعد چهار مورد پایین
                                    (شناسهٔ واریز، مبلغ، چهار رقم آخر کارت مبدأ و تاریخ) را
                                    در همین صفحه پر کند. */ ?>
                            <?php if (($cardPan = trim((string)getSettingRaw('pay_card_number', ''))) !== ''): ?>
                            <div class="pay-card-mini">
                                <div class="pay-card-mini-row"><span>شمارهٔ کارت</span><b dir="ltr" class="pay-pan-inline"><?= h($cardPan) ?></b></div>
                                <?php if (($cardOwner = trim((string)getSettingRaw('pay_card_holder', ''))) !== ''): ?>
                                <div class="pay-card-mini-row"><span>به نام</span><b class="pay-holder-inline"><?= h($cardOwner) ?></b></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <small>پس از واریز، اطلاعات آن را در کادر پایین وارد کنید؛ سفارش تا تأیید فروشگاه «در انتظار تأیید واریز» می‌ماند.</small>
                            <?php elseif ($pk === 'credit'): ?>
                            <?php /* پرداخت اعتباری/اقساطی: متن توضیح از تنظیمات می‌آید چون نام و
                                    شرایط ارائه‌دهندهٔ اعتبار متفاوت است. اگر مدیر متن را خالی
                                    کند، جملهٔ عمومی نشان داده می‌شود. */ ?>
                            <small><?= h(getSetting('pay_credit_note', 'مبلغ سفارش را به‌صورت اعتباری/اقساطی بپردازید. پس از انتخاب، به درگاه ارائه‌دهندهٔ اعتبار می‌روید.')) ?></small>
                            <?php if (($crMin = paymentCreditMin()) > 0): ?>
                            <small>حداقل مبلغ سفارش برای این روش: <?= formatPrice($crMin) ?></small>
                            <?php endif; ?>
                            <?php elseif ($pk === 'sim'): ?>
                            <small>حالت آزمایشی فعال است؛ این گزینه پول واقعی جابه‌جا نمی‌کند و فقط برای تست فرآیند است.</small>
                            <?php elseif ($pk === 'partner_month'): ?>
                            <?php /* ویژهٔ همکار تأییدشده — چیزی اینجا گرفته نمی‌شود؛ سفارش
                                    کامل می‌شود و در انتظار پیگیری مدیر برای تسویهٔ اول ماه می‌ماند. */ ?>
                            <small>سفارش بدون پرداخت الان ثبت می‌شود؛ تسویه در ابتدای ماه انجام می‌شود.</small>
                            <?php elseif ($pk === 'cheque'): ?>
                            <?php /* ویژهٔ همکار تأییدشده — اطلاعات چک را در کادر پایین وارد می‌کند؛
                                    اصل چک هم‌چنان باید بعدا فیزیکی برایمان برسد — جزئیاتش در صفحهٔ بعد. */ ?>
                            <small>اطلاعات چک را در کادر پایین وارد کنید؛ اصل چک را هم باید تا مهلتی که در صفحهٔ بعد می‌بینید برایمان ارسال یا تحویل دهید.</small>
                            <?php else: ?>
                            <small>انتقال به درگاه بانکی و پرداخت اینترنتی با کارت‌های شتاب.</small>
                            <?php endif; ?>
                            <small class="pay-off-note"><?= ($stockPending && in_array($pk, $payAllowed, true)) ? 'در انتظار تأیید موجودی است.' : 'برای روش ارسالی که انتخاب کرده‌اید در دسترس نیست.' ?></small>
                        </span>
                    </label>
                    <?php endforeach; ?>

                    <?php if ($c2cReady): ?>
                    <?php /* اطلاعات واریز، فقط وقتی «کارت به کارت» انتخاب شده باشد.
                            هیچ فیلدی required نیست: کادر پنهان می‌شود و فیلد
                            پنهان required جلوی ارسال فرم را بی‌آنکه دیده شود
                            می‌گرفت. اسکریپت پایین با نمایش کادر required را
                            می‌گذارد و سرور در هر حال دوباره اعتبار را می‌سنجد. */ ?>
                    <div class="pay-c2c" id="pay-c2c"<?= $payDefault === 'card' ? '' : ' hidden' ?>>
                        <div class="pc2-t"><?= icon('receipt', 'ic-sm') ?> اطلاعات واریز</div>
                        <?php if (($c2cNote = trim((string)getSetting('pay_c2c_note', ''))) !== ''): ?>
                        <p class="pc2-note"><?= icon('info', 'ic-sm') ?> <?= h($c2cNote) ?></p>
                        <?php endif; ?>
                        <div class="pc2-grid">
                            <div class="form-group">
                                <label for="c2c_ref">شناسهٔ واریز / شمارهٔ پیگیری *</label>
                                <input type="text" name="c2c_ref" id="c2c_ref" class="form-control" dir="ltr"
                                       value="<?= h($c2cIn['ref']) ?>" placeholder="مثلا 123456789">
                            </div>
                            <div class="form-group">
                                <label for="c2c_amount">مبلغ واریزی (تومان) *</label>
                                <input type="text" name="c2c_amount" id="c2c_amount" class="form-control" dir="ltr"
                                       inputmode="numeric" value="<?= h($c2cIn['amount']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="c2c_last4">چهار رقم آخر کارت شما *</label>
                                <input type="text" name="c2c_last4" id="c2c_last4" class="form-control" dir="ltr"
                                       inputmode="numeric" maxlength="6" value="<?= h($c2cIn['last4']) ?>" placeholder="1234">
                            </div>
                            <div class="form-group">
                                <label for="c2c_date">تاریخ واریز *</label>
                                <?php /* تقویم شمسی (خواستهٔ مدیر: «تاریخ رو از روی تقویم فارسی
                                        بهم بده»). خود ورودی همچنان قابل تایپ است تا بدون
                                        جاوااسکریپت هم پر شود؛ تقویم فقط کار مشتری را راحت
                                        می‌کند و همان قالب 1405/06/03 را می‌نویسد — همان چیزی
                                        که jDate() سرور می‌سازد. */ ?>
                                <div class="jdp" id="jdp-c2c">
                                    <input type="text" name="c2c_date" id="c2c_date" class="form-control" dir="ltr"
                                           inputmode="numeric" autocomplete="off"
                                           value="<?= h($c2cIn['date']) ?>" placeholder="1405/06/03">
                                    <button type="button" class="jdp-btn" title="انتخاب از تقویم"
                                            aria-label="انتخاب تاریخ از تقویم"><?= icon('calendar', 'ic-sm') ?></button>
                                    <div class="jdp-pop" hidden>
                                        <div class="jdp-h">
                                            <button type="button" class="jdp-nav" data-mv="-1" aria-label="ماه قبل"><?= icon('chevron-right', 'ic-sm') ?></button>
                                            <b class="jdp-ttl"></b>
                                            <button type="button" class="jdp-nav" data-mv="1" aria-label="ماه بعد"><?= icon('chevron-left', 'ic-sm') ?></button>
                                        </div>
                                        <div class="jdp-w"><i>ش</i><i>ی</i><i>د</i><i>س</i><i>چ</i><i>پ</i><i>ج</i></div>
                                        <div class="jdp-g"></div>
                                        <div class="jdp-f">
                                            <button type="button" class="jdp-now">امروز</button>
                                            <button type="button" class="jdp-x">بستن</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="c2c_time_h">ساعت واریز *</label>
                                <?php /* ساعت و دقیقهٔ انتخابی (خواستهٔ مدیر: «زمان رو هم با یک
                                        ساعت و دقیقه قابل انتخاب»). دو انتخابگر ساده‌اند تا
                                        بدون جاوااسکریپت هم کار کنند؛ سرور آن‌ها را به
                                        «HH:MM» تبدیل می‌کند. */ ?>
                                <div class="jtp">
                                    <select name="c2c_time_h" id="c2c_time_h" class="form-control" aria-label="ساعت">
                                        <?php for ($hh = 0; $hh <= 23; $hh++): ?>
                                        <option value="<?= $hh ?>"<?= $hh === $c2cH ? ' selected' : '' ?>><?= sprintf('%02d', $hh) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <b class="jtp-sep">:</b>
                                    <select name="c2c_time_m" id="c2c_time_m" class="form-control" aria-label="دقیقه">
                                        <?php for ($mm = 0; $mm <= 59; $mm++): ?>
                                        <option value="<?= $mm ?>"<?= $mm === $c2cM ? ' selected' : '' ?>><?= sprintf('%02d', $mm) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($chqReady): ?>
                    <?php /* اطلاعات چک، فقط وقتی «چک» انتخاب شده باشد — همان الگوی کادر
                            کارت‌به‌کارت بالا. هیچ فیلدی required نیست: کادر پنهان می‌شود و
                            فیلد پنهان required جلوی ارسال فرم را بی‌آنکه دیده شود می‌گرفت.
                            اسکریپت پایین با نمایش کادر required را می‌گذارد و سرور در هر
                            حال دوباره اعتبار را می‌سنجد. پیغام کامل «تا چند روز» بعد از
                            ثبت سفارش در order-success.php با paymentChequeNoteText() نشان
                            داده می‌شود. */ ?>
                    <div class="pay-c2c" id="pay-cheque"<?= $payDefault === 'cheque' ? '' : ' hidden' ?>>
                        <div class="pc2-t"><?= icon('receipt', 'ic-sm') ?> اطلاعات چک</div>
                        <?php if (($chqSampleImg = trim((string)getSettingRaw('pay_cheque_sample', ''))) !== ''): ?>
                        <div class="pc2-sample">
                            <img src="uploads/settings/<?= h($chqSampleImg) ?>" alt="نمونهٔ چک">
                            <span><?= icon('info', 'ic-sm') ?> نمونهٔ یک چک خوانا</span>
                        </div>
                        <?php endif; ?>
                        <p class="pc2-note"><?= icon('info', 'ic-sm') ?> بعد از ثبت سفارش، اصل چک را هم باید تا مهلتی که در صفحهٔ بعد می‌بینید برایمان ارسال یا تحویل دهید.</p>
                        <div class="pc2-grid">
                            <div class="form-group">
                                <label for="chq_bank">بانک *</label>
                                <input type="text" name="chq_bank" id="chq_bank" class="form-control"
                                       value="<?= h($chqIn['bank']) ?>" placeholder="مثلا ملت">
                            </div>
                            <div class="form-group">
                                <label for="chq_number">سریال چک *</label>
                                <input type="text" name="chq_number" id="chq_number" class="form-control" dir="ltr"
                                       value="<?= h($chqIn['number']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="chq_date">تاریخ چک *</label>
                                <?php /* همان تقویم شمسی کادر «تاریخ واریز» بالا (خواستهٔ کاربر:
                                        «مثل تاریخی که در کارت به کارت استفاده کرده»)، با شناسهٔ
                                        جداگانه چون هر دو کادر می‌توانند هم‌زمان در DOM باشند. */ ?>
                                <div class="jdp" id="jdp-cheque">
                                    <input type="text" name="chq_date" id="chq_date" class="form-control" dir="ltr"
                                           inputmode="numeric" autocomplete="off"
                                           value="<?= h($chqIn['date']) ?>" placeholder="1405/06/03">
                                    <button type="button" class="jdp-btn" title="انتخاب از تقویم"
                                            aria-label="انتخاب تاریخ از تقویم"><?= icon('calendar', 'ic-sm') ?></button>
                                    <div class="jdp-pop" hidden>
                                        <div class="jdp-h">
                                            <button type="button" class="jdp-nav" data-mv="-1" aria-label="ماه قبل"><?= icon('chevron-right', 'ic-sm') ?></button>
                                            <b class="jdp-ttl"></b>
                                            <button type="button" class="jdp-nav" data-mv="1" aria-label="ماه بعد"><?= icon('chevron-left', 'ic-sm') ?></button>
                                        </div>
                                        <div class="jdp-w"><i>ش</i><i>ی</i><i>د</i><i>س</i><i>چ</i><i>پ</i><i>ج</i></div>
                                        <div class="jdp-g"></div>
                                        <div class="jdp-f">
                                            <button type="button" class="jdp-now">امروز</button>
                                            <button type="button" class="jdp-x">بستن</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="chq_amount">مبلغ چک (تومان) *</label>
                                <input type="text" name="chq_amount" id="chq_amount" class="form-control" dir="ltr"
                                       inputmode="numeric" value="<?= h($chqIn['amount']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="chq_payee">در وجه *</label>
                                <input type="text" name="chq_payee" id="chq_payee" class="form-control"
                                       value="<?= h($chqIn['payee']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="chq_sayad">شناسهٔ صیاد</label>
                                <input type="text" name="chq_sayad" id="chq_sayad" class="form-control" dir="ltr"
                                       inputmode="numeric" value="<?= h($chqIn['sayad']) ?>" placeholder="۱۶ رقم">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php /* دکمهٔ ثبت داخل همین ستون و در اندازهٔ معمولی است تا صفحه از
                        پایین کشیده نشود (خواستهٔ مدیر: «از پایین یک جمع تر کن …
                        کلید ثبت سفارش رو هم کوچکتر کن»). */ ?>
                <div class="checkout-actions">
                    <button type="submit" name="submit_order" class="btn btn-primary checkout-submit">
                        <?= icon('check-circle') ?> ثبت سفارش
                    </button>
                    <a href="cart.php" class="checkout-back"><?= icon('arrow-right', 'ic-sm') ?> بازگشت به سبد</a>
                    <?php if (partCheckOn()): ?>
                    <a href="part-check.php" class="checkout-back"><?= icon('camera', 'ic-sm') ?> بازگشت به بررسی عکس قطعه</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($shipMethods || paymentReady()): ?>
        <script>
        /* این صفحه فقط ثبت نهایی است: روش ارسال در سبد انتخاب شده و این‌جا
           عوض نمی‌شود. کار اسکریپت سه چیز است — هایلایت گزینهٔ پرداخت،
           بازکردن کادر اطلاعات واریز برای «کارت به کارت»، و بازحساب‌کردن
           هزینهٔ ارسال و مبلغ قابل پرداخت وقتی مشتری شهر را عوض می‌کند. */
        (function(){
            var boxes  = document.querySelectorAll('.pay-picker');
            var c2cBox = document.getElementById('pay-c2c');
            var amtEl  = document.getElementById('c2c_amount');
            var chqBox = document.getElementById('pay-cheque');
            var cityEl = document.getElementById('city');
            var sumBox = document.querySelector('.checkout-summary');
            /* متن‌ها و نرخ‌نامه از سرور می‌آیند تا ارقام زنده با آنچه سرور رندر
               کرده و در ثبت سفارش حساب می‌کند یکی باشد. فقط سه متنی که این‌جا
               لازم است فرستاده می‌شود؛ قالب جملهٔ توضیحی نرخ‌نامه حتی به‌صورت
               داده هم نمی‌آید، چون این صفحه آن جمله را نشان نمی‌دهد (خواستهٔ
               مدیر: «نیازی نیست نمایش داده بشه»). */
            var TXT  = <?= json_encode([
                            'pick'         => $shipTexts['pick'],
                            'later'        => $shipTexts['later'],
                            'collect_only' => $shipTexts['collect_only'],
                        ], JSON_UNESCAPED_UNICODE) ?>;
            var SHIP = <?= json_encode($shipJs, JSON_UNESCAPED_UNICODE) ?>;
            var KEY  = <?= json_encode($shipChosen, JSON_UNESCAPED_UNICODE) ?>;

            for (var i = 0; i < boxes.length; i++) bind(boxes[i]);
            syncPayBoxes();

            /* شهر انتخاب‌شده که عوض می‌شود، نرخ همان روش هم عوض می‌شود. رخداد
               change را خود انتخابگر استان هم پس از بازسازی فهرست شهر شلیک
               می‌کند (shippingProvinceCityFields در includes/shipping.php). */
            if (cityEl) {
                cityEl.addEventListener('input', updateTotals);
                cityEl.addEventListener('change', updateTotals);
            }
            /* مبلغ واریزی/چک تا وقتی مشتری خودش عددی نزده، همان مبلغ قابل پرداخت
               می‌ماند؛ بعد از آن دست‌نخورده باقی می‌ماند. */
            if (amtEl) amtEl.addEventListener('input', function(){ amtEl.setAttribute('data-touched', '1'); });

            function bind(box) {
                box.addEventListener('change', function(){
                    highlight(box);
                    syncPayBoxes();
                });
            }

            /* کادرهای اطلاعات واریز/چک فقط با انتخاب همان روش پرداخت باز
               می‌شوند. required را هم‌زمان می‌گذاریم و برمی‌داریم، چون فیلد
               پنهان required ارسال فرم را بی‌آنکه پیامی دیده شود متوقف می‌کند —
               همین یعنی «تا اطلاعات کامل نشود، ثبت سفارش پیش نمی‌رود»، چون سرور
               هم دوباره همان اعتبارسنجی را می‌کند. */
            function syncPayBoxes() {
                var sel = document.querySelector('input[name=payment_method]:checked');
                var val = sel ? sel.value : '';
                toggleBox(c2cBox, val === 'card',   ['c2c_ref', 'c2c_amount', 'c2c_last4', 'c2c_date']);
                toggleBox(chqBox, val === 'cheque', ['chq_bank', 'chq_number', 'chq_date', 'chq_amount', 'chq_payee']);
            }

            function toggleBox(box, on, requiredIds) {
                if (!box) return;
                box.hidden = !on;
                for (var k = 0; k < requiredIds.length; k++) {
                    var el = document.getElementById(requiredIds[k]);
                    if (!el) continue;
                    if (on) el.setAttribute('required', 'required');
                    else    el.removeAttribute('required');
                }
            }

            function highlight(box) {
                var opts = box.querySelectorAll('.pay-opt');
                for (var k = 0; k < opts.length; k++) {
                    var r = opts[k].querySelector('input[type=radio]');
                    opts[k].classList.toggle('is-on', !!(r && r.checked));
                }
            }

            /* ---------- نرخ‌نامه، همان محاسبهٔ shippingResolveCost() سرور ---------- */
            /* یکدست‌سازی نام شهر، مثل shipNormCity(): ارقام فارسی/عربی به لاتین،
               حذف نیم‌فاصله و کشیده، ی و ک عربی به فارسی، فاصله‌های اضافه. */
            /* نیم‌فاصله/ZWJ/کشیده با کد ساخته می‌شوند چون دیده نمی‌شوند و ویرایش
               بعدی همین خط را خطرناک می‌کردند. */
            var STRIP = new RegExp('[' + String.fromCharCode(0x200C, 0x200D, 0x0640) + ']', 'g');

            function normCity(s) {
                s = String(s || '');
                s = s.replace(/[۰-۹]/g, function(d){ return String.fromCharCode(d.charCodeAt(0) - 0x06F0 + 48); });
                s = s.replace(/[٠-٩]/g, function(d){ return String.fromCharCode(d.charCodeAt(0) - 0x0660 + 48); });
                s = s.replace(STRIP, '');
                s = s.replace(/ي/g, 'ی').replace(/ك/g, 'ک')
                     .replace(/[ةۀ]/g, 'ه').replace(/[أإآ]/g, 'ا')
                     .replace(/ﻻ/g, 'لا');
                s = s.replace(/\s+/g, ' ');
                return s.trim().toLowerCase();
            }

            /* تعداد واحدهای وزنی — مثل shippingRateUnits(): همیشه رند به بالا و
               حداقل یک واحد. ۰٫۰۰۰۰۱ خطای شناوری را می‌گیرد تا ۳ کیلو دقیق با
               واحد ۱ کیلو، ۴ واحد حساب نشود. */
            function unitsFor(w, u) {
                w = parseFloat(w) || 0;
                u = parseFloat(u) || 0; if (u <= 0) u = 1;
                if (w <= 0) return 1;
                var n = Math.ceil(w / u - 0.00001);
                return n < 1 ? 1 : n;
            }

            /* همان تصمیم‌گیری shippingResolveCost() سرور:
               پس‌کرایه ⇒ هیچ محاسبه‌ای؛ وگرنه نرخ شهر × تعداد واحدهای وزنی. */
            function resolve(key, city) {
                var out = {cost: 0, display: 0, source: 'none', row: null, rows: [],
                           has: false, collect: false, unit: 1, units: 1};
                var m = SHIP.m ? SHIP.m[key] : null;
                if (!m) return out;

                /* پس‌کرایه: کل فرآیند محاسبه غیرفعال است */
                if (m.c) { out.collect = true; out.source = 'collect'; return out; }

                var all = m.r || [], i;
                out.has = all.length > 0;

                var nc = normCity(city), rows = [];
                if (nc !== '') {
                    for (i = 0; i < all.length; i++) {
                        var rc = all[i].c || '';
                        if (rc === '') continue;
                        if (nc === rc)                                              { all[i].x = 1; rows.push(all[i]); }
                        else if (nc.indexOf(rc) !== -1 || rc.indexOf(nc) !== -1)    { all[i].x = 0; rows.push(all[i]); }
                    }
                    /* برابری کامل مقدم است (کسی که «قم» می‌نویسد نرخ «قمصر» را
                       نگیرد)، بعد کوچک‌ترین واحد وزنی — مثل shippingRatesForCity() */
                    rows.sort(function(a, b){
                        if ((b.x || 0) !== (a.x || 0)) return (b.x || 0) - (a.x || 0);
                        return (parseFloat(a.u) || 1) - (parseFloat(b.u) || 1);
                    });
                }
                out.rows = rows;
                if (!rows.length) return out;

                var w = parseFloat(SHIP.w) || 0;
                out.row     = rows[0];
                out.unit    = parseFloat(out.row.u) || 1;
                out.units   = unitsFor(w, out.unit);
                out.source  = w > 0 ? 'rate' : 'rate_base';
                out.display = Math.round(out.units * (parseFloat(out.row.p) || 0));
                out.cost    = out.display;
                return out;
            }

            /* متن هزینه — مثل shippingCostText($cost, $key) */
            function costText(res) {
                if (res.collect) return TXT.collect_only;
                return res.display > 0 ? money(res.display) : TXT.later;
            }

            /* جمع زدن زندهٔ هزینهٔ ارسال با مبلغ کالاها. رقم از همان resolve()
               می‌آید که نسخهٔ جاوااسکریپتی shippingResolveCost() سرور است، پس
               «هزینهٔ ارسال» و «مبلغ قابل پرداخت» هیچ‌وقت با هم اختلاف پیدا
               نمی‌کنند. مبلغ نهایی هنگام ثبت سفارش دوباره در سرور حساب می‌شود؛
               این فقط نمایش است. */
            function updateTotals() {
                var goods = sumBox ? (parseInt(sumBox.getAttribute('data-goods'), 10) || 0) : 0;
                var res   = KEY === '' ? null : resolve(KEY, cityEl ? cityEl.value : '');
                var cCell = document.getElementById('ship-cost-cell');
                var pCell = document.getElementById('payable-cell');
                var pay   = goods + (res ? res.cost : 0);
                if (cCell) cCell.textContent = res ? costText(res) : TXT.pick;
                if (pCell) pCell.textContent = money(pay);
                if (amtEl && amtEl.getAttribute('data-touched') !== '1') amtEl.value = String(pay);
            }

            function money(n) { return n.toLocaleString('en-US') + ' تومان'; }
        })();
        </script>
        <?php endif; ?>

        <?php if ($c2cReady): ?>
        <script>
        /* ---------- تقویم شمسی «تاریخ واریز» ----------
           خواستهٔ مدیر: «تاریخ رو از روی تقویم فارسی بهم بده». بدون هیچ
           کتابخانه‌ای؛ یک کادر بازشو که همان قالب 1405/06/03 را در ورودی
           می‌نویسد — هم‌شکل خروجی jDate() سرور، تا رقم‌های ادمین یکدست
           بمانند. روزهای آینده قابل انتخاب نیستند، چون واریز پیش از ثبت سفارش
           انجام شده است. */
        (function(){
            var wrap = document.getElementById('jdp-c2c');
            if (!wrap) return;
            var inp  = wrap.querySelector('input');
            var btn  = wrap.querySelector('.jdp-btn');
            var pop  = wrap.querySelector('.jdp-pop');
            var ttl  = wrap.querySelector('.jdp-ttl');
            var grid = wrap.querySelector('.jdp-g');
            var MN   = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            var view = null;   /* ماهی که همین حالا نشان داده می‌شود */

            /* دو تبدیل زیر همان الگوریتم gregorianToJalali() سرور هستند، پس
               تاریخی که تقویم می‌نویسد با تاریخی که سرور می‌سازد یکی است. */
            function g2j(gy, gm, gd) {
                var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
                var gy2 = (gm > 2) ? (gy + 1) : gy;
                var days = 355666 + (365 * gy) + ~~((gy2 + 3) / 4) - ~~((gy2 + 99) / 100)
                         + ~~((gy2 + 399) / 400) + gd + gdm[gm - 1];
                var jy = -1595 + (33 * ~~(days / 12053));
                days %= 12053;
                jy += 4 * ~~(days / 1461);
                days %= 1461;
                if (days > 365) { jy += ~~((days - 1) / 365); days = (days - 1) % 365; }
                if (days < 186) return [jy, 1 + ~~(days / 31), 1 + (days % 31)];
                return [jy, 7 + ~~((days - 186) / 30), 1 + ((days - 186) % 30)];
            }
            function j2g(jy, jm, jd) {
                jy += 1595;
                var days = -355668 + (365 * jy) + (~~(jy / 33) * 8) + ~~(((jy % 33) + 3) / 4)
                         + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
                var gy = 400 * ~~(days / 146097);
                days %= 146097;
                if (days > 36524) {
                    gy += 100 * ~~(--days / 36524);
                    days %= 36524;
                    if (days >= 365) days++;
                }
                gy += 4 * ~~(days / 1461);
                days %= 1461;
                if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; }
                var gd = days + 1;
                var leap = ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0);
                var dim  = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                var gm = 0;
                while (gm < 13 && gd > dim[gm]) { gd -= dim[gm]; gm++; }
                return [gy, gm, gd];
            }

            /* شمار روزهای ماه: شش ماه اول ۳۱، پنج ماه بعد ۳۰، اسفند ۲۹ یا ۳۰.
               کبیسه‌بودن با رفت‌وبرگشت «۳۰ اسفند» سنجیده می‌شود تا با همین دو
               تبدیل سازگار بماند و فرمول جداگانه‌ای لازم نشود. */
            function mdays(jy, jm) {
                if (jm < 7)  return 31;
                if (jm < 12) return 30;
                var g = j2g(jy, 12, 30), b = g2j(g[0], g[1], g[2]);
                return (b[0] === jy && b[1] === 12 && b[2] === 30) ? 30 : 29;
            }
            function pad(n)  { return (n < 10 ? '0' : '') + n; }
            function today() { var d = new Date(); return g2j(d.getFullYear(), d.getMonth() + 1, d.getDate()); }
            /* عددی برای مقایسهٔ دو روز (آینده است یا نه) */
            function ord(y, m, d) { var g = j2g(y, m, d); return Date.UTC(g[0], g[1] - 1, g[2]); }

            function parse(s) {
                s = String(s || '').replace(/[۰-۹]/g, function(c){
                    return String.fromCharCode(c.charCodeAt(0) - 0x06F0 + 48);
                });
                var m = s.match(/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/);
                if (!m) return null;
                var y = +m[1], mo = +m[2], d = +m[3];
                if (mo < 1 || mo > 12 || d < 1 || d > mdays(y, mo)) return null;
                return [y, mo, d];
            }

            function draw() {
                var t = today(), sel = parse(inp.value), i;
                if (!view) view = sel ? {y: sel[0], m: sel[1]} : {y: t[0], m: t[1]};
                var n   = mdays(view.y, view.m);
                var g1  = j2g(view.y, view.m, 1);
                /* هفتهٔ ایرانی از شنبه شروع می‌شود و getDay() یکشنبه را ۰ می‌دهد */
                var lead = (new Date(g1[0], g1[1] - 1, g1[2]).getDay() + 1) % 7;
                var top  = ord(t[0], t[1], t[2]);
                var out  = '';
                for (i = 0; i < lead; i++) out += '<span></span>';
                for (i = 1; i <= n; i++) {
                    if (ord(view.y, view.m, i) > top) { out += '<span class="is-off">' + i + '</span>'; continue; }
                    var cls = [];
                    if (sel && sel[0] === view.y && sel[1] === view.m && sel[2] === i) cls.push('is-on');
                    if (t[0] === view.y && t[1] === view.m && t[2] === i) cls.push('is-today');
                    out += '<button type="button" class="' + cls.join(' ') + '" data-d="' + i + '">' + i + '</button>';
                }
                ttl.textContent = MN[view.m - 1] + ' ' + view.y;
                grid.innerHTML  = out;
            }

            function open()  { view = null; draw(); pop.hidden = false; }
            function close() { pop.hidden = true; }
            function pick(y, m, d) { inp.value = y + '/' + pad(m) + '/' + pad(d); close(); }

            btn.addEventListener('click', function(){ if (pop.hidden) open(); else close(); });
            inp.addEventListener('click', open);
            /* تایپ دستی و تقویم هم‌گام می‌مانند */
            inp.addEventListener('input', function(){
                if (pop.hidden) return;
                var v = parse(inp.value);
                if (v) view = {y: v[0], m: v[1]};
                draw();
            });

            pop.addEventListener('click', function(e){
                /* بالا رفتن تا خود دکمه: کلیک ممکن است روی SVG درون آن باشد */
                var b = e.target;
                while (b && b !== pop && b.tagName !== 'BUTTON') b = b.parentNode;
                if (!b || b === pop) return;
                if (!view) draw();
                if (b.hasAttribute('data-mv')) {
                    view.m += parseInt(b.getAttribute('data-mv'), 10);
                    if (view.m > 12) { view.m = 1;  view.y++; }
                    if (view.m < 1)  { view.m = 12; view.y--; }
                    draw();
                } else if (b.hasAttribute('data-d')) {
                    pick(view.y, view.m, parseInt(b.getAttribute('data-d'), 10));
                } else if (b.className.indexOf('jdp-now') !== -1) {
                    var t = today(); pick(t[0], t[1], t[2]);
                } else if (b.className.indexOf('jdp-x') !== -1) {
                    close();
                }
            });

            document.addEventListener('click', function(e){
                if (!pop.hidden && !wrap.contains(e.target)) close();
            });
            document.addEventListener('keydown', function(e){
                if (!pop.hidden && (e.key === 'Escape' || e.keyCode === 27)) close();
            });
        })();
        </script>
        <?php endif; ?>

        <?php if ($chqReady): ?>
        <script>
        /* ---------- تقویم شمسی «تاریخ چک» ----------
           عینا همان تقویم «تاریخ واریز»ی کارت‌به‌کارت بالا (خواستهٔ کاربر: «مثل
           تاریخی که در کارت به کارت استفاده کرده») روی یک شناسهٔ جداگانه، چون هر
           دو کادر می‌توانند هم‌زمان در DOM باشند. */
        (function(){
            var wrap = document.getElementById('jdp-cheque');
            if (!wrap) return;
            var inp  = wrap.querySelector('input');
            var btn  = wrap.querySelector('.jdp-btn');
            var pop  = wrap.querySelector('.jdp-pop');
            var ttl  = wrap.querySelector('.jdp-ttl');
            var grid = wrap.querySelector('.jdp-g');
            var MN   = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
                        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            var view = null;   /* ماهی که همین حالا نشان داده می‌شود */

            function g2j(gy, gm, gd) {
                var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
                var gy2 = (gm > 2) ? (gy + 1) : gy;
                var days = 355666 + (365 * gy) + ~~((gy2 + 3) / 4) - ~~((gy2 + 99) / 100)
                         + ~~((gy2 + 399) / 400) + gd + gdm[gm - 1];
                var jy = -1595 + (33 * ~~(days / 12053));
                days %= 12053;
                jy += 4 * ~~(days / 1461);
                days %= 1461;
                if (days > 365) { jy += ~~((days - 1) / 365); days = (days - 1) % 365; }
                if (days < 186) return [jy, 1 + ~~(days / 31), 1 + (days % 31)];
                return [jy, 7 + ~~((days - 186) / 30), 1 + ((days - 186) % 30)];
            }
            function j2g(jy, jm, jd) {
                jy += 1595;
                var days = -355668 + (365 * jy) + (~~(jy / 33) * 8) + ~~(((jy % 33) + 3) / 4)
                         + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
                var gy = 400 * ~~(days / 146097);
                days %= 146097;
                if (days > 36524) {
                    gy += 100 * ~~(--days / 36524);
                    days %= 36524;
                    if (days >= 365) days++;
                }
                gy += 4 * ~~(days / 1461);
                days %= 1461;
                if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; }
                var gd = days + 1;
                var leap = ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0);
                var dim  = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                var gm = 0;
                while (gm < 13 && gd > dim[gm]) { gd -= dim[gm]; gm++; }
                return [gy, gm, gd];
            }

            function mdays(jy, jm) {
                if (jm < 7)  return 31;
                if (jm < 12) return 30;
                var g = j2g(jy, 12, 30), b = g2j(g[0], g[1], g[2]);
                return (b[0] === jy && b[1] === 12 && b[2] === 30) ? 30 : 29;
            }
            function pad(n)  { return (n < 10 ? '0' : '') + n; }
            function today() { var d = new Date(); return g2j(d.getFullYear(), d.getMonth() + 1, d.getDate()); }
            function ord(y, m, d) { var g = j2g(y, m, d); return Date.UTC(g[0], g[1] - 1, g[2]); }

            function parse(s) {
                s = String(s || '').replace(/[۰-۹]/g, function(c){
                    return String.fromCharCode(c.charCodeAt(0) - 0x06F0 + 48);
                });
                var m = s.match(/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/);
                if (!m) return null;
                var y = +m[1], mo = +m[2], d = +m[3];
                if (mo < 1 || mo > 12 || d < 1 || d > mdays(y, mo)) return null;
                return [y, mo, d];
            }

            function draw() {
                var t = today(), sel = parse(inp.value), i;
                if (!view) view = sel ? {y: sel[0], m: sel[1]} : {y: t[0], m: t[1]};
                var n   = mdays(view.y, view.m);
                var g1  = j2g(view.y, view.m, 1);
                var lead = (new Date(g1[0], g1[1] - 1, g1[2]).getDay() + 1) % 7;
                var out  = '';
                for (i = 0; i < lead; i++) out += '<span></span>';
                for (i = 1; i <= n; i++) {
                    var cls = [];
                    if (sel && sel[0] === view.y && sel[1] === view.m && sel[2] === i) cls.push('is-on');
                    if (t[0] === view.y && t[1] === view.m && t[2] === i) cls.push('is-today');
                    out += '<button type="button" class="' + cls.join(' ') + '" data-d="' + i + '">' + i + '</button>';
                }
                ttl.textContent = MN[view.m - 1] + ' ' + view.y;
                grid.innerHTML  = out;
            }

            function open()  { view = null; draw(); pop.hidden = false; }
            function close() { pop.hidden = true; }
            function pick(y, m, d) { inp.value = y + '/' + pad(m) + '/' + pad(d); close(); }

            btn.addEventListener('click', function(){ if (pop.hidden) open(); else close(); });
            inp.addEventListener('click', open);
            inp.addEventListener('input', function(){
                if (pop.hidden) return;
                var v = parse(inp.value);
                if (v) view = {y: v[0], m: v[1]};
                draw();
            });

            pop.addEventListener('click', function(e){
                var b = e.target;
                while (b && b !== pop && b.tagName !== 'BUTTON') b = b.parentNode;
                if (!b || b === pop) return;
                if (!view) draw();
                if (b.hasAttribute('data-mv')) {
                    view.m += parseInt(b.getAttribute('data-mv'), 10);
                    if (view.m > 12) { view.m = 1;  view.y++; }
                    if (view.m < 1)  { view.m = 12; view.y--; }
                    draw();
                } else if (b.hasAttribute('data-d')) {
                    pick(view.y, view.m, parseInt(b.getAttribute('data-d'), 10));
                } else if (b.className.indexOf('jdp-now') !== -1) {
                    var t = today(); pick(t[0], t[1], t[2]);
                } else if (b.className.indexOf('jdp-x') !== -1) {
                    close();
                }
            });

            document.addEventListener('click', function(e){
                if (!pop.hidden && !wrap.contains(e.target)) close();
            });
            document.addEventListener('keydown', function(e){
                if (!pop.hidden && (e.key === 'Escape' || e.keyCode === 27)) close();
            });
        })();
        </script>
        <?php endif; ?>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
