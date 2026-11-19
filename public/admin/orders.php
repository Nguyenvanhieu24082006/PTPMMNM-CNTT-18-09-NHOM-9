<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);
$user = currentUser();

$db = db();
$orderService = new OrderService($db);
$orderService->checkAndExpireReservations();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_status') {
    checkCsrf($_POST);
    $orderId = fieldInt($_POST, 'order_id');
    $newStatus = field($_POST, 'new_status');
    try {
        $orderService->updateShippingStatus($orderId, $newStatus, (int)$user['id'], $user['role']);
        setFlash('success', "Đã cập nhật trạng thái đơn ID $orderId sang $newStatus.");
    } catch (Throwable $e) {
        setFlash('danger', 'Lỗi cập nhật: ' . $e->getMessage());
    }
    header('Location: /admin/orders.php');
    exit;
}

$statusFilter = field($_GET, 'status');
$search = field($_GET, 'search');

$filter = [];
if ($statusFilter !== '') $filter['status'] = $statusFilter;
if ($search !== '') $filter['search'] = $search;

$orders = $orderService->listOrders($filter);

require __DIR__ . '/../templates/admin_header.php';
?>

<div class="card-admin">
    <div class="card-admin-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h3 class="card-admin-title" style="margin: 0; font-size: 0.95rem;">
                <span>📦</span> Quản lý Đơn hàng
            </h3>
            <span class="badge-admin badge-info" style="font-size: 0.72rem;">
                Tổng: <?= count($orders) ?> đơn
            </span>
            <span style="font-size: 0.75rem; color: var(--admin-text-muted, #6c757d);">
                (Điều phối: PAID ➔ PROCESSING ➔ SHIPPED ➔ DELIVERED)
            </span>
        </div>
        <div class="card-admin-tools">
            <a href="/admin/orders.php" class="btn-admin btn-admin-secondary btn-admin-sm" title="Tải lại danh sách">
                🔄 Làm mới
            </a>
        </div>
    </div>

    <div style="padding: 10px 14px; background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--admin-border-color);">
        <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0;">
            <div style="flex: 1 1 240px;">
                <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="🔍 Nhập mã đơn, tên khách, số điện thoại..." style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.85rem; border: 1px solid var(--admin-border-color, #ced4da); border-radius: 4px; background: var(--admin-card-bg, #fff); color: inherit; box-sizing: border-box;">
            </div>

            <div style="flex: 1 1 240px;">
                <select id="status" name="status" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.85rem; border: 1px solid var(--admin-border-color, #ced4da); border-radius: 4px; background: var(--admin-card-bg, #fff); color: inherit; box-sizing: border-box;">
                    <option value="">-- Tất cả trạng thái --</option>
                    <?php foreach (['PENDING', 'PAID', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'PARTIALLY_RETURNED', 'RETURNED', 'CANCELLED', 'EXPIRED'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 6px; align-items: center;">
                <button type="submit" class="btn-admin btn-admin-primary" style="height: 36px; padding: 0 14px; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px;">
                    🔍 Lọc đơn
                </button>
                <?php if ($search !== '' || $statusFilter !== ''): ?>
                    <a href="/admin/orders.php" class="btn-admin btn-admin-secondary" style="height: 36px; padding: 0 14px; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px; text-decoration: none;">
                        ✕ Xóa lọc
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-admin-body" style="padding: 0;">
        <table class="table-admin table-striped table-hover" style="margin: 0;">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Ngày tạo</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th style="text-align: right;">Cập nhật trạng thái giao nhận</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #6c757d;">
                            Không tìm thấy đơn hàng nào phù hợp với điều kiện lọc.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($orders as $ord): ?>
                    <tr>
                        <td>
                            <strong style="color: #007bff;"><?= e($ord['order_code']) ?></strong><br>
                            <small style="color: #6c757d;">ID: #<?= (int)$ord['id'] ?></small>
                        </td>
                        <td>
                            <strong><?= e($ord['customer_name'] ?? 'Khách lẻ') ?></strong><br>
                            <small style="color: #6c757d;"><?= e($ord['customer_phone']) ?> | <?= e($ord['shipping_address']) ?></small>
                        </td>
                        <td style="font-size: 0.8125rem; color: #6c757d;"><?= formatDate($ord['created_at']) ?></td>
                        <td><strong style="color: #28a745;"><?= formatMoney((float)$ord['total_amount']) ?></strong></td>
                        <td><?= getStatusBadge($ord['status']) ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="/admin/invoice.php?order_id=<?= (int)$ord['id'] ?>" target="_blank" class="btn-admin btn-admin-secondary btn-admin-sm" title="In hóa đơn xuất kho" style="margin-right: 4px;">
                                🖨️ In hóa đơn
                            </a>
                            <?php if ($ord['status'] === 'PAID'): ?>
                                <form method="post" style="margin: 0; display: inline-block;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="quick_status">
                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                    <input type="hidden" name="new_status" value="PROCESSING">
                                    <button type="submit" class="btn-admin btn-admin-warning btn-admin-sm">
                                        📦 Bắt đầu đóng gói
                                    </button>
                                </form>
                            <?php elseif ($ord['status'] === 'PROCESSING'): ?>
                                <form method="post" style="margin: 0; display: inline-block;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="quick_status">
                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                    <input type="hidden" name="new_status" value="SHIPPED">
                                    <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm">
                                        🚚 Xuất kho giao hàng
                                    </button>
                                </form>
                            <?php elseif ($ord['status'] === 'SHIPPED'): ?>
                                <form method="post" style="margin: 0; display: inline-block;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="quick_status">
                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                    <input type="hidden" name="new_status" value="DELIVERED">
                                    <button type="submit" class="btn-admin btn-admin-success btn-admin-sm">
                                        ✅ Giao thành công
                                    </button>
                                </form>
                            <?php elseif (in_array($ord['status'], ['DELIVERED', 'PARTIALLY_RETURNED', 'RETURNED'])): ?>
                                <span style="font-size: 0.8125rem; color: #28a745; font-weight: 600;">✓ Đã giao hàng</span>
                            <?php else: ?>
                                <span style="font-size: 0.8125rem; color: #6c757d;">Đang chờ thanh toán</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
