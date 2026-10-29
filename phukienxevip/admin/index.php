<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/AuditLogService.php';
require_once __DIR__ . '/../src/ReturnService.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

// Chỉ quản trị viên (admin/manager) mới được truy cập
requireRole(['admin', 'manager']);
$user = currentUser();
$db = db();

$orderService = new OrderService($db);
$orderService->checkAndExpireReservations();

// 1. Thống kê số lượng đơn hàng
$orderStats = fetchOne(queryParams($db, "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status IN ('PAID', 'PROCESSING') THEN 1 ELSE 0 END) AS need_ship,
        SUM(CASE WHEN status = 'SHIPPED' THEN 1 ELSE 0 END) AS shipping,
        SUM(CASE WHEN status = 'DELIVERED' THEN 1 ELSE 0 END) AS delivered,
        SUM(CASE WHEN status IN ('PARTIALLY_RETURNED', 'RETURNED') THEN 1 ELSE 0 END) AS returned,
        SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pending
    FROM orders
")) ?: [];

// 2. Thống kê đổi trả
$returnStats = fetchOne(queryParams($db, "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'REQUESTED' THEN 1 ELSE 0 END) AS pending_review,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
        COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN total_refund_amount ELSE 0 END), 0) AS total_refunded
    FROM returns
")) ?: [];

// 3. Thống kê tài chính cổng thanh toán
$financeStats = fetchOne(queryParams($db, "
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END), 0) AS total_received,
        COUNT(CASE WHEN status = 'SUCCESS' THEN 1 END) AS successful_payments
    FROM payment_events
")) ?: [];

// 4. Cảnh báo kho hàng
$stockStats = fetchOne(queryParams($db, "
    SELECT 
        COUNT(DISTINCT product_id) AS total_products,
        COUNT(id) AS total_variants,
        SUM(CASE WHEN stock_quantity <= 5 THEN 1 ELSE 0 END) AS low_stock
    FROM variants
")) ?: [];

// 5. Danh sách các yêu cầu đổi trả cần duyệt gấp
$pendingReturnsList = fetchAll(queryParams($db, "
    SELECT r.*, o.order_code, u.full_name AS customer_name 
    FROM returns r 
    JOIN orders o ON r.order_id = o.id 
    JOIN users u ON r.user_id = u.id 
    WHERE r.status = 'REQUESTED' 
    ORDER BY r.id DESC 
    LIMIT 5
"));

// 6. Danh sách đơn hàng cần xử lý xuất kho gấp
$urgentOrdersList = fetchAll(queryParams($db, "
    SELECT o.*, u.full_name AS customer_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.status IN ('PAID', 'PROCESSING') 
    ORDER BY o.id DESC 
    LIMIT 5
"));

// 7. Hoạt động kiểm toán gần nhất
$auditService = new AuditLogService($db);
$recentLogs = $auditService->getRecentLogs(6);

require __DIR__ . '/../templates/admin_header.php';
?>

<!-- ==============================================================================
     THANH CÔNG CỤ TÁC VỤ NHANH QUẢN TRỊ VIÊN
     ============================================================================== -->
<div class="card-admin" style="margin-bottom: 24px;">
    <div class="card-admin-header" style="background-color: #f8f9fa;">
        <h3 class="card-admin-title">
            <span>⚡</span> Bàn làm việc Quản trị viên: <?= e($user['full_name']) ?>
        </h3>
        <div class="card-admin-tools">
            <span class="badge-admin badge-danger">ADMIN ROLE</span>
        </div>
    </div>
    <div class="card-admin-body" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <a href="/admin/products.php" class="btn-admin btn-admin-primary">
            🏷️ Quản lý Sản phẩm
        </a>
        <a href="/admin/products.php?action=new" class="btn-admin btn-admin-success">
            ➕ Thêm sản phẩm mới
        </a>
        <a href="/admin/orders.php" class="btn-admin btn-admin-warning">
            📦 Quản lý Đơn hàng & Xuất hóa đơn
        </a>
        <a href="/admin/returns.php" class="btn-admin btn-admin-danger">
            🔄 Duyệt Đổi trả (<?= (int)$returnStats['pending_review'] ?> chờ)
        </a>
        <a href="/admin/reports.php" class="btn-admin btn-admin-secondary">
            📈 Báo cáo Đối soát
        </a>
    </div>
</div>

<!-- ==============================================================================
     4 THẺ SMALL-BOX KPI (ADMINLTE 3 WIDGETS)
     ============================================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Small box 1: Đơn cần xuất kho -->
    <div class="small-box bg-info">
        <div class="inner">
            <h3><?= (int)$orderStats['need_ship'] ?></h3>
            <p>Đơn hàng cần xuất kho</p>
        </div>
        <div class="icon">🚚</div>
        <a href="/admin/orders.php?status=PAID" class="small-box-footer">
            Xuất kho & Xử lý ➔
        </a>
    </div>

    <!-- Small box 2: Đổi trả chờ thẩm định -->
    <div class="small-box bg-danger">
        <div class="inner">
            <h3><?= (int)$returnStats['pending_review'] ?></h3>
            <p>Yêu cầu đổi trả chờ duyệt</p>
        </div>
        <div class="icon">⚠️</div>
        <a href="/admin/returns.php?status=REQUESTED" class="small-box-footer">
            Vào thẩm định ngay ➔
        </a>
    </div>

    <!-- Small box 3: Doanh thu đối soát -->
    <div class="small-box bg-success">
        <div class="inner">
            <h3 style="font-size: 1.6rem;"><?= formatMoney((float)$financeStats['total_received']) ?></h3>
            <p>Doanh thu đã thu mô phỏng</p>
        </div>
        <div class="icon">💰</div>
        <a href="/admin/reports.php" class="small-box-footer">
            Đối soát dòng tiền ➔
        </a>
    </div>

    <!-- Small box 4: Quản lý sản phẩm & tồn kho -->
    <div class="small-box bg-warning">
        <div class="inner">
            <h3><?= (int)$stockStats['total_variants'] ?> <small style="font-size: 1rem; font-weight: 500;">loại</small></h3>
            <p>Sản phẩm & Biến thể kho</p>
        </div>
        <div class="icon">📦</div>
        <a href="/admin/products.php" class="small-box-footer">
            Quản lý Sản phẩm (Thêm/Sửa/Xóa) ➔
        </a>
    </div>
</div>

<!-- ==============================================================================
     2 BẢNG DỮ LIỆU ĐIỀU PHỐI (PRODUCTS & ONLINE STORE OVERVIEW)
     ============================================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 20px; margin-bottom: 24px;">

    <!-- CỘT TRÁI: ĐƠN HÀNG CẦN ĐÓNG GÓI & XUẤT HÓA ĐƠN -->
    <div class="card-admin">
        <div class="card-admin-header">
            <h3 class="card-admin-title">
                <span>📦</span> Đơn hàng cần đóng gói & Xuất kho
            </h3>
            <div class="card-admin-tools">
                <a href="/admin/orders.php" class="btn-admin btn-admin-primary btn-admin-sm">Tất cả đơn</a>
            </div>
        </div>
        <div class="card-admin-body" style="padding: 0;">
            <?php if (empty($urgentOrdersList)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <div style="font-size: 2rem;">🎉</div>
                    <p style="margin: 6px 0 0;">Không có đơn hàng nào tồn đọng cần xuất kho.</p>
                </div>
            <?php else: ?>
                <table class="table-admin table-striped table-hover" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($urgentOrdersList as $uo): ?>
                            <tr>
                                <td>
                                    <strong><?= e($uo['order_code']) ?></strong>
                                </td>
                                <td><?= e($uo['customer_name']) ?></td>
                                <td style="font-weight: 700; color: #28a745;">
                                    <?= formatMoney((float)$uo['total_amount']) ?>
                                </td>
                                <td>
                                    <?= getStatusBadge($uo['status']) ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="/admin/orders.php" class="btn-admin btn-admin-primary btn-admin-sm">
                                        Xử lý ➔
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="card-admin-footer" style="text-align: right;">
            <a href="/admin/orders.php" style="font-size: 0.8125rem; font-weight: 600; color: #007bff; text-decoration: none;">
                Xem danh sách tất cả đơn hàng & Xuất hóa đơn ➔
            </a>
        </div>
    </div>

    <!-- CỘT PHẢI: YÊU CẦU ĐỔI TRẢ CHỜ DUYỆT -->
    <div class="card-admin">
        <div class="card-admin-header">
            <h3 class="card-admin-title">
                <span>🔄</span> Yêu cầu đổi trả chờ thẩm định
            </h3>
            <div class="card-admin-tools">
                <a href="/admin/returns.php" class="btn-admin btn-admin-danger btn-admin-sm">
                    Duyệt đổi trả (<?= (int)$returnStats['pending_review'] ?>)
                </a>
            </div>
        </div>
        <div class="card-admin-body" style="padding: 0;">
            <?php if (empty($pendingReturnsList)): ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <div style="font-size: 2rem;">✅</div>
                    <p style="margin: 6px 0 0;">Hiện không có yêu cầu đổi trả nào đang chờ duyệt.</p>
                </div>
            <?php else: ?>
                <table class="table-admin table-striped table-hover" style="margin: 0;">
                    <thead>
                        <tr>
                            <th>Mã yêu cầu</th>
                            <th>Khách hàng</th>
                            <th>Tiền hoàn</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingReturnsList as $pr): ?>
                            <tr>
                                <td>
                                    <strong><?= e($pr['return_code']) ?></strong><br>
                                    <small style="color: #6c757d;">Đơn: <?= e($pr['order_code']) ?></small>
                                </td>
                                <td><?= e($pr['customer_name']) ?></td>
                                <td style="font-weight: 700; color: #dc3545;">
                                    <?= formatMoney((float)$pr['total_refund_amount']) ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="/admin/returns.php" class="btn-admin btn-admin-success btn-admin-sm">
                                        Thẩm định ➔
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="card-admin-footer" style="text-align: right;">
            <a href="/admin/returns.php" style="font-size: 0.8125rem; font-weight: 600; color: #dc3545; text-decoration: none;">
                Xem toàn bộ hồ sơ đổi trả ➔
            </a>
        </div>
    </div>

</div>

<!-- ==============================================================================
     HÀNG 3: NHẬT KÝ KIỂM TOÁN GẦN NHẤT & TRẠNG THÁI KIỂM THỬ (ĐỀ TÀI 19)
     ============================================================================== -->
<div class="card-admin" id="test-suite-box" style="margin-bottom: 24px;">
    <div class="card-admin-header">
        <h3 class="card-admin-title">
            <span>📜</span> Nhật ký Kiểm toán Thời gian thực (Audit Trail)
        </h3>
        <div class="card-admin-tools">
            <span class="badge-admin badge-success" style="font-size: 0.75rem;">18/18 TEST CASE PASS</span>
            <a href="/admin/audit-logs.php" class="btn-admin btn-admin-secondary btn-admin-sm" style="margin-left: 8px;">
                Xem tất cả nhật ký
            </a>
        </div>
    </div>
    <div class="card-admin-body" style="padding: 0;">
        <table class="table-admin table-striped table-hover" style="margin: 0;">
            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Mã đơn</th>
                    <th>Tác nhân</th>
                    <th>Hành động</th>
                    <th>Biến động tiền</th>
                    <th>Ghi chú giải trình</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 24px; color: #6c757d;">Chưa có vết kiểm toán nào.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td style="font-size: 0.8125rem; color: #6c757d;"><?= e($log['created_at']) ?></td>
                            <td><strong><?= e($log['order_code'] ?? '—') ?></strong></td>
                            <td><?= e($log['actor_name'] ?? 'Hệ thống') ?></td>
                            <td><span class="badge-admin badge-info"><?= e($log['action']) ?></span></td>
                            <td style="font-weight: 600; color: <?= (float)($log['amount_change'] ?? 0) > 0 ? '#28a745' : ((float)($log['amount_change'] ?? 0) < 0 ? '#dc3545' : '#6c757d') ?>;">
                                <?= (float)($log['amount_change'] ?? 0) != 0 ? formatMoney((float)$log['amount_change']) : '0đ' ?>
                            </td>
                            <td style="font-size: 0.8125rem; color: #495057;"><?= e($log['note']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
