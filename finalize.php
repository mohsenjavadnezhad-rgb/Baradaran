<?php
$cfg = file_get_contents(__DIR__.'/includes/config.php');
$cfg = str_replace('http://localhost/AutoPartsShop', 'http://yadakii.ir', $cfg);
file_put_contents(__DIR__.'/includes/config.php', $cfg);
echo 'SITE_URL updated';
@unlink(__FILE__);