<?php
$results = [];

// 1. Fix CSS - try to remove old and copy new
$css_old = __DIR__ . '/assets/css/style.css';
$css_new = __DIR__ . '/styles.css';
$js_old  = __DIR__ . '/assets/js/main.js';
$js_new  = __DIR__ . '/mainjs.js';

// Remove old CSS if exists
if (file_exists($css_old)) {
    unlink($css_old);
    $results[] = "old CSS deleted";
}
// Copy new CSS from root
if (file_exists($css_new)) {
    $ok = copy($css_new, $css_old);
    $results[] = $ok ? "CSS copied OK" : "CSS copy FAILED (perms: " . substr(sprintf('%o', fileperms(dirname($css_old))), -4) . ")";
    if ($ok) unlink($css_new);
} else {
    $results[] = "styles.css not found in root";
}

// Fix JS
if (file_exists($js_old)) {
    unlink($js_old);
    $results[] = "old JS deleted";
}
if (file_exists($js_new)) {
    $ok = copy($js_new, $js_old);
    $results[] = $ok ? "JS copied OK" : "JS copy FAILED";
    if ($ok) unlink($js_new);
} else {
    $results[] = "mainjs.js not found in root";
}

// Update SITE_URL in config
$cfg = __DIR__ . '/includes/config.php';
if (file_exists($cfg)) {
    $c = file_get_contents($cfg);
    $c = str_replace("http://localhost/AutoPartsShop", "http://yadakii.ir", $c);
    file_put_contents($cfg, $c);
    $results[] = "SITE_URL updated";
}

echo "<h3>Fix Results:</h3><ul>";
foreach ($results as $r) echo "<li>$r</li>";
echo "</ul><a href='/'>Go to site</a> | <a href='/?rand=" . time() . "'>Force refresh</a>";