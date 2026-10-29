<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuditLogService.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);

$db = db();
$orderService = new OrderService($db);
$orderService->checkAndExpireReservations();

$auditService = new AuditLogService($db);
$report = $auditService->getReconciliationReport();

require __DIR__ . '/../templates/admin_header.php';
?>

<!-- 4 THẺ CHỈ SỐ TỔNG QUAN (SMALL BOX ADMINLTE 3) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <div class="small-box bg-info">
        <div class="inner">
            <h3><?= number_format($report['total_orders']) ?></h3>
            <p>Tổng đơn hàng hệ thống</p>
        </div>
        <div class="icon">📦</div>
        <a href="/admin/orders.php" class="small-box-footer">Xem chi tiết đơn hàng ➔</a>
    </div>

    <div class="small-box bg-success">
        <div class="inner">
            <h3 style="font-size: 1.6rem;"><?= formatMoney($report['finance']['total_received']) ?></h3>
            <p>Tổng tiền thu mô phỏng</p>
        </div>
        <div class="icon">💰</div>
        <a href="#table-finance" class="small-box-footer">Ghi nhận qua cổng thanh toán ➔</a>
    </div>

    <div class="small-box bg-danger">
        <div class="inner">
            <h3 style="font-size: 1.6rem;"><?= formatMoney($report['finance']['total_refunded']) ?></h3>
            <p>Tổng tiền hoàn mô phỏng</p>
        </div>
        <div class="icon">🔄</div>
        <a href="/admin/returns.php" class="small-box-footer"><?= $report['finance']['approved_returns'] ?> hồ sơ đã hoàn ➔</a>
    </div>

    <div class="small-box bg-warning">
        <div class="inner">
            <h3 style="font-size: 1.6rem;"><?= formatMoney($report['finance']['net_revenue']) ?></h3>
            <p>Doanh thu thực nhận ròng</p>
        </div>
        <div class="icon">📊</div>
        <a href="#table-lifecycle" class="small-box-footer">Doanh thu sau hoàn trả ➔</a>
    </div>
</div>

<!-- CHI TIẾT ĐỐI SOÁT SỐ LƯỢNG HÀNG HOÁ (CARD ADMINLTE 3) -->
<div class="card-admin" id="table-finance" style="margin-bottom: 24px;">
    <div class="card-admin-header">
        <h3 class="card-admin-title">
            <span>📦</span> 1. Bảng đối chiếu luân chuyển số lượng hàng (Items Reconciliation)
        </h3>
        <div class="card-admin-tools">
            <span class="badge-admin badge-info">Đề tài 19 - Chức năng 5</span>
        </div>
    </div>
    <div class="card-admin-body" style="padding: 0;">
        <table class="table-admin table-striped table-hover" style="margin: 0;">
            <thead>
                <tr>
                    <th>Chỉ số luân chuyển hàng</th>
                    <th>Số lượng (cái)</th>
                    <th>Diễn giải nghiệp vụ đối soát</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Tổng số lượng khách đã đặt mua</strong></td>
                    <td><strong style="color: #007bff; font-size: 1rem;"><?= number_format((int)($report['inventory']['total_ordered'] ?? $report['items']['ordered'] ?? 0)) ?></strong></td>
                    <td>Tổng số sản phẩm ghi nhận trong tất cả các dòng đơn hàng</td>
                </tr>
                <tr>
                    <td><strong>Số lượng hàng đã giao thành công</strong></td>
                    <td><strong style="color: #28a745; font-size: 1rem;"><?= number_format((int)($report['inventory']['total_delivered'] ?? $report['items']['delivered'] ?? 0)) ?></strong></td>
                    <td>Đã thực giao đến tận tay khách hàng (Trạng thái DELIVERED)</td>
                </tr>
                <tr>
                    <td><strong>Số lượng hàng đã được duyệt đổi trả</strong></td>
                    <td><strong style="color: #dc3545; font-size: 1rem;"><?= number_format((int)($report['inventory']['total_returned'] ?? $report['items']['returned'] ?? 0)) ?></strong></td>
                    <td>Khách yêu cầu đổi trả một phần và được Quản trị viên thẩm định chấp thuận</td>
                </tr>
                <tr>
                    <td><strong>Số lượng hàng đang được giữ chỗ (HOLDING)</strong></td>
                    <td><strong style="color: #ffc107; font-size: 1rem;"><?= number_format((int)($report['inventory']['current_holding'] ?? $report['items']['holding'] ?? 0)) ?></strong></td>
                    <td>Đang được giữ chỗ độc quyền 15 phút có thời hạn (Chống Overselling)</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- CHI TIẾT THEO TỪNG TRẠNG THÁI ĐƠN HÀNG (CARD ADMINLTE 3) -->
<div class="card-admin" id="table-lifecycle">
    <div class="card-admin-header">
        <h3 class="card-admin-title">
            <span>📈</span> 2. Phân bổ đơn hàng theo vòng đời (Lifecycle Breakdown)
        </h3>
        <div class="card-admin-tools">
            <span style="font-size: 0.8125rem; color: #6c757d;">Tỷ lệ & Tổng giá trị tiền chốt</span>
        </div>
    </div>
    <div class="card-admin-body" style="padding: 0;">
        <table class="table-admin table-striped table-hover" style="margin: 0;">
            <thead>
                <tr>
                    <th>Trạng thái vòng đời</th>
                    <th>Số lượng đơn</th>
                    <th>Tổng giá trị tiền chốt</th>
                    <th>Tỷ lệ phần trăm</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalOrd = max(1, (int)($report['total_orders'] ?? 0));
                $breakdown = $report['status_breakdown'] ?? [];
                foreach ($breakdown as $sb): 
                    $cnt = (int)($sb['cnt'] ?? $sb['count'] ?? 0);
                    $pct = round(($cnt / $totalOrd) * 100, 1);
                ?>
                    <tr>
                        <td><?= getStatusBadge($sb['status'] ?? '') ?></td>
                        <td><strong><?= number_format($cnt) ?></strong></td>
                        <td style="color: #28a745; font-weight: 700;"><?= formatMoney((float)($sb['total_val'] ?? 0)) ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; height: 10px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; width: <?= $pct ?>%; background: #007bff;"></div>
                                </div>
                                <span style="font-size: 0.8125rem; width: 45px; color: #495057; font-weight: 600;"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
