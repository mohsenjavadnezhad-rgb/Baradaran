<?php
for ($i=0; $i<20; $i++) {
    $f = __DIR__ . "/c{$i}.php";
    if (file_exists($f)) { include $f; unlink($f); }
}
$css = '';
for ($i=0; $i<20; $i++) {
    $f = __DIR__ . "/css_c{$i}.tmp";
    if (file_exists($f)) { $css .= file_get_contents($f); unlink($f); }
}
if ($css) { file_put_contents(__DIR__.'/assets/css/style.css', $css); echo "CSS OK<br>"; }

if (file_exists(__DIR__.'/ix.php')) { copy(__DIR__.'/ix.php', __DIR__.'/index.php'); unlink(__DIR__.'/ix.php'); echo "IDX OK<br>"; }
if (file_exists(__DIR__.'/mj.js'))  { copy(__DIR__.'/mj.js', __DIR__.'/assets/js/main.js'); unlink(__DIR__.'/mj.js'); echo "JS OK<br>"; }

@unlink(__FILE__);
echo "DONE. <a href='/'>Go</a>";