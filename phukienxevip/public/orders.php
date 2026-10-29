<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireLogin();
$user = currentUser();

$db = db();
$orderService = new OrderService($db);
$orderService->checkAndExpireReservations();

$filter = [];
if ($user['role'] === 'customer') {
    $filter['user_id'] = (int)$user['id'];
}
$statusFilter = field($_GET, 'status');
if ($statusFilter !== '') {
    $filter['status'] = $statusFilter;
}

$orders = $orderService->listOrders($filter);

require __DIR__ . '/../templates/header.php';
?>

<!-- TAB TRẠNG THÁI ĐƠN HÀNG PHONG CÁCH SHOPEE "ĐƠN MUA" -->
<div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); margin-bottom: 20px; box-shadow: var(--shadow-sm); overflow-x: auto;">
    <div style="display: flex; min-width: 650px;">
        <a href="/orders.php" style="flex: 1; text-align: center; padding: 14px 10px; font-size: 0.875rem; font-weight: 600; border-bottom: 2px solid <?= empty($statusFilter) ? '#ee4d2d' : 'transparent' ?>; color: <?= empty($statusFilter) ? '#ee4d2d' : '#495057' ?>;">
            Tất cả
        </a>
        <?php 
        $tabs = [
            'PENDING' => 'Chờ thanh toán',
            'PAID' => 'Đã thanh toán',
            'SHIPPED' => 'Đang giao',
            'DELIVERED' => 'Đã giao',
            'PARTIALLY_RETURNED' => 'Đổi trả',
            'CANCELLED' => 'Đã hủy',
            'EXPIRED' => 'Hết hạn'
        ];
        foreach ($tabs as $st => $label): 
        ?>
            <a href="/orders.php?status=<?= e($st) ?>" style="flex: 1; text-align: center; padding: 14px 10px; font-size: 0.875rem; font-weight: 600; border-bottom: 2px solid <?= $statusFilter === $st ? '#ee4d2d' : 'transparent' ?>; color: <?= $statusFilter === $st ? '#ee4d2d' : '#495057' ?>;">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($orders)): ?>
    <div style="text-align: center; padding: 64px 20px; background: #ffffff; border-radius: var(--radius-md); border: 1px solid var(--outline); box-shadow: var(--shadow-sm);">
        <span style="font-size: 4rem;">📦</span>
        <p style="font-size: 1.125rem; color: #6c757d; margin: 16px 0 20px;">Chưa có đơn hàng nào trong mục này</p>
        <a href="/" class="filled-button" style="padding: 10px 24px;">Tiếp tục mua sắm</a>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($orders as $ord): ?>
            <?php 
                $fullOrder = $orderService->getOrderById((int)$ord['id']);
                $items = $fullOrder['items'] ?? [];
            ?>
            <div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); overflow: hidden;">
                <!-- Header Đơn hàng -->
                <div style="background: #fafafa; padding: 12px 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="font-weight: 700; color: #212529;">Mã đơn: <?= e($ord['order_code']) ?></span>
                        <span style="margin: 0 8px; color: #ccc;">•</span>
                        <span style="font-size: 0.8125rem; color: #6c757d;">Ngày đặt: <?= formatDate($ord['created_at']) ?></span>
                    </div>
                    <div>
                        <?= getStatusBadge($ord['status']) ?>
                    </div>
                </div>

                <!-- Danh sách sản phẩm rút gọn -->
                <div style="padding: 16px 20px;">
                    <?php foreach ($items as $it): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #f5f5f5;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if (!empty($it['image_url'])): ?>
                                    <img src="<?= e(productImageUrl($it['image_url'])) ?>" alt="<?= e($it['product_name']) ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #eee;">
                                <?php endif; ?>
                                <div>
                                    <strong style="font-size: 0.9375rem; color: #212529;"><?= e($it['product_name']) ?></strong>
                                    <div style="font-size: 0.8125rem; color: #6c757d;">
                                        Phân loại: <?= e($it['variant_info']) ?> | Số lượng: ×<?= $it['quantity'] ?>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color: #6c757d; font-size: 0.8125rem;"><?= formatMoney((float)$it['unit_price']) ?></div>
                                <div style="font-weight: 700; color: #ee4d2d;"><?= formatMoney((float)$it['subtotal']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Footer Đơn hàng: Tổng tiền & Nút bấm hành động -->
                <div style="background: #fffdfb; padding: 14px 20px; border-top: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div style="font-size: 0.875rem;">
                        <?php if ($ord['status'] === 'PENDING' && $ord['expires_at']): ?>
                            <span style="color: #d9480f; font-weight: 600;">⏱️ Giữ hàng đến: <?= date('H:i:s d/m', strtotime($ord['expires_at'])) ?></span>
                        <?php elseif (in_array($ord['status'], ['PARTIALLY_RETURNED', 'RETURNED'], true)): ?>
                            <span style="color: #2e7d32; font-weight: 600; background: #e8f5e9; border: 1px solid #c8e6c9; padding: 3px 8px; border-radius: 4px;">✅ Đã duyệt đổi trả & hoàn tiền</span>
                        <?php else: ?>
                            <span style="color: #6c757d;">Giao đến: <?= e($ord['shipping_address']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div>
                            <span style="font-size: 0.8125rem; color: #6c757d;">Thành tiền:</span>
                            <span style="font-size: 1.25rem; font-weight: 800; color: #ee4d2d;"><?= formatMoney((float)$ord['total_amount']) ?></span>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <?php if ($ord['status'] === 'PENDING'): ?>
                                <a href="/payment-mock.php?order_code=<?= urlencode($ord['order_code']) ?>" class="filled-button" style="background: #28a745; min-height: 36px; padding: 6px 14px; font-size: 0.8125rem;">
                                    Thanh toán ngay
                                </a>
                            <?php endif; ?>

                            <a href="/order-detail.php?id=<?= (int)$ord['id'] ?>" class="button-secondary" style="min-height: 36px; padding: 6px 14px; font-size: 0.8125rem; font-weight: 600;">
                                Xem chi tiết ➔
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
