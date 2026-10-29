<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ReturnService
{
    public function __construct(private mysqli $db) {}

    /**
     * Khách hàng gửi yêu cầu đổi trả một phần (Partial Return Request)
     * ĐÁP ỨNG KỸ THUẬT 2 & PHÉP THỬ 4:
     * - Kiểm tra tổng lượng trả qua nhiều lần không được vượt lượng đã giao.
     * - Tiền hoàn mô phỏng được tính chuẩn xác theo đơn giá đã chốt của đơn lúc mua.
     */
    public function requestPartialReturn(
        int $orderId,
        int $userId,
        array $itemsToReturn, // [['order_item_id' => int, 'quantity' => int], ...]
        string $reason
    ): array {
        if (empty($itemsToReturn)) {
            throw new InvalidArgumentException("Chưa chọn sản phẩm nào để yêu cầu đổi trả.");
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException("Vui lòng nhập lý do đổi trả hàng.");
        }

        $this->db->begin_transaction();
        try {
            // 1. Kiểm tra đơn hàng thuộc về khách hàng và đã ở trạng thái DELIVERED
            $orderStmt = queryParams(
                $this->db,
                "SELECT * FROM orders WHERE id = ? FOR UPDATE",
                [$orderId]
            );
            $order = fetchOne($orderStmt);
            if (!$order) {
                throw new RuntimeException("Không tìm thấy đơn hàng ID: $orderId");
            }

            if ((int)$order['user_id'] !== $userId) {
                throw new RuntimeException("Bạn không có quyền yêu cầu đổi trả cho đơn hàng của người khác.");
            }

            if (!in_array($order['status'], ['DELIVERED', 'PARTIALLY_RETURNED'], true)) {
                throw new RuntimeException("Chỉ có thể yêu cầu đổi trả khi đơn hàng đã được giao thành công (DELIVERED).");
            }

            $totalRefundAmount = 0.0;
            $validatedItems = [];

            // 2. Thẩm định từng sản phẩm muốn đổi trả
            foreach ($itemsToReturn as $req) {
                $orderItemId = (int)$req['order_item_id'];
                $returnQty = (int)$req['quantity'];

                if ($returnQty <= 0) {
                    continue; // Bỏ qua nếu không yêu cầu trả món này
                }

                $itemStmt = queryParams(
                    $this->db,
                    "SELECT * FROM order_items WHERE id = ? AND order_id = ? FOR UPDATE",
                    [$orderItemId, $orderId]
                );
                $item = fetchOne($itemStmt);
                if (!$item) {
                    throw new RuntimeException("Sản phẩm đơn hàng ID $orderItemId không hợp lệ.");
                }

                $deliveredQty = (int)$item['delivered_quantity'];
                $alreadyReturnedQty = (int)$item['returned_quantity'];
                $maxReturnable = $deliveredQty - $alreadyReturnedQty;

                // RÀNG BUỘC TOÁN HỌC BẮT BUỘC (PHÉP THỬ 4)
                if ($returnQty > $maxReturnable) {
                    throw new RuntimeException(sprintf(
                        "Số lượng yêu cầu trả (%d) cho món '%s' vượt quá số lượng còn có thể trả (%d). Tổng đã giao: %d, đã trả trước: %d.",
                        $returnQty,
                        $item['product_name'],
                        $maxReturnable,
                        $deliveredQty,
                        $alreadyReturnedQty
                    ));
                }

                // TÍNH TIỀN HOÀN THEO ĐƠN GIÁ ĐÃ CHỐT TRONG ĐƠN (PRICE SNAPSHOT)
                $unitPrice = (float)$item['unit_price'];
                $subtotalRefund = $unitPrice * $returnQty;
                $totalRefundAmount += $subtotalRefund;

                $validatedItems[] = [
                    'order_item_id' => $orderItemId,
                    'variant_id' => (int)$item['variant_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $returnQty,
                    'unit_price' => $unitPrice,
                    'subtotal_refund' => $subtotalRefund
                ];
            }

            if (empty($validatedItems)) {
                throw new InvalidArgumentException("Không có số lượng hợp lệ nào được chọn để trả.");
            }

            // 3. Tạo bản ghi yêu cầu đổi trả (returns)
            $returnCode = 'RET-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            queryParams(
                $this->db,
                "INSERT INTO returns (return_code, order_id, user_id, status, reason, total_refund_amount, created_at) 
                 VALUES (?, ?, ?, 'REQUESTED', ?, ?, NOW())",
                [$returnCode, $orderId, $userId, $reason, $totalRefundAmount]
            );
            $returnId = (int)$this->db->insert_id;

            // 4. Lưu chi tiết hàng trả (return_items)
            foreach ($validatedItems as $vi) {
                queryParams(
                    $this->db,
                    "INSERT INTO return_items (return_id, order_item_id, quantity, unit_price, subtotal_refund, item_condition) 
                     VALUES (?, ?, ?, ?, ?, 'GOOD')",
                    [$returnId, $vi['order_item_id'], $vi['quantity'], $vi['unit_price'], $vi['subtotal_refund']]
                );
            }

            // 5. Ghi audit log
            queryParams(
                $this->db,
                "INSERT INTO order_audit_logs (order_id, actor_id, actor_role, action, old_state, new_state, amount_change, note) 
                 VALUES (?, ?, 'customer', 'RETURN_REQUESTED', ?, 'RETURN_PENDING', ?, ?)",
                [$orderId, $userId, $order['status'], -$totalRefundAmount, "Khách hàng yêu cầu đổi trả mã: $returnCode"]
            );

            $this->db->commit();

            return [
                'return_id' => $returnId,
                'return_code' => $returnCode,
                'total_refund_amount' => $totalRefundAmount,
                'items_count' => count($validatedItems)
            ];

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Người xác nhận trả hàng (Return Inspector) thẩm định và phê duyệt yêu cầu trả hàng
     */
    public function approveReturn(
        int $returnId,
        int $inspectorId,
        string $inspectorNote,
        bool $restockInventory = true
    ): bool {
        $this->db->begin_transaction();
        try {
            $retStmt = queryParams(
                $this->db,
                "SELECT * FROM returns WHERE id = ? FOR UPDATE",
                [$returnId]
            );
            $return = fetchOne($retStmt);
            if (!$return) {
                throw new RuntimeException("Yêu cầu đổi trả ID $returnId không tồn tại.");
            }

            if ($return['status'] !== 'REQUESTED') {
                throw new RuntimeException("Yêu cầu này đã được xử lý (Trạng thái hiện tại: {$return['status']}).");
            }

            $orderId = (int)$return['order_id'];
            $refundAmount = (float)$return['total_refund_amount'];

            // Lấy danh sách các mặt hàng được trả trong yêu cầu này
            $itemsStmt = queryParams(
                $this->db,
                "SELECT ri.*, oi.variant_id, oi.delivered_quantity, oi.returned_quantity 
                 FROM return_items ri 
                 JOIN order_items oi ON ri.order_item_id = oi.id 
                 WHERE ri.return_id = ?",
                [$returnId]
            );
            $returnItems = fetchAll($itemsStmt);

            // Cập nhật số lượng đã trả vào order_items & hoàn kho variants
            foreach ($returnItems as $ri) {
                $qty = (int)$ri['quantity'];
                $orderItemId = (int)$ri['order_item_id'];
                $variantId = (int)$ri['variant_id'];

                // Tăng số lượng đã trả trong order_items
                queryParams(
                    $this->db,
                    "UPDATE order_items SET returned_quantity = returned_quantity + ? WHERE id = ?",
                    [$qty, $orderItemId]
                );

                // Nếu hàng còn tốt, nhập lại kho thực tế
                if ($restockInventory) {
                    queryParams(
                        $this->db,
                        "UPDATE variants SET stock_quantity = stock_quantity + ? WHERE id = ?",
                        [$qty, $variantId]
                    );
                }
            }

            // Cập nhật trạng thái của returns sang COMPLETED
            queryParams(
                $this->db,
                "UPDATE returns SET status = 'COMPLETED', inspector_id = ?, inspector_note = ?, updated_at = NOW() WHERE id = ?",
                [$inspectorId, $inspectorNote, $returnId]
            );

            // Kiểm tra xem đơn hàng đã trả toàn bộ hay trả một phần
            $checkAllStmt = queryParams(
                $this->db,
                "SELECT SUM(delivered_quantity) AS total_deliv, SUM(returned_quantity) AS total_ret 
                 FROM order_items WHERE order_id = ?",
                [$orderId]
            );
            $totals = fetchOne($checkAllStmt);
            $newOrderStatus = ($totals['total_deliv'] > 0 && $totals['total_deliv'] == $totals['total_ret']) 
                ? 'RETURNED' 
                : 'PARTIALLY_RETURNED';

            queryParams($this->db, "UPDATE orders SET status = ? WHERE id = ?", [$newOrderStatus, $orderId]);

            // Ghi audit log
            queryParams(
                $this->db,
                "INSERT INTO order_audit_logs (order_id, actor_id, actor_role, action, old_state, new_state, amount_change, note) 
                 VALUES (?, ?, 'manager', 'RETURN_APPROVED', 'DELIVERED', ?, ?, ?)",
                [
                    $orderId,
                    $inspectorId,
                    $newOrderStatus,
                    -$refundAmount,
                    "Duyệt yêu cầu đổi trả {$return['return_code']}. Hoàn tiền mô phỏng: " . number_format($refundAmount, 0, ',', '.') . " đ. " . ($restockInventory ? 'Đã hoàn kho.' : 'Không nhập kho.')
                ]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Từ chối yêu cầu đổi trả
     */
    public function rejectReturn(int $returnId, int $inspectorId, string $inspectorNote): bool
    {
        $this->db->begin_transaction();
        try {
            $retStmt = queryParams($this->db, "SELECT * FROM returns WHERE id = ? FOR UPDATE", [$returnId]);
            $return = fetchOne($retStmt);
            if (!$return) {
                throw new RuntimeException("Yêu cầu đổi trả không tồn tại.");
            }

            if ($return['status'] !== 'REQUESTED') {
                throw new RuntimeException("Yêu cầu này không ở trạng thái chờ duyệt.");
            }

            queryParams(
                $this->db,
                "UPDATE returns SET status = 'REJECTED', inspector_id = ?, inspector_note = ?, updated_at = NOW() WHERE id = ?",
                [$inspectorId, $inspectorNote, $returnId]
            );

            queryParams(
                $this->db,
                "INSERT INTO order_audit_logs (order_id, actor_id, actor_role, action, old_state, new_state, note) 
                 VALUES (?, ?, 'manager', 'RETURN_REJECTED', 'REQUESTED', 'REJECTED', ?)",
                [$return['order_id'], $inspectorId, "Từ chối đổi trả: " . $inspectorNote]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function listReturns(array $filter = []): array
    {
        $sql = "SELECT r.*, o.order_code, u.full_name AS customer_name, insp.full_name AS inspector_name 
                FROM returns r 
                JOIN orders o ON r.order_id = o.id 
                JOIN users u ON r.user_id = u.id 
                LEFT JOIN users insp ON r.inspector_id = insp.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filter['user_id'])) {
            $sql .= " AND r.user_id = ?";
            $params[] = (int)$filter['user_id'];
        }

        if (!empty($filter['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filter['status'];
        }

        $sql .= " ORDER BY r.id DESC";
        $stmt = queryParams($this->db, $sql, $params);
        $returns = fetchAll($stmt);

        foreach ($returns as &$ret) {
            $itemsStmt = queryParams(
                $this->db,
                "SELECT ri.*, oi.product_name, oi.variant_info 
                 FROM return_items ri 
                 JOIN order_items oi ON ri.order_item_id = oi.id 
                 WHERE ri.return_id = ?",
                [(int)$ret['id']]
            );
            $ret['items'] = fetchAll($itemsStmt);
        }

        return $returns;
    }
}

