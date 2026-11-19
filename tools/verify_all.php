<?php
require_once __DIR__ . '/../src/Database.php';

try {
    $c = db();
    echo "=== KIEM TRA TRANG THAI DON HANG (ORDERS) ===\n";
    $res = queryParams($c, "SELECT status, count(*) as cnt FROM orders GROUP BY status", [])->get_result();
    while ($r = $res->fetch_assoc()) {
        printf("  - %-15s : %d\n", $r['status'], $r['cnt']);
    }

    echo "\n=== KIEM TRA CAC DONG TRA HANG (RETURNS) ===\n";
    $res = queryParams($c, "SELECT status, count(*) as cnt FROM returns GROUP BY status", [])->get_result();
    while ($r = $res->fetch_assoc()) {
        printf("  - Return status: %-12s : %d\n", $r['status'], $r['cnt']);
    }

    echo "\n=== KIEM TRA THONG BAO THANH TOAN (PAYMENT_EVENTS) ===\n";
    $res = queryParams($c, "SELECT status, count(*) as cnt FROM payment_events GROUP BY status", [])->get_result();
    while ($r = $res->fetch_assoc()) {
        printf("  - Payment event status: %-15s : %d\n", $r['status'], $r['cnt']);
    }


} catch (Throwable $e) {
    echo "Loi: " . $e->getMessage() . "\n";
}
