<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isLoggedIn()) { header('Location: login.php'); exit; }
$msg = '';
$msgErr = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $name = trim($_POST['name'] ?? ''); $editId = (int)($_POST['edit_id'] ?? 0); $parentId = (int)($_POST['parent_id'] ?? 0) ?: null;
    if ($name) {
        $slug = slugify($name);
        if ($editId) { $pdo->prepare("UPDATE part_categories SET name=?, slug=?, parent_id=? WHERE id=?")->execute([$name, $slug, $parentId, $editId]); $msg='به‌روز شد.'; }
        else { $pdo->prepare("INSERT INTO part_categories (name,slug,parent_id,sort_order) VALUES (?,?,?,0)")->execute([$name, $slug, $parentId]); $msg='افزوده شد.'; }
    }
}
/* حذف — چه دستهٔ اصلی باشد چه زیرمجموعه، اگر خودش زیرمجموعه دارد رد می‌شود
   (وگرنه آن زیرمجموعه‌ها یتیم می‌مانند: parent_id‌شان به ردیف پاک‌شده اشاره
   می‌کند و از درخت گم می‌شوند، بی‌آنکه واقعا حذف شده باشند). */
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $chk = $pdo->prepare("SELECT COUNT(*) FROM part_categories WHERE parent_id=?");
    $chk->execute([$delId]);
    if ((int)$chk->fetchColumn() > 0) {
        $msg = 'این دسته زیرمجموعه دارد؛ ابتدا زیرمجموعه‌هایش را حذف یا به دستهٔ دیگری منتقل کنید.';
        $msgErr = true;
    } else {
        $pdo->prepare("DELETE FROM part_categories WHERE id=?")->execute([$delId]);
        $msg = 'حذف شد.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move'])) {
    $moveId = (int)($_POST['move_id'] ?? 0);
    $moveTo = (int)($_POST['move_to'] ?? 0);
    if ($moveId && $moveTo && $moveId !== $moveTo) {
        // مقصد باید یک سرشاخهٔ معتبر باشد (parent_id IS NULL)
        $chk = $pdo->prepare("SELECT COUNT(*) FROM part_categories WHERE id=? AND parent_id IS NULL");
        $chk->execute([$moveTo]);
        if ($chk->fetchColumn()) {
            $pdo->prepare("UPDATE part_categories SET parent_id=? WHERE id=?")->execute([$moveTo, $moveId]);
            $msg = 'منتقل شد.';
        }
    }
}

$parents = $pdo->query("SELECT * FROM part_categories WHERE parent_id IS NULL ORDER BY sort_order")->fetchAll();
$tree = [];
foreach ($parents as $p) {
    $children = $pdo->prepare("SELECT * FROM part_categories WHERE parent_id = ? ORDER BY sort_order");
    $children->execute([$p['id']]);
    $tree[] = ['p' => $p, 'c' => $children->fetchAll()];
}

$editPC = null;
if (isset($_GET['edit'])) { $st = $pdo->prepare("SELECT * FROM part_categories WHERE id = ?"); $st->execute([(int)$_GET['edit']]); $editPC = $st->fetch(); }

/* «+ زیرمجموعه جدید» زیر یک دستهٔ اصلی — فرم را خالی نگه می‌دارد و فقط
   «والد» را از پیش روی همان دسته می‌گذارد (قبلا به‌اشتباه خود دستهٔ اصلی
   را در فرم ویرایش می‌گذاشت، یعنی با ذخیره، نام دستهٔ اصلی عوض می‌شد نه
   این‌که زیرمجموعهٔ تازه‌ای ساخته شود). */
$newChildParent = 0;
if (!$editPC && isset($_GET['new_child'])) $newChildParent = (int)$_GET['new_child'];

require_once __DIR__ . '/layout-top.php';
?>

<?php if ($msg): ?><div class="flash <?= $msgErr ? 'flash-error' : 'flash-success' ?>" style="margin:0 0 1rem;"><?= h($msg) ?></div><?php endif; ?>

<div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
  <a href="part-categories-report.php" target="_blank" class="btn btn-secondary btn-sm">چاپ / خروجی اکسل</a>
</div>

<div style="display:grid; grid-template-columns:300px 1fr; gap:1rem; margin-bottom:1rem;">
<div>
  <div class="dg-box">
    <div class="dg-box-hd"><h3>
      <?php if ($editPC): ?>ویرایش «<?= h($editPC['name']) ?>»
      <?php elseif ($newChildParent): ?>زیرمجموعهٔ جدید برای «<?= h(getPartCategory($newChildParent)['name'] ?? '') ?>»
      <?php else: ?>دستهٔ اصلی یا زیرمجموعهٔ جدید
      <?php endif; ?>
    </h3></div>
    <div class="dg-box-bd" style="padding:1rem;">
      <form method="POST">
        <input type="hidden" name="edit_id" value="<?= $editPC['id'] ?? 0 ?>">
        <div class="form-group"><label>نام</label><input type="text" name="name" class="form-control" value="<?= h($editPC['name'] ?? '') ?>" required></div>
        <div class="form-group"><label>والد</label><select name="parent_id" class="form-control"><option value="0">-- دستهٔ اصلی (بدون والد) --</option><?php foreach ($parents as $pp): ?><option value="<?=$pp['id']?>" <?= ($editPC['parent_id'] ?? $newChildParent) == $pp['id'] ? 'selected' : '' ?>><?=h($pp['name'])?></option><?php endforeach; ?></select>
          <small style="display:block;color:var(--text-muted);font-size:0.72rem;margin-top:0.25rem;">خالی/«دستهٔ اصلی» بگذارید تا یک دستهٔ اصلی تازه (مثل «موتور و قطعات موتوری») بسازید که در منوی سایت هم نمایش داده می‌شود؛ یک والد انتخاب کنید تا زیرمجموعهٔ همان دسته شود.</small>
        </div>
        <button type="submit" name="save" class="btn btn-primary btn-block"><?= $editPC ? 'به‌روزرسانی' : 'افزودن' ?></button>
        <?php if ($editPC || $newChildParent): ?><a href="part-categories.php" class="btn btn-secondary btn-block" style="margin-top:0.5rem;">انصراف</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<div>
  <?php foreach ($tree as $t): ?>
  <div style="margin-bottom:0.5rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;background:var(--bg-secondary);padding:0.5rem 1rem;border-radius:var(--radius-sm);">
      <b style="font-weight:600;color:var(--red-primary);font-size:0.85rem;"><?= h($t['p']['name']) ?> (<?= count($t['c']) ?>)</b>
      <div style="display:flex;gap:0.25rem;align-items:center;flex-wrap:wrap;">
        <a href="?edit=<?= $t['p']['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;">ویرایش</a>
        <?php /* اگر زیرمجموعه دارد، لینک بدون confirm() رد می‌شود چون سرور به‌جای
                حذف، فقط پیام خطا نشان می‌دهد — چیزی واقعا پاک نمی‌شود. */ ?>
        <a href="?delete=<?= $t['p']['id'] ?>" class="btn btn-danger btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;"
           <?= $t['c'] ? '' : 'onclick="return confirm(\'این دستهٔ اصلی حذف شود؟\');"' ?>>حذف</a>
      </div>
    </div>
    <div style="padding:0.5rem 0;">
    <?php foreach ($t['c'] as $c): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;padding:0.3rem 0.5rem;border-bottom:1px solid var(--border-color);font-size:0.8rem;flex-wrap:wrap;">
        <span><?= h($c['name']) ?></span>
        <div style="display:flex;gap:0.25rem;align-items:center;flex-wrap:wrap;">
          <a href="?edit=<?= $c['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;">ویرایش</a>
          <a href="?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;" onclick="return confirm('حذف؟')">حذف</a>
          <?php if (count($parents) > 1): ?>
          <form method="POST" style="display:flex;gap:0.2rem;align-items:center;margin:0;">
            <input type="hidden" name="move_id" value="<?= $c['id'] ?>">
            <select name="move_to" class="form-control" style="padding:0.15rem 0.3rem;font-size:0.7rem;height:auto;width:auto;">
              <option value="0">انتقال به…</option>
              <?php foreach ($parents as $pp): if ($pp['id'] == $t['p']['id']) continue; ?>
              <option value="<?= $pp['id'] ?>"><?= h($pp['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="move" class="btn btn-primary btn-sm" style="padding:0.15rem 0.4rem;font-size:0.7rem;">انتقال</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <a href="?new_child=<?= $t['p']['id'] ?>" class="btn btn-sm btn-secondary" style="margin-top:0.3rem;font-size:0.7rem;">+ زیرمجموعه جدید</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
</div>

<?php require_once __DIR__ . '/layout-bottom.php';