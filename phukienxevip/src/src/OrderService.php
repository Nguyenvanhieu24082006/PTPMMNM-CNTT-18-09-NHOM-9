<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class OrderService
{
    public function __construct(private mysqli $db) {}

    /**
     * Tự động giải phóng các đơn PENDING đã quá hạn giữ hàng (Reservation TTL)
     * Đảm bảo tính Idempotent: Chỉ chuyển các đơn PENDING quá hạn sang EXPIRED và nhả HOLDING -> RELEASED
     */
    public function checkAndExpireReservations(): int
    {
        $sql = "SELECT id, order_code FROM orders WHERE status = 'PENDING' AND expires_at < NOW() FOR UPDATE";
        $this->db->begin_transaction();
        try {
            $stmt = queryParams($this->db, $sql);
            $expiredOrders = fetchAll($stmt);
            $count = 0;

            foreach ($expiredOrders as $ord) {
                $orderId = (int)$ord['id'];
                
                // Nhả trạng thái giữ hàng
                queryParams(
                    $this->db,
                    "UPDATE reservations SET status = 'RELEASED', released_at = NOW() 
                     WHERE order_id = ? AND status = 'HOLDING'",
                    [$orderId]
                );

                // Chuyển trạng thái đơn sang EXPIRED
                queryParams(
                    $this->db,
                    "UPDATE orders SET status = 'EXPIRED' WHERE id = ? AND status = 'PENDING'",
                    [$orderId]
                );

                // Ghi audit log
                queryParams(
                    $this->db,
                    "INSERT INTO order_audit_logs (order_id, actor_role, action, old_state, new_state, note) 
                     VALUES (?, 'SYSTEM', 'ORDER_EXPIRED', 'PENDING', 'EXPIRED', 'Đơn hàng quá thời hạn giữ hàng 15 phút mà chưa thanh toán')",
                    [$orderId]
                );

                $count++;
            }

            $this->db->commit();
            return $count;
        } catch (Throwable $e) {
            $this->db->rollback();
            error_log('checkAndExpireReservations error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Tạo đơn hàng và giữ hàng có thời hạn (15 phút)
     * Giải quyết bài toán tranh chấp sản phẩm cuối cùng (Race Condition / Concurrency Control):
     * Sử dụng Transaction + Pessimistic Locking (SELECT ... FOR UPDATE)
     * Lưu giá đã chốt tại thời điểm đặt (Price Snapshot)
     */
    public function createOrderWithReservation(
        int $userId,
        string $shippingAddress,
        string $customerPhone,
        array $items // [['variant_id' => int, 'quantity' => int], ...]
    ): array {
        if (empty($items)) {
            throw new InvalidArgumentException("Giỏ hàng không có sản phẩm nào.");
        }

        $this->db->begin_transaction();
        try {
            // Bước 0: Quét dọn các đơn hết hạn trước khi tính lượng hàng có thể bán
            $this->checkAndExpireReservations();

            $totalAmount = 0.0;
            $orderItemsToInsert = [];

            // Bước 1: Khóa và kiểm tra tồn kho cho từng biến thể
            foreach ($items as $item) {
                $variantId = (int)$item['variant_id'];
                $requestedQty = (int)$item['quantity'];

                if ($requestedQty <= 0) {
                    throw new InvalidArgumentException("Số lượng đặt phải lớn hơn 0.");
                }

                // KHÓA DÒNG BIẾN THỂ TRONG TRANSACTION (Pessimistic Locking)
                $vStmt = queryParams(
                    $this->db,
                    "SELECT v.id, v.product_id, v.price, v.stock_quantity, p.name AS product_name, v.attributes 
                     FROM variants v 
                     JOIN products p ON v.product_id = p.id 
                     WHERE v.id = ? FOR UPDATE",
                    [$variantId]
                );
                $variant = fetchOne($vStmt);
                if (!$variant) {
                    throw new RuntimeException("Biến thể ID $variantId không tồn tại.");
                }

                // TÍNH LƯỢNG HÀNG ĐANG GIỮ TRONG CÁC ĐƠN PENDING KHÁC (KHÓA DÒNG RESERVATIONS)
                $rStmt = queryParams(
                    $this->db,
                    "SELECT COALESCE(SUM(quantity), 0) AS holding_qty 
                     FROM reservations 
                     WHERE variant_id = ? AND status = 'HOLDING' AND expires_at > NOW() FOR UPDATE",
                    [$variantId]
                );
                $holdingRow = fetchOne($rStmt);
                $holdingQty = (int)($holdingRow['holding_qty'] ?? 0);

                $stockQty = (int)$variant['stock_quantity'];
                $availableQty = $stockQty - $holdingQty;

                // KIỂM TRA LƯỢNG CÓ THỂ BÁN
                if ($availableQty < $requestedQty) {
                    throw new RuntimeException(sprintf(
                        "Sản phẩm '%s' không đủ số lượng có thể bán (Còn lại: %d, Yêu cầu: %d).",
                        $variant['product_name'],
                        max(0, $availableQty),
                        $requestedQty
                    ));
                }

                // LƯU BẢN SAO CHỐT GIÁ (PRICE SNAPSHOT)
                $unitPrice = (float)$variant['price'];
                $subtotal = $unitPrice * $requestedQty;
                $totalAmount += $subtotal;

                $attr = json_decode($variant['attributes'] ?: '{}', true);
                $attrStr = implode(' - ', array_values($attr));

                $orderItemsToInsert[] = [
                    'variant_id' => $variantId,
                    'product_name' => $variant['product_name'],
                    'variant_info' => $attrStr,
                    'quantity' => $requestedQty,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ];
            }

            // Bước 2: Tạo đơn hàng với trạng thái PENDING và hạn giữ hàng 15 phút
            $orderCode = 'ORD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            
            queryParams(
                $this->db,
                "INSERT INTO orders (order_code, user_id, status, total_amount, shipping_address, customer_phone, expires_at, created_at) 
                 VALUES (?, ?, 'PENDING', ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())",
                [$orderCode, $userId, $totalAmount, $shippingAddress, $customerPhone]
            );
            $orderId = (int)$this->db->insert_id;

            // Bước 3: Lưu chi tiết đơn hàng (order_items)
            foreach ($orderItemsToInsert as $oi) {
                queryParams(
                    $this->db,
                    "INSERT INTO order_items (order_id, variant_id, product_name, variant_info, quantity, unit_price, subtotal) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $orderId,
                        $oi['variant_id'],
                        $oi['product_name'],
                        $oi['variant_info'],
                        $oi['quantity'],
                        $oi['unit_price'],
                        $oi['subtotal']
                    ]
                );

                // Bước 4: Tạo bản ghi giữ hàng có thời hạn (reservations)
                queryParams(
                    $this->db,
                    "INSERT INTO reservations (order_id, variant_id, quantity, status, expires_at) 
                     VALUES (?, ?, ?, 'HOLDING', DATE_ADD(NOW(), INTERVAL 15 MINUTE))",
                    [$orderId, $oi['variant_id'], $oi['quantity']]
                );
            }

            // Bước 5: Ghi nhật ký hệ thống (Audit log)
            queryParams(
                $this->db,
                "INSERT INTO order_audit_logs (order_id, actor_id, actor_role, action, old_state, new_state, amount_change, note) 
                 VALUES (?, ?, 'customer', 'ORDER_CREATED', NULL, 'PENDING', ?, 'Khách hàng tạo đơn và giữ hàng')",
                [$orderId, $userId, $totalAmount]
            );

            $this->db->commit();

            return [
                'order_id' => $orderId,
                'order_code' => $orderCode,
                'total_amount' => $totalAmount,
                'status' => 'PENDING',
                'items_count' => count($orderItemsToInsert)
            ];
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Hủy đơn hàng và giải phóng hàng đang giữ
     * ĐẢM BẢO TÍNH IDEMPOTENT: Hủy gửi lại không hoàn hàng hai lần!
     */
    public function cancelOrder(int $orderId, int $actorId, string $actorRole): bool
    {
        $this->db->begin_transaction();
        try {
            $stmt = queryParams($this->db, "SELECT * FROM orders WHERE id = ? FOR UPDATE", [$orderId]);
            $order = fetchOne($stmt);
            if (!$order) {
                throw new RuntimeException("Đơn hàng không tồn tại.");
            }

            $currentStatus = $order['status'];

            // Nếu đơn đã hủy hoặc đã hết hạn từ trước => Idempotent: Bỏ qua an toàn, không xử lý lại
            if ($currentStatus === 'CANCELLED' || $currentStatus === 'EXPIRED') {
                $this->db->commit();
                return true;
            }

            // Đơn đã giao hoặc đang giao thì không được tự ý bấm hủy
            if (in_array($currentStatus, ['SHIPPED', 'DELIVERED', 'PARTIALLY_RETURNED', 'RETURNED'])) {
                throw new RuntimeException("Không thể hủy đơn hàng đang giao hoặc đã giao thành công.");
            }

            // Nếu đơn đã thanh toán (PAID) và đã trừ kho thực tế: hoàn kho lại cho variants
            if ($currentStatus === 'PAID' || $currentStatus === 'PROCESSING') {
                $itemsStmt = queryParams($this->db, "SELECT variant_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
                $items = fetchAll($itemsStmt);
                foreach ($items as $it) {
                    queryParams(
                        $this->db,
                        "UPDATE variants SET stock_quantity = stock_quantity + ? WHERE id = ?",
                        [$it['quantity'], $it['variant_id']]
                    );
                }
            }

            // Giải phóng trạng thái giữ hàng (chỉ các dòng đang HOLDING mới chuyển sang RELEASED)
            queryParams(
                $this->db,
                "UPDATE reservations SET status = 'RELEASED', released_at = NOW() 
                 WHERE order_id = ? AND status = 'HOLDING'",
                [$orderId]
            );

            // Cập nhật trạng thái đơn sang CANCELLED
            queryParams($this->db, "UPDATE orders SET status = 'CANCELLED' WHERE id = ?", [$orderId]);

            // Ghi audit log
            queryParams(
                $this->db,
                "INSERT INTO order_audit_logs (order_id, actor_id, actor_role, action, old_state, new_state, note) 
                 VALUES (?, ?, ?, 'ORDER_CANCELLED', ?, 'CANCELLED', 'Hủy đơn hàng và giải phóng lượng hàng đã giữ')",
                [$orderId, $actorId, $actorRole, $currentStatus]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Cập nhật tiến độ giao hàng: PAID -> PROCESSING -> SHIPPED -> DELIVERED
     */
    public function updateShippingStatus(int $orderId, string $newStatus, int $actorId, string $actorRole): bool
    {
        $allowed = ['PROCESSING', 'SHIPPED', 'DELIVERED'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new InvalidArgumentException("Trạng thái giao nhận không hợp lệ.");
        }

        $this->db->begin_transaction();
        try {
            $stmt = queryParams($this->db, "SELECT * FROM orders WHERE id = ? FOR UPDATE", [$orderId]);
            $order = fetchOne($stmt);
            if (!$order) {
                throw new RuntimeException("Đơn hàng không tồn tại.");
            }

            $oldStatus = $order['status'];

            // Kiểm tra quy tắc chuyển trạng thái hợp lệ
            $valid = false;
            if ($oldStatus === 'PAID' && $newStatus === 'PROCESSING') $valid = true;
            if (in_array($oldStatus, ['PAID', 'PROCESSING']) && $newStatus === 'SHIPPED') $valid = true;
            if ($oldStatus === 'SHIPPED' && $newStatus === 'DELIVERED') $valid = true;

            if (!$valid) {
                throw new RuntimeException("Không thể chuyển từ trạng thái '$oldStatus' sang '$newStatus'.");
            }

            queryParams($this->db, "UPDATE orders SET status = ? WHERE id = ?", [$newStatus, $orderId]);

            // Nếu đã giao thành công (DELIVERED), cập nhật delivered_quantity trong order_items
            if ($newStatus === 'DELIVERED') {
                queryParams(
                    $this->db,
                    "UPDATE order_items SET delivered_quantity = quantity WHERE order_id = ?",
                    [$orderId]
                );
            }

            // Ghi audit log
            queryParams(
                $this->db,
                "INSERT INTO order_audit_logs (order_id, actor_id, actor_role, action, old_state, new_state, note) 
                 VALUES (?, ?, ?, 'STATUS_UPDATED', ?, ?, 'Cập nhật tiến trình giao hàng')",
                [$orderId, $actorId, $actorRole, $oldStatus, $newStatus]
            );

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getOrderById(int $orderId): ?array
    {
        $stmt = queryParams(
            $this->db,
            "SELECT o.*, u.full_name AS customer_name, u.email AS customer_email 
             FROM orders o 
             JOIN users u ON o.user_id = u.id 
             WHERE o.id = ?",
            [$orderId]
        );
        $order = fetchOne($stmt);
        if (!$order) return null;

        $itemsStmt = queryParams(
            $this->db,
            "SELECT oi.*, v.sku, p.image_url 
             FROM order_items oi 
             JOIN variants v ON oi.variant_id = v.id 
             JOIN products p ON v.product_id = p.id 
             WHERE oi.order_id = ?",
            [$orderId]
        );
        $order['items'] = fetchAll($itemsStmt);

        // Lấy thông tin thanh toán nếu có
        $payStmt = queryParams($this->db, "SELECT * FROM payment_events WHERE order_id = ?", [$orderId]);
        $order['payments'] = fetchAll($payStmt);

        // Lấy danh sách đổi trả nếu có
        $retStmt = queryParams($this->db, "SELECT * FROM returns WHERE order_id = ?", [$orderId]);
        $order['returns'] = fetchAll($retStmt);

        return $order;
    }

    public function getOrderByCode(string $code): ?array
    {
        $stmt = queryParams($this->db, "SELECT id FROM orders WHERE order_code = ?", [$code]);
        $row = fetchOne($stmt);
        return $row ? $this->getOrderById((int)$row['id']) : null;
    }

    public function listOrders(array $filter = []): array
    {
        $sql = "SELECT o.*, u.full_name AS customer_name, u.email AS customer_email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filter['user_id'])) {
            $sql .= " AND o.user_id = ?";
            $params[] = (int)$filter['user_id'];
        }

        if (!empty($filter['status'])) {
            $sql .= " AND o.status = ?";
            $params[] = $filter['status'];
        }

        if (!empty($filter['search'])) {
            $sql .= " AND (o.order_code LIKE ? OR u.full_name LIKE ? OR o.customer_phone LIKE ?)";
            $s = "%" . $filter['search'] . "%";
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $sql .= " ORDER BY o.id DESC";
        $stmt = queryParams($this->db, $sql, $params);
        return fetchAll($stmt);
    }
}

