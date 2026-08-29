<?php
$admin = __DIR__ . '/admin';
$files = ['index.php'=>'ix.php','products.php'=>'pr.php','product-edit.php'=>'pe.php','part-categories.php'=>'pc.php','layout-top.php'=>'lt.php','layout-bottom.php'=>'lb.php'];
foreach ($files as $dest => $src) {
  $to = "$admin/$dest";
  $from = "$admin/$src";
  if (file_exists($from)) { copy($from, $to); unlink($from); echo "OK: $dest<br>"; }
  else { echo "MISS: $src<br>"; }
}
echo "DONE";
@unlink(__FILE__);