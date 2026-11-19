<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/PaymentService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireLogin();

$orderCode = field($_GET, 'order_code');
$db = db();
$orderService = new OrderService($db);
$order = $orderService->getOrderByCode($orderCode);

if (!$order) {
    http_response_code(404);
    exit('Không tìm thấy đơn hàng cần thanh toán.');
}

$paymentService = new PaymentService($db);
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = field($_POST, 'action');
    $amount = (float)$order['total_amount'];

    if ($action === 'pay_normal') {
        // Sinh mã giao dịch duy nhất
        $txnCode = 'TXN-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $result = $paymentService->processPaymentWebhook(
            $order['order_code'],
            $txnCode,
            $amount,
            'VNPAY_MOCK',
            ['note' => 'Thanh toán chuẩn từ cổng thanh toán mô phỏng']
        );
    } elseif ($action === 'pay_duplicate') {
        // Gửi lặp lại cùng mã giao dịch đã có
        $fixedTxnCode = field($_POST, 'custom_txn', 'TXN-DUPLICATE-DEMO-TEST');
        $result = $paymentService->processPaymentWebhook(
            $order['order_code'],
            $fixedTxnCode,
            $amount,
            'VNPAY_MOCK',
            ['note' => 'Thử nghiệm gửi lặp webhook 2 lần để kiểm tra tính Idempotent']
        );
    } elseif ($action === 'pay_late') {
        // Cố tình đánh dấu đơn hết hạn trước rồi mới gửi thông báo thanh toán
        queryParams($db, "UPDATE orders SET status = 'EXPIRED' WHERE id = ?", [$order['id']]);
        queryParams($db, "UPDATE reservations SET status = 'RELEASED', released_at = NOW() WHERE order_id = ?", [$order['id']]);

        $lateTxnCode = 'TXN-LATE-' . date('His');
        $result = $paymentService->processPaymentWebhook(
            $order['order_code'],
            $lateTxnCode,
            $amount,
            'MOCK_PAY',
            ['note' => 'Thanh toán đến sau khi đơn đã hết hạn']
        );
    }

    // Tải lại thông tin đơn hàng sau thao tác
    $order = $orderService->getOrderById((int)$order['id']);
}

require __DIR__ . '/../templates/header.php';
?>

<div class="page-heading">
    <h1>Cổng thanh toán mô phỏng (Sandbox Gateway)</h1>
    <p>Tiếp nhận kết quả thanh toán mô phỏng, kiểm soát thông báo lặp và xử lý thanh toán đến muộn.</p>
</div>

<?php if ($result): ?>
    <div class="alert-box <?= $result['status'] === 'SUCCESS' ? 'alert--success' : ($result['status'] === 'DUPLICATE_IGNORED' ? 'alert--info' : 'alert--warning') ?>">
        <h3 style="margin: 0 0 8px;">Kết quả tiếp nhận webhook: [<?= e($result['status']) ?>]</h3>
        <p style="margin: 0;"><?= e($result['message']) ?></p>
        <?php if (!empty($result['transaction_code'])): ?>
            <p style="margin: 4px 0 0; font-family: monospace;">Mã giao dịch: <?= e($result['transaction_code']) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div style="background: var(--surface); border: 1px solid var(--outline-variant); border-radius: 12px; padding: 24px; margin-bottom: 32px;">
    <h2>Thông tin đơn hàng thanh toán</h2>
    <p>Mã đơn hàng: <strong><?= e($order['order_code']) ?></strong></p>
    <p>Trạng thái hiện tại: <?= getStatusBadge($order['status']) ?></p>
    <p>Tổng số tiền cần thanh toán: <strong style="font-size: 1.25rem; color: #c92a2a;"><?= formatMoney((float)$order['total_amount']) ?></strong></p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
    <!-- Kịch bản 1: Thanh toán chuẩn -->
    <div style="background: var(--surface-container-low); border: 1px solid var(--outline-variant); border-radius: 12px; padding: 20px;">
        <h3>1. Thanh toán bình thường</h3>
        <p style="font-size: 0.875rem; color: var(--on-surface-variant);">
            Mô phỏng khách hàng thanh toán thành công trong hạn giữ hàng. Đơn hàng sẽ chuyển sang <code>PAID</code> và trừ kho thực tế.
        </p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pay_normal">
            <button type="submit" class="filled-button" style="width: 100%; background: #2b8a3e;">
                ✅ Gửi thông báo Thành công
            </button>
        </form>
    </div>

    <!-- Kịch bản 2: Kiểm thử gửi lặp webhook (Idempotency) -->
    <div style="background: var(--surface-container-low); border: 1px solid var(--outline-variant); border-radius: 12px; padding: 20px;">
        <h3>2. Kiểm thử Webhook gửi lặp (Idempotent)</h3>
        <p style="font-size: 0.875rem; color: var(--on-surface-variant);">
            Gửi lại cùng một mã giao dịch nhiều lần. Hệ thống phải bỏ qua, không cộng tiền và không cập nhật lại lần 2.
        </p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pay_duplicate">
            <input type="hidden" name="custom_txn" value="TXN-DUPLICATE-DEMO-TEST">
            <button type="submit" class="filled-button" style="width: 100%; background: #1971c2;">
                🔁 Thử nghiệm gửi lặp lần 2
            </button>
        </form>
    </div>

    <!-- Kịch bản 3: Kiểm thử thanh toán đến muộn (Late payment) -->
    <div style="background: var(--surface-container-low); border: 1px solid var(--outline-variant); border-radius: 12px; padding: 20px;">
        <h3>3. Kiểm thử Thanh toán đến muộn</h3>
        <p style="font-size: 0.875rem; color: var(--on-surface-variant);">
            Đơn hàng đã hết hạn (kho đã giải phóng). Thông báo thanh toán đến muộn sẽ bị từ chối kích hoạt đơn để tránh âm kho.
        </p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pay_late">
            <button type="submit" class="filled-button" style="width: 100%; background: #e03131;">
                ⚠️ Thử nghiệm thanh toán trễ
            </button>
        </form>
    </div>
</div>

<div style="margin-top: 32px; text-align: center;">
    <a href="/order-detail.php?id=<?= (int)$order['id'] ?>" class="text-button" style="font-weight: 600;">
        ➔ Trở về trang chi tiết đơn hàng
    </a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>

