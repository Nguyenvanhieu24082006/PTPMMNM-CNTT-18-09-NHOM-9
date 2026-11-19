<?php
// Script tạo seed.sql chuẩn chỉnh cho Đề tài 19
$passwordHash = password_hash('123456', PASSWORD_BCRYPT);

$sql = [];
$sql[] = "-- SEED DATA CHO ĐỀ TÀI 19";
$sql[] = "SET FOREIGN_KEY_CHECKS = 0;";
$sql[] = "TRUNCATE TABLE order_audit_logs;";
$sql[] = "TRUNCATE TABLE return_items;";
$sql[] = "TRUNCATE TABLE returns;";
$sql[] = "TRUNCATE TABLE payment_events;";
$sql[] = "TRUNCATE TABLE reservations;";
$sql[] = "TRUNCATE TABLE order_items;";
$sql[] = "TRUNCATE TABLE orders;";
$sql[] = "TRUNCATE TABLE variants;";
$sql[] = "TRUNCATE TABLE products;";
$sql[] = "TRUNCATE TABLE users;";
$sql[] = "SET FOREIGN_KEY_CHECKS = 1;\n";

// 1. TẠO 30 TÀI KHOẢN (1-10: Customer, 11-20: Staff, 21-30: Manager)
$sql[] = "-- 1. SEED 30 NGƯỜI DÙNG";
$users = [];
$names = [
    'Nguyễn Văn An', 'Trần Thị Mai', 'Lê Hoàng Long', 'Phạm Minh Đức', 'Vũ Thị Ngọc',
    'Đỗ Quang Huy', 'Bùi Thu Hà', 'Hồ Quốc Bảo', 'Ngô Thanh Vân', 'Dương Tuấn Kiệt',
    'Nguyễn Hữu Thắng', 'Phan Bích Phương', 'Đinh Xuân Trường', 'Lý Gia Hân', 'Trịnh Đình Quang',
    'Mai Văn Cường', 'Võ Thị Yến', 'Cao Minh Triết', 'Lương Hải Đăng', 'Tạ Thu Thảo',
    'Hoàng Thế Huy', 'Nguyễn Thu Trang', 'Đặng Quốc Toàn', 'Bạch Ngọc Lan', 'Trương Bá Đạt',
    'Lâm Thanh Sơn', 'Diệp Anh Khoa', 'Khổng Thị Cúc', 'Ân Hữu Phước', 'Giang Tuấn Linh'
];

for ($i = 1; $i <= 30; $i++) {
    $role = ($i <= 10) ? 'customer' : (($i <= 20) ? 'staff' : 'manager');
    $uname = "user{$i}";
    if ($i <= 10) $uname = "customer{$i}";
    elseif ($i <= 20) $uname = "staff" . ($i - 10);
    else $uname = "manager" . ($i - 20);
    
    $name = $names[$i - 1];
    $email = "{$uname}@shoponline.vn";
    $phone = "098" . str_pad((string)$i, 7, '0', STR_PAD_LEFT);
    $addr = "Số {$i}, Phố Hoàng Mai, Hà Nội";
    $sql[] = sprintf(
        "INSERT INTO users (id, username, password_hash, full_name, email, phone, address, role) VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s');",
        $i, $uname, $passwordHash, $name, $email, $phone, $addr, $role
    );
}

// 2. TẠO 20 SẢN PHẨM & CÁC BIẾN THỂ
$sql[] = "\n-- 2. SEED 20 SẢN PHẨM & BIẾN THỂ";
$productList = [
    ['Áo thun Cotton Basic', 'Thời trang', 'Áo thun thoáng mát, thấm hút mồ hôi 100% cotton', 'ao_thun.svg', [
        ['Trắng', 'M', 150000, 50],
        ['Trắng', 'L', 150000, 40],
        ['Đen', 'M', 150000, 30],
        ['Đen', 'L', 150000, 1] // Tồn kho = 1 để test race condition
    ]],
    ['Áo Hoodie Nỉ Bông Unisex', 'Thời trang', 'Áo hoodie dày dặn, giữ ấm mùa đông', 'hoodie.svg', [
        ['Xám', 'L', 320000, 25],
        ['Đen', 'XL', 320000, 20]
    ]],
    ['Quần Jean Slimfit Co Giãn', 'Thời trang', 'Quần jean thời trang, dễ phối đồ', 'quan_jean.svg', [
        ['Xanh nhạt', '30', 280000, 35],
        ['Xanh đậm', '31', 280000, 30]
    ]],
    ['Áo Sơ Mi Nam Công Sở Oxford', 'Thời trang', 'Chất vải chống nhăn, phom chuẩn', 'so_mi.svg', [
        ['Trắng', '39', 250000, 20],
        ['Xanh dương', '40', 250000, 15]
    ]],
    ['Áo Khoác Gió Chống Nước', 'Thời trang', 'Chống gió, chống thấm nước đi mưa nhẹ', 'ao_khoac.svg', [
        ['Rêu', 'L', 350000, 18],
        ['Đen', 'XL', 350000, 22]
    ]],
    ['Giày Sneaker Cổ Thấp', 'Giày dép', 'Đế cao su êm ái, thích hợp đi bộ và thể thao', 'giay_sneaker.svg', [
        ['Trắng', '41', 450000, 15],
        ['Trắng', '42', 450000, 12],
        ['Đen', '41', 450000, 10]
    ]],
    ['Giày Tây Da Nam Oxford', 'Giày dép', 'Da tổng hợp cao cấp, sang trọng', 'giay_tay.svg', [
        ['Đen bóng', '40', 550000, 10],
        ['Nâu', '41', 550000, 8]
    ]],
    ['Dép Sandal Quai Dù Đi Mưa', 'Giày dép', 'Chống trơn trượt, quai bền bỉ', 'sandal.svg', [
        ['Xanh lính', '40', 160000, 40],
        ['Đen', '41', 160000, 35]
    ]],
    ['Balo Laptop Chống Sốc 15.6 inch', 'Phụ kiện', 'Vải trượt nước, nhiều ngăn tiện lợi', 'balo.svg', [
        ['Xám tro', 'Tiêu chuẩn', 290000, 30],
        ['Đen', 'Tiêu chuẩn', 290000, 25]
    ]],
    ['Ví Nam Da Bò Khâu Tay', 'Phụ kiện', 'Thiết kế nhỏ gọn, nhiều ngăn chứa thẻ', 'vi_da.svg', [
        ['Nâu bò', 'Ngắn', 190000, 50],
        ['Đen', 'Dài', 220000, 40]
    ]],
    ['Thắt Lưng Nam Khóa Tự Động', 'Phụ kiện', 'Mặt hợp kim chống gỉ, dây da dẻo', 'that_lung.svg', [
        ['Bạc - Đen', '120cm', 140000, 60]
    ]],
    ['Đồng Hồ Thể Thao Chống Nước', 'Phụ kiện', 'Hiển thị giờ điện tử, báo thức, đèn nền', 'dong_ho.svg', [
        ['Đen viền đỏ', 'Mặt 44mm', 210000, 20]
    ]],
    ['Tai Nghe Bluetooth True Wireless', 'Điện tử', 'Thời lượng pin 24h kèm dock sạc, âm bass ấm', 'tai_nghe.svg', [
        ['Trắng', 'V5.3', 390000, 25],
        ['Đen', 'V5.3', 390000, 30]
    ]],
    ['Chuột Máy Tính Không Dây Silent', 'Điện tử', 'Bấm êm không ồn, pin dùng 6 tháng', 'chuot_kd.svg', [
        ['Xám kim loại', 'Wireless 2.4G', 120000, 45]
    ]],
    ['Bàn Phím Cơ Tenkeyless RGB', 'Điện tử', 'Blue switch gõ nảy, đèn led nhiều chế độ', 'ban_phim.svg', [
        ['Trắng', 'Blue Switch', 590000, 15],
        ['Đen', 'Red Switch', 620000, 10]
    ]],
    ['Sạc Dự Phòng 20000mAh Sạc Nhanh', 'Điện tử', 'Hỗ trợ sạc nhanh PD 22.5W, 3 cổng sạc', 'sac_du_phong.svg', [
        ['Trắng', '20000mAh', 320000, 35],
        ['Xanh đen', '20000mAh', 320000, 28]
    ]],
    ['Bình Giữ Nhiệt Inox 304 800ml', 'Gia dụng', 'Giữ nóng lạnh lên đến 12 giờ liên tục', 'binh_giu_nhiet.svg', [
        ['Bạc Inox', '800ml', 130000, 50],
        ['Đen nhám', '800ml', 130000, 45]
    ]],
    ['Cốc Sứ Pha Cà Phê Có Nắp', 'Gia dụng', 'Men sứ bóng mịn, kèm muỗng inox', 'coc_su.svg', [
        ['Trắng chữ đen', '450ml', 65000, 70]
    ]],
    ['Đèn Bàn Học LED Chống Cận', 'Gia dụng', '3 chế độ sáng, sạc cổng Type-C tích điện', 'den_ban.svg', [
        ['Trắng tinh', 'Pin 2000mAh', 175000, 30]
    ]],
    ['Gối Cổ Chữ U Cao Su Non', 'Gia dụng', 'Nâng đỡ cổ vai gáy khi ngồi làm việc và du lịch', 'goi_co.svg', [
        ['Xanh navy', 'Tiêu chuẩn', 110000, 40],
        ['Xám nhạt', 'Tiêu chuẩn', 110000, 35]
    ]]
];

$variantIdCounter = 1;
$variantPriceMap = [];
for ($pid = 1; $pid <= count($productList); $pid++) {
    $p = $productList[$pid - 1];
    $sql[] = sprintf(
        "INSERT INTO products (id, name, category, description, image_url, is_active) VALUES (%d, '%s', '%s', '%s', '%s', 1);",
        $pid, addslashes($p[0]), addslashes($p[1]), addslashes($p[2]), $p[3]
    );
    foreach ($p[4] as $idx => $v) {
        $sku = sprintf("SKU-P%02d-V%02d", $pid, $idx + 1);
        $attrJson = json_encode(['option1' => $v[0], 'option2' => $v[1]], JSON_UNESCAPED_UNICODE);
        $sql[] = sprintf(
            "INSERT INTO variants (id, product_id, sku, attributes, price, stock_quantity) VALUES (%d, %d, '%s', '%s', %.2f, %d);",
            $variantIdCounter, $pid, $sku, addslashes($attrJson), $v[2], $v[3]
        );
        $variantPriceMap[$variantIdCounter] = [
            'name' => $p[0],
            'info' => "{$v[0]} - {$v[1]}",
            'price' => $v[2]
        ];
        $variantIdCounter++;
    }
}
$totalVariants = $variantIdCounter - 1;

// 3. TẠO 100 ĐƠN HÀNG MẪU (Bao quát mọi trường hợp)
$sql[] = "\n-- 3. SEED 100 ĐƠN HÀNG MẪU";
$orderStatuses = [
    // 40 đơn DELIVERED
    'DELIVERED' => 40,
    // 15 đơn SHIPPED
    'SHIPPED' => 15,
    // 10 đơn PROCESSING / PAID
    'PAID' => 10,
    // 15 đơn PENDING (đang giữ hàng)
    'PENDING' => 15,
    // 10 đơn EXPIRED (quá hạn giữ hàng)
    'EXPIRED' => 10,
    // 5 đơn CANCELLED (hủy bởi khách)
    'CANCELLED' => 5,
    // 5 đơn PARTIALLY_RETURNED (đổi trả 1 phần)
    'PARTIALLY_RETURNED' => 5
];

$orderId = 1;
$orderItemId = 1;
$returnId = 1;
$returnItemId = 1;

foreach ($orderStatuses as $status => $count) {
    for ($c = 0; $c < $count; $c++) {
        $orderCode = sprintf("ORD-%04d", $orderId);
        $userId = (($orderId - 1) % 10) + 1; // Khách hàng 1 đến 10
        $phone = "098" . str_pad((string)$userId, 7, '0', STR_PAD_LEFT);
        $address = "Số {$orderId}, Đường Giải Phóng, Hà Nội";
        
        // Chọn ngẫu nhiên 1 hoặc 2 sản phẩm
        $numItems = ($orderId % 3 === 0) ? 2 : 1;
        $chosenVariants = [];
        $totalAmount = 0;
        
        for ($k = 0; $k < $numItems; $k++) {
            $vId = (($orderId + $k * 7) % $totalVariants) + 1;
            $qty = 1 + (($orderId + $k) % 2); // 1 hoặc 2
            $vInfo = $variantPriceMap[$vId];
            $subtotal = $vInfo['price'] * $qty;
            $totalAmount += $subtotal;
            $chosenVariants[] = [
                'variant_id' => $vId,
                'name' => $vInfo['name'],
                'info' => $vInfo['info'],
                'qty' => $qty,
                'price' => $vInfo['price'],
                'subtotal' => $subtotal
            ];
        }
        
        $expiresAt = ($status === 'PENDING') ? "DATE_ADD(NOW(), INTERVAL 15 MINUTE)" : "DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $expiresAtSql = ($expiresAt === 'NULL') ? "NULL" : $expiresAt;
        
        $sql[] = sprintf(
            "INSERT INTO orders (id, order_code, user_id, status, total_amount, shipping_address, customer_phone, expires_at, created_at) " .
            "VALUES (%d, '%s', %d, '%s', %.2f, '%s', '%s', %s, DATE_SUB(NOW(), INTERVAL %d HOUR));",
            $orderId, $orderCode, $userId, $status, $totalAmount, $address, $phone, $expiresAtSql, (101 - $orderId)
        );
        
        // Thêm order_items
        $savedItems = [];
        foreach ($chosenVariants as $cv) {
            $delivQty = in_array($status, ['DELIVERED', 'PARTIALLY_RETURNED']) ? $cv['qty'] : 0;
            $retQty = ($status === 'PARTIALLY_RETURNED') ? 1 : 0; // Trả 1 cái
            $sql[] = sprintf(
                "INSERT INTO order_items (id, order_id, variant_id, product_name, variant_info, quantity, unit_price, subtotal, delivered_quantity, returned_quantity) " .
                "VALUES (%d, %d, %d, '%s', '%s', %d, %.2f, %.2f, %d, %d);",
                $orderItemId, $orderId, $cv['variant_id'], addslashes($cv['name']), addslashes($cv['info']), $cv['qty'], $cv['price'], $cv['subtotal'], $delivQty, $retQty
            );
            $savedItems[] = [
                'order_item_id' => $orderItemId,
                'variant_id' => $cv['variant_id'],
                'qty' => $cv['qty'],
                'price' => $cv['price'],
                'returned_qty' => $retQty
            ];
            $orderItemId++;
        }
        
        // Xử lý reservations
        if ($status === 'PENDING') {
            foreach ($savedItems as $si) {
                $sql[] = sprintf(
                    "INSERT INTO reservations (order_id, variant_id, quantity, status, expires_at) VALUES (%d, %d, %d, 'HOLDING', DATE_ADD(NOW(), INTERVAL 15 MINUTE));",
                    $orderId, $si['variant_id'], $si['qty']
                );
            }
        } elseif (in_array($status, ['PAID', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'PARTIALLY_RETURNED'])) {
            foreach ($savedItems as $si) {
                $sql[] = sprintf(
                    "INSERT INTO reservations (order_id, variant_id, quantity, status, expires_at) VALUES (%d, %d, %d, 'CONSUMED', DATE_SUB(NOW(), INTERVAL 2 HOUR));",
                    $orderId, $si['variant_id'], $si['qty']
                );
            }
            // Thêm payment_events
            $txCode = sprintf("TXN-PAY-%04d", $orderId);
            $sql[] = sprintf(
                "INSERT INTO payment_events (order_id, transaction_code, amount, status, gateway_name, raw_payload) VALUES (%d, '%s', %.2f, 'SUCCESS', 'VNPAY_MOCK', '{\"status\": \"OK\", \"code\": \"00\"}');",
                $orderId, $txCode, $totalAmount
            );
        } elseif (in_array($status, ['CANCELLED', 'EXPIRED'])) {
            foreach ($savedItems as $si) {
                $sql[] = sprintf(
                    "INSERT INTO reservations (order_id, variant_id, quantity, status, expires_at, released_at) VALUES (%d, %d, %d, 'RELEASED', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW());",
                    $orderId, $si['variant_id'], $si['qty']
                );
            }
        }
        
        // Xử lý PARTIALLY_RETURNED
        if ($status === 'PARTIALLY_RETURNED') {
            $retCode = sprintf("RET-%04d", $returnId);
            $targetItem = $savedItems[0];
            $refundAmount = $targetItem['price'] * 1; // hoàn tiền cho 1 cái
            $sql[] = sprintf(
                "INSERT INTO returns (id, return_code, order_id, user_id, status, reason, total_refund_amount, inspector_id, inspector_note) " .
                "VALUES (%d, '%s', %d, %d, 'COMPLETED', 'Sản phẩm hơi rộng so với kích thước cá nhân', %.2f, 21, 'Đã kiểm tra tem mác nguyên vẹn, duyệt hoàn kho');",
                $returnId, $retCode, $orderId, $userId, $refundAmount
            );
            $sql[] = sprintf(
                "INSERT INTO return_items (return_id, order_item_id, quantity, unit_price, subtotal_refund, item_condition) " .
                "VALUES (%d, %d, 1, %.2f, %.2f, 'GOOD');",
                $returnId, $targetItem['order_item_id'], $targetItem['price'], $refundAmount
            );
            $returnId++;
        }
        
        // Thêm audit log
        $sql[] = sprintf(
            "INSERT INTO order_audit_logs (order_id, actor_role, action, old_state, new_state, amount_change, note) " .
            "VALUES (%d, 'SYSTEM', 'ORDER_CREATED', NULL, '%s', %.2f, 'Đơn hàng được khởi tạo');",
            $orderId, $status, $totalAmount
        );
        
        $orderId++;
    }
}

// Thêm một số payment event trùng / trễ để test báo cáo đối soát
$sql[] = "\n-- 4. SEED PAYMENT EVENTS BẤT THƯỜNG (DÀNH CHO ĐỐI SOÁT & PHÉP THỬ)";
$sql[] = "INSERT INTO payment_events (order_id, transaction_code, amount, status, gateway_name, raw_payload) VALUES (66, 'TXN-DUPLICATE-MOCK-66', 150000, 'SUCCESS', 'MOCK_PAY', '{\"note\": \"Thử nghiệm gửi webhook lặp\"}');";
$sql[] = "INSERT INTO payment_events (order_id, transaction_code, amount, status, gateway_name, raw_payload) VALUES (76, 'TXN-LATE-MOCK-76', 280000, 'LATE_IGNORED', 'MOCK_PAY', '{\"note\": \"Thanh toán đến sau khi đơn đã hết hạn 1 ngày\"}');";

file_put_contents(__DIR__ . '/seed.sql', implode("\n", $sql));
echo "SUCCESS_GENERATED_SEED: " . count($sql) . " statements\n";

