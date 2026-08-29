<?php
$files = [
    'admin/a_dash.php' => 'admin/index.php',
    'admin/a_prod.php' => 'admin/products.php',
    'admin/a_lt.php' => 'admin/layout-top.php',
    'admin/a_lb.php' => 'admin/layout-bottom.php',
];
foreach ($files as $src => $dst) {
    if (file_exists(__DIR__.'/'.$src)) {
        copy(__DIR__.'/'.$src, __DIR__.'/'.$dst);
        echo "OK $dst<br>";
    } else {
        echo "MISS $src<br>";
    }
}
if(function_exists('opcache_reset')) opcache_reset();
@unlink(__FILE__);