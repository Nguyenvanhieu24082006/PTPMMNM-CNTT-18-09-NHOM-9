<?php
require_once __DIR__ . '/../src/Database.php';

try {
    $c = db();
    $server = queryParams($c, "SELECT VERSION() as v, DATABASE() as d, USER() as u", [])->get_result()->fetch_assoc();
    
    echo "=== KẾT NỐI MYSQL THÀNH CÔNG RỰC RỠ ===\n";
    echo "Hệ quản trị CSDL : Oracle MySQL Server " . $server['v'] . "\n";
    echo "Tên Database     : " . $server['d'] . "\n";
    echo "Người dùng       : " . $server['u'] . "\n";
    echo "Địa chỉ máy chủ  : 127.0.0.1:3306\n\n";
    
    echo "--- DỮ LIỆU ĐANG HOẠT ĐỘNG TRONG 10 BẢNG INNODB ---\n";
    $tables = [
        'users' => 'Tài khoản người dùng (Customer, Staff, Manager)',
        'products' => 'Danh mục sản phẩm chuẩn e-commerce',
        'variants' => 'Biến thể sản phẩm (Size, Màu sắc)',
        'orders' => 'Đơn hàng (Mọi trạng thái PENDING, PAID, DELIVERED...)',
        'order_items' => 'Chi tiết sản phẩm từng đơn (Price snapshot)',
        'reservations' => 'Bản ghi giữ hàng thông minh (Khóa 15 phút)',
        'payment_events' => 'Lịch sử thanh toán & Webhook (Idempotent)',
        'returns' => 'Yêu cầu đổi trả hàng một phần',
        'return_items' => 'Chi tiết các món hàng đổi trả',
        'order_audit_logs' => 'Nhật ký đối soát doanh thu & kiểm toán hệ thống'
    ];
    
    foreach ($tables as $t => $desc) {
        $row = queryParams($c, "SELECT COUNT(*) as cnt FROM `$t`", [])->get_result()->fetch_assoc();
        printf("  + %-18s : %4d dòng  | %s\n", $t, $row['cnt'], $desc);
    }
} catch (Throwable $e) {
    echo "LỖI KẾT NỐI: " . $e->getMessage() . "\n";
    exit(1);
}

