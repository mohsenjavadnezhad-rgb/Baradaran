<?php
$files = [
    'p_latest.php' => 'product.php',
    'c_latest.php' => 'cart.php',
    'includes/cf_latest.php' => 'includes/cart-functions.php',
];
foreach ($files as $s => $d) { if (file_exists(__DIR__.'/'.$s)) { copy(__DIR__.'/'.$s, __DIR__.'/'.$d); echo "OK $d<br>"; } else { echo "MISS $s<br>"; } }
if(function_exists('opcache_reset'))opcache_reset();
@unlink(__FILE__);