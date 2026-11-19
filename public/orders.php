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
if ($statusFilter !== '' && $statusFilter !== 'ALL') {
    if ($statusFilter === 'PARTIALLY_RETURNED') {
        $filter['status'] = 'PARTIALLY_RETURNED';
    } else {
        $filter['status'] = $statusFilter;
    }
}

$searchQuery = field($_GET, 'q');
if ($searchQuery !== '') {
    $filter['search'] = $searchQuery;
}

$orders = $orderService->listOrders($filter);

// Đếm số lượng đơn theo từng trạng thái chuẩn sàn TMĐT lớn
$countParams = [];
$countSql = "SELECT status, COUNT(*) as cnt FROM orders WHERE 1=1";
if ($user['role'] === 'customer') {
    $countSql .= " AND user_id = ?";
    $countParams[] = (int)$user['id'];
}
$countSql .= " GROUP BY status";
$cntStmt = queryParams($db, $countSql, $countParams);
$statusCounts = [];
$totalOrdersCount = 0;
foreach (fetchAll($cntStmt) as $cr) {
    $statusCounts[$cr['status']] = (int)$cr['cnt'];
    $totalOrdersCount += (int)$cr['cnt'];
}

$tabs = [
    'ALL' => ['label' => 'Tất cả', 'count' => $totalOrdersCount, 'icon' => '📦'],
    'PENDING' => ['label' => 'Chờ thanh toán', 'count' => $statusCounts['PENDING'] ?? 0, 'icon' => '⏳'],
    'PAID' => ['label' => 'Đã thanh toán', 'count' => ($statusCounts['PAID'] ?? 0) + ($statusCounts['PROCESSING'] ?? 0), 'icon' => '💳'],
    'SHIPPED' => ['label' => 'Đang giao', 'count' => $statusCounts['SHIPPED'] ?? 0, 'icon' => '🚚'],
    'DELIVERED' => ['label' => 'Đã giao', 'count' => $statusCounts['DELIVERED'] ?? 0, 'icon' => '✓'],
    'PARTIALLY_RETURNED' => ['label' => 'Đổi trả', 'count' => ($statusCounts['PARTIALLY_RETURNED'] ?? 0) + ($statusCounts['RETURNED'] ?? 0), 'icon' => '🔄'],
    'CANCELLED' => ['label' => 'Đã hủy', 'count' => ($statusCounts['CANCELLED'] ?? 0) + ($statusCounts['EXPIRED'] ?? 0), 'icon' => '✕']
];

require __DIR__ . '/../templates/header.php';
?>

<style>
/* ==========================================================================
   CSS DANH SÁCH ĐƠN HÀNG - CHUẨN SÀN THƯƠNG MẠI ĐIỆN TỬ LỚN (SHOPEE/LAZADA/TIKI)
   Font chữ nhỏ gọn (11px - 14px), mật độ thông tin tối ưu, tinh tế & sắc nét
   ========================================================================== */
.orders-page-wrapper {
    max-width: 1200px;
    margin: 18px auto 48px;
    padding: 0 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #e5e7eb;
    font-size: 0.8125rem; /* Chuẩn 13px */
}

/* 1. Header & Quick Search Bar */
.orders-top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}
.orders-page-title {
    margin: 0;
    font-size: 1.15rem; /* 18.4px */
    font-weight: 700;
    color: #f3f4f6;
    display: flex;
    align-items: center;
    gap: 8px;
}
.orders-page-sub {
    margin: 2px 0 0;
    font-size: 0.75rem; /* 12px */
    color: #9ca3af;
}
.orders-search-form {
    display: flex;
    align-items: center;
    background: #181818;
    border: 1px solid #2e2e2e;
    border-radius: 8px;
    padding: 3px 4px 3px 12px;
    min-width: 280px;
    transition: all 0.2s ease;
}
.orders-search-form:focus-within {
    border-color: #dfa056;
    box-shadow: 0 0 10px rgba(223, 160, 86, 0.2);
}
.orders-search-input {
    background: transparent;
    border: none;
    outline: none;
    color: #f3f4f6;
    font-size: 0.8125rem; /* 13px */
    width: 100%;
}
.orders-search-input::placeholder {
    color: #6b7280;
    font-size: 0.78rem;
}
.orders-search-btn {
    background: #242424;
    border: 1px solid #383838;
    color: #dfa056;
    padding: 5px 12px;
    border-radius: 5px;
    font-size: 0.75rem; /* 12px */
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.orders-search-btn:hover {
    background: #dfa056;
    color: #121212;
}

/* 2. Thanh Tabs trạng thái nhỏ gọn (Shopee Tab Bar) */
.orders-tabs-container {
    background: #181818;
    border: 1px solid #282828;
    border-radius: 10px;
    padding: 4px;
    margin-bottom: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
    overflow-x: auto;
}
.orders-tabs-scroll {
    display: flex;
    gap: 4px;
    min-width: 640px;
}
.orders-tab-pill {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.8125rem; /* 13px */
    font-weight: 600;
    color: #9ca3af;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.orders-tab-pill:hover {
    color: #f3f4f6;
    background: rgba(255, 255, 255, 0.04);
}
.orders-tab-pill.active {
    background: linear-gradient(135deg, #dfa056 0%, #c98a44 100%);
    color: #120e0b;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(223, 160, 86, 0.3);
}
.orders-tab-badge {
    background: rgba(0, 0, 0, 0.25);
    color: inherit;
    font-size: 0.6875rem; /* 11px */
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
    line-height: 1.2;
}
.orders-tab-pill.active .orders-tab-badge {
    background: rgba(18, 14, 11, 0.35);
    color: #120e0b;
}

/* 3. Danh sách thẻ đơn hàng Card-Based (Kích thước gọn) */
.orders-list-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.order-card {
    background: #181818;
    border: 1px solid #282828;
    border-radius: 10px;
    box-shadow: 0 3px 14px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.order-card:hover {
    border-color: #383838;
    transform: translateY(-1px);
}

/* 4. Order Card Header */
.order-card-header {
    background: #151515;
    padding: 9px 18px;
    border-bottom: 1px solid #242424;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.order-meta-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.order-code-chip {
    font-family: 'SF Mono', Consolas, Monaco, monospace;
    font-size: 0.8125rem; /* 13px */
    font-weight: 700;
    color: #f5b061;
    background: #201a14;
    border: 1px solid rgba(223, 160, 86, 0.35);
    padding: 2px 7px;
    border-radius: 4px;
    letter-spacing: 0.3px;
}
.order-copy-btn {
    background: #242424;
    border: 1px solid #383838;
    color: #9ca3af;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.6875rem; /* 11px */
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    line-height: 1;
}
.order-copy-btn:hover {
    background: #333333;
    color: #dfa056;
    border-color: #dfa056;
}
.order-time-text {
    font-size: 0.75rem; /* 12px */
    color: #858c97;
    display: flex;
    align-items: center;
    gap: 4px;
}

/* 5. Soft Glow Status Pills (Nhỏ nhắn 11.5px, chuẩn TMĐT) */
.soft-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.72rem; /* 11.5px */
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}
.soft-status-pill.pending {
    background: rgba(245, 158, 11, 0.12);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.35);
}
.soft-status-pill.delivered {
    background: rgba(34, 197, 94, 0.12);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.35);
}
.soft-status-pill.paid {
    background: rgba(14, 165, 233, 0.12);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.35);
}
.soft-status-pill.shipped {
    background: rgba(59, 130, 246, 0.12);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.35);
}
.soft-status-pill.returned {
    background: rgba(16, 185, 129, 0.12);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.35);
}
.soft-status-pill.cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.soft-status-pill.expired {
    background: rgba(156, 163, 175, 0.1);
    color: #9ca3af;
    border: 1px solid rgba(156, 163, 175, 0.25);
}

/* 6. Order Card Body & Clean Typography (KHÔNG in thừa giá) */
.order-card-body {
    padding: 12px 18px;
}
.order-item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 9px 0;
    border-bottom: 1px solid #202020;
    gap: 14px;
}
.order-item-row:last-child {
    border-bottom: none;
    padding-bottom: 2px;
}
.order-item-row:first-child {
    padding-top: 2px;
}
.order-item-left {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}
.order-item-thumb {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #2e2e2e;
    background: #1e1e1e;
    flex-shrink: 0;
}
.order-item-info {
    min-width: 0;
}
.order-item-name {
    font-size: 0.84rem; /* 13.5px */
    font-weight: 600;
    color: #f3f4f6;
    margin-bottom: 4px;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.order-item-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.order-variant-chip {
    background: #222222;
    border: 1px solid #333333;
    color: #9ca3af;
    padding: 1px 7px;
    border-radius: 4px;
    font-size: 0.72rem; /* 11.5px */
    font-weight: 500;
}
.order-qty-chip {
    color: #9ca3af;
    font-size: 0.75rem; /* 12px */
    font-weight: 600;
}

/* Cột giá bên phải - Gọn gàng thanh lịch */
.order-item-right {
    text-align: right;
    white-space: nowrap;
}
.order-item-calc {
    font-size: 0.72rem; /* 11.5px */
    color: #858c97;
    margin-bottom: 1px;
}
.order-item-subtotal {
    font-size: 0.875rem; /* 14px */
    font-weight: 700;
    color: #dfa056;
    letter-spacing: 0.2px;
}

/* 7. Order Card Footer */
.order-card-footer {
    background: #141414;
    border-top: 1px solid #222222;
    padding: 10px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.order-footer-left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem; /* 12px */
    color: #858c97;
    flex: 1;
    min-width: 240px;
}
.order-hold-pill {
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #fbbf24;
    font-size: 0.72rem; /* 11.5px */
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.order-refund-pill {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.35);
    color: #4ade80;
    font-size: 0.72rem; /* 11.5px */
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.order-address-text {
    color: #858c97;
    line-height: 1.35;
}
.order-address-text strong {
    color: #c9ced6;
}

/* Tổng tiền & Nút bấm chân thẻ */
.order-footer-right {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-left: auto;
    flex-wrap: wrap;
}
.order-total-block {
    text-align: right;
    white-space: nowrap;
}
.order-total-label {
    font-size: 0.75rem; /* 12px */
    color: #9ca3af;
    margin-right: 4px;
}
.order-total-val {
    font-size: 1.15rem; /* 18.4px - Chuẩn Shopee Mall */
    font-weight: 800;
    color: #f5b061;
    letter-spacing: 0.3px;
}
.order-actions-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}
.ord-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.78rem; /* 12.5px */
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    white-space: nowrap;
}
.ord-btn-pay {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
}
.ord-btn-pay:hover {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #ffffff;
}
.ord-btn-return {
    background: rgba(234, 88, 12, 0.12);
    border: 1px solid rgba(234, 88, 12, 0.4);
    color: #fb923c;
}
.ord-btn-return:hover {
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    color: #ffffff;
}
.ord-btn-detail {
    background: #242424;
    border: 1px solid #383838;
    color: #d1d5db;
}
.ord-btn-detail:hover {
    background: #2e2e2e;
    border-color: #dfa056;
    color: #dfa056;
}

/* 8. Empty State */
.orders-empty-card {
    text-align: center;
    padding: 48px 20px;
    background: #181818;
    border: 1px solid #282828;
    border-radius: 10px;
}
.orders-empty-icon {
    font-size: 2.75rem;
    margin-bottom: 12px;
    display: inline-block;
}
.orders-empty-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #f3f4f6;
    margin: 0 0 6px;
}
.orders-empty-desc {
    font-size: 0.8125rem;
    color: #9ca3af;
    max-width: 440px;
    margin: 0 auto 18px;
    line-height: 1.45;
}
.orders-empty-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #dfa056 0%, #c98a44 100%);
    color: #120e0b;
    font-weight: 700;
    padding: 8px 20px;
    border-radius: 6px;
    font-size: 0.8125rem;
    text-decoration: none;
}

/* Responsive */
@media (max-width: 768px) {
    .orders-top-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    .orders-search-form {
        width: 100%;
        min-width: 100%;
    }
    .order-card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .order-item-row {
        flex-direction: column;
        align-items: flex-start;
    }
    .order-item-right {
        text-align: left;
        width: 100%;
        display: flex;
        justify-content: space-between;
        margin-top: 4px;
    }
    .order-card-footer {
        flex-direction: column;
        align-items: flex-start;
    }
    .order-footer-right {
        margin-left: 0;
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    .order-total-block {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }
    .order-actions-wrap {
        width: 100%;
    }
    .ord-btn {
        flex: 1;
        justify-content: center;
    }
}
</style>

<div class="orders-page-wrapper">
    <!-- 1. Thanh tiêu đề & Tìm kiếm nhanh -->
    <div class="orders-top-bar">
        <div>
            <h1 class="orders-page-title">
                <span>📦</span> Đơn hàng của tôi
            </h1>
            <p class="orders-page-sub">
                Theo dõi tiến trình vận chuyển, kiểm soát giữ hàng và yêu cầu đổi trả bảo hành
            </p>
        </div>

        <form method="get" action="/orders.php" class="orders-search-form">
            <?php if (!empty($statusFilter) && $statusFilter !== 'ALL'): ?>
                <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Tìm theo mã đơn hoặc sản phẩm..." class="orders-search-input">
            <button type="submit" class="orders-search-btn">Tìm kiếm</button>
        </form>
    </div>

    <!-- 2. Apple Segmented Pill Tabs -->
    <div class="orders-tabs-container">
        <div class="orders-tabs-scroll">
            <?php foreach ($tabs as $stKey => $tab): ?>
                <?php 
                    $isActive = ($stKey === 'ALL' && empty($statusFilter)) || ($statusFilter === $stKey);
                    $tabUrl = $stKey === 'ALL' ? '/orders.php' : '/orders.php?status=' . urlencode($stKey);
                    if (!empty($searchQuery)) {
                        $tabUrl .= (strpos($tabUrl, '?') !== false ? '&' : '?') . 'q=' . urlencode($searchQuery);
                    }
                ?>
                <a href="<?= $tabUrl ?>" class="orders-tab-pill <?= $isActive ? 'active' : '' ?>">
                    <span><?= $tab['icon'] ?></span>
                    <span><?= e($tab['label']) ?></span>
                    <span class="orders-tab-badge"><?= (int)$tab['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 3. Danh sách đơn hàng Card-Based -->
    <?php if (empty($orders)): ?>
        <div class="orders-empty-card">
            <div class="orders-empty-icon">🛒</div>
            <h2 class="orders-empty-title">Chưa tìm thấy đơn hàng nào</h2>
            <p class="orders-empty-desc">
                <?php if (!empty($searchQuery)): ?>
                    Không có đơn hàng nào khớp với từ khóa "<strong><?= e($searchQuery) ?></strong>". Vui lòng thử tìm kiếm với từ khóa khác.
                <?php else: ?>
                    Bạn chưa có đơn hàng nào trong danh mục này. Hãy khám phá ngay các món phụ kiện xe hơi cao cấp của chúng tôi!
                <?php endif; ?>
            </p>
            <a href="/" class="orders-empty-btn">
                <span>🛍️</span> Khám phá phụ kiện xe hơi ngay
            </a>
        </div>
    <?php else: ?>
        <div class="orders-list-grid">
            <?php foreach ($orders as $ord): ?>
                <?php 
                    $fullOrder = $orderService->getOrderById((int)$ord['id']);
                    $items = $fullOrder['items'] ?? [];
                    $st = $ord['status'];

                    // Phân loại Soft Glow Pill theo chuẩn Apple / Sàn TMĐT
                    $pillClass = 'pending';
                    $pillLabel = 'Chờ thanh toán';
                    $pillIcon = '⏳';

                    switch ($st) {
                        case 'PENDING':
                            $pillClass = 'pending';
                            $pillLabel = 'Chờ thanh toán';
                            $pillIcon = '⏳';
                            break;
                        case 'PAID':
                            $pillClass = 'paid';
                            $pillLabel = 'Đã thanh toán';
                            $pillIcon = '💳';
                            break;
                        case 'PROCESSING':
                            $pillClass = 'paid';
                            $pillLabel = 'Đang xử lý';
                            $pillIcon = '⚙️';
                            break;
                        case 'SHIPPED':
                            $pillClass = 'shipped';
                            $pillLabel = 'Đang giao hàng';
                            $pillIcon = '🚚';
                            break;
                        case 'DELIVERED':
                            $pillClass = 'delivered';
                            $pillLabel = 'Đã giao thành công';
                            $pillIcon = '✓';
                            break;
                        case 'PARTIALLY_RETURNED':
                            $pillClass = 'returned';
                            $pillLabel = 'Đổi trả một phần';
                            $pillIcon = '🔄';
                            break;
                        case 'RETURNED':
                            $pillClass = 'returned';
                            $pillLabel = 'Đã trả toàn bộ';
                            $pillIcon = '🛡️';
                            break;
                        case 'CANCELLED':
                            $pillClass = 'cancelled';
                            $pillLabel = 'Đã hủy';
                            $pillIcon = '✕';
                            break;
                        case 'EXPIRED':
                            $pillClass = 'expired';
                            $pillLabel = 'Hết hạn giữ hàng';
                            $pillIcon = '⏱️';
                            break;
                    }
                ?>
                <div class="order-card">
                    <!-- Header Đơn hàng -->
                    <div class="order-card-header">
                        <div class="order-meta-left">
                            <span style="font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase;">MÃ ĐƠN:</span>
                            <span class="order-code-chip"><?= e($ord['order_code']) ?></span>
                            <button type="button" class="order-copy-btn" onclick="copyOrderCode('<?= e($ord['order_code']) ?>')" title="Sao chép mã đơn">
                                📋 Copy
                            </button>
                            <span class="order-time-text">
                                • Ngày đặt: <?= formatDate($ord['created_at']) ?>
                            </span>
                        </div>
                        <div>
                            <span class="soft-status-pill <?= $pillClass ?>">
                                <span><?= $pillIcon ?></span>
                                <span><?= e($pillLabel) ?></span>
                            </span>
                        </div>
                    </div>

                    <!-- Danh sách sản phẩm (Typography chuẩn TMĐT 11-14px, KHÔNG in thừa giá) -->
                    <div class="order-card-body">
                        <?php foreach ($items as $it): ?>
                            <?php $qty = (int)$it['quantity']; ?>
                            <div class="order-item-row">
                                <div class="order-item-left">
                                    <?php if (!empty($it['image_url'])): ?>
                                        <img src="<?= e(productImageUrl($it['image_url'])) ?>" alt="<?= e($it['product_name']) ?>" class="order-item-thumb">
                                    <?php endif; ?>
                                    <div class="order-item-info">
                                        <div class="order-item-name"><?= e($it['product_name']) ?></div>
                                        <div class="order-item-meta">
                                            <span class="order-variant-chip"><?= e($it['variant_info']) ?></span>
                                            <span class="order-qty-chip">Số lượng: <strong>×<?= $qty ?></strong></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="order-item-right">
                                    <?php if ($qty > 1): ?>
                                        <div class="order-item-calc"><?= formatMoney((float)$it['unit_price']) ?> × <?= $qty ?></div>
                                    <?php endif; ?>
                                    <div class="order-item-subtotal"><?= formatMoney((float)$it['subtotal']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Footer Đơn hàng: Thông tin giữ hàng/giao hàng & Tổng tiền -->
                    <div class="order-card-footer">
                        <div class="order-footer-left">
                            <?php if ($ord['status'] === 'PENDING' && $ord['expires_at']): ?>
                                <span class="order-hold-pill">
                                    <span>⏱️</span>
                                    <span>Khóa giữ hàng đến: <strong><?= date('H:i:s d/m', strtotime($ord['expires_at'])) ?></strong></span>
                                </span>
                            <?php elseif (in_array($ord['status'], ['PARTIALLY_RETURNED', 'RETURNED'], true)): ?>
                                <span class="order-refund-pill">
                                    <span>🛡️</span>
                                    <span>Đã duyệt đổi trả & hoàn tiền</span>
                                </span>
                            <?php else: ?>
                                <span class="order-address-text">
                                    📍 Giao đến: <strong><?= e($ord['shipping_address'] ?: 'Tại showroom / Kho nhận') ?></strong>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="order-footer-right">
                            <div class="order-total-block">
                                <span class="order-total-label">Thành tiền:</span>
                                <span class="order-total-val"><?= formatMoney((float)$ord['total_amount']) ?></span>
                            </div>

                            <div class="order-actions-wrap">
                                <?php if ($ord['status'] === 'PENDING'): ?>
                                    <a href="/payment-mock.php?order_code=<?= urlencode($ord['order_code']) ?>" class="ord-btn ord-btn-pay">
                                        <span>💳</span> Thanh toán ngay
                                    </a>
                                <?php endif; ?>

                                <?php if (in_array($ord['status'], ['DELIVERED', 'PARTIALLY_RETURNED']) && $user['role'] === 'customer'): ?>
                                    <a href="/return-request.php?order_id=<?= (int)$ord['id'] ?>" class="ord-btn ord-btn-return">
                                        <span>🔄</span> Đổi trả hàng
                                    </a>
                                <?php endif; ?>

                                <a href="/order-detail.php?id=<?= (int)$ord['id'] ?>" class="ord-btn ord-btn-detail">
                                    Xem chi tiết ➔
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function copyOrderCode(code) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(function() {
            if (window.showToast) {
                window.showToast('Đã sao chép mã đơn hàng thành công!', code, 'success');
            }
        });
    } else {
        var temp = document.createElement('textarea');
        temp.value = code;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
        if (window.showToast) {
            window.showToast('Đã sao chép mã đơn hàng thành công!', code, 'success');
        }
    }
}
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
