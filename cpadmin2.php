<?php
$files = [
    'admin/al.php' => 'admin/login.php',
    'admin/alo.php' => 'admin/logout.php',
    'admin/ac.php' => 'admin/categories.php',
    'admin/ao.php' => 'admin/orders.php',
    'admin/aod.php' => 'admin/order-detail.php',
];
foreach ($files as $src => $dst) {
    if (file_exists(__DIR__.'/'.$src)) { copy(__DIR__.'/'.$src, __DIR__.'/'.$dst); echo "OK $dst<br>"; }
    else { echo "MISS $src<br>"; }
}
echo 'DONE';