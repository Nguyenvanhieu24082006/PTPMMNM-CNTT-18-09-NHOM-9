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

// Kiểm tra quyền truy cập
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

// Cấu hình Bậc thang tiến trình đơn hàng (Stepper)
$status = $order['status'];
$steps = [
    ['code' => 'CREATED', 'label' => 'Đã đặt hàng', 'num' => 1, 'desc' => 'Tạo đơn'],
    ['code' => 'PENDING', 'label' => 'Giữ hàng (15p)', 'num' => 2, 'desc' => 'Khóa tồn kho'],
    ['code' => 'PAID', 'label' => 'Đã thanh toán', 'num' => 3, 'desc' => 'Xác nhận cọc'],
    ['code' => 'SHIPPED', 'label' => 'Đang giao hàng', 'num' => 4, 'desc' => 'Vận chuyển'],
    ['code' => 'DELIVERED', 'label' => 'Đã giao thành công', 'num' => 5, 'desc' => 'Hoàn tất giao'],
    ['code' => 'RETURNED', 'label' => 'Đổi trả / Hoàn tiền', 'num' => 6, 'desc' => 'Hậu mãi']
];

$stepWeight = [
    'CREATED' => 1,
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
$progressPercent = $currentWeight > 1 ? min(100, (($currentWeight - 1) / (count($steps) - 1)) * 100) : 0;
?>

<style>
/* ==========================================================================
   CSS GIAO DIỆN CHI TIẾT ĐƠN HÀNG - DARK LUXURY AUTOMOTIVE (PHUKIENXEVIP)
   ========================================================================== */
.order-detail-wrapper {
    max-width: 1240px;
    margin: 24px auto 60px;
    padding: 0 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #e5e7eb;
}

/* Breadcrumb Navigation */
.od-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: #9ca3af;
    margin-bottom: 20px;
}
.od-breadcrumb a {
    color: #9ca3af;
    text-decoration: none;
    transition: color 0.2s;
}
.od-breadcrumb a:hover {
    color: #dfa056;
}
.od-breadcrumb .sep {
    color: #4b5563;
}
.od-breadcrumb .current {
    color: #dfa056;
    font-weight: 600;
}

/* Base Card */
.od-card {
    background: #181818;
    border: 1px solid #2a2a2a;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35);
}

/* Card Header with Icon */
.od-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 16px;
    margin-bottom: 20px;
    border-bottom: 1px solid #282828;
    flex-wrap: wrap;
    gap: 12px;
}
.od-card-title-group {
    display: flex;
    align-items: center;
    gap: 12px;
}
.od-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(223, 160, 86, 0.12);
    border: 1px solid rgba(223, 160, 86, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    color: #dfa056;
}
.od-card-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #f3f4f6;
    display: flex;
    align-items: center;
    gap: 8px;
}
.od-card-subtitle {
    margin: 3px 0 0;
    font-size: 0.8125rem;
    color: #9ca3af;
}

/* 1. Header Order Top Bar */
.od-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 28px;
    border-bottom: 1px solid #282828;
    flex-wrap: wrap;
    gap: 16px;
}
.od-order-id-block {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.od-order-id-label {
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #9ca3af;
    text-transform: uppercase;
}
.od-order-id-val {
    font-family: 'SF Mono', Consolas, Monaco, monospace;
    font-size: 1.25rem;
    font-weight: 800;
    color: #f5b061;
    background: #221c16;
    border: 1px solid rgba(223, 160, 86, 0.35);
    padding: 4px 12px;
    border-radius: 6px;
    letter-spacing: 0.5px;
}
.od-btn-copy {
    background: #262626;
    border: 1px solid #3a3a3a;
    color: #9ca3af;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.od-btn-copy:hover {
    background: #333333;
    color: #dfa056;
    border-color: #dfa056;
}
.od-meta-date {
    font-size: 0.8125rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Status Pill in Header */
.od-status-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}
.od-status-label {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.order-detail-wrapper .badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}
.order-detail-wrapper .badge--warning {
    background: rgba(245, 158, 11, 0.15) !important;
    color: #fbbf24 !important;
    border: 1px solid rgba(245, 158, 11, 0.45) !important;
    box-shadow: 0 0 12px rgba(245, 158, 11, 0.15);
}
.order-detail-wrapper .badge--success {
    background: rgba(34, 197, 94, 0.15) !important;
    color: #4ade80 !important;
    border: 1px solid rgba(34, 197, 94, 0.45) !important;
    box-shadow: 0 0 12px rgba(34, 197, 94, 0.15);
}
.order-detail-wrapper .badge--info {
    background: rgba(14, 165, 233, 0.15) !important;
    color: #38bdf8 !important;
    border: 1px solid rgba(14, 165, 233, 0.45) !important;
}
.order-detail-wrapper .badge--primary {
    background: rgba(223, 160, 86, 0.15) !important;
    color: #f5b061 !important;
    border: 1px solid rgba(223, 160, 86, 0.45) !important;
}
.order-detail-wrapper .badge--danger {
    background: rgba(239, 68, 68, 0.15) !important;
    color: #f87171 !important;
    border: 1px solid rgba(239, 68, 68, 0.45) !important;
}

/* 2. Modern Stepper */
.od-stepper-box {
    position: relative;
    padding: 10px 16px 20px;
}
.od-stepper-track-bg {
    position: absolute;
    top: 28px;
    left: 45px;
    right: 45px;
    height: 4px;
    background: #2a2a2a;
    border-radius: 2px;
    z-index: 1;
}
.od-stepper-track-fill {
    position: absolute;
    top: 28px;
    left: 45px;
    height: 4px;
    background: linear-gradient(90deg, #22c55e, #dfa056);
    border-radius: 2px;
    z-index: 2;
    transition: width 0.4s ease;
    box-shadow: 0 0 8px rgba(223, 160, 86, 0.5);
}
.od-stepper {
    display: flex;
    justify-content: space-between;
    position: relative;
    z-index: 3;
}
.od-step-item {
    text-align: center;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.od-step-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 700;
    background: #222222;
    border: 2px solid #3a3a3a;
    color: #6b7280;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}
.od-step-item.completed .od-step-circle {
    background: #052e16;
    border-color: #22c55e;
    color: #22c55e;
    box-shadow: 0 0 10px rgba(34, 197, 94, 0.3);
}
.od-step-item.active .od-step-circle {
    background: linear-gradient(135deg, #dfa056 0%, #c98a44 100%);
    border-color: #f5b061;
    color: #14110e;
    box-shadow: 0 0 18px rgba(223, 160, 86, 0.6);
    animation: odPulse 2s infinite;
}
@keyframes odPulse {
    0% { box-shadow: 0 0 0 0 rgba(223, 160, 86, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(223, 160, 86, 0); }
    100% { box-shadow: 0 0 0 0 rgba(223, 160, 86, 0); }
}
.od-step-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #6b7280;
    line-height: 1.3;
}
.od-step-item.completed .od-step-title {
    color: #d1d5db;
}
.od-step-item.active .od-step-title {
    color: #f5b061;
    font-weight: 700;
}
.od-step-desc {
    font-size: 0.6875rem;
    color: #4b5563;
    margin-top: 2px;
}
.od-step-item.active .od-step-desc {
    color: #dfa056;
}

/* 3. Luxury 15-Minute Lock Banner */
.od-lock-banner {
    background: linear-gradient(135deg, rgba(38, 26, 12, 0.92) 0%, rgba(20, 17, 14, 0.98) 100%);
    border: 1px solid rgba(223, 160, 86, 0.4);
    border-left: 5px solid #dfa056;
    border-radius: 14px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 215, 150, 0.1);
    flex-wrap: wrap;
    gap: 20px;
}
.od-lock-left {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    flex: 1;
    min-width: 280px;
}
.od-lock-icon-wrap {
    position: relative;
    width: 44px;
    height: 44px;
    background: rgba(223, 160, 86, 0.15);
    border: 1px solid rgba(223, 160, 86, 0.4);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.od-pulse-indicator {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 10px;
    height: 10px;
    background: #ef4444;
    border-radius: 50%;
    border: 2px solid #181818;
    animation: odPulseRed 1.5s infinite;
}
@keyframes odPulseRed {
    0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.8); }
    70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
.od-lock-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #f5b061;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.od-lock-text {
    font-size: 0.84rem;
    color: #d1d5db;
    margin: 0 0 10px;
    line-height: 1.5;
}
.od-lock-pills {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.od-lock-pill {
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(223, 160, 86, 0.3);
    color: #fde68a;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
}
.od-clock-display {
    background: #0f0d0a;
    border: 1px solid rgba(223, 160, 86, 0.45);
    border-radius: 12px;
    padding: 12px 24px;
    text-align: center;
    box-shadow: 0 0 20px rgba(223, 160, 86, 0.2);
    min-width: 180px;
}
.od-clock-caption {
    font-size: 0.6875rem;
    font-weight: 700;
    color: #9ca3af;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 4px;
}
.od-clock-digits {
    font-family: 'SF Mono', Consolas, Monaco, monospace;
    font-size: 2.1rem;
    font-weight: 900;
    color: #ff9f1c;
    letter-spacing: 2px;
    line-height: 1.1;
    text-shadow: 0 0 15px rgba(255, 159, 28, 0.5);
    font-variant-numeric: tabular-nums;
}
.od-clock-deadline {
    font-size: 0.75rem;
    color: #dfa056;
    margin-top: 4px;
}

/* 4. Thông tin nhận hàng (Shipping Box) */
.od-shipping-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
    background: #141414;
    border: 1px solid #282828;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.od-ship-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.od-ship-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.od-ship-val {
    font-size: 0.9375rem;
    color: #f3f4f6;
    font-weight: 500;
}
.od-ship-val strong {
    color: #f5b061;
}

/* 5. Products Table */
.od-table-container {
    overflow-x: auto;
    border: 1px solid #282828;
    border-radius: 10px;
    background: #151515;
    margin-bottom: 20px;
}
.od-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    color: #d1d5db;
}
.od-table th {
    background: #1e1e1e;
    color: #9ca3af;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 16px;
    border-bottom: 1px solid #2c2c2c;
    text-align: left;
    white-space: nowrap;
}
.od-table td {
    padding: 16px;
    border-bottom: 1px solid #242424;
    vertical-align: middle;
}
.od-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.02);
}
.od-prod-cell {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 240px;
}
.od-prod-thumb {
    width: 54px;
    height: 54px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #333333;
    background: #1e1e1e;
    flex-shrink: 0;
}
.od-prod-name {
    font-weight: 600;
    color: #f3f4f6;
    line-height: 1.4;
}
.od-prod-sku {
    display: inline-block;
    font-family: 'SF Mono', Consolas, Monaco, monospace;
    font-size: 0.7rem;
    color: #9ca3af;
    background: #222222;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid #333333;
    margin-top: 4px;
}
.od-variant-tag {
    background: #242424;
    border: 1px solid #383838;
    color: #d1d5db;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8125rem;
    display: inline-block;
}
.od-price-cell {
    font-weight: 700;
    color: #f3f4f6;
    white-space: nowrap;
}
.od-qty-badge {
    display: inline-block;
    background: #262626;
    border: 1px solid #3a3a3a;
    padding: 3px 10px;
    border-radius: 6px;
    font-weight: 700;
    color: #f3f4f6;
}
.od-deliv-badge {
    color: #4ade80;
    font-weight: 700;
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.3);
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.8125rem;
}
.od-ret-badge {
    color: #f87171;
    font-weight: 700;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 0.8125rem;
}
.od-subtotal-cell {
    font-weight: 800;
    color: #dfa056;
    font-size: 1rem;
    white-space: nowrap;
    text-align: right;
}

/* 6. Summary Bar & Fixed Single-Line Total */
.od-summary-bar {
    background: #141414;
    border: 1px solid #282828;
    border-radius: 12px;
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
}
.od-summary-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.od-prot-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(223, 160, 86, 0.12);
    border: 1px solid rgba(223, 160, 86, 0.35);
    color: #f5b061;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 20px;
}
.od-summary-right {
    display: flex;
    align-items: baseline;
    gap: 14px;
    margin-left: auto;
    white-space: nowrap;
}
.od-summary-label {
    font-size: 1.05rem;
    font-weight: 700;
    color: #9ca3af;
}
.od-summary-total {
    font-size: 1.85rem;
    font-weight: 900;
    color: #f5b061;
    letter-spacing: 0.5px;
    white-space: nowrap;
    text-shadow: 0 0 16px rgba(223, 160, 86, 0.35);
}

/* 7. Action Buttons Bar */
.od-actions-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding-top: 8px;
}
.od-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 0.9375rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s ease;
    border: none;
    white-space: nowrap;
}
.od-btn-pay {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(34, 197, 94, 0.35);
}
.od-btn-pay:hover {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(34, 197, 94, 0.5);
    color: #ffffff;
}
.od-btn-cancel {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #f87171;
}
.od-btn-cancel:hover {
    background: rgba(239, 68, 68, 0.22);
    border-color: #ef4444;
    color: #ffffff;
    transform: translateY(-2px);
}
.od-btn-return {
    background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(234, 88, 12, 0.35);
}
.od-btn-return:hover {
    background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    transform: translateY(-2px);
    color: #ffffff;
}
.od-btn-secondary {
    background: #242424;
    border: 1px solid #3a3a3a;
    color: #d1d5db;
}
.od-btn-secondary:hover {
    background: #2e2e2e;
    color: #ffffff;
    border-color: #555555;
    transform: translateY(-1px);
}

/* 8. Audit Trail Table & Badges */
.od-audit-meta {
    display: inline-block;
    background: #222222;
    border: 1px solid #333333;
    color: #9ca3af;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 12px;
}
.od-role-badge {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.od-role-customer {
    background: rgba(14, 165, 233, 0.15);
    color: #38bdf8;
    border: 1px solid rgba(14, 165, 233, 0.3);
}
.od-role-system {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.3);
}
.od-role-admin {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.od-action-code {
    font-family: 'SF Mono', Consolas, Monaco, monospace;
    font-size: 0.78rem;
    background: #101010;
    border: 1px solid #2e2e2e;
    color: #dfa056;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
}
.od-state-trans {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8125rem;
}
.od-state-old {
    color: #9ca3af;
}
.od-state-arr {
    color: #dfa056;
    font-weight: 700;
}
.od-state-new {
    color: #f3f4f6;
    font-weight: 700;
}
.od-amt-plus {
    color: #4ade80;
    font-weight: 700;
    font-family: 'SF Mono', Consolas, Monaco, monospace;
    white-space: nowrap;
}
.od-amt-minus {
    color: #f87171;
    font-weight: 700;
    font-family: 'SF Mono', Consolas, Monaco, monospace;
    white-space: nowrap;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .od-header-top {
        flex-direction: column;
        align-items: flex-start;
    }
    .od-lock-banner {
        flex-direction: column;
        align-items: flex-start;
    }
    .od-clock-display {
        width: 100%;
    }
    .od-stepper-track-bg, .od-stepper-track-fill {
        display: none;
    }
    .od-stepper {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    .od-step-item {
        flex-direction: row;
        gap: 14px;
        text-align: left;
    }
    .od-step-circle {
        margin-bottom: 0;
    }
    .od-summary-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    .od-summary-right {
        margin-left: 0;
        width: 100%;
        justify-content: space-between;
    }
    .od-actions-bar {
        flex-direction: column;
        width: 100%;
    }
    .od-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="order-detail-wrapper">
    <!-- Breadcrumb -->
    <nav class="od-breadcrumb">
        <a href="/">Trang chủ</a> <span class="sep">/</span>
        <a href="/orders.php">Đơn hàng của tôi</a> <span class="sep">/</span>
        <span class="current">Chi tiết #<?= e($order['order_code']) ?></span>
    </nav>

    <!-- THÔNG BÁO TÌNH TRẠNG DUYỆT ĐỔI TRẢ DẠNG TOAST NỔI BÊN PHẢI -->
    <?php if (!empty($order['returns'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($order['returns'] as $retNotice): ?>
                <?php if ($retNotice['status'] === 'COMPLETED'): ?>
                    if (window.showToast) {
                        window.showToast(
                            'Yêu cầu đổi trả <?= e($retNotice['return_code']) ?> đã được phê duyệt!',
                            'Số tiền hoàn: <?= formatMoney((float)$retNotice['total_refund_amount']) ?> • Ghi chú: "<?= e($retNotice['inspector_note'] ?? 'Đã xác minh và hoàn tiền thành công.') ?>"',
                            'success',
                            6500
                        );
                    }
                <?php elseif ($retNotice['status'] === 'REJECTED'): ?>
                    if (window.showToast) {
                        window.showToast(
                            'Yêu cầu đổi trả <?= e($retNotice['return_code']) ?> đã bị từ chối',
                            'Lý do: "<?= e($retNotice['inspector_note'] ?? 'Sản phẩm không đáp ứng đủ tiêu chuẩn đổi trả.') ?>"',
                            'danger',
                            6500
                        );
                    }
                <?php elseif ($retNotice['status'] === 'REQUESTED'): ?>
                    if (window.showToast) {
                        window.showToast(
                            'Yêu cầu đổi trả <?= e($retNotice['return_code']) ?> đang chờ thẩm định',
                            'Hệ thống đang kiểm tra tình trạng hàng (Đề xuất hoàn: <?= formatMoney((float)$retNotice['total_refund_amount']) ?>)',
                            'info',
                            5500
                        );
                    }
                <?php endif; ?>
            <?php endforeach; ?>
        });
        </script>
    <?php endif; ?>

    <!-- 1. HEADER CARD & TIẾN TRÌNH STEPPER -->
    <div class="od-card">
        <div class="od-header-top">
            <div class="od-order-id-block">
                <span class="od-order-id-label">Mã đơn hàng:</span>
                <span class="od-order-id-val" id="orderCodeText"><?= e($order['order_code']) ?></span>
                <button type="button" class="od-btn-copy" onclick="copyOrderCode()" title="Sao chép mã đơn">
                    📋 Sao chép
                </button>
                <div class="od-meta-date">
                    <span>🕒 Đặt lúc: <?= formatDate($order['created_at']) ?></span>
                </div>
            </div>
            <div class="od-status-wrap">
                <span class="od-status-label">Trạng thái:</span>
                <?= getStatusBadge($order['status']) ?>
            </div>
        </div>

        <!-- BẬC THANG TIẾN TRÌNH ĐƠN HÀNG (STEPPER CHUẨN SÀN XE VIP) -->
        <?php if (!in_array($status, ['CANCELLED', 'EXPIRED'])): ?>
            <div class="od-stepper-box">
                <div class="od-stepper-track-bg"></div>
                <div class="od-stepper-track-fill" style="width: <?= $progressPercent ?>%;"></div>
                <div class="od-stepper">
                    <?php foreach ($steps as $st): ?>
                        <?php 
                            $isDone = $currentWeight > $st['num'];
                            $isActive = $currentWeight === $st['num'];
                            $cls = $isDone ? 'completed' : ($isActive ? 'active' : '');
                        ?>
                        <div class="od-step-item <?= $cls ?>">
                            <div class="od-step-circle">
                                <?= $isDone ? '✓' : $st['num'] ?>
                            </div>
                            <div class="od-step-title"><?= $st['label'] ?></div>
                            <div class="od-step-desc"><?= $st['desc'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 8px; padding: 14px 20px; text-align: center; color: #f87171; font-weight: 600;">
                ⚠️ Đơn hàng này đang ở trạng thái <strong><?= $status === 'CANCELLED' ? 'ĐÃ HỦY' : 'HẾT HẠN GIỮ HÀNG' ?></strong>. Lượng hàng đã được tự động giải phóng về kho.
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. ĐỒNG HỒ ĐẾM NGƯỢC GIỮ HÀNG 15 PHÚT (KHI PENDING) -->
    <?php if ($order['status'] === 'PENDING' && $order['expires_at']): ?>
        <?php 
            $expiresTs = strtotime($order['expires_at']);
            $nowTs = time();
            $diffSec = max(0, $expiresTs - $nowTs);
        ?>
        <div class="od-lock-banner">
            <div class="od-lock-left">
                <div class="od-lock-icon-wrap">
                    <span>⏱️</span>
                    <span class="od-pulse-indicator"></span>
                </div>
                <div>
                    <div class="od-lock-title">LƯỢNG HÀNG ĐANG ĐƯỢC GIỮ CHỖ ĐỘC QUYỀN TRONG KHO</div>
                    <p class="od-lock-text">
                        Vui lòng tiếp nhận thanh toán trước khi thời gian giữ hàng kết thúc. Khi hết hạn, hệ thống tự động giải phóng tồn kho đã khóa cho khách hàng khác.
                    </p>
                    <div class="od-lock-pills">
                        <span class="od-lock-pill">🔒 Khóa giữ chỗ 15 phút</span>
                        <span class="od-lock-pill">🏷️ Snapshot đơn giá bất biến</span>
                        <span class="od-lock-pill">⚡ Chống Overselling tuyệt đối</span>
                    </div>
                </div>
            </div>
            <div class="od-clock-display">
                <div class="od-clock-caption">Thời gian còn lại</div>
                <div id="countdown" class="od-clock-digits">--:--</div>
                <div class="od-clock-deadline">Hạn chót: <?= date('H:i:s', $expiresTs) ?></div>
            </div>
        </div>

        <script>
        (function() {
            var remaining = <?= $diffSec ?>;
            var el = document.getElementById('countdown');
            function update() {
                if (remaining <= 0) {
                    el.innerText = '00:00';
                    el.style.color = '#ef4444';
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

    <!-- 3. THÔNG TIN NHẬN HÀNG & GIAO DỊCH -->
    <div class="od-shipping-grid">
        <div class="od-ship-item">
            <span class="od-ship-label">👤 Người nhận hàng</span>
            <span class="od-ship-val"><strong><?= e($order['shipping_name'] ?? $order['customer_name'] ?? 'Khách hàng') ?></strong></span>
        </div>
        <div class="od-ship-item">
            <span class="od-ship-label">📞 Số điện thoại</span>
            <span class="od-ship-val"><strong><?= e($order['shipping_phone'] ?? 'Chưa cập nhật') ?></strong></span>
        </div>
        <div class="od-ship-item" style="grid-column: span 1;">
            <span class="od-ship-label">📍 Địa chỉ nhận hàng</span>
            <span class="od-ship-val"><?= e($order['shipping_address'] ?? 'Tại cửa hàng / Kho mặc định') ?></span>
        </div>
        <div class="od-ship-item">
            <span class="od-ship-label">💳 Phương thức thanh toán</span>
            <span class="od-ship-val">Chuyển khoản / Cổng thanh toán mô phỏng</span>
        </div>
    </div>

    <!-- 4. DANH SÁCH SẢN PHẨM & SNAPSHOT GIÁ ĐÃ CHỐT -->
    <div class="od-card">
        <div class="od-card-header">
            <div class="od-card-title-group">
                <div class="od-card-icon">🛍️</div>
                <div>
                    <h2 class="od-card-title">Sản phẩm trong đơn (Snapshot giá đã chốt)</h2>
                    <p class="od-card-subtitle">Cam kết giá không đổi kể cả khi shop tăng giá sản phẩm ngoài thị trường</p>
                </div>
            </div>
            <div class="od-prot-badge">
                🔒 Price Snapshot Active
            </div>
        </div>

        <div class="od-table-container">
            <table class="od-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Biến thể</th>
                        <th>Đơn giá lúc mua (Snapshot)</th>
                        <th style="text-align: center;">Số lượng</th>
                        <th style="text-align: center;">Đã giao</th>
                        <th style="text-align: center;">Đã trả</th>
                        <th style="text-align: right;">Tạm tính</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td>
                                <div class="od-prod-cell">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?= e(productImageUrl($item['image_url'])) ?>" alt="<?= e($item['product_name']) ?>" class="od-prod-thumb">
                                    <?php endif; ?>
                                    <div>
                                        <div class="od-prod-name"><?= e($item['product_name']) ?></div>
                                        <span class="od-prod-sku"><?= e($item['sku']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="od-variant-tag"><?= e($item['variant_info']) ?></span>
                            </td>
                            <td class="od-price-cell">
                                <?= formatMoney((float)$item['unit_price']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="od-qty-badge"><?= (int)$item['quantity'] ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="od-deliv-badge"><?= (int)$item['delivered_quantity'] ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="od-ret-badge"><?= (int)$item['returned_quantity'] ?></span>
                            </td>
                            <td class="od-subtotal-cell">
                                <?= formatMoney((float)$item['subtotal']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- TỔNG THANH TOÁN (SINGLE LINE CHỐNG GÃY CHỮ Đ) -->
        <div class="od-summary-bar">
            <div class="od-summary-left">
                <span class="od-prot-badge">
                    🛡️ Đóng băng giá an toàn
                </span>
                <span style="font-size: 0.8125rem; color: #9ca3af;">
                    Đã khóa số lượng <?= count($order['items']) ?> mặt hàng
                </span>
            </div>
            <div class="od-summary-right">
                <span class="od-summary-label">Tổng số tiền thanh toán:</span>
                <span class="od-summary-total"><?= formatMoney((float)$order['total_amount']) ?></span>
            </div>
        </div>

        <!-- KHU VỰC THAO TÁC / HÀNH ĐỘNG -->
        <div class="od-actions-bar">
            <?php if ($order['status'] === 'PENDING'): ?>
                <a href="/payment-mock.php?order_code=<?= urlencode($order['order_code']) ?>" class="od-btn od-btn-pay">
                    💳 Tiếp nhận thanh toán mô phỏng
                </a>
                
                <form method="post" id="cancelOrderForm" style="margin: 0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button type="button" class="od-btn od-btn-cancel" onclick="confirmCancelOrder()">✕ Hủy đơn hàng</button>
                </form>
            <?php endif; ?>

            <?php if (in_array($order['status'], ['DELIVERED', 'PARTIALLY_RETURNED']) && $user['role'] === 'customer'): ?>
                <a href="/return-request.php?order_id=<?= (int)$order['id'] ?>" class="od-btn od-btn-return">
                    🔄 Yêu cầu đổi trả một phần
                </a>
            <?php endif; ?>

            <a href="/orders.php" class="od-btn od-btn-secondary">
                ← Quay lại Đơn hàng của tôi
            </a>
        </div>
    </div>

    <!-- 5. LỊCH SỬ ĐỔI TRẢ NẾU CÓ -->
    <?php if (!empty($order['returns'])): ?>
        <div class="od-card">
            <div class="od-card-header">
                <div class="od-card-title-group">
                    <div class="od-card-icon">🔄</div>
                    <div>
                        <h3 class="od-card-title">Lịch sử yêu cầu đổi trả</h3>
                        <p class="od-card-subtitle">Theo dõi trạng thái hoàn hàng và đối soát tiền hoàn theo quy chế Đề tài 19</p>
                    </div>
                </div>
                <div class="od-audit-meta"><?= count($order['returns']) ?> yêu cầu</div>
            </div>
            <div class="od-table-container">
                <table class="od-table">
                    <thead>
                        <tr>
                            <th>Mã đổi trả</th>
                            <th>Ngày yêu cầu</th>
                            <th>Lý do</th>
                            <th style="text-align: right;">Tiền hoàn mô phỏng</th>
                            <th style="text-align: center;">Trạng thái</th>
                            <th>Ghi chú thẩm định</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order['returns'] as $ret): ?>
                            <tr>
                                <td><strong style="color: #f5b061;"><?= e($ret['return_code']) ?></strong></td>
                                <td><?= formatDate($ret['created_at']) ?></td>
                                <td><?= e($ret['reason']) ?></td>
                                <td style="text-align: right; font-weight: 700; color: #4ade80;">
                                    <?= formatMoney((float)$ret['total_refund_amount']) ?>
                                </td>
                                <td style="text-align: center;"><?= getStatusBadge($ret['status']) ?></td>
                                <td><?= e($ret['inspector_note'] ?? 'Đang chờ thẩm định...') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 6. NHẬT KÝ ĐỐI SOÁT & VẾT KIỂM TOÁN HỆ THỐNG (AUDIT TRAIL) -->
    <div class="od-card">
        <div class="od-card-header">
            <div class="od-card-title-group">
                <div class="od-card-icon">📜</div>
                <div>
                    <h3 class="od-card-title">Vết kiểm toán biến động đơn hàng (Audit Trail)</h3>
                    <p class="od-card-subtitle">Ghi nhận minh bạch mọi biến động trạng thái, số lượng hàng và dòng tiền để phục vụ đối soát</p>
                </div>
            </div>
            <div class="od-audit-meta"><?= count($logs) ?> bản ghi kiểm toán</div>
        </div>

        <div class="od-table-container">
            <table class="od-table">
                <thead>
                    <tr>
                        <th>Thời gian</th>
                        <th>Tác nhân</th>
                        <th>Hành động</th>
                        <th>Chuyển trạng thái</th>
                        <th style="text-align: right;">Biến động tiền</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #9ca3af; padding: 24px;">Chưa có bản ghi kiểm toán nào.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <?php 
                                $role = strtolower($l['actor_role'] ?? 'system');
                                $roleCls = 'od-role-system';
                                if ($role === 'customer') $roleCls = 'od-role-customer';
                                elseif (in_array($role, ['staff', 'manager', 'admin'])) $roleCls = 'od-role-admin';
                            ?>
                            <tr>
                                <td style="font-family: 'SF Mono', Consolas, Monaco, monospace; font-size: 0.8125rem; color: #9ca3af; white-space: nowrap;">
                                    <?= formatDate($l['created_at']) ?>
                                </td>
                                <td>
                                    <span class="od-role-badge <?= $roleCls ?>"><?= e($l['actor_role']) ?></span>
                                    <?php if (!empty($l['actor_name'])): ?>
                                        <span style="font-size: 0.8125rem; color: #d1d5db; margin-left: 4px;">(<?= e($l['actor_name']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="od-action-code"><?= e($l['action']) ?></span>
                                </td>
                                <td>
                                    <?php if ($l['old_state'] || $l['new_state']): ?>
                                        <div class="od-state-trans">
                                            <span class="od-state-old"><?= e($l['old_state'] ?? 'NONE') ?></span>
                                            <span class="od-state-arr">➔</span>
                                            <span class="od-state-new"><?= e($l['new_state'] ?? '') ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #6b7280;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ((float)$l['amount_change'] != 0): ?>
                                        <?php if ((float)$l['amount_change'] > 0): ?>
                                            <span class="od-amt-plus">+<?= formatMoney((float)$l['amount_change']) ?></span>
                                        <?php else: ?>
                                            <span class="od-amt-minus"><?= formatMoney((float)$l['amount_change']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #6b7280;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #d1d5db;"><?= e($l['note']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function copyOrderCode() {
    var code = document.getElementById('orderCodeText').innerText.trim();
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

function confirmCancelOrder() {
    if (window.showConfirm) {
        window.showConfirm({
            title: 'Xác nhận hủy đơn hàng?',
            message: 'Bạn có chắc muốn hủy đơn hàng này? Lượng hàng đang giữ sẽ được giải phóng ngay lập tức về kho.',
            confirmText: 'Xác nhận hủy đơn',
            cancelText: 'Giữ lại đơn',
            danger: true
        }, function() {
            document.getElementById('cancelOrderForm').submit();
        });
    } else {
        if (confirm('Bạn có chắc muốn hủy đơn hàng này? Lượng hàng đang giữ sẽ được giải phóng ngay lập tức về kho.')) {
            document.getElementById('cancelOrderForm').submit();
        }
    }
}
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
