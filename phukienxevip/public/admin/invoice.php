<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);
$user = currentUser();

$db = db();
$orderService = new OrderService($db);

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$orderCode = isset($_GET['code']) ? trim($_GET['code']) : '';

$order = null;
if ($orderId > 0) {
    $order = $orderService->getOrderById($orderId);
} elseif ($orderCode !== '') {
    $order = $orderService->getOrderByCode($orderCode);
}

if (!$order) {
    http_response_code(404);
    echo '<div style="font-family:sans-serif; text-align:center; padding:50px;"><h2>Không tìm thấy đơn hàng để xuất hóa đơn!</h2><p><a href="/admin/orders.php">Quay lại danh sách đơn hàng</a></p></div>';
    exit;
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hóa Đơn Bán Hàng #<?= e($order['order_code']) ?> • PhuKienXeVIP</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 24px;
        background-color: #f1f3f5;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #212529;
        font-size: 14px;
        line-height: 1.5;
    }
    .invoice-card {
        max-width: 860px;
        margin: 0 auto;
        background: #ffffff;
        padding: 40px;
        border-radius: 8px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        border: 1px solid #dee2e6;
    }
    .no-print-toolbar {
        max-width: 860px;
        margin: 0 auto 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border: 1px solid transparent;
        transition: all 0.2s ease;
    }
    .btn-primary { background: #007bff; color: #fff; }
    .btn-primary:hover { background: #0056b3; }
    .btn-secondary { background: #6c757d; color: #fff; }
    .btn-secondary:hover { background: #5a6268; }

    .invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #007bff;
        padding-bottom: 20px;
        margin-bottom: 24px;
    }
    .company-info h2 {
        margin: 0 0 6px;
        font-size: 20px;
        color: #007bff;
        font-weight: 800;
        letter-spacing: 0.5px;
    }
    .company-info p {
        margin: 2px 0;
        color: #495057;
        font-size: 13px;
    }
    .invoice-title-block {
        text-align: right;
    }
    .invoice-title {
        font-size: 24px;
        font-weight: 800;
        color: #212529;
        margin: 0 0 4px;
        text-transform: uppercase;
    }
    .invoice-number {
        font-size: 15px;
        font-weight: 700;
        color: #dc3545;
    }
    .customer-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 16px 20px;
        margin-bottom: 28px;
    }
    .info-group h4 {
        margin: 0 0 8px;
        font-size: 13px;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.5px;
    }
    .info-group p {
        margin: 4px 0;
        font-size: 14px;
    }
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 24px;
    }
    .invoice-table th {
        background: #007bff;
        color: #ffffff;
        font-weight: 600;
        text-align: left;
        padding: 10px 12px;
        font-size: 13px;
        border: 1px solid #007bff;
    }
    .invoice-table td {
        padding: 10px 12px;
        border: 1px solid #dee2e6;
        vertical-align: middle;
    }
    .invoice-table tr:nth-child(even) {
        background-color: #fafbfc;
    }
    .total-box {
        margin-left: auto;
        width: 340px;
        margin-bottom: 30px;
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 14px;
    }
    .total-row.grand-total {
        border-top: 2px solid #212529;
        margin-top: 6px;
        padding-top: 10px;
        font-size: 18px;
        font-weight: 800;
        color: #dc3545;
    }
    .signature-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        text-align: center;
        margin-top: 40px;
        padding-top: 20px;
    }
    .signature-box h5 {
        margin: 0 0 4px;
        font-size: 14px;
        font-weight: 700;
    }
    .signature-box small {
        color: #6c757d;
        font-style: italic;
    }
    .signature-space {
        height: 80px;
    }

    @media print {
        body {
            background: #fff;
            padding: 0;
        }
        .no-print, .no-print-toolbar {
            display: none !important;
        }
        .invoice-card {
            border: none;
            box-shadow: none;
            padding: 10px;
            max-width: 100%;
        }
    }
</style>
</head>
<body>

<!-- THANH CÔNG CỤ ĐIỀU KHIỂN (ẨN KHI IN) -->
<div class="no-print-toolbar">
    <div style="display: flex; gap: 8px;">
        <a href="/admin/orders.php" class="btn btn-secondary">
            ⬅️ Quay lại danh sách đơn hàng
        </a>
        <a href="/admin/index.php" class="btn btn-secondary">
            🏠 Bàn làm việc Admin
        </a>
    </div>
    <div>
        <button type="button" onclick="window.print()" class="btn btn-primary" style="font-size: 15px; padding: 10px 20px;">
            🖨️ In Hóa Đơn / Lưu PDF
        </button>
    </div>
</div>

<!-- KHỐI HÓA ĐƠN BÁN HÀNG CHUẨN A4 -->
<div class="invoice-card">

    <!-- PHẦN ĐẦU HÓA ĐƠN -->
    <div class="invoice-header">
        <div class="company-info">
            <h2>HỆ THỐNG PHỤ KIỆN XE VIP PRO</h2>
            <p><strong>Địa chỉ:</strong> Số 88 Lê Văn Lương, P. Nhân Chính, Q. Thanh Xuân, Hà Nội</p>
            <p><strong>Hotline tư vấn:</strong> 0988.888.888 • <strong>Kỹ thuật:</strong> 0977.777.777</p>
            <p><strong>Website:</strong> phukienxevip.vn • <strong>Email:</strong> support@phukienxevip.vn</p>
        </div>
        <div class="invoice-title-block">
            <h1 class="invoice-title">HÓA ĐƠN BÁN HÀNG</h1>
            <div class="invoice-number">MÃ ĐƠN: <?= e($order['order_code']) ?></div>
            <p style="margin: 4px 0 0; color: #6c757d; font-size: 12px;">Ngày lập: <?= formatDate($order['created_at']) ?></p>
        </div>
    </div>

    <!-- KHỐI THÔNG TIN KHÁCH HÀNG & GIAO NHẬN -->
    <div class="customer-grid">
        <div class="info-group">
            <h4>Thông tin người nhận</h4>
            <p><strong>Họ và tên:</strong> <?= e($order['customer_name'] ?? 'Khách mua hàng') ?></p>
            <p><strong>Số điện thoại:</strong> <?= e($order['customer_phone'] ?? 'Chưa cung cấp') ?></p>
            <p><strong>Địa chỉ giao hàng:</strong> <?= e($order['shipping_address'] ?? 'Tại cửa hàng') ?></p>
        </div>
        <div class="info-group">
            <h4>Thông tin thanh toán & Giao nhận</h4>
            <p><strong>Phương thức:</strong> Cổng thanh toán trực tuyến mô phỏng</p>
            <p><strong>Trạng thái đơn:</strong> <span style="font-weight: 700; color: #007bff;"><?= e($order['status']) ?></span></p>
            <p><strong>Ghi chú:</strong> Giá đã chốt bất biến tại thời điểm đặt (Đề tài 19)</p>
        </div>
    </div>

    <!-- BẢNG CHI TIẾT SẢN PHẨM -->
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">STT</th>
                <th>Tên sản phẩm & Quy cách biến thể</th>
                <th style="width: 100px;">Mã SKU</th>
                <th style="width: 80px; text-align: center;">SL</th>
                <th style="width: 120px; text-align: right;">Đơn giá chốt</th>
                <th style="width: 130px; text-align: right;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $stt = 1;
            $items = $order['items'] ?? [];
            foreach ($items as $item): 
            ?>
                <tr>
                    <td style="text-align: center;"><?= $stt++ ?></td>
                    <td>
                        <strong><?= e($item['product_name']) ?></strong><br>
                        <small style="color: #6c757d;">Biến thể: <?= e($item['variant_info']) ?></small>
                    </td>
                    <td><code><?= e($item['sku'] ?? 'SKU-VIP') ?></code></td>
                    <td style="text-align: center; font-weight: 700;"><?= (int)$item['quantity'] ?></td>
                    <td style="text-align: right;"><?= formatMoney((float)$item['unit_price']) ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= formatMoney((float)$item['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- TỔNG TIỀN THANH TOÁN -->
    <div class="total-box">
        <div class="total-row">
            <span>Tổng tiền hàng:</span>
            <span><?= formatMoney((float)$order['total_amount']) ?></span>
        </div>
        <div class="total-row">
            <span>Phí vận chuyển giao nhanh:</span>
            <span style="color: #28a745; font-weight: 600;">Miễn phí (0đ)</span>
        </div>
        <div class="total-row grand-total">
            <span>TỔNG CỘNG:</span>
            <span><?= formatMoney((float)$order['total_amount']) ?></span>
        </div>
    </div>

    <!-- CHỮ KÝ VÀ XÁC NHẬN CÁC BÊN -->
    <div class="signature-grid">
        <div class="signature-box">
            <h5>Khách hàng</h5>
            <small>(Ký và ghi rõ họ tên)</small>
            <div class="signature-space"></div>
            <strong><?= e($order['customer_name'] ?? '') ?></strong>
        </div>
        <div class="signature-box">
            <h5>Người lập hóa đơn</h5>
            <small>(Ký và ghi rõ họ tên)</small>
            <div class="signature-space"></div>
            <strong><?= e($user['full_name'] ?? 'Bộ phận Kế toán') ?></strong>
        </div>
        <div class="signature-box">
            <h5>Thủ kho & Đóng gói</h5>
            <small>(Ký, đóng dấu xuất kho)</small>
            <div class="signature-space"></div>
            <strong>Bộ phận Kho vận VIP</strong>
        </div>
    </div>

    <!-- LỜI CẢM ƠN DƯỚI CÙNG -->
    <div style="margin-top: 40px; border-top: 1px dashed #ced4da; padding-top: 14px; text-align: center; font-size: 12px; color: #6c757d;">
        Cảm ơn quý khách đã tin tưởng và đồng hành cùng <strong>Phụ Kiện Xe VIP Pro</strong>!<br>
        Mọi khiếu nại hoặc yêu cầu đổi trả một phần vui lòng liên hệ Hotline trong vòng 7 ngày kể từ khi nhận hàng.
    </div>

</div>

</body>
</html>

