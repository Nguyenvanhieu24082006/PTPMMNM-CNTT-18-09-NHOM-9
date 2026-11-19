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

<style>
/* ==========================================================================
   CSS GIAO DIỆN THANH TOÁN DARK LUXURY AUTOMOTIVE (PHUKIENXEVIP)
   ========================================================================== */
.checkout-wrapper {
    max-width: 1240px;
    margin: 24px auto 48px;
    padding: 0 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: #e5e7eb;
}

/* Breadcrumb & Tiến trình đặt hàng */
.checkout-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #181818;
    border: 1px solid #2e2e2e;
    border-radius: 12px;
    padding: 16px 28px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.step-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #9ca3af;
}
.step-item.active {
    color: #dfa056;
}
.step-item.done {
    color: #22c55e;
}
.step-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    background: #262626;
    border: 2px solid #3a3a3a;
    color: #9ca3af;
    font-weight: 700;
}
.step-item.active .step-circle {
    background: linear-gradient(135deg, #dfa056, #c98a44);
    border-color: #dfa056;
    color: #120e0b;
    box-shadow: 0 0 12px rgba(223, 160, 86, 0.4);
}
.step-item.done .step-circle {
    background: #052e16;
    border-color: #22c55e;
    color: #22c55e;
}
.step-divider {
    flex: 1;
    height: 2px;
    background: #2a2a2a;
    margin: 0 20px;
}

/* Bố cục 2 Cột */
.checkout-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 24px;
    align-items: start;
}
@media (max-width: 992px) {
    .checkout-grid {
        grid-template-columns: 1fr;
    }
    .checkout-steps {
        padding: 12px 16px;
    }
    .step-item span.step-text {
        display: none;
    }
}

/* Thẻ khối Dark Luxury */
.vip-card {
    background: #181818;
    border: 1px solid #2e2e2e;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.25);
    transition: border-color 0.25s ease;
}
.vip-card:hover {
    border-color: #3d3d3d;
}

/* Dải viền mạ vàng trên nóc thẻ */
.card-gold-stripe {
    height: 3px;
    background: linear-gradient(90deg, #dfa056, #c98a44 50%, #7b4f30);
}

.vip-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid #262626;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.vip-card-title {
    margin: 0;
    font-size: 1.0625rem;
    font-weight: 700;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: 0.3px;
}
.vip-card-title svg {
    width: 20px;
    height: 20px;
    color: #dfa056;
}
.vip-badge-tag {
    font-size: 0.6875rem;
    text-transform: uppercase;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    background: rgba(223, 160, 86, 0.15);
    color: #dfa056;
    border: 1px solid rgba(223, 160, 86, 0.3);
}

.vip-card-body {
    padding: 24px;
}

/* Input Form sang trọng */
.form-group-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
@media (max-width: 640px) {
    .form-group-grid {
        grid-template-columns: 1fr;
    }
}
.vip-input-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}
.vip-input-group.full-width {
    grid-column: 1 / -1;
}
.vip-input-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 6px;
}
.vip-input-control {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    background: #121212;
    border: 1px solid #333333;
    border-radius: 8px;
    color: #ffffff;
    font-size: 0.9375rem;
    outline: none;
    transition: all 0.2s ease;
}
.vip-input-control:focus {
    border-color: #dfa056;
    box-shadow: 0 0 0 3px rgba(223, 160, 86, 0.15);
    background: #141414;
}
.vip-input-control:disabled {
    background: #1f1f1f;
    color: #a3a3a3;
    border-color: #2c2c2c;
    cursor: not-allowed;
}

/* Danh sách Thẻ Sản phẩm (Thay thế bảng Table) */
.product-item-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #141414;
    border: 1px solid #282828;
    border-radius: 10px;
    margin-bottom: 12px;
    transition: background 0.2s ease, border-color 0.2s ease;
}
.product-item-card:last-child {
    margin-bottom: 0;
}
.product-item-card:hover {
    background: #1a1a1a;
    border-color: #383838;
}
.product-item-thumb {
    width: 68px;
    height: 68px;
    border-radius: 8px;
    object-fit: cover;
    background: #222222;
    border: 1px solid #333333;
    flex-shrink: 0;
}
.product-item-info {
    flex: 1;
    min-width: 0;
}
.product-item-name {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #f3f4f6;
    margin: 0 0 6px;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.product-item-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    font-size: 0.8125rem;
}
.variant-chip {
    background: rgba(223, 160, 86, 0.12);
    color: #dfa056;
    border: 1px solid rgba(223, 160, 86, 0.25);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}
.qty-tag {
    color: #9ca3af;
    background: #242424;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    border: 1px solid #333333;
}
.product-item-pricing {
    text-align: right;
    flex-shrink: 0;
}
.item-unit-price {
    font-size: 0.75rem;
    color: #6b7280;
    text-decoration: none;
    margin-bottom: 2px;
}
.item-subtotal-price {
    font-size: 1.0625rem;
    font-weight: 700;
    color: #dfa056;
}



/* Cột phải - Sticky Order Summary */
.sticky-summary-box {
    position: sticky;
    top: 90px;
}
.summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    font-size: 0.9375rem;
    color: #9ca3af;
}
.summary-line strong {
    color: #f3f4f6;
    font-weight: 600;
}
.summary-line.free-shipping strong {
    color: #22c55e;
}
.summary-divider {
    height: 1px;
    background: #2a2a2a;
    margin: 16px 0;
}
.summary-total-block {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 20px;
}
.summary-total-label {
    font-size: 1rem;
    font-weight: 700;
    color: #ffffff;
}
.summary-total-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: #dfa056;
    letter-spacing: -0.5px;
    text-shadow: 0 0 20px rgba(223, 160, 86, 0.25);
}

/* Nút đặt hàng siêu VIP */
.btn-checkout-luxury {
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, #dfa056 0%, #c98a44 50%, #9e6425 100%);
    color: #000000;
    font-weight: 800;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(201, 138, 68, 0.45);
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.btn-checkout-luxury:hover {
    background: linear-gradient(135deg, #f5b061 0%, #dfa056 50%, #b47530 100%);
    box-shadow: 0 8px 26px rgba(223, 160, 86, 0.6);
    transform: translateY(-2px);
}
.btn-checkout-luxury:active {
    transform: translateY(0);
    box-shadow: 0 3px 12px rgba(201, 138, 68, 0.4);
}

.checkout-policy-notes {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px dashed #282828;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 0.75rem;
    color: #9ca3af;
}
.policy-note-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.policy-note-item span.icon {
    color: #dfa056;
}
</style>

<div class="checkout-wrapper">

    <!-- 1. THANH TIẾN TRÌNH ĐẶT HÀNG -->
    <div class="checkout-steps">
        <div class="step-item done">
            <div class="step-circle">✓</div>
            <span class="step-text">1. Giỏ hàng</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item active">
            <div class="step-circle">2</div>
            <span class="step-text">2. Thanh toán & Khóa giữ hàng</span>
        </div>
        <div class="step-divider"></div>
        <div class="step-item">
            <div class="step-circle">3</div>
            <span class="step-text">3. Tiếp nhận đơn hàng</span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert-box alert--danger" style="margin-bottom: 24px; background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 14px 18px; border-radius: 8px;">
            ⚠️ <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- 2. BỐ CỤC 2 CỘT -->
    <div class="checkout-grid">

        <!-- CỘT TRÁI: FORM ĐỊA CHỈ & DANH SÁCH MÓN HÀNG -->
        <div class="checkout-left-col">

            <!-- CARD ĐỊA CHỈ GIAO HÀNG -->
            <div class="vip-card">
                <div class="card-gold-stripe"></div>
                <div class="vip-card-header">
                    <h3 class="vip-card-title">
                        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                        ĐỊA CHỈ NHẬN HÀNG
                    </h3>
                    <span class="vip-badge-tag">GIAO NHANH 24H</span>
                </div>
                <div class="vip-card-body">
                    <form method="post" id="checkout-form">
                        <?= csrfField() ?>
                        
                        <div class="form-group-grid">
                            <div class="vip-input-group">
                                <label class="vip-input-label">
                                    <span>👤</span> Họ và tên người nhận
                                </label>
                                <input type="text" class="vip-input-control" value="<?= e($user['full_name']) ?>" disabled title="Tài khoản khách hàng">
                            </div>

                            <div class="vip-input-group">
                                <label for="phone" class="vip-input-label">
                                    <span>📞</span> Số điện thoại nhận hàng <span style="color: #ef4444;">*</span>
                                </label>
                                <input id="phone" name="phone" class="vip-input-control" value="<?= e($_POST['phone'] ?? $user['phone'] ?? '0981234567') ?>" required placeholder="Ví dụ: 0988.888.888">
                            </div>

                            <div class="vip-input-group full-width">
                                <label for="address" class="vip-input-label">
                                    <span>🏠</span> Địa chỉ nhận hàng chi tiết <span style="color: #ef4444;">*</span>
                                </label>
                                <input id="address" name="address" class="vip-input-control" value="<?= e($_POST['address'] ?? $user['address'] ?? 'Số 1, Phố Đại Nam, Phường Phú Lãm, Hà Đông, Hà Nội') ?>" required placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố">
                            </div>

                            <div class="vip-input-group full-width">
                                <label class="vip-input-label">
                                    <span>📝</span> Ghi chú đơn hàng (Tùy chọn)
                                </label>
                                <input type="text" class="vip-input-control" placeholder="Ví dụ: Giao hàng vào giờ hành chính, gọi trước khi giao 15 phút..." style="font-size: 0.875rem;">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CARD DANH SÁCH MẶT HÀNG THANH TOÁN -->
            <div class="vip-card">
                <div class="vip-card-header">
                    <h3 class="vip-card-title">
                        <svg fill="currentColor" viewBox="0 0 20 20"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.24-1.2A1 1 0 005.06 1H3zM7 17a2 2 0 100 4 2 2 0 000-4zm8 0a2 2 0 100 4 2 2 0 000-4z"></path></svg>
                        DANH SÁCH SẢN PHẨM (<?= count($cartItems) ?> món)
                    </h3>
                    <a href="/cart.php" style="color: #9ca3af; font-size: 0.8125rem; text-decoration: none;">✏️ Chỉnh sửa giỏ</a>
                </div>
                <div class="vip-card-body">
                    <?php foreach ($cartItems as $it): ?>
                        <?php 
                            $v = $it['variant']; 
                            $attrStr = implode(' • ', array_values($v['attributes']));
                        ?>
                        <div class="product-item-card">
                            <img src="<?= e(productImageUrl($v['image_url'])) ?>" alt="<?= e($v['product_name']) ?>" class="product-item-thumb">
                            <div class="product-item-info">
                                <h4 class="product-item-name" title="<?= e($v['product_name']) ?>"><?= e($v['product_name']) ?></h4>
                                <div class="product-item-meta">
                                    <span class="variant-chip"><?= e($attrStr) ?></span>
                                    <span class="qty-tag">SL: <?= (int)$it['quantity'] ?></span>
                                </div>
                            </div>
                            <div class="product-item-pricing">
                                <div class="item-unit-price"><?= formatMoney((float)$v['price']) ?> / món</div>
                                <div class="item-subtotal-price"><?= formatMoney($it['subtotal']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>



                </div>
            </div>

        </div>

        <!-- CỘT PHẢI: STICKY TÓM TẮT ĐƠN HÀNG & NÚT ĐẶT HÀNG -->
        <div class="checkout-right-col">
            <div class="sticky-summary-box">
                <div class="vip-card">
                    <div class="card-gold-stripe"></div>
                    <div class="vip-card-header">
                        <h3 class="vip-card-title">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path></svg>
                            TÓM TẮT ĐƠN HÀNG
                        </h3>
                    </div>
                    <div class="vip-card-body">
                        <div class="summary-line">
                            <span>Tạm tính tiền hàng:</span>
                            <strong><?= formatMoney($totalAmount) ?></strong>
                        </div>
                        <div class="summary-line free-shipping">
                            <span>Phí vận chuyển giao nhanh:</span>
                            <strong>MIỄN PHÍ (0 ₫)</strong>
                        </div>
                        <div class="summary-line">
                            <span>Bảo hiểm kiện hàng xe sang:</span>
                            <strong style="color: #dfa056;">Đã bao gồm</strong>
                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-total-block">
                            <div>
                                <div class="summary-total-label">TỔNG THANH TOÁN</div>
                                <div style="font-size: 0.75rem; color: #6b7280;">(Đã bao gồm thuế VAT & Hóa đơn)</div>
                            </div>
                            <div class="summary-total-value"><?= formatMoney($totalAmount) ?></div>
                        </div>

                        <button type="submit" form="checkout-form" class="btn-checkout-luxury" id="btn-submit-order">
                            <span>⚡</span> ĐẶT HÀNG & GIỮ CHỖ (15P)
                        </button>

                        <div class="checkout-policy-notes">
                            <div class="policy-note-item">
                                <span class="icon">🔒</span>
                                <span>Giao dịch bảo mật cấp ngân hàng 256-bit SSL</span>
                            </div>
                            <div class="policy-note-item">
                                <span class="icon">🔄</span>
                                <span>Hỗ trợ chính sách đổi trả một phần minh bạch</span>
                            </div>
                            <div class="policy-note-item">
                                <span class="icon">📦</span>
                                <span>Kiểm tra hàng trước khi thanh toán</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
