<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class PaymentService
{
    public function __construct(private mysqli $db) {}

    /**
     * Xử lý webhook / thông báo thanh toán mô phỏng
     * ĐÁP ỨNG KỸ THUẬT 2:
     * 1. Kiểm soát thông báo lặp (Idempotency): Gửi lặp webhook không làm đổi số lượng hoặc tiền thêm lần nữa.
     * 2. Xử lý thanh toán đến muộn (Late Payment): Đơn đã hết hạn không tự động hồi sinh để tránh cấp hàng vượt tồn kho.
     */
    public function processPaymentWebhook(
        string $orderCode,
        string $transactionCode,
        float $amount,
        string $gatewayName = 'MOCK_PAY',
        array $payload = []
    ): array {
        $this->db->begin_transaction();
        try {
            // =========================================================================
            // BƯỚC 1: KIỂM SOÁT THÔNG BÁO LẶP (IDEMPOTENCY CHECK)
            // =========================================================================
            $checkStmt = queryParams(
                $this->db,
                "SELECT * FROM payment_events WHERE transaction_code = ? FOR UPDATE",
                [$transactionCode]
            );
            $existingPayment = fetchOne($checkStmt);

            if ($existingPayment) {
                // Đã xử lý giao dịch này rồi -> Trả về kết quả cũ an toàn mà không cộng tiền hay đổi trạng thái
                $this->db->commit();
                return [
                    'status' => 'DUPLICATE_IGNORED',
                    'message' => 'Giao dịch thanh toán đã được xử lý từ trước. Bỏ qua thông báo lặp (Idempotent).',
                    'transaction_code' => $transactionCode,
                    'original_status' => $existingPayment['status']
                ];
            }

            // =========================================================================
            // BƯỚC 2: TÌM ĐƠN HÀNG VÀ KHÓA DÒNG (PESSIMISTIC LOCK)
            // =========================================================================
            $orderStmt = queryParams(
                $this->db,
                "SELECT * FROM orders WHERE order_code = ? FOR UPDATE",
                [$orderCode]
            );
            $order = fetchOne($orderStmt);

            if (!$order) {
                throw new RuntimeException("Không tìm thấy đơn hàng có mã: $orderCode");
            }

            $orderId = (int)$order['id'];
            $currentStatus = $order['status'];
            $totalAmount = (float)$order['total_amount'];

            // =========================================================================
            // BƯỚC 3: XỬ LÝ THANH TOÁN ĐẾN MUỘN (LATE PAYMENT)
            // Nếu đơn đã quá hạn (EXPIRED) hoặc đã bị hủy (CANCELLED):
            // Lượng hàng giữ đã được giải phóng cho người khác, KHÔNG ĐƯỢC TỰ Ý HỒI SINH ĐƠN!
            // =========================================================================
            if ($currentStatus === 'EXPIRED' || $currentStatus === 'CANCELLED') {
                queryParams(
                    $this->db,
                    "INSERT INTO payment_events (order_id, transaction_code, amount, status, gateway_name, raw_payload) 
                     VALUES (?, ?, ?, 'LATE_IGNORED', ?, ?)",
                    [
                        $orderId,
                        $transactionCode,
                        $amount,
                        $gatewayName,
                        json_encode($payload, JSON_UNESCAPED_UNICODE)
                    ]
                );

                queryParams(
                    $this->db,
                    "INSERT INTO order_audit_logs (order_id, actor_role, action, old_state, new_state, amount_change, note) 
                     VALUES (?, 'PAYMENT_GATEWAY', 'LATE_PAYMENT_RECEIVED', ?, ?, ?, 'Thanh toán đến sau khi đơn đã hết hạn/hủy. Không hồi sinh đơn, tạo yêu cầu hoàn tiền cho khách')",
                    [$orderId, $currentStatus, $currentStatus, $amount]
                );

                $this->db->commit();

                return [
                    'status' => 'LATE_PAYMENT',
                    'message' => 'Đơn hàng đã hết hạn hoặc đã hủy. Tiền đã nhận nhưng không thể kích hoạt đơn (tránh cấp hàng vượt tồn). Vui lòng hoàn tiền cho khách.',
                    'order_code' => $orderCode,
                    'transaction_code' => $transactionCode
                ];
            }

            // =========================================================================
            // BƯỚC 4: THANH TOÁN HỢP LỆ CHO ĐƠN PENDING
            // =========================================================================
            if ($currentStatus === 'PENDING') {
                if ($amount < $totalAmount) {
                    throw new RuntimeException("Số tiền thanh toán ($amount) nhỏ hơn tổng đơn ($totalAmount).");
                }

                // 1. Chốt trừ kho thực tế vĩnh viễn trong bảng variants
                $itemsStmt = queryParams($this->db, "SELECT variant_id, quantity FROM order_items WHERE order_id = ?", [$orderId]);
                $items = fetchAll($itemsStmt);
                foreach ($items as $it) {
                    queryParams(
                        $this->db,
                        "UPDATE variants SET stock_quantity = stock_quantity - ? WHERE id = ?",
                        [$it['quantity'], $it['variant_id']]
                    );
                }

                // 2. Chuyển trạng thái giữ hàng sang CONSUMED (đã tiêu thụ)
                queryParams(
                    $this->db,
                    "UPDATE reservations SET status = 'CONSUMED' WHERE order_id = ? AND status = 'HOLDING'",
                    [$orderId]
                );

                // 3. Cập nhật trạng thái đơn hàng sang PAID
                queryParams(
                    $this->db,
                    "UPDATE orders SET status = 'PAID' WHERE id = ?",
                    [$orderId]
                );

                // 4. Lưu sự kiện thanh toán thành công
                queryParams(
                    $this->db,
                    "INSERT INTO payment_events (order_id, transaction_code, amount, status, gateway_name, raw_payload) 
                     VALUES (?, ?, ?, 'SUCCESS', ?, ?)",
                    [
                        $orderId,
                        $transactionCode,
                        $amount,
                        $gatewayName,
                        json_encode($payload, JSON_UNESCAPED_UNICODE)
                    ]
                );

                // 5. Ghi audit log
                queryParams(
                    $this->db,
                    "INSERT INTO order_audit_logs (order_id, actor_role, action, old_state, new_state, amount_change, note) 
                     VALUES (?, 'PAYMENT_GATEWAY', 'PAYMENT_SUCCESS', 'PENDING', 'PAID', ?, 'Khách hàng thanh toán thành công')",
                    [$orderId, $amount]
                );

                $this->db->commit();

                return [
                    'status' => 'SUCCESS',
                    'message' => 'Thanh toán thành công. Đơn hàng đã chuyển sang trạng thái PAID.',
                    'order_code' => $orderCode,
                    'transaction_code' => $transactionCode
                ];
            }

            // Nếu đơn đã ở trạng thái khác (ví dụ: đã PAID từ trước với mã giao dịch khác)
            $this->db->commit();
            return [
                'status' => 'ALREADY_PAID',
                'message' => 'Đơn hàng này đã được thanh toán trước đó.',
                'order_code' => $orderCode
            ];

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}

