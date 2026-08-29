<?php
/* Run-once DB setup #3 — partner/customer split + MVM brand rename.
   Adds: customers.customer_type, partner_status, partner_company,
         partner_requested_at, partner_approved_at
   Renames brand «مدیران خودرو (MVM)» → «ام وی ام» (name only — slug 'mvm'
   must stay Latin because assets/images/brands/mvm.svg is looked up by slug).
   Delete or neutralize this file after a successful run. */
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

function colExists3($pdo, $table, $col) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $col]);
    return (int)$stmt->fetchColumn() > 0;
}

function addCol3($pdo, $table, $col, $ddl) {
    if (!colExists3($pdo, $table, $col)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $col $ddl");
        echo "OK $table.$col added\n";
    } else {
        echo "SKIP $table.$col exists\n";
    }
}

try {
    // 1) partner columns on customers
    addCol3($pdo, 'customers', 'customer_type',       "VARCHAR(10) NOT NULL DEFAULT 'retail'");
    addCol3($pdo, 'customers', 'partner_status',      "VARCHAR(10) NOT NULL DEFAULT 'none'");
    addCol3($pdo, 'customers', 'partner_company',     "VARCHAR(150) NOT NULL DEFAULT ''");
    addCol3($pdo, 'customers', 'partner_requested_at', "DATETIME NULL");
    addCol3($pdo, 'customers', 'partner_approved_at',  "DATETIME NULL");

    // index for the admin tabs (all|partner|retail|pending)
    try {
        $idx = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND INDEX_NAME = 'idx_ctype'");
        $idx->execute();
        if ((int)$idx->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE customers ADD INDEX idx_ctype (customer_type, partner_status)");
            echo "OK customers.idx_ctype added\n";
        } else { echo "SKIP customers.idx_ctype exists\n"; }
    } catch (Throwable $e) { echo "WARN index: " . $e->getMessage() . "\n"; }

    // normalize any NULL/empty leftovers
    $pdo->exec("UPDATE customers SET customer_type = 'retail'
                WHERE customer_type IS NULL OR customer_type = ''");
    $pdo->exec("UPDATE customers SET partner_status = 'none'
                WHERE partner_status IS NULL OR partner_status = ''");
    echo "OK customer_type/partner_status normalized\n";

    // 2) brand rename — NAME ONLY, never the slug
    $st = $pdo->prepare("UPDATE categories SET name = ? WHERE slug = ? AND parent_id IS NULL");
    $st->execute(['ام وی ام', 'mvm']);
    echo "OK MVM brand renamed (rows affected: " . $st->rowCount() . ")\n";

    $chk = $pdo->prepare("SELECT id, name, slug FROM categories WHERE slug = ?");
    $chk->execute(['mvm']);
    foreach ($chk->fetchAll() as $r) {
        echo "   -> id={$r['id']} name={$r['name']} slug={$r['slug']}\n";
    }

    echo "\nALL DONE\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
