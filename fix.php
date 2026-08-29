<?php
/* AutoPartsShop file updater. Run via HTTP, then DELETE. */
$path = $_GET['f'] ?? '';
if ($path === 'css' || !$path) {
    $css = file_get_contents(__DIR__.'/style.css.tmp');
    if ($css) { file_put_contents(__DIR__.'/assets/css/style.css', $css); echo "CSS OK "; }
}
if ($path === 'js' || !$path) {
    $js = file_get_contents(__DIR__.'/main.js.tmp');
    if ($js) { file_put_contents(__DIR__.'/assets/js/main.js', $js); echo "JS OK "; }
}
if ($path === 'sql' || !$path) {
    $sql = file_get_contents(__DIR__.'/database.sql.tmp');
    if ($sql) { file_put_contents(__DIR__.'/database.sql', $sql); echo "SQL OK "; }
}
echo "| DONE";