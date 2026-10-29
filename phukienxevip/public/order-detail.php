<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/AuditLogService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireLogin();
$user = currentUser();

$orderId = fieldInt($_GET, 'id');
$db = db();
$orderService = new OrderService($db);
$orderService->checkAndExpireReservations();

$order = $orderService->getOrderById($orderId);
if (!$order) {
    http_response_code(404);
    exit('Không tìm thấy đơn hàng.');
}

// Kiểm tra quyền
if ($user['role'] === 'customer' && (int)$order['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    exit('FORBIDDEN: Bạn không có quyền truy cập đơn hàng này.');
}

// Xử lý hành động Hủy đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    checkCsrf($_POST);
    try {
        $orderService->cancelOrder($orderId, (int)$user['id'], $user['role']);
        setFlash('success', 'Đơn hàng đã được hủy thành công và giải phóng lượng hàng đã giữ.');
        header("Location: /order-detail.php?id=$orderId");
        exit;
    } catch (Throwable $e) {
        setFlash('danger', 'Lỗi hủy đơn: ' . $e->getMessage());
    }
}

// Xử lý chuyển trạng thái giao hàng (Staff/Manager)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_shipping') {
    checkCsrf($_POST);
    requireRole(['staff', 'manager']);
    $newStatus = field($_POST, 'new_status');
    try {
        $orderService->updateShippingStatus($orderId, $newStatus, (int)$user['id'], $user['role']);
        setFlash('success', "Đã cập nhật trạng thái đơn hàng sang '$newStatus'.");
        header("Location: /order-detail.php?id=$orderId");
        exit;
    } catch (Throwable $e) {
        setFlash('danger', 'Lỗi cập nhật: ' . $e->getMessage());
    }
}

$auditService = new AuditLogService($db);
$logs = $auditService->getLogsForOrder($orderId);

require __DIR__ . '/../templates/header.php';
?>

<!-- BẬC THANG TIẾN TRÌNH ĐƠN HÀNG PHONG CÁCH SÀN TMĐT (SHOPEE STEPPER) -->
<?php
$status = $order['status'];
$steps = [
    ['code' => 'CREATED', 'label' => 'Đã đặt hàng', 'num' => 1],
    ['code' => 'PENDING', 'label' => 'Giữ hàng (15p)', 'num' => 2],
    ['code' => 'PAID', 'label' => 'Đã thanh toán', 'num' => 3],
    ['code' => 'SHIPPED', 'label' => 'Đang giao hàng', 'num' => 4],
    ['code' => 'DELIVERED', 'label' => 'Đã giao thành công', 'num' => 5],
    ['code' => 'RETURNED', 'label' => 'Đổi trả / Hoàn tiền', 'num' => 6]
];

$stepWeight = [
    'PENDING' => 2,
    'PAID' => 3,
    'PROCESSING' => 3,
    'SHIPPED' => 4,
    'DELIVERED' => 5,
    'PARTIALLY_RETURNED' => 6,
    'RETURNED' => 6,
    'CANCELLED' => 0,
    'EXPIRED' => 0
];
$currentWeight = $stepWeight[$status] ?? 1;
?>

<!-- THÔNG BÁO TÌNH TRẠNG DUYỆT ĐỔI TRẢ DÀNH CHO KHÁCH HÀNG -->
<?php if (!empty($order['returns'])): ?>
    <?php foreach ($order['returns'] as $retNotice): ?>
        <?php if ($retNotice['status'] === 'COMPLETED'): ?>
            <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 1px solid #81c784; border-left: 6px solid #2e7d32; border-radius: 8px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(46,125,50,0.15); display: flex; align-items: flex-start; gap: 16px;">
                <div style="font-size: 2rem; line-height: 1;">🎉</div>
                <div style="flex: 1;">
                    <div style="font-size: 1.05rem; font-weight: 700; color: #1b5e20; margin-bottom: 4px;">
                        THÔNG BÁO: Yêu cầu đổi trả mã <?= e($retNotice['return_code']) ?> đã được QUẢN TRỊ VIÊN PHÊ DUYỆT!
                    </div>
                    <div style="font-size: 0.9rem; color: #2e7d32; line-height: 1.6;">
                        • <strong>Số tiền hoàn trả mô phỏng:</strong> <span style="font-size: 1.1rem; font-weight: 800; color: #b71c1c;"><?= formatMoney((float)$retNotice['total_refund_amount']) ?></span> (đã được ghi nhận vào tài khoản)<br>
                        • <strong>Ghi chú người thẩm định:</strong> <em>"<?= e($retNotice['inspector_note'] ?? 'Đã xác minh và hoàn tiền thành công.') ?>"</em><br>
                        • <strong>Thời gian cập nhật:</strong> <?= formatDate($retNotice['updated_at'] ?? $retNotice['created_at']) ?>
                    </div>
                </div>
            </div>
        <?php elseif ($retNotice['status'] === 'REJECTED'): ?>
            <div style="background: #ffebee; border: 1px solid #ef9a9a; border-left: 6px solid #c62828; border-radius: 8px; padding: 18px 20px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 16px;">
                <div style="font-size: 2rem; line-height: 1;">❌</div>
                <div style="flex: 1;">
                    <div style="font-size: 1.05rem; font-weight: 700; color: #b71c1c; margin-bottom: 4px;">
                        THÔNG BÁO: Yêu cầu đổi trả mã <?= e($retNotice['return_code']) ?> đã bị TỪ CHỐI
                    </div>
                    <div style="font-size: 0.9rem; color: #c62828;">
                        • <strong>Lý do từ chối:</strong> <em>"<?= e($retNotice['inspector_note'] ?? 'Sản phẩm không đáp ứng đủ tiêu chuẩn đổi trả.') ?>"</em>
                    </div>
                </div>
            </div>
        <?php elseif ($retNotice['status'] === 'REQUESTED'): ?>
            <div style="background: #e1f5fe; border: 1px solid #81d4fa; border-left: 6px solid #0288d1; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 16px;">
                <div style="font-size: 2rem; line-height: 1;">⏳</div>
                <div style="flex: 1;">
                    <div style="font-size: 1.05rem; font-weight: 700; color: #01579b; margin-bottom: 4px;">
                        Yêu cầu đổi trả mã <?= e($retNotice['return_code']) ?> đang chờ Thẩm định
                    </div>
                    <div style="font-size: 0.875rem; color: #0277bd;">
                        Hệ thống đã tiếp nhận yêu cầu đổi trả của bạn (Số tiền đề xuất hoàn: <strong><?= formatMoney((float)$retNotice['total_refund_amount']) ?></strong>). Quản lý đang kiểm tra tình trạng hàng hóa.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0; padding-bottom: 12px; margin-bottom: 16px;">
        <span style="font-size: 0.875rem; color: #6c757d;">
            MÃ ĐƠN HÀNG: <strong style="color: #212529;"><?= e($order['order_code']) ?></strong>
        </span>
        <span style="font-size: 0.875rem;">
            TRẠNG THÁI: <?= getStatusBadge($order['status']) ?>
        </span>
    </div>

    <!-- Stepper Graphic -->
    <?php if (!in_array($status, ['CANCELLED', 'EXPIRED'])): ?>
        <div class="order-stepper">
            <?php foreach ($steps as $st): ?>
                <?php 
                    $isDone = $currentWeight > $st['num'];
                    $isActive = $currentWeight === $st['num'];
                    $cls = $isDone ? 'completed' : ($isActive ? 'active' : '');
                ?>
                <div class="step-item <?= $cls ?>">
                    <div class="step-icon"><?= $isDone ? '✓' : $st['num'] ?></div>
                    <div class="step-label"><?= $st['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert-box alert--danger" style="text-align: center; margin: 10px 0;">
            ⚠️ Đơn hàng này đang ở trạng thái <strong><?= $status === 'CANCELLED' ? 'ĐÃ HỦY' : 'HẾT HẠN GIỮ HÀNG' ?></strong>. Lượng hàng đã được giải phóng về kho.
        </div>
    <?php endif; ?>
</div>

<!-- ĐỒNG HỒ ĐẾM NGƯỢC GIỮ HÀNG NẾU ĐƠN ĐANG PENDING -->
<?php if ($order['status'] === 'PENDING' && $order['expires_at']): ?>
    <?php 
        $expiresTs = strtotime($order['expires_at']);
        $nowTs = time();
        $diffSec = max(0, $expiresTs - $nowTs);
    ?>
    <div class="timer-container">
        <div>
            <strong style="color: #b78103; font-size: 1.125rem;">⏱️ Lượng hàng đang được giữ chỗ độc quyền trong kho</strong>
            <p style="margin: 2px 0 0; font-size: 0.8125rem; color: #856404;">
                Vui lòng tiếp nhận thanh toán trước khi hết hạn. Nếu hết hạn, hệ thống tự động giải phóng tồn kho cho khách hàng khác.
            </p>
        </div>
        <div style="text-align: right;">
            <div id="countdown" class="timer-digits">--:--</div>
            <small style="color: #856404;">Hạn chót: <?= date('H:i:s', $expiresTs) ?></small>
        </div>
    </div>

    <script>
    (function() {
        var remaining = <?= $diffSec ?>;
        var el = document.getElementById('countdown');
        function update() {
            if (remaining <= 0) {
                el.innerText = '00:00 (Hết hạn)';
                return;
            }
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            el.innerText = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            remaining--;
        }
        update();
        setInterval(update, 1000);
    })();
    </script>
<?php endif; ?>

<!-- BẢNG CHI TIẾT SẢN PHẨM & CHỐT GIÁ (PRICE SNAPSHOT) -->
<div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ee4d2d; padding-bottom: 10px; margin-bottom: 16px;">
        <h2 style="margin: 0; font-size: 1.125rem; color: #ee4d2d;">
            🛍️ Sản phẩm trong đơn (Snapshot giá đã chốt)
        </h2>
        <span style="font-size: 0.8125rem; color: #6c757d;">
            Cam kết giá không đổi kể cả khi shop tăng giá sản phẩm
        </span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Biến thể</th>
                    <th>Đơn giá lúc mua (Snapshot)</th>
                    <th>Số lượng</th>
                    <th>Đã giao</th>
                    <th>Đã đổi trả</th>
                    <th>Tạm tính</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 12px;">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= e(productImageUrl($item['image_url'])) ?>" alt="<?= e($item['product_name']) ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px; border: 1px solid #eee;">
                            <?php endif; ?>
                            <div>
                                <strong><?= e($item['product_name']) ?></strong><br>
                                <small style="color: #6c757d; font-family: monospace;"><?= e($item['sku']) ?></small>
                            </div>
                        </td>
                        <td><?= e($item['variant_info']) ?></td>
                        <td><strong><?= formatMoney((float)$item['unit_price']) ?></strong></td>
                        <td><?= (int)$item['quantity'] ?></td>
                        <td><span style="color: #28a745; font-weight: 700;"><?= (int)$item['delivered_quantity'] ?></span></td>
                        <td><span style="color: #dc3545; font-weight: 700;"><?= (int)$item['returned_quantity'] ?></span></td>
                        <td style="font-weight: 700; color: #ee4d2d;"><?= formatMoney((float)$item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align: right; font-weight: 700; font-size: 1.125rem;">Tổng số tiền thanh toán:</td>
                    <td style="font-weight: 800; font-size: 1.375rem; color: #ee4d2d;"><?= formatMoney((float)$order['total_amount']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- KHU VỰC THAO TÁC / HÀNH ĐỘNG DỰA TRÊN TRẠNG THÁI -->
    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; align-items: center; justify-content: flex-end;">
        <?php if ($order['status'] === 'PENDING'): ?>
            <a href="/payment-mock.php?order_code=<?= urlencode($order['order_code']) ?>" class="filled-button" style="background: #28a745;">
                💳 Tiếp nhận thanh toán mô phỏng
            </a>
            
            <form method="post" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn hủy đơn hàng này? Hàng đang giữ sẽ được giải phóng ngay lập tức.')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="button-cancel">Hủy đơn hàng</button>
            </form>
        <?php endif; ?>

        <?php if (in_array($order['status'], ['DELIVERED', 'PARTIALLY_RETURNED']) && $user['role'] === 'customer'): ?>
            <a href="/return-request.php?order_id=<?= (int)$order['id'] ?>" class="filled-button" style="background: #d9480f;">
                🔄 Yêu cầu đổi trả một phần
            </a>
        <?php endif; ?>

        <a href="/orders.php" class="button-secondary" style="text-decoration: none; font-weight: 600;">
            ⬅ Quay lại Đơn hàng của tôi
        </a>
    </div>
</div>

<!-- LỊCH SỬ ĐỔI TRẢ NẾU CÓ -->
<?php if (!empty($order['returns'])): ?>
    <div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
        <h3 style="margin: 0 0 12px; color: #d9480f;">🔄 Lịch sử yêu cầu đổi trả</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Mã đổi trả</th>
                    <th>Ngày yêu cầu</th>
                    <th>Lý do</th>
                    <th>Tiền hoàn mô phỏng</th>
                    <th>Trạng thái</th>
                    <th>Ghi chú thẩm định</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['returns'] as $ret): ?>
                    <tr>
                        <td><strong><?= e($ret['return_code']) ?></strong></td>
                        <td><?= formatDate($ret['created_at']) ?></td>
                        <td><?= e($ret['reason']) ?></td>
                        <td style="font-weight: 700; color: #ee4d2d;"><?= formatMoney((float)$ret['total_refund_amount']) ?></td>
                        <td><?= getStatusBadge($ret['status']) ?></td>
                        <td><?= e($ret['inspector_note'] ?? 'Đang chờ thẩm định...') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- NHẬT KÝ ĐỐI SOÁT & VẾT KIỂM TOÁN HỆ THỐNG (AUDIT TRAIL) -->
<div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-sm);">
    <h3 style="margin: 0 0 6px; color: #004085;">📜 Vết kiểm toán biến động đơn hàng (Audit Trail)</h3>
    <p style="font-size: 0.8125rem; color: #6c757d; margin: 0 0 16px;">
        Ghi nhận minh bạch mọi biến động trạng thái, số lượng hàng và dòng tiền để phục vụ đối soát.
    </p>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Tác nhân</th>
                    <th>Hành động</th>
                    <th>Chuyển trạng thái</th>
                    <th>Biến động tiền</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= formatDate($l['created_at']) ?></td>
                        <td>
                            <strong><?= e($l['actor_role']) ?></strong>
                            <?= !empty($l['actor_name']) ? ' (' . e($l['actor_name']) . ')' : '' ?>
                        </td>
                        <td><code><?= e($l['action']) ?></code></td>
                        <td>
                            <?php if ($l['old_state'] || $l['new_state']): ?>
                                <?= e($l['old_state'] ?? 'NONE') ?> ➔ <strong><?= e($l['new_state'] ?? '') ?></strong>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((float)$l['amount_change'] != 0): ?>
                                <span style="font-weight: 700; color: <?= (float)$l['amount_change'] > 0 ? '#28a745' : '#dc3545' ?>;">
                                    <?= (float)$l['amount_change'] > 0 ? '+' : '' ?><?= formatMoney((float)$l['amount_change']) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= e($l['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
