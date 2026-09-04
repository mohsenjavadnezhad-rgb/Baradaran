<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/menu.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }

$msg = '';
$msgErr = false;
$group = (($_GET['group'] ?? $_POST['menu_group'] ?? 'main') === 'footer') ? 'footer' : 'main';

/* قبل از اجرای مهاجرت، به‌جای خطای دیتابیس یک راهنمای روشن نشان می‌دهیم */
if (!menuReady()) {
    require_once __DIR__ . '/layout-top.php';
    ?>
    <div class="flash flash-error">جدول منوها هنوز روی این نصب ساخته نشده. یک‌بار آدرس <code>migrate-menus.php</code> را در مرورگر باز کنید تا ساخته و با چیدمان فعلی سایت پر شود؛ بعدش همین صفحه فعال می‌شود.</div>
    <?php
    require_once __DIR__ . '/layout-bottom.php';
    exit;
}

$iconChoices = menuIconChoices();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $editId = (int)($_POST['edit_id'] ?? 0);
    $label  = trim($_POST['label'] ?? '');
    $url    = trim($_POST['url'] ?? '');
    $icon   = trim($_POST['icon'] ?? 'menu');
    if (!in_array($icon, $iconChoices, true)) $icon = 'menu';

    if ($label === '') {
        $msg = 'برچسب را وارد کنید.'; $msgErr = true;
    } else {
        $existing = null;
        if ($editId) {
            $st = $pdo->prepare("SELECT * FROM site_menus WHERE id=?");
            $st->execute([$editId]);
            $existing = $st->fetch();
        }
        /* آیتم‌های سیستمی (مثل مگامنوی فروشگاه یا حساب‌کاربری/ورود) آدرس یا
           محتوای‌شان در کد سایت ساخته می‌شود — فرم اجازه ندارد آدرس دلخواه
           رویشان بنویسد، فقط برچسب/آیکون‌شان قابل تغییر است. */
        $isSystem = $existing && $existing['item_key'] !== null;
        if (!$isSystem && $url === '') {
            $msg = 'آدرس را وارد کنید.'; $msgErr = true;
        } else {
            $saveUrl = $isSystem ? ($existing['url'] ?? null) : $url;
            if ($editId && $existing) {
                $pdo->prepare("UPDATE site_menus SET label=?, url=?, icon=? WHERE id=?")
                    ->execute([$label, $saveUrl, $icon, $editId]);
                $msg = 'به‌روز شد.';
            } else {
                $pdo->prepare("INSERT INTO site_menus (menu_group, item_key, label, url, icon, sort_order, is_active)
                               VALUES (?, NULL, ?, ?, ?, ?, 1)")
                    ->execute([$group, $label, $saveUrl, $icon, menuNextOrder($group)]);
                $msg = 'افزوده شد.';
            }
        }
    }
}

if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE site_menus SET is_active = 1 - is_active WHERE id=?")->execute([(int)$_GET['toggle']]);
    $msg = 'وضعیت فعال‌بودن تغییر کرد.';
}

if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $st = $pdo->prepare("SELECT item_key FROM site_menus WHERE id=?");
    $st->execute([$delId]);
    $row = $st->fetch();
    if (!$row) {
        // چیزی برای حذف نیست
    } elseif ($row['item_key'] !== null) {
        $msg = 'این آیتم سیستمی است (آدرس/محتوایش به کد سایت وابسته است) و حذف نمی‌شود؛ فقط می‌توانید غیرفعالش کنید.';
        $msgErr = true;
    } else {
        $pdo->prepare("DELETE FROM site_menus WHERE id=?")->execute([$delId]);
        $msg = 'حذف شد.';
    }
}

/* جابه‌جایی ترتیب: مبادلهٔ sort_order با نزدیک‌ترین همسایهٔ همان گروه */
if (isset($_GET['up']) || isset($_GET['down'])) {
    $dir = isset($_GET['up']) ? 'up' : 'down';
    $id  = (int)($_GET['up'] ?? $_GET['down']);
    $st = $pdo->prepare("SELECT * FROM site_menus WHERE id=?");
    $st->execute([$id]);
    $cur = $st->fetch();
    if ($cur) {
        $cmp = $dir === 'up' ? '<' : '>';
        $ord = $dir === 'up' ? 'DESC' : 'ASC';
        $st2 = $pdo->prepare("SELECT * FROM site_menus WHERE menu_group=? AND sort_order $cmp ? ORDER BY sort_order $ord, id $ord LIMIT 1");
        $st2->execute([$cur['menu_group'], $cur['sort_order']]);
        $nb = $st2->fetch();
        if ($nb) {
            $pdo->prepare("UPDATE site_menus SET sort_order=? WHERE id=?")->execute([$nb['sort_order'], $cur['id']]);
            $pdo->prepare("UPDATE site_menus SET sort_order=? WHERE id=?")->execute([$cur['sort_order'], $nb['id']]);
        }
    }
}

$items = menuAllItems($group);
$editItem = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM site_menus WHERE id=?");
    $st->execute([(int)$_GET['edit']]);
    $editItem = $st->fetch();
    if ($editItem) $group = $editItem['menu_group'];
}
$editIsSystem = $editItem && $editItem['item_key'] !== null;

require_once __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="flash <?= $msgErr ? 'flash-error' : 'flash-success' ?>" style="margin:0 0 1rem;"><?= h($msg) ?></div><?php endif; ?>

<div class="cust-tabs" style="margin-bottom:1rem;">
  <a href="?group=main" class="cust-tab <?= $group === 'main' ? 'active' : '' ?>"><?= icon('menu', 'ic-sm') ?> منوی بالای سایت</a>
  <a href="?group=footer" class="cust-tab <?= $group === 'footer' ? 'active' : '' ?>"><?= icon('layers', 'ic-sm') ?> دسترسی سریع فوتر</a>
</div>

<div style="display:grid; grid-template-columns:320px 1fr; gap:1rem; margin-bottom:1rem;">
<div>
  <div class="dg-box">
    <div class="dg-box-hd"><h3><?= $editItem ? 'ویرایش «' . h($editItem['label']) . '»' : 'آیتم جدید' ?></h3></div>
    <div class="dg-box-bd" style="padding:1rem;">
      <form method="POST">
        <input type="hidden" name="menu_group" value="<?= h($group) ?>">
        <input type="hidden" name="edit_id" value="<?= $editItem['id'] ?? 0 ?>">

        <?php if ($editIsSystem): ?>
        <p style="font-size:0.75rem;color:var(--text-muted);background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:0.5rem 0.7rem;margin-bottom:0.75rem;">
          <?= icon('info', 'ic-sm') ?> این آیتم سیستمی است؛ آدرس/محتوایش (مثل مگامنوی فروشگاه یا لینک حساب‌کاربری) در کد سایت ساخته می‌شود. فقط برچسب، آیکون، ترتیب و فعال‌بودنش قابل تغییر است — حذف واقعی ندارد.
        </p>
        <?php endif; ?>

        <div class="form-group">
          <label>برچسب *</label>
          <input type="text" name="label" class="form-control" value="<?= h($editItem['label'] ?? '') ?>" required>
        </div>

        <?php if (!$editIsSystem): ?>
        <div class="form-group">
          <label>آدرس *</label>
          <input type="text" name="url" class="form-control" dir="ltr" value="<?= h($editItem['url'] ?? '') ?>" placeholder="مثلا search.php?tag=x یا https://...">
          <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">آدرس داخلی سایت (مثل shop.php) یا یک لینک کامل خارجی.</small>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label>آیکون</label>
          <select name="icon" class="form-control">
            <?php foreach ($iconChoices as $ic): ?>
            <option value="<?= h($ic) ?>" <?= ($editItem['icon'] ?? 'menu') === $ic ? 'selected' : '' ?>><?= h($ic) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" name="save" class="btn btn-primary btn-block"><?= $editItem ? 'به‌روزرسانی' : 'افزودن' ?></button>
        <?php if ($editItem): ?><a href="?group=<?= h($group) ?>" class="btn btn-secondary btn-block" style="margin-top:0.5rem;">انصراف</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>

<div>
  <div class="dg-box">
    <div class="dg-box-hd"><div class="dg-hd-t"><?= icon('menu', 'ic-sm') ?> <?= h(menuGroupLabel($group)) ?> (<?= count($items) ?> آیتم)</div></div>
    <div class="dg-box-bd" style="padding:0;">
      <?php if (!$items): ?>
      <p style="padding:1rem;color:var(--text-muted);font-size:0.85rem;">هنوز آیتمی نیست.</p>
      <?php else: foreach ($items as $i => $it): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;padding:0.55rem 0.9rem;border-bottom:1px solid var(--border-color);<?= $it['is_active'] ? '' : 'opacity:0.5;' ?>flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:0.5rem;min-width:0;">
          <span class="am-icon" style="width:auto;"><?= icon($it['icon']) ?></span>
          <div style="min-width:0;">
            <b style="font-size:0.85rem;<?= $it['is_active'] ? '' : 'text-decoration:line-through;' ?>"><?= h($it['label']) ?></b>
            <?php if ($it['item_key'] !== null): ?><span class="badge-retail" style="margin-inline-start:0.4rem;">سیستمی</span><?php endif; ?>
            <div style="color:var(--text-muted);font-size:0.72rem;direction:ltr;text-align:right;">
              <?= $it['url'] !== null ? h($it['url']) : '(خودکار در کد سایت)' ?>
            </div>
          </div>
        </div>
        <div style="display:flex;gap:0.25rem;align-items:center;flex-wrap:wrap;">
          <?php $upDis = $i === 0; $downDis = $i === count($items) - 1; ?>
          <a href="<?= $upDis ? '#' : '?group=' . h($group) . '&up=' . $it['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;<?= $upDis ? 'opacity:.4;pointer-events:none;' : '' ?>" title="بالاتر" <?= $upDis ? 'aria-disabled="true" tabindex="-1"' : '' ?>><?= icon('chevron-right', 'ic-sm') ?></a>
          <a href="<?= $downDis ? '#' : '?group=' . h($group) . '&down=' . $it['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;<?= $downDis ? 'opacity:.4;pointer-events:none;' : '' ?>" title="پایین‌تر" <?= $downDis ? 'aria-disabled="true" tabindex="-1"' : '' ?>><?= icon('chevron-left', 'ic-sm') ?></a>
          <a href="?group=<?= h($group) ?>&toggle=<?= $it['id'] ?>" class="btn btn-sm <?= $it['is_active'] ? 'btn-secondary' : 'btn-primary' ?>" style="padding:0.15rem 0.5rem;font-size:0.7rem;"><?= $it['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?></a>
          <a href="?group=<?= h($group) ?>&edit=<?= $it['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;">ویرایش</a>
          <?php if ($it['item_key'] === null): ?>
          <a href="?group=<?= h($group) ?>&delete=<?= $it['id'] ?>" class="btn btn-danger btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;" data-confirm="این آیتم منو حذف شود؟">حذف</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
</div>

<?php require_once __DIR__ . '/layout-bottom.php';
