<?php
require_once 'includes/header.php';

/*
 * صفحهٔ اصلی = فقط بنرها.
 * برای نمایش هر بنر، کافی است تصویری با نام مشخص‌شده در پوشهٔ uploads/banners/ بارگذاری کنید.
 * تا وقتی تصویری نباشد، «جای‌گیر» با ابعاد پیشنهادی نمایش داده می‌شود.
 */

/* اگر تصویری برای این بنر موجود بود، مسیر وبی‌اش را برمی‌گرداند؛ وگرنه رشتهٔ خالی. */
function findBanner($base) {
    $dir = __DIR__ . '/uploads/banners/';
    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $ext) {
        $f = $base . '.' . $ext;
        if (is_file($dir . $f)) return 'uploads/banners/' . $f;
    }
    return '';
}

/* رندر یک بنر: تصویر واقعی (لینک‌دار) یا جای‌گیرِ خالی با ابعاد. */
function renderBanner($b) {
    $img = findBanner($b['base']);
    $ratio = 'aspect-ratio:' . $b['w'] . '/' . $b['h'];
    if ($img) {
        $href = !empty($b['href']) ? $b['href'] : '#';
        return '<a href="' . h($href) . '" class="banner" style="' . $ratio . '">'
             . '<img src="' . h($img) . '" alt="' . h($b['label']) . '" class="banner-img">'
             . '</a>';
    }
    return '<div class="banner is-empty" style="' . $ratio . '">'
         . '<span class="banner-ph">'
         . '<span class="banner-ph-icon">' . icon('image') . '</span>'
         . '<span class="banner-ph-label">' . h($b['label']) . '</span>'
         . '<span class="banner-ph-size">' . $b['w'] . ' &times; ' . $b['h'] . ' px</span>'
         . '<span class="banner-ph-file">uploads/banners/' . h($b['base']) . '.jpg</span>'
         . '</span>'
         . '</div>';
}

/* بنر اصلی (تمام‌عرض) */
$mainBanner = ['base' => 'main', 'w' => 1200, 'h' => 420, 'label' => 'بنر اصلی', 'href' => 'index.php'];

/* دو بنر عریض (نصف عرض) */
$wideBanners = [
    ['base' => 'wide1', 'w' => 590, 'h' => 250, 'label' => 'بنر عریض ۱', 'href' => 'index.php'],
    ['base' => 'wide2', 'w' => 590, 'h' => 250, 'label' => 'بنر عریض ۲', 'href' => 'index.php'],
];

/* چهار بنر کوچک (یک‌چهارم عرض) */
$smallBanners = [
    ['base' => 'box1', 'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۱', 'href' => 'index.php'],
    ['base' => 'box2', 'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۲', 'href' => 'index.php'],
    ['base' => 'box3', 'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۳', 'href' => 'index.php'],
    ['base' => 'box4', 'w' => 285, 'h' => 190, 'label' => 'بنر کوچک ۴', 'href' => 'index.php'],
];
?>

<div class="container banners-page">
    <div class="banners-wrap">

        <!-- بنر اصلی -->
        <?= renderBanner($mainBanner) ?>

        <!-- دو بنر عریض -->
        <div class="banners-row row-2">
            <?php foreach ($wideBanners as $b): ?>
            <?= renderBanner($b) ?>
            <?php endforeach; ?>
        </div>

        <!-- چهار بنر کوچک -->
        <div class="banners-row row-4">
            <?php foreach ($smallBanners as $b): ?>
            <?= renderBanner($b) ?>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
