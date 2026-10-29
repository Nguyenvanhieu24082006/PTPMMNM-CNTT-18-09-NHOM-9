<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class AuditLogService
{
    public function __construct(private mysqli $db) {}

    public function getLogsForOrder(int $orderId): array
    {
        $sql = "SELECT al.*, u.full_name AS actor_name 
                FROM order_audit_logs al 
                LEFT JOIN users u ON al.actor_id = u.id 
                WHERE al.order_id = ? 
                ORDER BY al.id ASC";
        $stmt = queryParams($this->db, $sql, [$orderId]);
        return fetchAll($stmt);
    }

    public function getRecentLogs(int $limit = 50): array
    {
        $sql = "SELECT al.*, o.order_code, u.full_name AS actor_name 
                FROM order_audit_logs al 
                JOIN orders o ON al.order_id = o.id 
                LEFT JOIN users u ON al.actor_id = u.id 
                ORDER BY al.id DESC 
                LIMIT ?";
        $stmt = queryParams($this->db, $sql, [$limit]);
        return fetchAll($stmt);
    }

    /**
     * Báo cáo đối chiếu số lượng hàng và khoản tiền mô phỏng
     * ĐÁP ỨNG CHỨC NĂNG 5:
     * - Theo dõi lịch sử đơn, đối chiếu hàng và khoản tiền mô phỏng
     * - Thống kê số lượng: Đã đặt, Đang giữ, Đã giao, Đã đổi trả
     * - Thống kê dòng tiền: Tổng thu từ cổng thanh toán, Tổng tiền đã duyệt hoàn
     * - Thống kê bất thường: Số thông báo thanh toán gửi lặp, Số thanh toán đến muộn
     */
    public function getReconciliationReport(): array
    {
        // 1. Thống kê số lượng đơn theo trạng thái
        $statusStmt = queryParams($this->db, "SELECT status, COUNT(*) AS count, SUM(total_amount) AS total_val FROM orders GROUP BY status");
        $statusRows = fetchAll($statusStmt);
        $ordersByStatus = [];
        $totalOrders = 0;
        foreach ($statusRows as $row) {
            $ordersByStatus[$row['status']] = [
                'count' => (int)$row['count'],
                'total_val' => (float)$row['total_val']
            ];
            $totalOrders += (int)$row['count'];
        }

        // 2. Thống kê hàng hóa (Số lượng đặt, đã giao, đã trả)
        $itemsStmt = queryParams(
            $this->db,
            "SELECT 
                SUM(quantity) AS total_ordered,
                SUM(delivered_quantity) AS total_delivered,
                SUM(returned_quantity) AS total_returned 
             FROM order_items"
        );
        $itemStats = fetchOne($itemsStmt);

        // 3. Thống kê lượng hàng đang giữ trong các đơn PENDING (Active reservations)
        $holdingStmt = queryParams(
            $this->db,
            "SELECT COALESCE(SUM(quantity), 0) AS total_holding 
             FROM reservations 
             WHERE status = 'HOLDING' AND expires_at > NOW()"
        );
        $holdingRow = fetchOne($holdingStmt);

        // 4. Thống kê dòng tiền thu mô phỏng từ cổng thanh toán
        $payStmt = queryParams(
            $this->db,
            "SELECT 
                COUNT(*) AS total_txns,
                COALESCE(SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END), 0) AS total_received,
                COUNT(CASE WHEN status = 'LATE_IGNORED' THEN 1 END) AS late_payment_count 
             FROM payment_events"
        );
        $payStats = fetchOne($payStmt);

        // 5. Thống kê tiền hoàn trả mô phỏng đã duyệt
        $refundStmt = queryParams(
            $this->db,
            "SELECT 
                COUNT(*) AS total_returns,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) AS approved_returns,
                COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN total_refund_amount ELSE 0 END), 0) AS total_refunded 
             FROM returns"
        );
        $refundStats = fetchOne($refundStmt);

        // Chuẩn bị danh sách phân bổ trạng thái dạng list cho giao diện duyệt foreach
        $statusBreakdown = [];
        foreach ($statusRows as $row) {
            $statusBreakdown[] = [
                'status' => $row['status'],
                'cnt' => (int)$row['count'],
                'count' => (int)$row['count'],
                'total_val' => (float)$row['total_val']
            ];
        }

        // Dữ liệu luân chuyển hàng hóa (tương thích cả 2 key: items và inventory)
        $itemsData = [
            'ordered' => (int)($itemStats['total_ordered'] ?? 0),
            'holding' => (int)($holdingRow['total_holding'] ?? 0),
            'delivered' => (int)($itemStats['total_delivered'] ?? 0),
            'returned' => (int)($itemStats['total_returned'] ?? 0),
            'total_ordered' => (int)($itemStats['total_ordered'] ?? 0),
            'current_holding' => (int)($holdingRow['total_holding'] ?? 0),
            'total_delivered' => (int)($itemStats['total_delivered'] ?? 0),
            'total_returned' => (int)($itemStats['total_returned'] ?? 0)
        ];

        return [
            'orders_by_status' => $ordersByStatus,
            'status_breakdown' => $statusBreakdown,
            'total_orders' => $totalOrders,
            'items' => $itemsData,
            'inventory' => $itemsData,
            'finance' => [
                'total_received' => (float)($payStats['total_received'] ?? 0),
                'total_refunded' => (float)($refundStats['total_refunded'] ?? 0),
                'net_revenue' => (float)($payStats['total_received'] ?? 0) - (float)($refundStats['total_refunded'] ?? 0),
                'late_payment_count' => (int)($payStats['late_payment_count'] ?? 0),
                'total_returns' => (int)($refundStats['total_returns'] ?? 0),
                'approved_returns' => (int)($refundStats['approved_returns'] ?? 0)
            ]
        ];
    }
}

