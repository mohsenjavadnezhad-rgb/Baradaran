<?php
$css = file_get_contents(__DIR__ . '/assets/css/style.css');

// 1. Fix تومان spacing - add margin-right to the word
$css = str_replace(
    '.price-box-value { font-size: 1.25rem; font-weight: bold; }',
    '.price-box-value { font-size: 1.25rem; font-weight: bold; } .price-box-value .tmn { font-size:0.7em; margin-right:6px; font-weight:normal; }',
    $css
);

// 2. Replace the entire price-box section with new version
$old = '.price-box {
    flex: 1;
    padding: 1rem;
    border-radius: var(--radius);
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.price-box:hover { opacity: 0.9; }

.price-box.retail {
    background: rgba(220, 38, 38, 0.1);
    border-color: rgba(220, 38, 38, 0.3);
}

.price-box.wholesale {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.price-box.price-active {
    border-color: #fff !important;
    box-shadow: 0 0 0 2px currentColor;
}

.price-box.retail.price-active {
    border-color: var(--red-light) !important;
    box-shadow: 0 0 0 2px var(--red-primary);
}

.price-box.wholesale.price-active {
    border-color: var(--green) !important;
    box-shadow: 0 0 0 2px var(--green);
}';

$new = '.price-box {
    flex: 1;
    padding: 1rem;
    border-radius: var(--radius);
    text-align: center;
    cursor: pointer;
    transition: all 0.25s;
    border: 2px solid transparent;
}

.price-box.price-dimmed {
    opacity: 0.35;
    transform: scale(0.95);
    filter: grayscale(0.3);
}

.price-box.retail {
    background: rgba(220, 38, 38, 0.1);
    border-color: rgba(220, 38, 38, 0.3);
}

.price-box.wholesale {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
}

.price-box.price-active {
    transform: scale(1.02);
}

.price-box.retail.price-active {
    border-color: var(--red-light) !important;
    box-shadow: 0 0 12px rgba(220, 38, 38, 0.5);
    background: rgba(220, 38, 38, 0.2);
}

.price-box.wholesale.price-active {
    border-color: var(--green) !important;
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.5);
    background: rgba(16, 185, 129, 0.2);
}';

$css = str_replace($old, $new, $css);

file_put_contents(__DIR__ . '/assets/css/style.css', $css);
if(function_exists('opcache_reset')) opcache_reset();
echo 'CSS PATCHED';
@unlink(__FILE__);