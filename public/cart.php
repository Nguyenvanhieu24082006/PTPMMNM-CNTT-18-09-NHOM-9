<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

$db = db();
$repo = new ProductRepository($db);
$cart = getCart();

// Xử lý cập nhật số lượng hoặc xóa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = field($_POST, 'action');
    $variantId = fieldInt($_POST, 'variant_id');

    if ($action === 'update') {
        $quantity = fieldInt($_POST, 'quantity');
        updateCart($variantId, $quantity);
        setFlash('info', 'Đã cập nhật số lượng trong giỏ hàng.');
    } elseif ($action === 'remove') {
        updateCart($variantId, 0);
        setFlash('info', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    } elseif ($action === 'clear') {
        clearCart();
        setFlash('info', 'Đã làm trống giỏ hàng.');
    }
    header('Location: /cart.php');
    exit;
}

$cartItems = [];
$totalAmount = 0.0;

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
    }
}

require __DIR__ . '/../templates/header.php';
?>

<div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 18px 24px; margin-bottom: 20px; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="margin: 0; font-size: 1.375rem; color: #ee4d2d;">🛒 Giỏ hàng của bạn</h1>
        <p style="margin: 4px 0 0; font-size: 0.8125rem; color: #6c757d;">Kiểm tra sản phẩm và số lượng trước khi chốt đơn giữ hàng 15 phút.</p>
    </div>
    <div style="font-size: 0.8125rem; background: #e8f5e9; color: #1b5e20; padding: 6px 12px; border-radius: 4px; font-weight: 600;">
        🚚 Miễn phí vận chuyển cho đơn từ 0Đ
    </div>
</div>

<?php if (empty($cartItems)): ?>
    <div style="text-align: center; padding: 64px 24px; background: #ffffff; border-radius: var(--radius-md); border: 1px solid var(--outline); box-shadow: var(--shadow-sm);">
        <span style="font-size: 4rem;">🛒</span>
        <p style="font-size: 1.125rem; color: #6c757d; margin: 16px 0 20px;">Giỏ hàng của bạn còn trống</p>
        <a href="/" class="filled-button" style="padding: 10px 24px;">Mua sắm ngay</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Biến thể</th>
                    <th>Đơn giá</th>
                    <th>Số lượng</th>
                    <th>Số tiền</th>
                    <th>Thao tác</th>
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
                                <img src="<?= e(productImageUrl($v['image_url'])) ?>" alt="<?= e($v['product_name']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #eee;">
                            <?php endif; ?>
                            <div>
                                <strong style="color: #212529; font-size: 0.9375rem;"><?= e($v['product_name']) ?></strong><br>
                                <small style="color: #6c757d; font-family: monospace;">SKU: <?= e($v['sku']) ?></small>
                            </div>
                        </td>
                        <td>
                            <span style="background: #f0f0f5; padding: 4px 8px; border-radius: 4px; font-size: 0.8125rem;">
                                <?= e($attrStr) ?>
                            </span>
                        </td>
                        <td><strong><?= formatMoney((float)$v['price']) ?></strong></td>
                        <td>
                            <form method="post" style="display: flex; gap: 4px; align-items: center; margin: 0;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="variant_id" value="<?= (int)$v['id'] ?>">
                                <input type="number" name="quantity" value="<?= (int)$it['quantity'] ?>" min="1" max="10" style="width: 55px; min-height: 34px; padding: 4px; text-align: center; border: 1px solid var(--outline); border-radius: 4px;">
                                <button type="submit" class="button-secondary" style="min-height: 34px; padding: 4px 8px; font-size: 0.75rem;">Sửa</button>
                            </form>
                        </td>
                        <td><strong style="color: #ee4d2d; font-size: 1rem;"><?= formatMoney($it['subtotal']) ?></strong></td>
                        <td>
                            <form method="post" style="margin: 0;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="variant_id" value="<?= (int)$v['id'] ?>">
                                <button type="submit" class="button-cancel" style="min-height: 34px; padding: 4px 10px; font-size: 0.75rem;">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Thanh tổng tiền và chốt đơn phong cách Shopee Bottom Bar -->
    <div style="background: #ffffff; border: 1px solid var(--outline); border-radius: var(--radius-md); padding: 20px 24px; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <form method="post" style="margin: 0;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="button-secondary" style="font-size: 0.8125rem;">🗑️ Xóa toàn bộ giỏ hàng</button>
        </form>

        <div style="display: flex; align-items: center; gap: 24px;">
            <div style="text-align: right;">
                <div style="font-size: 0.875rem; color: #6c757d;">Tổng thanh toán (<?= count($cartItems) ?> mặt hàng):</div>
                <div style="font-size: 1.625rem; font-weight: 800; color: #ee4d2d;"><?= formatMoney($totalAmount) ?></div>
            </div>

            <a href="/checkout.php" class="filled-button" style="padding: 12px 32px; font-size: 1rem;">
                Mua Hàng & Giữ Chỗ (15 Phút) ➔
            </a>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
