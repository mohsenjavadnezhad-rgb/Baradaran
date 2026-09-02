<?php
/* قطعهٔ مشترک نمایش عکس‌های ارسالی مشتری در صفحهٔ part-check.php
   از سه حالت «در انتظار»، «تأیید شد» و «تأیید نشد» require می‌شود.
   ورودی‌ها (از خود part-check.php): $imgs، $row، و در صورت وجود $rowProd.
   نام فایل‌ها ساختهٔ سرور است (pc<time>_<rand>.<ext>) ولی باز هم با h() از
   آن‌ها محافظت می‌کنیم و مسیر را ثابت می‌سازیم تا هیچ ورودی مشتری در آدرس
   دخالت نکند. */
if (!isset($imgs) || !is_array($imgs)) $imgs = [];
$pcRow  = isset($row) && is_array($row) ? $row : [];
$pcProd = isset($rowProd) && is_array($rowProd) ? $rowProd : null;
?>
<div class="pchk-sent">
    <?php if ($pcProd): ?>
    <p class="pchk-sent-for">
        <?= icon('package', 'ic-sm') ?> برای کالای: <b><?= h((string)$pcProd['name']) ?></b>
        <?php if (trim((string)$pcProd['technical_number']) !== ''): ?>
        <span class="pchk-tech">شماره فنی: <?= h((string)$pcProd['technical_number']) ?></span>
        <?php endif; ?>
    </p>
    <?php endif; ?>

    <?php if (trim((string)($pcRow['car_info'] ?? '')) !== ''): ?>
    <p class="pchk-sent-line"><?= icon('truck', 'ic-sm') ?> <b>خودرو:</b> <?= h((string)$pcRow['car_info']) ?></p>
    <?php endif; ?>

    <?php if (trim((string)($pcRow['note'] ?? '')) !== ''): ?>
    <p class="pchk-sent-line"><?= icon('clipboard-list', 'ic-sm') ?> <b>توضیح شما:</b> <?= nl2br(h((string)$pcRow['note'])) ?></p>
    <?php endif; ?>

    <?php if ($imgs): ?>
    <div class="pchk-sent-head"><?= icon('camera', 'ic-sm') ?> عکس‌های ارسالی شما (<?= count($imgs) ?> عکس) — برای دیدن اندازهٔ کامل روی هر عکس بزنید</div>
    <div class="pchk-thumbs">
        <?php foreach ($imgs as $i => $im):
            $src = 'uploads/partchecks/' . basename((string)$im['image']); ?>
        <a href="<?= h($src) ?>" target="_blank" rel="noopener" class="pchk-thumb" title="عکس <?= (int)$i + 1 ?>">
            <img src="<?= h($src) ?>" alt="عکس قطعهٔ شما شمارهٔ <?= (int)$i + 1 ?>" loading="lazy">
            <span class="pchk-thumb-n"><?= (int)$i + 1 ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="pchk-sent-line"><?= icon('info', 'ic-sm') ?> عکسی برای نمایش پیدا نشد.</p>
    <?php endif; ?>
</div>
