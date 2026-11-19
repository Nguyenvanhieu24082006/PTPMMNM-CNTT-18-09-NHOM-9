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

$totalOrdered = (int)($report['inventory']['total_ordered'] ?? $report['items']['ordered'] ?? 0);
$totalDelivered = (int)($report['inventory']['total_delivered'] ?? $report['items']['delivered'] ?? 0);
$totalReturned = (int)($report['inventory']['total_returned'] ?? $report['items']['returned'] ?? 0);
$currentHolding = (int)($report['inventory']['current_holding'] ?? $report['items']['holding'] ?? 0);

$deliveredPct = $totalOrdered > 0 ? round(($totalDelivered / $totalOrdered) * 100, 1) : 0;
$returnedPct = $totalOrdered > 0 ? round(($totalReturned / $totalOrdered) * 100, 1) : 0;
$holdingPct = $totalOrdered > 0 ? round(($currentHolding / $totalOrdered) * 100, 1) : 0;
$otherItems = max(0, $totalOrdered - ($totalDelivered + $totalReturned + $currentHolding));
$otherPct = $totalOrdered > 0 ? round(($otherItems / $totalOrdered) * 100, 1) : 0;

$statusColors = [
    'PENDING' => '#F39C12',
    'PAID' => '#3498DB',
    'PROCESSING' => '#9B59B6',
    'SHIPPED' => '#1ABC9C',
    'DELIVERED' => '#26B99A',
    'CANCELLED' => '#E74C3C',
    'EXPIRED' => '#C0392B',
    'PARTIALLY_RETURNED' => '#E67E22',
    'RETURNED' => '#7F8C8D',
];

$statusNames = [
    'PENDING' => 'Chờ thanh toán',
    'PAID' => 'Đã thanh toán',
    'PROCESSING' => 'Đang xử lý',
    'SHIPPED' => 'Đang giao hàng',
    'DELIVERED' => 'Đã giao thành công',
    'CANCELLED' => 'Đã hủy',
    'EXPIRED' => 'Hết hạn giữ hàng',
    'PARTIALLY_RETURNED' => 'Đổi trả một phần',
    'RETURNED' => 'Đã trả toàn bộ',
];

$breakdown = $report['status_breakdown'] ?? [];
$chartLabels = [];
$chartCounts = [];
$chartAmounts = [];
$chartBgColors = [];
$totalOrderVal = 0.0;
$totalOrdCount = max(1, (int)($report['total_orders'] ?? 0));

foreach ($breakdown as $sb) {
    $st = $sb['status'] ?? '';
    $cnt = (int)($sb['cnt'] ?? $sb['count'] ?? 0);
    $val = (float)($sb['total_val'] ?? 0);
    $totalOrderVal += $val;
    $chartLabels[] = $statusNames[$st] ?? $st;
    $chartCounts[] = $cnt;
    $chartAmounts[] = $val;
    $chartBgColors[] = $statusColors[$st] ?? '#6c757d';
}

require __DIR__ . '/../templates/admin_header.php';
?>

<style>
.gente-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: var(--admin-text-main, #2A3F54);
}

.gente-tile-count {
    background: var(--admin-card-bg, #ffffff);
    border: 1px solid var(--admin-border-color, #E6E9ED);
    border-radius: 6px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    overflow: hidden;
}

.gente-tile-stats {
    padding: 12px 16px;
    border-right: 1px solid var(--admin-border-color, #E6E9ED);
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    transition: background-color 0.15s ease;
}

.gente-tile-stats:last-child {
    border-right: none;
}

.gente-tile-stats:hover {
    background: rgba(38, 185, 154, 0.03);
}

.gente-tile-title {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--admin-text-muted, #73879C);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.gente-tile-num {
    font-size: 1.55rem;
    font-weight: 700;
    line-height: 1.15;
    color: #26B99A;
    margin-bottom: 3px;
    letter-spacing: -0.5px;
}

.gente-tile-sub {
    font-size: 0.7rem;
    color: var(--admin-text-muted, #73879C);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gente-card {
    background: var(--admin-card-bg, #ffffff);
    border: 1px solid var(--admin-border-color, #E6E9ED);
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    margin-bottom: 20px;
}

.gente-card-header {
    padding: 12px 18px;
    border-bottom: 1px solid var(--admin-border-color, #E6E9ED);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.gente-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 8px;
}

.gente-card-subtitle {
    font-size: 0.75rem;
    font-weight: 400;
    color: var(--admin-text-muted, #73879C);
    margin-left: 6px;
}

.gente-card-body {
    padding: 18px;
}

.gente-progress-wrap {
    margin-bottom: 14px;
}

.gente-progress-head {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.gente-progress-bar-bg {
    height: 7px;
    background: rgba(0,0,0,0.06);
    border-radius: 4px;
    overflow: hidden;
}

.gente-progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease-in-out;
}

.gente-btn-group {
    display: inline-flex;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid var(--admin-border-color, #E6E9ED);
}

.gente-btn-toggle {
    padding: 4px 10px;
    font-size: 0.725rem;
    font-weight: 600;
    background: transparent;
    border: none;
    color: var(--admin-text-muted, #73879C);
    cursor: pointer;
    transition: all 0.15s ease;
}

.gente-btn-toggle.active {
    background: #26B99A;
    color: #ffffff;
}

.gente-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 4px;
    border: 1px solid var(--admin-border-color, #E6E9ED);
    background: var(--admin-card-bg, #ffffff);
    color: inherit;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.15s;
}

.gente-btn-action:hover {
    background: rgba(38, 185, 154, 0.1);
    border-color: #26B99A;
    color: #26B99A;
}

@media print {
    .main-sidebar, .main-header, .card-admin-tools, .gente-btn-group, .gente-btn-action {
        display: none !important;
    }
    .content-wrapper, .gente-dashboard {
        margin: 0 !important;
        padding: 0 !important;
    }
}
</style>

<div class="gente-dashboard">
    <div class="gente-tile-count">
        <div class="gente-tile-stats">
            <span class="gente-tile-title">📦 Tổng đơn hàng</span>
            <div class="gente-tile-num"><?= number_format($report['total_orders']) ?></div>
            <span class="gente-tile-sub"><i style="color: #26B99A;">▲ Hoạt động</i> • Toàn hệ thống</span>
        </div>

        <div class="gente-tile-stats">
            <span class="gente-tile-title">💰 Tổng tiền thu</span>
            <div class="gente-tile-num" style="font-size: 1.35rem;"><?= formatMoney($report['finance']['total_received']) ?></div>
            <span class="gente-tile-sub"><i style="color: #26B99A;">✔ Xác nhận</i> Cổng thanh toán</span>
        </div>

        <div class="gente-tile-stats">
            <span class="gente-tile-title">🔄 Tiền hoàn</span>
            <div class="gente-tile-num" style="font-size: 1.35rem; color: #E74C3C;"><?= formatMoney($report['finance']['total_refunded']) ?></div>
            <span class="gente-tile-sub"><i style="color: #E74C3C;">▼ Hoàn tiền</i> <?= $report['finance']['approved_returns'] ?> hồ sơ</span>
        </div>

        <div class="gente-tile-stats">
            <span class="gente-tile-title">💵 Doanh thu ròng</span>
            <div class="gente-tile-num" style="font-size: 1.35rem; color: #3498DB;"><?= formatMoney($report['finance']['net_revenue']) ?></div>
            <span class="gente-tile-sub"><i style="color: #3498DB;">✔ Sau đối soát</i> Đã trừ hoàn trả</span>
        </div>

        <div class="gente-tile-stats">
            <span class="gente-tile-title">🛒 Tổng hàng đặt</span>
            <div class="gente-tile-num"><?= number_format($totalOrdered) ?> <small style="font-size: 0.75rem; font-weight: 500; color: inherit;">cái</small></div>
            <span class="gente-tile-sub"><i style="color: #26B99A;">100%</i> Số lượng sản phẩm</span>
        </div>

        <div class="gente-tile-stats">
            <span class="gente-tile-title">🚚 Đã giao hàng</span>
            <div class="gente-tile-num"><?= number_format($totalDelivered) ?> <small style="font-size: 0.75rem; font-weight: 500; color: inherit;">cái</small></div>
            <span class="gente-tile-sub"><i style="color: #26B99A;">▲ <?= $deliveredPct ?>%</i> Tận tay khách hàng</span>
        </div>
    </div>

    <div class="gente-card">
        <div class="gente-card-header">
            <div>
                <h3 class="gente-card-title">
                    <span>📈</span> Diễn biến doanh thu & Phân bổ vòng đời đơn hàng
                    <span class="gente-card-subtitle">Thống kê theo trạng thái và luồng giá trị chốt</span>
                </h3>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div class="gente-btn-group">
                    <button type="button" id="btnModeAmount" class="gente-btn-toggle active" onclick="switchChartMode('amount')">
                        💰 Doanh thu tiền chốt
                    </button>
                    <button type="button" id="btnModeCount" class="gente-btn-toggle" onclick="switchChartMode('count')">
                        📊 Số lượng đơn
                    </button>
                </div>
                <button type="button" class="gente-btn-action" onclick="window.print()" title="In báo cáo">
                    🖨️ In
                </button>
            </div>
        </div>

        <div class="gente-card-body">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
                
                <div>
                    <div style="position: relative; width: 100%; height: 280px;">
                        <canvas id="businessAreaChart"></canvas>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-size: 0.75rem; color: var(--admin-text-muted, #73879C);">
                        <span>📊 Đường cong biểu diễn giá trị từng giai đoạn trong chu kỳ xử lý đơn hàng</span>
                        <span>Tổng giá trị chốt: <strong style="color: #26B99A; font-size: 0.85rem;"><?= formatMoney($totalOrderVal) ?></strong></span>
                    </div>
                </div>

                <div style="border-left: 1px solid var(--admin-border-color, #E6E9ED); padding-left: 20px;">
                    <div style="font-size: 0.8125rem; font-weight: 700; margin-bottom: 14px; text-transform: uppercase; color: var(--admin-text-muted, #73879C); letter-spacing: 0.5px;">
                        🎯 Hiệu suất xử lý đơn
                    </div>

                    <?php 
                    foreach ($breakdown as $sb): 
                        $st = $sb['status'] ?? '';
                        $stName = $statusNames[$st] ?? $st;
                        $cnt = (int)($sb['cnt'] ?? $sb['count'] ?? 0);
                        $val = (float)($sb['total_val'] ?? 0);
                        $pct = round(($cnt / $totalOrdCount) * 100, 1);
                        $barColor = $statusColors[$st] ?? '#26B99A';
                    ?>
                        <div class="gente-progress-wrap">
                            <div class="gente-progress-head">
                                <span style="display: flex; align-items: center; gap: 5px;">
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= $barColor ?>;"></span>
                                    <?= htmlspecialchars($stName) ?>
                                </span>
                                <span style="color: var(--admin-text-muted, #73879C);">
                                    <?= $cnt ?> đơn <strong style="color: inherit; margin-left: 4px;"><?= $pct ?>%</strong>
                                </span>
                            </div>
                            <div class="gente-progress-bar-bg">
                                <div class="gente-progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $barColor ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div style="margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--admin-border-color, #E6E9ED); font-size: 0.72rem; color: var(--admin-text-muted, #73879C); display: flex; justify-content: space-between;">
                        <span>Tỷ lệ hoàn tất giao: <strong><?= $deliveredPct ?>%</strong></span>
                        <span>Đổi trả thẩm định: <strong><?= $returnedPct ?>%</strong></span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
        
        <div class="gente-card" style="margin-bottom: 0;">
            <div class="gente-card-header">
                <h4 class="gente-card-title">
                    <span>📦</span> Luân chuyển kho hàng
                    <span class="gente-card-subtitle">Đề tài 19 • Mục 5</span>
                </h4>
                <span style="font-size: 0.7rem; font-weight: 700; color: #26B99A; background: rgba(38,185,154,0.1); padding: 2px 7px; border-radius: 3px;">Tồn khả dụng</span>
            </div>
            <div class="gente-card-body">
                <div class="gente-progress-wrap">
                    <div class="gente-progress-head">
                        <span>🚚 Đã giao thành công (DELIVERED)</span>
                        <span style="color: #26B99A;"><?= number_format($totalDelivered) ?> cái (<?= $deliveredPct ?>%)</span>
                    </div>
                    <div class="gente-progress-bar-bg">
                        <div class="gente-progress-bar-fill" style="width: <?= $deliveredPct ?>%; background: #26B99A;"></div>
                    </div>
                </div>

                <div class="gente-progress-wrap">
                    <div class="gente-progress-head">
                        <span>🔄 Đã duyệt đổi trả một phần</span>
                        <span style="color: #E74C3C;"><?= number_format($totalReturned) ?> cái (<?= $returnedPct ?>%)</span>
                    </div>
                    <div class="gente-progress-bar-bg">
                        <div class="gente-progress-bar-fill" style="width: <?= $returnedPct ?>%; background: #E74C3C;"></div>
                    </div>
                </div>

                <div class="gente-progress-wrap">
                    <div class="gente-progress-head">
                        <span>⏳ Đang giữ chỗ độc quyền (HOLD 15p)</span>
                        <span style="color: #F39C12;"><?= number_format($currentHolding) ?> cái (<?= $holdingPct ?>%)</span>
                    </div>
                    <div class="gente-progress-bar-bg">
                        <div class="gente-progress-bar-fill" style="width: <?= $holdingPct ?>%; background: #F39C12;"></div>
                    </div>
                </div>

                <div class="gente-progress-wrap">
                    <div class="gente-progress-head">
                        <span>⚪ Đã hủy / Hết hạn giữ chỗ</span>
                        <span style="color: #7F8C8D;"><?= number_format($otherItems) ?> cái (<?= $otherPct ?>%)</span>
                    </div>
                    <div class="gente-progress-bar-bg">
                        <div class="gente-progress-bar-fill" style="width: <?= $otherPct ?>%; background: #95A5A6;"></div>
                    </div>
                </div>

                <div style="height: 8px; border-radius: 4px; overflow: hidden; display: flex; background: rgba(0,0,0,0.06); margin-top: 14px;">
                    <div style="width: <?= $deliveredPct ?>%; background: #26B99A;" title="Đã giao: <?= $deliveredPct ?>%"></div>
                    <div style="width: <?= $returnedPct ?>%; background: #E74C3C;" title="Đã hoàn: <?= $returnedPct ?>%"></div>
                    <div style="width: <?= $holdingPct ?>%; background: #F39C12;" title="Giữ chỗ: <?= $holdingPct ?>%"></div>
                    <div style="width: <?= $otherPct ?>%; background: #95A5A6;" title="Hủy/Hết hạn: <?= $otherPct ?>%"></div>
                </div>
            </div>
        </div>

        <div class="gente-card" style="margin-bottom: 0;">
            <div class="gente-card-header">
                <h4 class="gente-card-title">
                    <span>🍩</span> Cơ cấu đơn hàng
                    <span class="gente-card-subtitle">Tỷ trọng vòng đời</span>
                </h4>
                <span style="font-size: 0.72rem; color: var(--admin-text-muted, #73879C);"><?= $report['total_orders'] ?> đơn</span>
            </div>
            <div class="gente-card-body" style="display: flex; align-items: center; justify-content: space-around; gap: 12px; padding: 14px;">
                <div style="position: relative; width: 140px; height: 140px; flex-shrink: 0;">
                    <canvas id="miniDonutChart"></canvas>
                </div>
                <div style="font-size: 0.72rem; display: flex; flex-direction: column; gap: 6px; flex: 1;">
                    <?php 
                    $sliceLimit = 0;
                    foreach ($breakdown as $sb):
                        if (++$sliceLimit > 5) break;
                        $st = $sb['status'] ?? '';
                        $stName = $statusNames[$st] ?? $st;
                        $cnt = (int)($sb['cnt'] ?? $sb['count'] ?? 0);
                        $pct = round(($cnt / $totalOrdCount) * 100, 1);
                        $color = $statusColors[$st] ?? '#26B99A';
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="display: flex; align-items: center; gap: 5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= $color ?>; flex-shrink: 0;"></span>
                                <?= htmlspecialchars($stName) ?>
                            </span>
                            <span style="font-weight: 700; color: inherit;"><?= $pct ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="gente-card" style="margin-bottom: 0;">
            <div class="gente-card-header">
                <h4 class="gente-card-title">
                    <span>⚡</span> Thẩm định & Đối soát
                    <span class="gente-card-subtitle">Ràng buộc hệ thống</span>
                </h4>
                <span style="font-size: 0.7rem; color: #26B99A; font-weight: 700;">ĐẠT 100%</span>
            </div>
            <div class="gente-card-body" style="display: flex; flex-direction: column; justify-content: space-between; height: calc(100% - 50px);">
                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: rgba(38,185,154,0.06); border-radius: 4px;">
                        <span style="color: #26B99A; font-weight: 700;">✔</span>
                        <span><strong>Tồn kho an toàn:</strong> Không bao giờ bị âm kho</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: rgba(243,156,18,0.06); border-radius: 4px;">
                        <span style="color: #F39C12; font-weight: 700;">⏳</span>
                        <span><strong>Chống Oversell:</strong> Khóa giữ 15 phút (TTL)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: rgba(231,76,60,0.06); border-radius: 4px;">
                        <span style="color: #E74C3C; font-weight: 700;">🔄</span>
                        <span><strong>Đổi trả an toàn:</strong> Chỉ hoàn trên hàng đã nhận</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: rgba(52,152,219,0.06); border-radius: 4px;">
                        <span style="color: #3498DB; font-weight: 700;">🔒</span>
                        <span><strong>Đóng băng giá:</strong> Snapshot giá đơn bất biến</span>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap;">
                    <a href="/admin/orders.php" class="gente-btn-action" style="flex: 1; justify-content: center;">
                        📦 Xem đơn hàng
                    </a>
                    <a href="/admin/returns.php" class="gente-btn-action" style="flex: 1; justify-content: center;">
                        🔄 Thẩm định đổi trả
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<script src="/assets/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.05)';
    const textColor = isDark ? '#adb5bd' : '#73879C';

    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const countData = <?= json_encode($chartCounts) ?>;
    const amountData = <?= json_encode($chartAmounts) ?>;
    const bgColors = <?= json_encode($chartBgColors) ?>;

    let activeMode = 'amount';

    const ctxArea = document.getElementById('businessAreaChart').getContext('2d');
    const gradient = ctxArea.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(38, 185, 154, 0.45)');
    gradient.addColorStop(1, 'rgba(38, 185, 154, 0.02)');

    const areaChart = new Chart(ctxArea, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu tiền chốt (đ)',
                data: amountData,
                borderColor: '#26B99A',
                backgroundColor: gradient,
                borderWidth: 2.5,
                pointBackgroundColor: '#26B99A',
                pointBorderColor: isDark ? '#212529' : '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.38
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? '#1a2226' : '#2A3F54',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 10,
                    cornerRadius: 4,
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            if (activeMode === 'amount') {
                                return ' Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(val) + ' đ';
                            }
                            return ' Số lượng: ' + val + ' đơn';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: textColor,
                        font: { size: 10.5 }
                    }
                },
                y: {
                    grid: {
                        color: gridColor,
                        borderDash: [3, 3]
                    },
                    ticks: {
                        color: textColor,
                        font: { size: 10.5 },
                        callback: function(val) {
                            if (activeMode === 'amount') {
                                if (val >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                                if (val >= 1000) return (val / 1000).toFixed(0) + 'k';
                                return val;
                            }
                            return val;
                        }
                    }
                }
            }
        }
    });

    const ctxDonut = document.getElementById('miniDonutChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: countData,
                backgroundColor: bgColors,
                borderColor: isDark ? '#2A3F54' : '#ffffff',
                borderWidth: 2,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ` ${ctx.label}: ${ctx.raw} đơn`;
                        }
                    }
                }
            },
            cutout: '72%'
        }
    });

    window.switchChartMode = function(mode) {
        activeMode = mode;
        const btnAmount = document.getElementById('btnModeAmount');
        const btnCount = document.getElementById('btnModeCount');

        if (mode === 'amount') {
            areaChart.data.datasets[0].label = 'Doanh thu tiền chốt (đ)';
            areaChart.data.datasets[0].data = amountData;
            btnAmount.className = 'gente-btn-toggle active';
            btnCount.className = 'gente-btn-toggle';
        } else {
            areaChart.data.datasets[0].label = 'Số lượng đơn hàng (đơn)';
            areaChart.data.datasets[0].data = countData;
            btnAmount.className = 'gente-btn-toggle';
            btnCount.className = 'gente-btn-toggle active';
        }
        areaChart.update();
    };
});
</script>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
