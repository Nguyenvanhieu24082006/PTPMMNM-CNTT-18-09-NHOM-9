<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/ReturnService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireLogin();
$user = currentUser();

$orderId = fieldInt($_GET, 'order_id');
$db = db();
$orderService = new OrderService($db);
$order = $orderService->getOrderById($orderId);

if (!$order) {
    http_response_code(404);
    exit('Không tìm thấy đơn hàng.');
}

if ($user['role'] === 'customer' && (int)$order['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    exit('FORBIDDEN: Bạn không thể đổi trả đơn của người khác.');
}

if (!in_array($order['status'], ['DELIVERED', 'PARTIALLY_RETURNED'], true)) {
    setFlash('warning', 'Chỉ có thể yêu cầu đổi trả khi đơn hàng đã được giao thành công.');
    header("Location: /order-detail.php?id=$orderId");
    exit;
}

$returnService = new ReturnService($db);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $reason = field($_POST, 'reason');
    $quantities = $_POST['quantity'] ?? [];

    $itemsToReturn = [];
    foreach ($quantities as $oiId => $qty) {
        $qtyInt = (int)$qty;
        if ($qtyInt > 0) {
            $itemsToReturn[] = [
                'order_item_id' => (int)$oiId,
                'quantity' => $qtyInt
            ];
        }
    }

    try {
        $retResult = $returnService->requestPartialReturn(
            $orderId,
            (int)$user['id'],
            $itemsToReturn,
            $reason
        );

        setFlash('success', "Đã gửi yêu cầu đổi trả thành công ({$retResult['return_code']}). Tiền hoàn dự kiến: " . formatMoney($retResult['total_refund_amount']));
        header("Location: /order-detail.php?id=$orderId");
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../templates/header.php';
?>

<div class="page-heading">
    <h1>Yêu cầu đổi trả một phần</h1>
    <p>Chọn các sản phẩm và số lượng cần trả lại cho đơn hàng <strong><?= e($order['order_code']) ?></strong>.</p>
</div>

<?php if ($error): ?>
    <div class="alert-box alert--danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" style="background: var(--surface); border: 1px solid var(--outline-variant); border-radius: 12px; padding: 24px;">
    <?= csrfField() ?>

    <h2>Danh sách mặt hàng có thể đổi trả</h2>
    <p style="font-size: 0.875rem; color: var(--on-surface-variant); margin-bottom: 20px;">
        ⚠️ <strong>Ràng buộc kỹ thuật:</strong> Tổng số lượng trả qua các lần không được vượt quá số lượng đã giao. Tiền hoàn được tính chuẩn xác theo đơn giá đã chốt tại thời điểm mua.
    </p>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sản phẩm & Biến thể</th>
                    <th>Đơn giá gốc đã chốt</th>
                    <th>Đã giao</th>
                    <th>Đã trả trước đây</th>
                    <th>Còn có thể trả</th>
                    <th>Số lượng muốn trả</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $it): ?>
                    <?php 
                        $deliv = (int)$it['delivered_quantity'];
                        $ret = (int)$it['returned_quantity'];
                        $maxRet = max(0, $deliv - $ret);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($it['product_name']) ?></strong><br>
                            <small style="color: var(--on-surface-variant);"><?= e($it['variant_info']) ?></small>
                        </td>
                        <td><strong><?= formatMoney((float)$it['unit_price']) ?></strong></td>
                        <td><?= $deliv ?></td>
                        <td><?= $ret ?></td>
                        <td><span style="font-weight: 700; color: #155724;"><?= $maxRet ?></span></td>
                        <td>
                            <?php if ($maxRet > 0): ?>
                                <input type="number" name="quantity[<?= (int)$it['id'] ?>]" value="0" min="0" max="<?= $maxRet ?>" style="width: 80px; min-height: 40px; text-align: center;">
                            <?php else: ?>
                                <span style="color: var(--on-surface-variant); font-size: 0.875rem;">Đã trả hết</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-field" style="margin-top: 24px;">
        <label for="reason">Lý do yêu cầu đổi trả hàng *</label>
        <textarea id="reason" name="reason" rows="4" style="width: 100%; padding: 12px; border: 1px solid var(--outline); border-radius: 6px; font: inherit;" placeholder="Ví dụ: Sản phẩm bị rộng hơn so với kích thước, hoặc có vết trầy xước nhẹ..." required><?= e($_POST['reason'] ?? '') ?></textarea>
    </div>

    <div style="display: flex; gap: 16px; align-items: center; margin-top: 24px;">
        <button type="submit" class="filled-button" style="background: #d9480f; font-size: 1rem; padding: 12px 24px;">
            📤 Gửi yêu cầu đổi trả
        </button>
        <a href="/order-detail.php?id=<?= $orderId ?>" class="text-button">Hủy bỏ</a>
    </div>
</form>

<?php require __DIR__ . '/../templates/footer.php'; ?>

