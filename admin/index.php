<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/AuditLogService.php';
require_once __DIR__ . '/../src/ReturnService.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);
$user = currentUser();
$db = db();

$orderService = new OrderService($db);
$orderService->checkAndExpireReservations();

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

$returnStats = fetchOne(queryParams($db, "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'REQUESTED' THEN 1 ELSE 0 END) AS pending_review,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
        COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN total_refund_amount ELSE 0 END), 0) AS total_refunded
    FROM returns
")) ?: [];

$financeStats = fetchOne(queryParams($db, "
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END), 0) AS total_received,
        COUNT(CASE WHEN status = 'SUCCESS' THEN 1 END) AS successful_payments
    FROM payment_events
")) ?: [];

$stockStats = fetchOne(queryParams($db, "
    SELECT 
        COUNT(DISTINCT product_id) AS total_products,
        COUNT(id) AS total_variants,
        SUM(CASE WHEN stock_quantity <= 5 THEN 1 ELSE 0 END) AS low_stock
    FROM variants
")) ?: [];

$statusBreakdown = fetchAll(queryParams($db, "
    SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total_val
    FROM orders
    GROUP BY status
    ORDER BY cnt DESC
"));

$statusNames = [
    'PAID' => 'Đã thanh toán',
    'PROCESSING' => 'Đang xử lý',
    'SHIPPED' => 'Đang giao',
    'DELIVERED' => 'Đã giao',
    'PENDING' => 'Chờ thanh toán',
    'CANCELLED' => 'Đã hủy',
    'EXPIRED' => 'Hết hạn giữ',
    'PARTIALLY_RETURNED' => 'Đổi trả 1 phần',
    'RETURNED' => 'Đã trả toàn bộ',
];

$statusColors = [
    'PAID' => '#2563eb',
    'PROCESSING' => '#8b5cf6',
    'SHIPPED' => '#06b6d4',
    'DELIVERED' => '#10b981',
    'PENDING' => '#f59e0b',
    'CANCELLED' => '#ef4444',
    'EXPIRED' => '#b91c1c',
    'PARTIALLY_RETURNED' => '#f97316',
    'RETURNED' => '#6b7280',
];

$chartLabels = [];
$chartCounts = [];
$chartAmounts = [];
$chartBgColors = [];
$totalOrderVal = 0.0;
$totalOrdCount = max(1, (int)($orderStats['total'] ?? 0));

foreach ($statusBreakdown as $sb) {
    $st = $sb['status'] ?? '';
    $cnt = (int)$sb['cnt'];
    $val = (float)$sb['total_val'];
    $totalOrderVal += $val;
    $chartLabels[] = $statusNames[$st] ?? $st;
    $chartCounts[] = $cnt;
    $chartAmounts[] = $val;
    $chartBgColors[] = $statusColors[$st] ?? '#64748b';
}

require __DIR__ . '/../templates/admin_header.php';
?>

<style>
.stat-card-pro {
    background: var(--admin-card-bg, #ffffff);
    border: 1px solid var(--admin-border-color, #e2e8f0);
    border-radius: 6px;
    padding: 14px 16px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.stat-card-pro:hover {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.07);
}

.stat-card-pro.accent-blue   { border-top: 3px solid #2563eb; }
.stat-card-pro.accent-red    { border-top: 3px solid #dc2626; }
.stat-card-pro.accent-green  { border-top: 3px solid #16a34a; }
.stat-card-pro.accent-amber  { border-top: 3px solid #d97706; }

.stat-label-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.stat-title {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--admin-text-muted, #64748b);
}

.stat-badge-soft {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
}

.stat-num-val {
    font-size: 1.55rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--admin-text-main, #1e293b);
    letter-spacing: -0.5px;
    margin-bottom: 4px;
}

.stat-footer-link {
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 6px;
}

.stat-footer-link:hover {
    text-decoration: underline;
}

.btn-chart-mode {
    padding: 3px 9px;
    font-size: 0.725rem;
    font-weight: 600;
    border-radius: 4px;
    border: 1px solid var(--admin-border-color, #e2e8f0);
    background: transparent;
    color: var(--admin-text-muted, #64748b);
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-chart-mode.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #ffffff;
}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px;">
    <div>
        <h2 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: inherit;">Bảng điều khiển tổng quan</h2>
        <span style="font-size: 0.78rem; color: var(--admin-text-muted, #64748b);">
            Phiên làm việc: <strong><?= e($user['full_name']) ?></strong> • Phụ Kiện Xe VIP
        </span>
    </div>
    <div style="display: flex; align-items: center; gap: 8px;">
        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; font-size: 0.72rem; font-weight: 500; border-radius: 4px; background: rgba(38, 185, 154, 0.1); color: #26b99a; border: 1px solid rgba(38, 185, 154, 0.2);">
            <span style="width: 7px; height: 7px; border-radius: 50%; background: #26b99a; display: inline-block;"></span>
            Máy chủ kết nối ổn định
        </span>
        <a href="/admin/index.php" class="btn-admin btn-admin-secondary" style="height: 28px; padding: 0 10px; font-size: 0.72rem; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px; text-decoration: none;">
            Làm mới
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 18px;">
    <div class="stat-card-pro accent-blue">
        <div>
            <div class="stat-label-row">
                <span class="stat-title">Đơn cần xuất kho</span>
                <span class="stat-badge-soft" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;">Chờ đóng gói</span>
            </div>
            <div class="stat-num-val" style="color: #2563eb;"><?= number_format((int)$orderStats['need_ship']) ?></div>
            <div style="font-size: 0.72rem; color: var(--admin-text-muted, #64748b);">Đơn đã thanh toán, sẵn sàng bàn giao</div>
        </div>
        <a href="/admin/orders.php?status=PAID" class="stat-footer-link" style="color: #2563eb;">
            Xử lý xuất kho ngay &rarr;
        </a>
    </div>

    <div class="stat-card-pro accent-red">
        <div>
            <div class="stat-label-row">
                <span class="stat-title">Đổi trả chờ duyệt</span>
                <span class="stat-badge-soft" style="background: rgba(220, 38, 38, 0.1); color: #dc2626;"><?= (int)$returnStats['pending_review'] ?> chờ duyệt</span>
            </div>
            <div class="stat-num-val" style="color: #dc2626;"><?= number_format((int)$returnStats['pending_review']) ?></div>
            <div style="font-size: 0.72rem; color: var(--admin-text-muted, #64748b);">Hồ sơ khách yêu cầu hoàn tiền</div>
        </div>
        <a href="/admin/returns.php?status=REQUESTED" class="stat-footer-link" style="color: #dc2626;">
            Thẩm định hồ sơ &rarr;
        </a>
    </div>

    <div class="stat-card-pro accent-green">
        <div>
            <div class="stat-label-row">
                <span class="stat-title">Doanh thu thực thu</span>
                <span class="stat-badge-soft" style="background: rgba(22, 163, 74, 0.1); color: #16a34a;">Cổng thanh toán</span>
            </div>
            <div class="stat-num-val" style="color: #16a34a; font-size: 1.35rem;"><?= formatMoney((float)$financeStats['total_received']) ?></div>
            <div style="font-size: 0.72rem; color: var(--admin-text-muted, #64748b);">Dòng tiền đã chốt qua cổng thanh toán</div>
        </div>
        <a href="/admin/reports.php" class="stat-footer-link" style="color: #16a34a;">
            Đối soát tài chính &rarr;
        </a>
    </div>

    <div class="stat-card-pro accent-amber">
        <div>
            <div class="stat-label-row">
                <span class="stat-title">Kho sản phẩm</span>
                <span class="stat-badge-soft" style="background: rgba(217, 119, 6, 0.1); color: #d97706;">Sẵn sàng</span>
            </div>
            <div class="stat-num-val" style="color: #d97706;"><?= number_format((int)$stockStats['total_variants']) ?> <small style="font-size: 0.8rem; font-weight: 500; color: inherit;">loại</small></div>
            <div style="font-size: 0.72rem; color: var(--admin-text-muted, #64748b);"><?= number_format((int)$stockStats['total_products']) ?> mặt hàng phụ kiện xe đang bán</div>
        </div>
        <a href="/admin/products.php" class="stat-footer-link" style="color: #d97706;">
            Quản lý kho hàng &rarr;
        </a>
    </div>
</div>

<div class="card-admin" style="margin-bottom: 20px;">
    <div class="card-admin-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; padding: 12px 16px;">
        <div>
            <h3 class="card-admin-title" style="margin: 0; font-size: 0.95rem;">
                Phân bổ doanh thu & cơ cấu đơn hàng theo vòng đời
            </h3>
            <span style="font-size: 0.75rem; color: var(--admin-text-muted, #64748b);">
                Theo dõi trực quan dòng tiền và tỷ trọng xử lý trên toàn sàn
            </span>
        </div>
        <div style="display: flex; align-items: center; gap: 6px;">
            <button type="button" id="btnModeAmount" class="btn-chart-mode active" onclick="switchMainChart('amount')">
                Doanh thu tiền chốt
            </button>
            <button type="button" id="btnModeCount" class="btn-chart-mode" onclick="switchMainChart('count')">
                Số lượng đơn
            </button>
        </div>
    </div>
    <div class="card-admin-body" style="padding: 16px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; align-items: center;">
            <div>
                <div style="position: relative; width: 100%; height: 250px;">
                    <canvas id="mainDashboardChart"></canvas>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; font-size: 0.72rem; color: var(--admin-text-muted, #64748b);">
                    <span>Tổng đơn ghi nhận: <strong><?= (int)$orderStats['total'] ?> đơn</strong></span>
                    <span>Tổng giá trị đơn chốt: <strong style="color: #2563eb;"><?= formatMoney($totalOrderVal) ?></strong></span>
                </div>
            </div>

            <div style="border-left: 1px solid var(--admin-border-color, #e2e8f0); padding-left: 18px;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--admin-text-muted, #64748b); margin-bottom: 8px;">
                    Cơ cấu đơn hàng
                </div>
                <div style="position: relative; width: 100%; height: 160px;">
                    <canvas id="donutDashboardChart"></canvas>
                </div>
                <div style="display: flex; flex-direction: column; gap: 5px; font-size: 0.72rem; margin-top: 10px;">
                    <?php 
                    $limit = 0;
                    foreach ($statusBreakdown as $sb):
                        if (++$limit > 4) break;
                        $st = $sb['status'] ?? '';
                        $stName = $statusNames[$st] ?? $st;
                        $cnt = (int)$sb['cnt'];
                        $pct = round(($cnt / $totalOrdCount) * 100, 1);
                        $color = $statusColors[$st] ?? '#64748b';
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="display: flex; align-items: center; gap: 5px;">
                                <span style="width: 7px; height: 7px; border-radius: 50%; background: <?= $color ?>; display: inline-block;"></span>
                                <?= htmlspecialchars($stName) ?>
                            </span>
                            <strong><?= $pct ?>%</strong>
                        </div>
                    <?php endforeach; ?>
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
    const textColor = isDark ? '#adb5bd' : '#64748b';

    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const countData = <?= json_encode($chartCounts) ?>;
    const amountData = <?= json_encode($chartAmounts) ?>;
    const bgColors = <?= json_encode($chartBgColors) ?>;

    let currentMode = 'amount';

    const ctxMain = document.getElementById('mainDashboardChart').getContext('2d');
    const gradient = ctxMain.createLinearGradient(0, 0, 0, 240);
    gradient.addColorStop(0, 'rgba(37, 99, 235, 0.35)');
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0.02)');

    const mainChart = new Chart(ctxMain, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Doanh thu tiền chốt (đ)',
                data: amountData,
                borderColor: '#2563eb',
                backgroundColor: gradient,
                borderWidth: 2.2,
                pointBackgroundColor: '#2563eb',
                pointBorderColor: isDark ? '#1e293b' : '#ffffff',
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
                    backgroundColor: isDark ? '#0f172a' : '#1e293b',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 8,
                    cornerRadius: 4,
                    callbacks: {
                        label: function(ctx) {
                            const val = ctx.raw || 0;
                            if (currentMode === 'amount') {
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
                        font: { size: 10 }
                    }
                },
                y: {
                    grid: {
                        color: gridColor,
                        borderDash: [3, 3]
                    },
                    ticks: {
                        color: textColor,
                        font: { size: 10 },
                        callback: function(val) {
                            if (currentMode === 'amount') {
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

    const ctxDonut = document.getElementById('donutDashboardChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: countData,
                backgroundColor: bgColors,
                borderColor: isDark ? '#1e293b' : '#ffffff',
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
            cutout: '70%'
        }
    });

    window.switchMainChart = function(mode) {
        currentMode = mode;
        const btnAmount = document.getElementById('btnModeAmount');
        const btnCount = document.getElementById('btnModeCount');

        if (mode === 'amount') {
            mainChart.data.datasets[0].label = 'Doanh thu tiền chốt (đ)';
            mainChart.data.datasets[0].data = amountData;
            btnAmount.className = 'btn-chart-mode active';
            btnCount.className = 'btn-chart-mode';
        } else {
            mainChart.data.datasets[0].label = 'Số lượng đơn hàng (đơn)';
            mainChart.data.datasets[0].data = countData;
            btnAmount.className = 'btn-chart-mode';
            btnCount.className = 'btn-chart-mode active';
        }
        mainChart.update();
    };
});
</script>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
