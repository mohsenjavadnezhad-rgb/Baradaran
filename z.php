<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__.'/assets/css/style.css', base64_decode($_POST['d']));
    echo 'CSS ';
}
if (file_exists(__DIR__.'/prd.php')) { copy(__DIR__.'/prd.php', __DIR__.'/product.php'); unlink(__DIR__.'/prd.php'); echo 'PRD '; }
if (file_exists(__DIR__.'/ix.php')) { copy(__DIR__.'/ix.php', __DIR__.'/index.php'); unlink(__DIR__.'/ix.php'); echo 'IDX '; }
if (file_exists(__DIR__.'/mj.js')) { copy(__DIR__.'/mj.js', __DIR__.'/assets/js/main.js'); unlink(__DIR__.'/mj.js'); echo 'JS '; }
if (file_exists(__DIR__.'/includes/hdr.php')) { copy(__DIR__.'/includes/hdr.php', __DIR__.'/includes/header.php'); unlink(__DIR__.'/includes/hdr.php'); echo 'HDR '; }
if (file_exists(__DIR__.'/includes/fnc.php')) { copy(__DIR__.'/includes/fnc.php', __DIR__.'/includes/functions.php'); unlink(__DIR__.'/includes/fnc.php'); echo 'FNC '; }
if (file_exists(__DIR__.'/admin/pe.php')) { copy(__DIR__.'/admin/pe.php', __DIR__.'/admin/product-edit.php'); unlink(__DIR__.'/admin/pe.php'); echo 'PE '; }
if (file_exists(__DIR__.'/admin/pr.php')) { copy(__DIR__.'/admin/pr.php', __DIR__.'/admin/products.php'); unlink(__DIR__.'/admin/pr.php'); echo 'PR '; }
if (file_exists(__DIR__.'/cat.php')) { copy(__DIR__.'/cat.php', __DIR__.'/category.php'); unlink(__DIR__.'/cat.php'); echo 'CAT '; }
if (file_exists(__DIR__.'/sr.php')) { copy(__DIR__.'/sr.php', __DIR__.'/search.php'); unlink(__DIR__.'/sr.php'); echo 'SR '; }
if (file_exists(__DIR__.'/includes/header-new.php')) { copy(__DIR__.'/includes/header-new.php', __DIR__.'/includes/header.php'); unlink(__DIR__.'/includes/header-new.php'); echo 'HDR2 '; }
echo 'DONE';