<?php
/* -------------------------------------------------------------------------
   کاوشگر یک‌بارمصرفِ بستهٔ «تنظیمات کشویی / انتخابگر مدل خودرو / آیکون‌های فوتر»
   با ?key محافظت شده و پس از تأیید با _404stub.php بی‌اثر می‌شود.
   حالت‌ها (چون هر صفحه فقط یک‌بار در هر درخواست قابل include است):
     ?m=main                → بررسی فایل‌ها + توابع + رندر یک صفحهٔ عمومی
     ?m=pnew                → رندر «افزودن محصول» + سایدبار
     ?m=pedit               → رندر «ویرایش محصول» + وضعیت تیک‌ها
     ?m=sec&sec=footer|...  → رندر یک بخش تنظیمات
     ?m=save                → آزمونِ «ذخیرهٔ یک بخش، بخش‌های دیگر را خالی نکند»
   ------------------------------------------------------------------------- */
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (($_GET['key'] ?? '') !== 'c13set9142') { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

function say($k, $v) { echo str_pad($k, 42, '.') . ' ' . $v . "\n"; }
function yn($b)      { return $b ? 'OK' : '*** FAIL ***'; }
function has($s, $n) { return strpos($s, $n) !== false; }
function cnt($s, $n) { return substr_count($s, $n); }
function hr($t)      { echo "\n== $t ==\n"; }

/* سایدبار/صفحه‌های ادمین ورود لازم دارند؛ در پایان پاک می‌شود تا کوکی این
   درخواست به یک نشستِ ادمینِ باز تبدیل نشود. */
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'probe';

$mode = $_GET['m'] ?? 'main';
$ROOT = __DIR__;

/* ======================= m=main ======================= */
if ($mode === 'main') {

    hr('توابع مشترک');
    $secs = settingsSections();
    say('settingsSections keys', yn(array_keys($secs) === ['footer', 'decor', 'sms', 'pay']));
    $lbls = true;
    foreach ($secs as $k => $d) {
        if (!isset($d['label'], $d['icon'], $d['title']) || $d['label'] === '' || icon($d['icon']) === '') $lbls = false;
    }
    say('every section label+icon exists', yn($lbls));
    say('sectionKey(pay)=pay', yn(settingsSectionKey('pay') === 'pay'));
    say('sectionKey(zzz)=footer', yn(settingsSectionKey('zzz') === 'footer'));
    say('sectionKey("")=footer', yn(settingsSectionKey('') === 'footer'));
    say('sectionKey(null)=footer', yn(settingsSectionKey(null) === 'footer'));
    say('sectionKey(array-ish)=footer', yn(settingsSectionKey('0') === 'footer'));

    $cd = icon('chevron-down');
    say('icon(chevron-down) not empty', yn($cd !== ''));
    say('chevron-down 24x24 viewBox', yn(has($cd, 'viewBox="0 0 24 24"')));
    say('chevron-down is stroked', yn(has($cd, 'stroke-width="1.7"')));

    hr('assets/css/style.css');
    $css = (string)@file_get_contents($ROOT . '/assets/css/style.css');
    say('css readable', yn($css !== ''));
    $nBase = cnt($css, '.social-chip {');
    $nPre  = cnt($css, '.footer-social .social-chip {');
    say('social-chip{} blocks total', $nBase);
    say('..of which prefixed', $nPre);
    say('NO bare ".social-chip {" left', yn($nBase === $nPre && $nPre >= 2));
    say('mobile override prefixed', yn(has($css, '.footer-social .social-chip { flex: 0 1 40px')));
    $pos = strpos($css, '.footer-social .social-chip {');
    $blk = $pos === false ? '' : substr($css, $pos, 620);
    say('base has align-items:center', yn(has($blk, 'align-items: center')));
    say('base has justify-content:center', yn(has($blk, 'justify-content: center')));
    say('base has gap:0', yn(has($blk, 'gap: 0;')));
    say('base has aspect-ratio 1/1', yn(has($blk, 'aspect-ratio: 1 / 1')));
    say('hover rules still unprefixed', yn(has($css, "\n.social-chip:hover")));
    say('brand hovers kept (8)', cnt($css, ':hover { border-color: #'));
    say('.social-chip .social-ic kept', yn(has($css, '.social-chip .social-ic {')));
    say('62% icon size kept', yn(has($css, 'width: 62%; height: 62%')));

    hr('نسخهٔ فایل‌های ایستا (v=18)');
    $vfiles = [
        'includes/header.php'      => ['style.css?v=18'],
        'includes/footer.php'      => ['main.js?v=18', 'cart.js?v=18', 'search.js?v=18'],
        'admin/layout-top.php'     => ['style.css?v=18'],
        'admin/orders.php'         => ['style.css?v=18'],
        'admin/order-detail.php'   => ['style.css?v=18'],
        'admin/login.php'          => ['style.css?v=18'],
        'payment-start.php'        => ['style.css?v=18'],
        'payment-callback.php'     => ['style.css?v=18'],
        'payment-gateway-sim.php'  => ['style.css?v=18'],
    ];
    foreach ($vfiles as $f => $needles) {
        $src = (string)@file_get_contents($ROOT . '/' . $f);
        $ok = ($src !== '');
        foreach ($needles as $n) if (!has($src, $n)) $ok = false;
        if (has($src, '?v=17')) $ok = false;
        say($f, yn($ok));
    }

    hr('رندر صفحهٔ عمومی (banners.php)');
    ob_start(); include $ROOT . '/banners.php'; $pub = ob_get_clean();
    say('page rendered (bytes)', strlen($pub));
    say('style.css?v=18 in page', yn(has($pub, 'style.css?v=18')));
    say('no ?v=17 in page', yn(!has($pub, '?v=17')));
    say('no PHP error text', yn(!has($pub, 'Fatal error') && !has($pub, 'Warning:') && !has($pub, 'Notice:')));
    say('footer-social wrapper', yn(has($pub, 'class="footer-social"')));
    say('social chips rendered', cnt($pub, 'class="social-chip social-'));
    say('social-ic svg class', cnt($pub, 'class="social-ic"'));
}

/* ======================= m=pnew / m=pedit ======================= */
if ($mode === 'pnew' || $mode === 'pedit') {

    /* شمارش واقعی از دیتابیس برای مقایسه با خروجی */
    $brandsWithModels = (int)$pdo->query(
        "SELECT COUNT(DISTINCT parent_id) FROM categories WHERE parent_id IS NOT NULL")->fetchColumn();
    $modelsAll = (int)$pdo->query(
        "SELECT COUNT(*) FROM categories WHERE parent_id IS NOT NULL")->fetchColumn();

    $pid = 0; $pidCats = 0;
    if ($mode === 'pedit') {
        $row = $pdo->query("SELECT product_id, COUNT(*) c FROM product_categories
                            GROUP BY product_id ORDER BY c DESC, product_id ASC LIMIT 1")->fetch();
        if ($row) { $pid = (int)$row['product_id']; $pidCats = (int)$row['c']; }
        if (!$pid) { say('no product with categories', 'SKIP'); $mode = 'skip'; }
        else { $_GET['id'] = $pid; }
    }

    if ($mode !== 'skip') {
        /* layout-top.php صفحهٔ فعال منو را از SCRIPT_NAME می‌خواند؛ در کاوشگر
           باید جعل شود وگرنه هیچ آیتمی active نمی‌شود. */
        $_SERVER['SCRIPT_NAME'] = '/admin/product-edit.php';
        chdir($ROOT . '/admin');
        ob_start(); include $ROOT . '/admin/product-edit.php'; $pe = ob_get_clean();

        hr($mode === 'pnew' ? 'افزودن محصول' : "ویرایش محصول #$pid ($pidCats مدل)");
        say('page rendered (bytes)', strlen($pe));
        say('no PHP error text', yn(!has($pe, 'Fatal error') && !has($pe, 'Warning:') && !has($pe, 'Notice:')));
        say('style.css?v=18', yn(has($pe, 'style.css?v=18') && !has($pe, '?v=17')));

        hr('سایدبار: نوار کشویی تنظیمات');
        say('<details class="am-group">', yn(has($pe, '<details class="am-group"')));
        say('summary am-parent', yn(has($pe, 'am-link am-parent')));
        say('caret span', yn(has($pe, 'class="am-caret"')));
        say('am-sub wrapper', yn(has($pe, 'class="am-sub"')));
        $subs = 0;
        foreach (['footer', 'decor', 'sms', 'pay'] as $sk) {
            if (has($pe, 'settings.php?sec=' . $sk)) $subs++;
        }
        say('4 sub-links present', yn($subs === 4) . " ($subs/4)");
        say('sub-links count', cnt($pe, 'am-link am-sublink'));
        say('old flat settings link gone', yn(!has($pe, "href='settings.php' class='am-link")));
        say('labels present', yn(has($pe, 'فوتر سایت') && has($pe, 'پیامک') && has($pe, 'درگاه پرداخت')));

        hr('انتخابگر مدل خودرو');
        say('DB brands-with-models', $brandsWithModels);
        say('DB models total', $modelsAll);
        say('old cat-checkboxes gone', yn(!has($pe, 'cat-checkboxes')));
        say('#cmBox present', yn(has($pe, 'id="cmBox"')));
        say('#cmSearch present', yn(has($pe, 'id="cmSearch"')));
        say('#cmTotal present', yn(has($pe, 'id="cmTotal"')));
        say('#cmChips present', yn(has($pe, 'id="cmChips"')));
        say('#cmNoHit present', yn(has($pe, 'id="cmNoHit"')));
        say('toolbar buttons (4)', cnt($pe, 'data-cm="'));
        say('rendered .cm-brand groups', cnt($pe, 'class="cm-brand"'));
        say('groups == DB brands', yn(cnt($pe, 'class="cm-brand"') === $brandsWithModels));
        say('data-brand attrs', cnt($pe, 'data-brand="'));
        say('data-name attrs', cnt($pe, 'data-name="'));
        /* فقط نشانگرِ خودِ مارک‌آپ شمرده شود، نه رشته‌های داخل جاوااسکریپت */
        $cbMark = 'type="checkbox" name="categories[]" value="';
        say('checkboxes name kept', cnt($pe, $cbMark));
        say('checkboxes == DB models', yn(cnt($pe, $cbMark) === $modelsAll));
        say('per-brand همه control', cnt($pe, 'class="cm-ball"'));
        say('caret icon in summary', cnt($pe, 'class="cm-caret"'));
        say('hint text present', yn(has($pe, 'class="cm-hint"')));

        hr('جاوااسکریپت ویجت');
        say('IIFE bound to cmBox', yn(has($pe, "getElementById('cmBox')")));
        say('norm() digit folding', yn(has($pe, 'function norm(')));
        say('toggleBrand()', yn(has($pe, 'function toggleBrand(')));
        say('buildChips()', yn(has($pe, 'function buildChips(')));
        say('applyFilter()', yn(has($pe, 'function applyFilter(')));
        say('fires bubbling change', yn(has($pe, "new Event('change', { bubbles: true })")));
        say('Enter in search suppressed', yn(has($pe, 'e.preventDefault()') && has($pe, "keyCode === 13")));
        say('tech-number IIFE intact', yn(has($pe, 'input[name="categories[]"]:checked')));

        if ($mode === 'pedit') {
            hr('حفظ تیک‌های موجود');
            $chk = preg_match_all('/name="categories\[\]" value="\d+" checked/', $pe);
            say('checked boxes rendered', $chk);
            say('checked == DB rows', yn($chk === $pidCats));
            say('brand groups auto-open', cnt($pe, '<details class="cm-brand"'));
            say('open attr on groups', cnt($pe, '" open>'));
            say('cm-bsel badge shown', yn(has($pe, 'class="cm-bsel" >') || has($pe, 'class="cm-bsel"')));
            /* شمارندهٔ بالای کادر باید برابر تعداد ردیف‌های دیتابیس باشد */
            if (preg_match('/id="cmTotal" data-all="(\d+)">(\d+) مدل/u', $pe, $m)) {
                say('data-all == DB models', yn((int)$m[1] === $modelsAll) . " ({$m[1]})");
                say('initial count == DB rows', yn((int)$m[2] === $pidCats) . " ({$m[2]})");
            } else {
                say('cmTotal parse', '*** FAIL ***');
            }
        }
    }
}

/* ======================= m=sec ======================= */
if ($mode === 'sec') {
    $want = $_GET['sec'] ?? 'footer';
    /* هم صفحهٔ فعالِ سایدبار و هم زیرشاخهٔ فعال از این دو مقدار می‌آیند */
    $_SERVER['SCRIPT_NAME'] = '/admin/settings.php';
    chdir($ROOT . '/admin');
    ob_start(); include $ROOT . '/admin/settings.php'; $st = ob_get_clean();

    /* فیلدِ منحصربه‌فردِ هر بخش */
    $marks = [
        'footer' => 'name="footer_about"',
        'decor'  => 'name="home_decor_style"',
        'sms'    => 'name="sms_api_key"',
        'pay'    => 'name="pay_desc"',
    ];
    hr("settings.php?sec=$want");
    say('page rendered (bytes)', strlen($st));
    say('no PHP error text', yn(!has($st, 'Fatal error') && !has($st, 'Warning:') && !has($st, 'Notice:')));
    say('style.css?v=18', yn(has($st, 'style.css?v=18') && !has($st, '?v=17')));
    say('hidden sec field', yn(has($st, 'name="sec" value="' . $want . '"')));
    say('tabs bar (4 links)', cnt($st, 'settings.php?sec=') >= 8 ? 'OK (' . cnt($st, 'settings.php?sec=') . ')' : '*** FAIL ***');
    say('this tab active', yn(has($st, 'cust-tab active" href="settings.php?sec=' . $want . '"')
                            || has($st, 'href="settings.php?sec=' . $want . '" class="cust-tab active"')));
    say('sidebar sub-link active', yn(cnt($st, 'am-link am-sublink active') === 1));
    say('..and it is this section', yn(has($st,
        'settings.php?sec=' . $want . '" class="am-link am-sublink active"')));
    say('sidebar group open', yn(has($st, '<details class="am-group" open>')));
    say('sidebar parent marked current', yn(has($st, 'am-link am-parent is-cur')));
    foreach ($marks as $k => $needle) {
        say(($k === $want ? 'SHOWS ' : 'hides ') . $k, yn(has($st, $needle) === ($k === $want)));
    }
    say('single submit button', cnt($st, 'type="submit"'));
}

/* ======================= m=save ======================= */
if ($mode === 'save') {
    hr('ذخیرهٔ یک بخش نباید بخش‌های دیگر را خالی کند');

    $eng = '';
    try {
        $q = $pdo->prepare("SELECT ENGINE FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'");
        $q->execute();
        $eng = (string)$q->fetchColumn();
    } catch (Throwable $e) { $eng = 'unknown'; }
    say('settings table engine', $eng);

    /* عکسِ کاملِ همهٔ کلیدها — بازگردانی قطعی، مستقل از rollback */
    $snap = getAllSettings(true);
    $smsKeys = ['sms_api_key', 'sms_template_id', 'sms_param_name', 'sms_line_number',
                'sms_method', 'sms_otp_text', 'sms_test_mode'];

    /* کلیدهای بخش‌های دیگر که باید دست‌نخورده بمانند (اگر خالی‌اند، مقدارِ
       نشانه‌گذاری می‌گیرند تا آزمون معنا داشته باشد) */
    $others = ['footer_about', 'contact_email', 'footer_copyright',
               'pay_desc', 'pay_card_holder', 'home_decor_style'];
    $inTx = false;
    if ($eng === 'InnoDB') { $pdo->beginTransaction(); $inTx = true; }
    say('transaction started', yn($inTx));

    foreach ($others as $k) {
        if (($snap[$k] ?? '') === '') setSetting($k, 'PROBE-' . $k);
    }
    getAllSettings(true);
    $before = [];
    foreach ($others as $k) $before[$k] = getSettingRaw($k, '');

    /* POST بخش «پیامک» با همان مقادیرِ واقعیِ فعلی (بی‌خطر) و فقط یک فیلدِ
       غیرحساس تغییر می‌کند تا معلوم شود نوشتن انجام شده است. */
    $probeParam = ($snap['sms_param_name'] ?? 'CODE') . '-PROBE';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'sec'             => 'sms',
        'sms_api_key'     => $snap['sms_api_key'] ?? '',
        'sms_template_id' => $snap['sms_template_id'] ?? '',
        'sms_param_name'  => $probeParam,
        'sms_line_number' => $snap['sms_line_number'] ?? '',
        'sms_method'      => ($snap['sms_method'] ?? 'bulk'),
        'sms_otp_text'    => ($snap['sms_otp_text'] ?? 'کد تأیید شما: {code}'),
    ];
    if (($snap['sms_test_mode'] ?? '0') === '1') $_POST['sms_test_mode'] = '1';

    $_SERVER['SCRIPT_NAME'] = '/admin/settings.php';
    chdir($ROOT . '/admin');
    ob_start(); include $ROOT . '/admin/settings.php'; $sv = ob_get_clean();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];

    say('save page rendered', strlen($sv));
    say('no PHP error text', yn(!has($sv, 'Fatal error') && !has($sv, 'Warning:') && !has($sv, 'Notice:')));
    say('success flash shown', yn(has($sv, 'flash-success')));
    say('flash names the section', yn(has($sv, 'پیامک')));

    getAllSettings(true);
    say('sms_param_name written', yn(getSettingRaw('sms_param_name', '') === $probeParam));
    $intact = true;
    foreach ($others as $k) {
        $now = getSettingRaw($k, '');
        if ($now !== $before[$k]) { $intact = false; say('CHANGED ' . $k, "'{$before[$k]}' -> '$now'"); }
    }
    say('other sections untouched', yn($intact));
    say('..keys checked', count($others));

    /* برگرداندن وضعیت: اول rollback، بعد بازنویسیِ صریح (شبکهٔ اطمینان) */
    if ($inTx) { $pdo->rollBack(); say('rolled back', 'OK'); }
    foreach ($smsKeys as $k) {
        if (array_key_exists($k, $snap)) { setSetting($k, $snap[$k]); }
        else { $d = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?"); $d->execute([$k]); }
    }
    foreach ($others as $k) {
        if (array_key_exists($k, $snap)) { setSetting($k, $snap[$k]); }
        else { $d = $pdo->prepare("DELETE FROM settings WHERE setting_key = ?"); $d->execute([$k]); }
    }
    getAllSettings(true);
    $restored = true;
    foreach (array_merge($smsKeys, $others) as $k) {
        $exp = array_key_exists($k, $snap) ? $snap[$k] : null;
        $now = array_key_exists($k, getAllSettings()) ? getAllSettings()[$k] : null;
        if ($exp !== $now) { $restored = false; say('NOT RESTORED ' . $k, var_export($now, true)); }
    }
    say('original settings restored', yn($restored));
}

/* نشست ادمینِ این کاوش نباید باقی بماند */
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
echo "\n-- done ($mode) --\n";
