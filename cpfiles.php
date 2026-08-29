<?php
$files = [
    'i2.php' => 'index.php',
    'p3.php' => 'product.php',
    'c3.php' => 'cart.php',
    'cat3.php' => 'category.php',
    's3.php' => 'search.php',
    'co3.php' => 'checkout.php',
    'includes/h2.php' => 'includes/header.php',
    'includes/f2.php' => 'includes/functions.php',
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