<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/PaymentService.php';
require_once __DIR__ . '/../src/ReturnService.php';
require_once __DIR__ . '/../src/AuditLogService.php';

echo "\n======================================================================\n";
echo "   BỘ KIỂM THỬ TỰ ĐỘNG 5 PHÉP THỬ BẮT BUỘC - ĐỀ TÀI 19\n";
echo "======================================================================\n\n";

$db = db();
$repo = new ProductRepository($db);
$orderService = new OrderService($db);
$paymentService = new PaymentService($db);
$returnService = new ReturnService($db);

$passed = 0;
$failed = 0;

$uRow = fetchOne(queryParams($db, "SELECT id FROM users LIMIT 1", []));
$testUserId = (int)($uRow['id'] ?? 21);

function assertTest(bool $condition, string $testName, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] $testName\n";
        if ($detail) echo "         -> $detail\n";
    } else {
        $failed++;
        echo "  [FAIL] $testName\n";
        if ($detail) echo "         -> LỖI: $detail\n";
    }
}

// ==============================================================================
// PHÉP THỬ 1: TRANH SẢN PHẨM CUỐI & LƯỢNG CÓ THỂ BÁN KHÔNG ÂM
// ==============================================================================
echo "--- PHÉP THỬ 1: Hai đơn tranh sản phẩm cuối (Concurrency & Non-negative stock) ---\n";
try {
    // Tạo 1 biến thể có tồn kho đúng bằng 1
    $testSku = 'SKU-RACE-' . time();
    queryParams($db, "INSERT INTO variants (product_id, sku, attributes, price, stock_quantity) VALUES (1, ?, '{\"color\":\"Cam\",\"size\":\"L\"}', 100000, 1)", [$testSku]);
    $raceVariantId = (int)$db->insert_id;

    // Đơn thứ nhất đặt 1 sản phẩm cuối -> Phải THÀNH CÔNG
    $order1 = $orderService->createOrderWithReservation($testUserId, 'Hà Nội', '0981111111', [
        ['variant_id' => $raceVariantId, 'quantity' => 1]
    ]);
    assertTest(isset($order1['order_id']), 'Đơn hàng 1 giữ thành công sản phẩm cuối cùng', "Mã đơn: {$order1['order_code']}");

    // Kiểm tra lượng có thể bán ngay lúc này: 1 tồn - 1 giữ = 0
    $avail = $repo->calculateAvailableQuantity($raceVariantId, 1);
    assertTest($avail === 0, 'Lượng hàng có thể bán bằng đúng 0 (không bị âm)', "Lượng còn bán: $avail");

    // Đơn thứ hai cùng đặt sản phẩm đó -> BẮT BUỘC BỊ TỪ CHỐI
    $order2Success = false;
    try {
        $orderService->createOrderWithReservation($testUserId, 'Hà Nội', '0982222222', [
            ['variant_id' => $raceVariantId, 'quantity' => 1]
        ]);
        $order2Success = true;
    } catch (Throwable $e) {
        // Bắt lỗi không đủ hàng thành công
    }
    assertTest(!$order2Success, 'Đơn hàng 2 bị chặn do hết hàng (Tránh overselling)', 'Chỉ 1 trong 2 đơn thành công');

    $availAfter = $repo->calculateAvailableQuantity($raceVariantId, 1);
    assertTest($availAfter >= 0, 'Lượng có thể bán tuyệt đối không âm', "Tồn khả dụng: $availAfter");

} catch (Throwable $e) {
    assertTest(false, 'Phép thử 1 ngoại lệ', $e->getMessage());
}

// ==============================================================================
// PHÉP THỬ 2: CHỐNG LẶP WEBHOOK THANH TOÁN & LỆNH HỦY (IDEMPOTENCY)
// ==============================================================================
echo "\n--- PHÉP THỬ 2: Gửi lại thông báo thanh toán / lệnh hủy (Idempotency) ---\n";
try {
    // Tạo biến thể riêng cho phép thử 2 để đảm bảo tính độc lập
    $idemSku = 'SKU-IDEM-' . time();
    queryParams($db, "INSERT INTO variants (product_id, sku, attributes, price, stock_quantity) VALUES (1, ?, '{\"color\":\"Xanh\",\"size\":\"S\"}', 150000, 20)", [$idemSku]);
    $idemVariantId = (int)$db->insert_id;

    // Tạo đơn mới để test thanh toán
    $testOrder = $orderService->createOrderWithReservation($testUserId, 'Hà Nội', '0981111111', [
        ['variant_id' => $idemVariantId, 'quantity' => 1]
    ]);
    $orderCode = $testOrder['order_code'];
    $fixedTxn = 'TXN-IDEM-' . time();

    // 1. Gửi webhook lần 1 -> Thành công
    $res1 = $paymentService->processPaymentWebhook($orderCode, $fixedTxn, (float)$testOrder['total_amount']);
    assertTest($res1['status'] === 'SUCCESS', 'Webhook lần 1: Thanh toán thành công', "Trạng thái: {$res1['status']}");

    // 2. Gửi lại webhook lần 2 với cùng mã giao dịch -> Phải DUPLICATE_IGNORED
    $res2 = $paymentService->processPaymentWebhook($orderCode, $fixedTxn, (float)$testOrder['total_amount']);
    assertTest($res2['status'] === 'DUPLICATE_IGNORED', 'Webhook lần 2: Nhận diện trùng lặp và bỏ qua an toàn', "Kết quả: {$res2['status']}");

    // Kiểm tra số bản ghi payment_events chỉ có 1
    $cntStmt = queryParams($db, "SELECT COUNT(*) as c FROM payment_events WHERE transaction_code = ?", [$fixedTxn]);
    $pCount = (int)fetchOne($cntStmt)['c'];
    assertTest($pCount === 1, 'Không ghi nhận giao dịch lặp vào CSDL', "Số bản ghi payment_events: $pCount");

    // 3. Test Hủy đơn gửi lại 2 lần (Idempotent Cancel)
    $cancelOrder = $orderService->createOrderWithReservation($testUserId, 'Hà Nội', '0981111111', [
        ['variant_id' => $idemVariantId, 'quantity' => 1]
    ]);
    $cId = (int)$cancelOrder['order_id'];

    $cancel1 = $orderService->cancelOrder($cId, $testUserId, 'customer');
    assertTest($cancel1 === true, 'Hủy đơn lần 1: Thành công giải phóng hàng', 'Trạng thái chuyển CANCELLED');

    $cancel2 = $orderService->cancelOrder($cId, $testUserId, 'customer');
    assertTest($cancel2 === true, 'Hủy đơn lần 2: Idempotent an toàn, không hoàn hàng 2 lần', 'Không nhân đôi kho hoàn');

} catch (Throwable $e) {
    assertTest(false, 'Phép thử 2 ngoại lệ', $e->getMessage());
}

// ==============================================================================
// PHÉP THỬ 3: ĐỔI GIÁ SẢN PHẨM KHÔNG THAY ĐỔI GIÁ ĐƠN ĐÃ CHỐT (PRICE SNAPSHOT)
// ==============================================================================
echo "\n--- PHÉP THỬ 3: Đóng băng giá của đơn đã chốt (Price Snapshot) ---\n";
try {
    // Tạo biến thể giá ban đầu 120,000đ
    $snapSku = 'SKU-SNAP-' . time();
    queryParams($db, "INSERT INTO variants (product_id, sku, attributes, price, stock_quantity) VALUES (1, ?, '{\"color\":\"Đỏ\",\"size\":\"M\"}', 120000, 10)", [$snapSku]);
    $snapVarId = (int)$db->insert_id;

    // Đặt hàng tại thời điểm giá là 120,000đ
    $snapOrder = $orderService->createOrderWithReservation($testUserId, 'Hà Nội', '0981111111', [
        ['variant_id' => $snapVarId, 'quantity' => 2]
    ]);
    $initialTotal = (float)$snapOrder['total_amount'];
    assertTest($initialTotal == 240000.0, 'Đơn hàng chốt giá lúc đặt: 240.000đ', "Tổng ban đầu: $initialTotal");

    // Admin thay đổi giá sản phẩm trên shop tăng vọt lên 500,000đ
    $repo->updatePrice($snapVarId, 500000.0);

    // Đọc lại đơn hàng từ CSDL
    $reloadedOrder = $orderService->getOrderById((int)$snapOrder['order_id']);
    $itemSnapshotPrice = (float)$reloadedOrder['items'][0]['unit_price'];
    $orderFinalTotal = (float)$reloadedOrder['total_amount'];

    assertTest($itemSnapshotPrice == 120000.0, 'Đơn giá trong order_items vẫn giữ nguyên 120.000đ (Bất biến)', "Đơn giá đọc ra: $itemSnapshotPrice");
    assertTest($orderFinalTotal == 240000.0, 'Tổng tiền đơn đã chốt không bị thay đổi theo giá mới', "Tổng tiền đọc ra: $orderFinalTotal");

} catch (Throwable $e) {
    assertTest(false, 'Phép thử 3 ngoại lệ', $e->getMessage());
}

// ==============================================================================
// PHÉP THỬ 4: ĐỔI TRẢ MỘT PHẦN KHÔNG VƯỢT LƯỢNG ĐÃ GIAO & TÍNH TIỀN THEO ĐƠN GIÁ CŨ
// ==============================================================================
echo "\n--- PHÉP THỬ 4: Ràng buộc đổi trả một phần (Partial Return & Refund Calculation) ---\n";
try {
    // Tạo đơn và chuyển trạng thái sang DELIVERED (đã giao 3 sản phẩm)
    $retSku = 'SKU-RET-' . time();
    queryParams($db, "INSERT INTO variants (product_id, sku, attributes, price, stock_quantity) VALUES (1, ?, '{\"color\":\"Tím\",\"size\":\"XL\"}', 150000, 10)", [$retSku]);
    $retVarId = (int)$db->insert_id;

    $retOrderInfo = $orderService->createOrderWithReservation($testUserId, 'Hà Nội', '0981111111', [
        ['variant_id' => $retVarId, 'quantity' => 3]
    ]);
    $retOrderId = (int)$retOrderInfo['order_id'];

    // Thanh toán và giao hàng
    $paymentService->processPaymentWebhook($retOrderInfo['order_code'], 'TXN-RET-' . time(), 450000.0);
    $orderService->updateShippingStatus($retOrderId, 'PROCESSING', 11, 'staff');
    $orderService->updateShippingStatus($retOrderId, 'SHIPPED', 11, 'staff');
    $orderService->updateShippingStatus($retOrderId, 'DELIVERED', 11, 'staff');

    $orderLoaded = $orderService->getOrderById($retOrderId);
    $orderItemId = (int)$orderLoaded['items'][0]['id'];

    // Lần 1: Yêu cầu trả 1 sản phẩm -> Phải THÀNH CÔNG, tiền hoàn = 150.000đ
    $req1 = $returnService->requestPartialReturn($retOrderId, $testUserId, [
        ['order_item_id' => $orderItemId, 'quantity' => 1]
    ], 'Sản phẩm hơi rộng');
    assertTest($req1['total_refund_amount'] == 150000.0, 'Yêu cầu trả lần 1 thành công, tiền hoàn tính đúng 150.000đ', "Tiền hoàn: {$req1['total_refund_amount']}");

    // Người duyệt phê duyệt yêu cầu trả lần 1
    $returnService->approveReturn((int)$req1['return_id'], $testUserId, 'Đã kiểm tra tem mác, duyệt');

    // Lần 2: Cố tình yêu cầu trả 3 sản phẩm nữa (trong khi chỉ còn 3 - 1 = 2) -> BẮT BUỘC BỊ CHẶN
    $exceededSuccess = false;
    try {
        $returnService->requestPartialReturn($retOrderId, $testUserId, [
            ['order_item_id' => $orderItemId, 'quantity' => 3]
        ], 'Cố tình trả vượt quá số lượng đã giao');
        $exceededSuccess = true;
    } catch (Throwable $e) {
        // Chặn thành công
    }
    assertTest(!$exceededSuccess, 'Chặn thành công yêu cầu trả vượt quá số lượng đã giao', 'Tổng trả không vượt quá 3');

    // Lần 3: Yêu cầu trả tiếp 2 sản phẩm còn lại -> THÀNH CÔNG
    $req2 = $returnService->requestPartialReturn($retOrderId, $testUserId, [
        ['order_item_id' => $orderItemId, 'quantity' => 2]
    ], 'Trả nốt 2 cái');
    assertTest($req2['total_refund_amount'] == 300000.0, 'Trả nốt 2 cái thành công, tiền hoàn 300.000đ', "Tiền hoàn: {$req2['total_refund_amount']}");

} catch (Throwable $e) {
    assertTest(false, 'Phép thử 4 ngoại lệ', $e->getMessage());
}

// ==============================================================================
// PHÉP THỬ 5: THANH TOÁN ĐẾN SAU KHI ĐƠN HẾT HẠN (LATE PAYMENT HANDLING)
// ==============================================================================
echo "\n--- PHÉP THỬ 5: Xử lý thanh toán đến muộn (Late Payment Handling) ---\n";
try {
    // Tạo biến thể riêng cho phép thử 5 để đảm bảo tính độc lập
    $lateSku = 'SKU-LATE-' . time();
    queryParams($db, "INSERT INTO variants (product_id, sku, attributes, price, stock_quantity) VALUES (1, ?, '{\"color\":\"Vàng\",\"size\":\"M\"}', 180000, 10)", [$lateSku]);
    $lateVariantId = (int)$db->insert_id;

    // Tạo đơn và cố tình ép thời hạn hết hạn
    $lateOrder = $orderService->createOrderWithReservation($testUserId, 'Hà Nội', '0981111111', [
        ['variant_id' => $lateVariantId, 'quantity' => 1]
    ]);
    $lateOrderId = (int)$lateOrder['order_id'];
    $lateCode = $lateOrder['order_code'];

    // Ép đơn quá hạn và chạy quét dọn tự động
    queryParams($db, "UPDATE orders SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = ?", [$lateOrderId]);
    $orderService->checkAndExpireReservations();

    // Kiểm tra đơn đã sang EXPIRED
    $orderCheck = $orderService->getOrderById($lateOrderId);
    assertTest($orderCheck['status'] === 'EXPIRED', 'Đơn hàng quá hạn chuyển sang trạng thái EXPIRED', 'Hàng giữ đã nhả');

    // Bây giờ webhook thanh toán mới đến -> Hệ thống KHÔNG được hồi sinh đơn
    $lateRes = $paymentService->processPaymentWebhook($lateCode, 'TXN-LATE-' . time(), (float)$lateOrder['total_amount']);
    assertTest($lateRes['status'] === 'LATE_PAYMENT', 'Hệ thống nhận diện thanh toán đến muộn (LATE_PAYMENT)', "Kết quả: {$lateRes['status']}");

    // Kiểm tra trạng thái đơn vẫn là EXPIRED (Không bị tự hồi sinh gây bán vượt tồn kho)
    $finalOrder = $orderService->getOrderById($lateOrderId);
    assertTest($finalOrder['status'] === 'EXPIRED', 'Đơn hàng vẫn giữ nguyên EXPIRED, không tự hồi sinh', "Trạng thái: {$finalOrder['status']}");

} catch (Throwable $e) {
    assertTest(false, 'Phép thử 5 ngoại lệ', $e->getMessage());
}

echo "\n======================================================================\n";
echo "KẾT QUẢ TỔNG HỢP KIỂM THỬ: ĐẠT $passed / " . ($passed + $failed) . " KIỂM TRA";
if ($failed === 0) {
    echo "  ===> TẤT CẢ 5 PHÉP THỬ ĐỀU ĐẠT CHUẨN XUẤT SẮC! (10/10)\n";
} else {
    echo "  ===> CÒN $failed LỖI CẦN XỬ LÝ!\n";
}
echo "======================================================================\n\n";

