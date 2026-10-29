<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireLogin();
$user = currentUser();

$db = db();
$repo = new ProductRepository($db);
$orderService = new OrderService($db);

$cart = getCart();
if (empty($cart)) {
    setFlash('warning', 'Giỏ hàng của bạn đang trống.');
    header('Location: /index.php');
    exit;
}

$cartItems = [];
$totalAmount = 0.0;
$itemsForOrder = [];

foreach ($cart as $vId => $qty) {
    $v = $repo->findVariantById((int)$vId);
    if ($v) {
        $subtotal = (float)$v['price'] * $qty;
        $totalAmount += $subtotal;
        $cartItems[] = [
            'variant' => $v,
            'quantity' => $qty,
            'subtotal' => $subtotal
        ];
        $itemsForOrder[] = [
            'variant_id' => (int)$vId,
            'quantity' => $qty
        ];
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $address = field($_POST, 'address');
    $phone = field($_POST, 'phone');

    if ($address === '' || $phone === '') {
        $error = 'Vui lòng nhập đầy đủ địa chỉ nhận hàng và số điện thoại.';
    } else {
        try {
            $result = $orderService->createOrderWithReservation(
                (int)$user['id'],
                $address,
                $phone,
                $itemsForOrder
            );

            clearCart();
            setFlash('success', "Đặt hàng thành công! Đơn hàng {$result['order_code']} đang được giữ hàng trong 15 phút.");
            header('Location: /order-detail.php?id=' . $result['order_id']);
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

require __DIR__ . '/../templates/header.php';
?>

<!-- RIBBON PHONG CÁCH SHOPEE ADDRESS -->
<div style="background: repeating-linear-gradient(45deg,#ee4d2d,#ee4d2d 30px,#ffffff 30px,#ffffff 40px,#1a94ff 40px,#1a94ff 70px,#ffffff 70px,#ffffff 80px); height: 4px; border-radius: 4px 4px 0 0; margin-bottom: 0;"></div>

<div style="background: #ffffff; border: 1px solid var(--outline); border-top: none; border-radius: 0 0 var(--radius-md) var(--radius-md); padding: 20px 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
    <h2 style="margin: 0 0 16px; font-size: 1.125rem; color: #ee4d2d; display: flex; align-items: center; gap: 8px;">
        📍 Địa chỉ nhận hàng
    </h2>

    <form method="post" id="checkout-form">
        <?= csrfField() ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <div class="form-field">
                <label style="font-size: 0.8125rem; color: #6c757d;">Họ và tên người nhận</label>
                <input type="text" value="<?= e($user['full_name']) ?>" disabled style="background: #f8f9fa; font-weight: 600;">
            </div>
            <div class="form-field">
                <label for="phone" style="font-size: 0.8125rem; color: #6c757d;">Số điện thoại nhận hàng *</label>
                <input id="phone" name="phone" value="<?= e($_POST['phone'] ?? $user['phone'] ?? '0981234567') ?>" required style="border: 1px solid var(--outline); padding: 10px; border-radius: 4px;">
            </div>
            <div class="form-field" style="grid-column: 1 / -1;">
                <label for="address" style="font-size: 0.8125rem; color: #6c757d;">Địa chỉ chi tiết (Số nhà, đường, phường, quận) *</label>
                <input id="address" name="address" value="<?= e($_POST['address'] ?? $user['address'] ?? 'Số 1, Phố Đại Nam, Phường Phú Lãm, Hà Đông, Hà Nội') ?>" required style="border: 1px solid var(--outline); padding: 10px; border-radius: 4px;">
            </div>
        </div>
    </form>
</div>

<?php if ($error): ?>
    <div class="alert-box alert--danger"><?= e($error) ?></div>
<?php endif; ?>

<!-- DANH SÁCH MẶT HÀNG CHỐT GIÁ -->
<div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
    <h2 style="margin: 0 0 16px; font-size: 1.125rem; color: #212529;">
        🛍️ Sản phẩm thanh toán (<?= count($cartItems) ?> món)
    </h2>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Biến thể</th>
                    <th>Đơn giá chốt</th>
                    <th>Số lượng</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $it): ?>
                    <?php 
                        $v = $it['variant']; 
                        $attrStr = implode(' - ', array_values($v['attributes']));
                    ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 12px;">
                            <?php if (!empty($v['image_url'])): ?>
                                <img src="<?= e(productImageUrl($v['image_url'])) ?>" alt="<?= e($v['product_name']) ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 4px; border: 1px solid #eee;">
                            <?php endif; ?>
                            <strong><?= e($v['product_name']) ?></strong>
                        </td>
                        <td><span style="background: #f0f0f5; padding: 2px 8px; border-radius: 4px; font-size: 0.8125rem;"><?= e($attrStr) ?></span></td>
                        <td><?= formatMoney((float)$v['price']) ?></td>
                        <td><?= $it['quantity'] ?></td>
                        <td><strong style="color: #ee4d2d;"><?= formatMoney($it['subtotal']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TÓM TẮT THANH TOÁN VÀ ĐẶT HÀNG -->
    <div style="border-top: 1px dashed #e0e0e0; padding-top: 20px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div style="max-width: 480px;">
            <p style="margin: 0; font-size: 0.875rem; color: #b78103; font-weight: 600;">
                🔒 Bảo vệ giữ hàng thông minh:
            </p>
            <p style="margin: 4px 0 0; font-size: 0.8125rem; color: #6c757d;">
                Sau khi bấm Đặt hàng, hệ thống khóa giữ chỗ sản phẩm trong <strong>15 phút</strong>. Giá thanh toán được đóng băng bất biến.
            </p>
        </div>

        <div style="text-align: right;">
            <div style="font-size: 0.875rem; color: #6c757d; margin-bottom: 4px;">
                Tổng tiền hàng: <strong><?= formatMoney($totalAmount) ?></strong>
            </div>
            <div style="font-size: 0.875rem; color: #28a745; margin-bottom: 8px;">
                Phí vận chuyển: <strong>0 ₫ (Miễn phí)</strong>
            </div>
            <div style="font-size: 1.125rem; color: #212529;">
                Tổng thanh toán: <strong style="font-size: 1.75rem; color: #ee4d2d; font-weight: 800;"><?= formatMoney($totalAmount) ?></strong>
            </div>
            <button type="submit" form="checkout-form" class="filled-button" style="margin-top: 12px; padding: 14px 36px; font-size: 1.0625rem;">
                Đặt Hàng & Giữ Chỗ Ngay ➔
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
