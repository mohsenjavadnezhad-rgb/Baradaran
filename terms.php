<?php
/* شرایط و قوانین — متن آزادی که مدیر در تنظیمات ← «شرایط و قوانین» می‌نویسد
   (کلید terms_content). صفحهٔ ساده و مستقل، لینک فوتر به همین‌جا می‌آید. */
require_once __DIR__ . '/includes/header.php';

$termsText = trim((string)getSettingRaw('terms_content', ''));
?>

<div class="container">
    <h1 class="page-title"><?= icon('clipboard-list') ?> شرایط و قوانین</h1>

    <div class="terms-page">
        <?php if ($termsText !== ''): ?>
        <div class="terms-body"><?= nl2br(h($termsText)) ?></div>
        <?php else: ?>
        <div class="no-results">
            <div class="no-results-icon"><?= icon('clipboard-list') ?></div>
            <p>شرایط و قوانین به‌زودی اینجا قرار می‌گیرد.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
