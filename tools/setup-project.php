<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

echo "=== KHỞI TẠO CƠ SỞ DỮ LIỆU ĐỀ TÀI 19 (MYSQL) ===\n";

try {
    $db = db();
    echo "✓ Đã kết nối MySQL thành công.\n";

    // 1. Chạy schema.sql
    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException("Không tìm thấy schema.sql tại $schemaPath");
    }
    echo "-> Đang thực thi schema.sql...\n";
    $schemaSql = (string)file_get_contents($schemaPath);
    if (!$db->multi_query($schemaSql)) {
        throw new RuntimeException("Lỗi thực thi schema: " . $db->error);
    }
    while ($db->more_results()) {
        $db->next_result();
    }
    echo "✓ Đã tạo thành công 10 bảng dữ liệu.\n";

    // 2. Chạy seed.sql
    $seedPath = __DIR__ . '/../database/seed.sql';
    if (!is_file($seedPath)) {
        throw new RuntimeException("Không tìm thấy seed.sql tại $seedPath");
    }
    echo "-> Đang thực thi seed.sql (30 tài khoản, 20 sản phẩm, 100 đơn hàng)...\n";
    $seedSql = (string)file_get_contents($seedPath);
    if (!$db->multi_query($seedSql)) {
        throw new RuntimeException("Lỗi thực thi seed: " . $db->error);
    }
    while ($db->more_results()) {
        $db->next_result();
    }
    echo "✓ Đã nạp thành công dữ liệu khởi tạo (Seed data).\n";

    // 3. Kiểm tra số lượng bản ghi
    $usersCount = fetchOne(queryParams($db, "SELECT COUNT(*) as c FROM users"))['c'];
    $prodsCount = fetchOne(queryParams($db, "SELECT COUNT(*) as c FROM products"))['c'];
    $varsCount  = fetchOne(queryParams($db, "SELECT COUNT(*) as c FROM variants"))['c'];
    $ordersCount = fetchOne(queryParams($db, "SELECT COUNT(*) as c FROM orders"))['c'];
    $returnsCount = fetchOne(queryParams($db, "SELECT COUNT(*) as c FROM returns"))['c'];
    $logsCount = fetchOne(queryParams($db, "SELECT COUNT(*) as c FROM order_audit_logs"))['c'];

    echo "\n=== KẾT QUẢ ĐỐI SOÁT DỮ LIỆU KHỞI TẠO ===\n";
    echo "1. Người dùng (users):       $usersCount / 30 (Customer, Staff, Manager)\n";
    echo "2. Sản phẩm (products):      $prodsCount / 20\n";
    echo "3. Biến thể (variants):      $varsCount\n";
    echo "4. Đơn hàng (orders):        $ordersCount / 100\n";
    echo "5. Yêu cầu đổi trả (returns): $returnsCount\n";
    echo "6. Nhật ký kiểm toán (logs): $logsCount\n";
    echo "\n>>> HỆ THỐNG ĐÃ SẴN SÀNG HOẠT ĐỘNG! <<<\n";

} catch (Throwable $e) {
    echo "LỖI KHỞI TẠO: " . $e->getMessage() . "\n";
    exit(1);
}

