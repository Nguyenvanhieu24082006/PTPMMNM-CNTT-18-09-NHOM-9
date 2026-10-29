<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

$db = db();
$repo = new ProductRepository($db);

$productId = fieldInt($_GET, 'id');
if ($productId <= 0) {
    // Nếu không truyền ID, mặc định lấy sản phẩm đầu tiên của danh mục Thảm lót sàn
    $sample = $repo->getAll('', 'Thảm lót sàn')[0] ?? null;
    $productId = $sample ? (int)$sample['id'] : 1;
}

$product = $repo->findById($productId);
if (!$product) {
    setFlash('danger', 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.');
    header('Location: /index.php');
    exit;
}

$variants = $product['variants'] ?? [];
if (empty($variants)) {
    // Nếu chưa có biến thể, tự tạo 1 biến thể mặc định
    $variants[] = [
        'id' => 0,
        'sku' => 'VIP-' . $product['id'],
        'price' => 250000,
        'stock_quantity' => 95,
        'available_quantity' => 95,
        'attributes' => ['Mẫu' => 'Tiêu chuẩn']
    ];
}

$firstVariant = $variants[0];
$selectedVariantId = fieldInt($_GET, 'var', (int)$firstVariant['id']);
$selectedVariant = null;
foreach ($variants as $v) {
    if ((int)$v['id'] === $selectedVariantId) {
        $selectedVariant = $v;
        break;
    }
}
if (!$selectedVariant) {
    $selectedVariant = $firstVariant;
}

// Xử lý thêm vào giỏ hàng hoặc mua ngay
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = field($_POST, 'action');
    $variantId = fieldInt($_POST, 'variant_id', (int)$selectedVariant['id']);
    $quantity = fieldInt($_POST, 'quantity', 1);
    if ($quantity < 1) $quantity = 1;

    $targetVariant = $repo->findVariantById($variantId);
    if (!$targetVariant) {
        setFlash('danger', 'Phiên bản sản phẩm đã chọn không tồn tại.');
    } elseif ($targetVariant['available_quantity'] < $quantity) {
        setFlash('danger', 'Lượng hàng có thể bán trong kho không đủ cho số lượng bạn chọn.');
    } else {
        $cart = getCart();
        $newQty = ($cart[$variantId] ?? 0) + $quantity;
        updateCart($variantId, $newQty);

        if ($action === 'buy_now') {
            header('Location: /checkout.php');
            exit;
        } else {
            setFlash('success', "Đã thêm {$quantity} sản phẩm '{$product['name']}' vào giỏ hàng thành công!");
            header("Location: /product.php?id={$productId}&var={$variantId}");
            exit;
        }
    }
}

// Giá & Khuyến mãi
$currentPrice = (float)$selectedVariant['price'];
$oldPrice = $currentPrice >= 1000000 ? round($currentPrice * 1.35, -4) : round($currentPrice * 1.30, -3);
$discountPercent = round((($oldPrice - $currentPrice) / $oldPrice) * 100);

// Chuẩn bị danh sách ảnh gallery (Thumbnail dọc bên trái)
$mainImg = productImageUrl($product['image_url']);
$galleryImages = [$mainImg];

// Lấy thêm ảnh từ các sản phẩm cùng danh mục để làm ảnh góc cạnh / tính năng chi tiết
$catProducts = $repo->getAll('', $product['category']);
$extraCount = 0;
foreach ($catProducts as $cp) {
    if ((int)$cp['id'] !== (int)$product['id'] && !empty($cp['image_url'])) {
        $imgUrl = productImageUrl($cp['image_url']);
        if (!in_array($imgUrl, $galleryImages, true)) {
            $galleryImages[] = $imgUrl;
            $extraCount++;
            if ($extraCount >= 5) break;
        }
    }
}

// Sản phẩm tương tự (5 sản phẩm cùng danh mục)
$relatedProducts = [];
foreach ($catProducts as $cp) {
    if ((int)$cp['id'] !== (int)$product['id']) {
        $relatedProducts[] = $cp;
        if (count($relatedProducts) >= 5) break;
    }
}

require __DIR__ . '/../templates/header.php';
?>

<div class="pdetail-wrapper">
    <!-- Breadcrumb điều hướng -->
    <nav class="pdetail-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Trang chủ</a>
        <span class="sep">/</span>
        <a href="/index.php#noithat-sang-trong">Nội thất ô tô</a>
        <span class="sep">/</span>
        <a href="/index.php?cat=<?= urlencode($product['category']) ?>"><?= e($product['category']) ?></a>
        <span class="sep">/</span>
        <span class="current"><?= e($product['name']) ?></span>
    </nav>

    <!-- Layout chính 2 cột theo phong cách PhuKienXeVIP -->
    <div class="pdetail-main-grid">
        <!-- CỘT BÊN TRÁI: THUMBNAILS DỌC & KHUNG ẢNH CHÍNH LỚN -->
        <div class="pdetail-gallery-container">
            <div class="pdetail-thumbs-col">
                <?php foreach ($galleryImages as $idx => $gImg): ?>
                    <button type="button" 
                            class="pdetail-thumb-btn <?= $idx === 0 ? 'active' : '' ?>"
                            onclick="switchGalleryImage('<?= e($gImg) ?>', this)"
                            title="Xem góc chụp <?= $idx + 1 ?>">
                        <img src="<?= e($gImg) ?>" alt="Ảnh góc <?= $idx + 1 ?>">
                        <?php if ($idx === 0): ?>
                            <!-- Biểu tượng Video Play góc ảnh 1 chuẩn như thiết kế mẫu -->
                            <div class="thumb-video-icon" title="Video giới thiệu">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="pdetail-main-img-box">
                <img id="mainProductPreview" src="<?= e($mainImg) ?>" alt="<?= e($product['name']) ?>">
                
                <!-- Huy hiệu nổi bật chuẩn theo ảnh mẫu thiết kế -->
                <div class="pdetail-floating-badges">
                    <span class="pdetail-badge-pill badge-fast">GIAO HÀNG NHANH</span>
                    <span class="pdetail-badge-pill badge-return">ĐỔI TRẢ LINH HOẠT</span>
                </div>

                <!-- Nút phóng to toàn màn hình -->
                <button type="button" class="pdetail-zoom-btn" onclick="openFullscreenImage()" title="Phóng to ảnh">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- CỘT BÊN PHẢI: KHỐI THÔNG TIN VÀ NÚT MUA HÀNG SANG TRỌNG -->
        <div class="pdetail-info-card">
            <!-- Thanh Banner Giá Khuyến Mãi Màu Nâu Đồng Sang Trọng -->
            <div class="pdetail-flash-price-bar">
                <div class="price-left">
                    <span class="price-current" id="dynPriceCurrent"><?= formatCurrency($currentPrice) ?></span>
                    <span class="price-old" id="dynPriceOld"><?= formatCurrency($oldPrice) ?></span>
                </div>
                <div class="price-right-badge">
                    <span class="lightning-icon">⚡</span>
                    <span class="discount-text">- <?= $discountPercent ?>%</span>
                    <span class="badge-sub">ƯU ĐÃI HÔM NAY</span>
                </div>
            </div>

            <!-- Tên sản phẩm chính -->
            <h1 class="pdetail-title"><?= e($product['name']) ?></h1>

            <!-- Đánh giá và SKU -->
            <div class="pdetail-meta-row">
                <div class="pdetail-rating">
                    <span class="stars">★★★★★</span>
                    <strong class="score">4.9</strong>
                    <span class="rating-count">(128 đánh giá)</span>
                </div>
                <a href="#danh-gia" class="pdetail-review-link">Xem đánh giá</a>
            </div>

            <!-- Form Đặt hàng & Lựa chọn biến thể -->
            <form method="post" action="/product.php?id=<?= $productId ?>" id="productPurchaseForm">
                <?= csrfField() ?>
                <input type="hidden" name="variant_id" id="selectedVariantInput" value="<?= (int)$selectedVariant['id'] ?>">

                <!-- Nhóm tùy chọn phân loại sản phẩm (Option Pills) -->
                <?php if (count($variants) > 1): ?>
                    <div class="pdetail-options-block">
                        <label class="pdetail-option-label">Chọn phiên bản / Phân loại:</label>
                        <div class="pdetail-pills-wrap">
                            <?php foreach ($variants as $idx => $v): 
                                $isVarActive = ((int)$v['id'] === (int)$selectedVariant['id']);
                                $vAttrs = is_array($v['attributes']) ? $v['attributes'] : [];
                                $vText = implode(' - ', array_values($vAttrs));
                                if ($vText === '') $vText = "Bản " . ($idx + 1);
                                $vPrice = (float)$v['price'];
                                $vOld = $vPrice >= 1000000 ? round($vPrice * 1.35, -4) : round($vPrice * 1.30, -3);
                            ?>
                                <button type="button" 
                                        class="pdetail-option-pill <?= $isVarActive ? 'active' : '' ?>"
                                        onclick="selectProductVariant(<?= (int)$v['id'] ?>, <?= $vPrice ?>, <?= $vOld ?>, this)"
                                        data-variant-id="<?= (int)$v['id'] ?>">
                                    <?= e($vText) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Nếu có 1 biến thể, hiển thị các đặc tính phân loại mẫu như ảnh để khách hàng chọn -->
                    <div class="pdetail-options-block">
                        <label class="pdetail-option-label">Loại sản phẩm:</label>
                        <div class="pdetail-pills-wrap">
                            <button type="button" class="pdetail-option-pill active">Đúc 3D Liền Khối</button>
                            <button type="button" class="pdetail-option-pill">Đúc 5D Sang Trọng</button>
                            <button type="button" class="pdetail-option-pill">Full Bộ Lót Cốp</button>
                            <button type="button" class="pdetail-option-pill">Mẫu Thảm Rối Cao Cấp</button>
                        </div>
                    </div>

                    <div class="pdetail-options-block">
                        <label class="pdetail-option-label">Đời xe / Năm sản xuất:</label>
                        <div class="pdetail-pills-wrap">
                            <button type="button" class="pdetail-option-pill active">2020 - 2023</button>
                            <button type="button" class="pdetail-option-pill">2024 - nay</button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Dòng chọn số lượng & Nút CTA Mua Hàng -->
                <div class="pdetail-action-row">
                    <div class="pdetail-stepper">
                        <button type="button" class="step-btn minus" onclick="updateQty(-1)">−</button>
                        <input type="number" name="quantity" id="productQtyInput" value="1" min="1" max="10">
                        <button type="button" class="step-btn plus" onclick="updateQty(1)">+</button>
                    </div>

                    <button type="submit" name="action" value="add_to_cart" class="pdetail-cta-btn btn-cart">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        Thêm vào giỏ hàng
                    </button>

                    <button type="submit" name="action" value="buy_now" class="pdetail-cta-btn btn-buynow">
                        Mua ngay
                    </button>
                </div>
            </form>

            <!-- Dòng cam kết tư vấn miễn phí -->
            <div class="pdetail-free-support-line">
                <span class="zalo-icon-badge">💬</span>
                <span>Tư vấn miễn phí trước khi mua (8:00 – 23:00)</span>
            </div>

            <!-- 4 Huy hiệu dịch vụ bảo chứng (4 thẻ kem/trắng bo góc) -->
            <div class="pdetail-trust-badges-grid">
                <div class="pdetail-trust-card">
                    <div class="trust-icon">🚚</div>
                    <div class="trust-title">Miễn phí vận chuyển</div>
                    <div class="trust-desc">Đơn từ 200.000đ</div>
                </div>
                <div class="pdetail-trust-card">
                    <div class="trust-icon">🔄</div>
                    <div class="trust-title">Đổi trả 14 ngày</div>
                    <div class="trust-desc">Nhận hàng tận nơi</div>
                </div>
                <div class="pdetail-trust-card">
                    <div class="trust-icon">🎧</div>
                    <div class="trust-title">Hỗ trợ nhanh</div>
                    <div class="trust-desc">Qua Zalo & Hotline</div>
                </div>
                <div class="pdetail-trust-card">
                    <div class="trust-icon">📦</div>
                    <div class="trust-title">Kiểm tra hàng trước</div>
                    <div class="trust-desc">Thanh toán sau</div>
                </div>
            </div>

            <!-- Dải nút liên hệ Zalo & Hotline Nâu Đồng Chuẩn Ảnh Mẫu -->
            <a href="https://zalo.me/0899388881" target="_blank" rel="noopener noreferrer" class="pdetail-zalo-cta-bar" title="Nhắn tin Zalo với chuyên viên">
                <div class="zalo-left">
                    <div class="zalo-pill-circle">Zalo</div>
                    <span class="zalo-cta-text">NHẮN TIN ZALO</span>
                </div>
                <div class="zalo-right">
                    <strong class="hotline-phone">0899 388 881</strong>
                    <span class="zalo-dots">•••</span>
                </div>
            </a>
        </div>
    </div>

    <!-- KHỐI DƯỚI: MÔ TẢ CHI TIẾT & BẢNG THÔNG SỐ KỸ THUẬT -->
    <div class="pdetail-tabs-container" id="danh-gia">
        <div class="pdetail-tabs-header">
            <button type="button" class="pdetail-tab-nav active" onclick="switchDetailTab('mota', this)">Mô tả sản phẩm</button>
            <button type="button" class="pdetail-tab-nav" onclick="switchDetailTab('thongso', this)">Thông số kỹ thuật</button>
            <button type="button" class="pdetail-tab-nav" onclick="switchDetailTab('chinhsach', this)">Chính sách Đổi trả (Đề tài 19)</button>
        </div>

        <!-- Tab 1: Mô tả chi tiết -->
        <div class="pdetail-tab-pane active" id="pane-mota">
            <div class="pdetail-prose">
                <h3>Đặc điểm nổi bật của <?= e($product['name']) ?></h3>
                <p>
                    <?= e($product['description'] ?: 'Sản phẩm được thiết kế và sản xuất theo tiêu chuẩn khắt khe, ứng dụng công nghệ đúc định hình 3D tiên tiến nhất hiện nay, đảm bảo ôm sát 100% phom dáng nội thất xe hơi.') ?>
                </p>
                <ul>
                    <li><strong>Công nghệ đúc liền khối:</strong> Mang lại vẻ đẹp hiện đại, không nếp gấp, ngăn bụi bẩn và chất lỏng tràn ra sàn xe.</li>
                    <li><strong>Chất liệu tuyển chọn cao cấp:</strong> Thân thiện với sức khỏe người dùng, không mùi hóa chất độc hại, chống mài mòn và chịu nhiệt tối đa.</li>
                    <li><strong>Lắp đặt chuẩn xác:</strong> Thiết kế riêng theo kích thước xe, thao tác lắp đặt chỉ trong 5 phút mà không cần tháo ghế hay can thiệp kết cấu xe.</li>
                    <li><strong>Dễ dàng vệ sinh:</strong> Chỉ cần lau nhẹ bằng khăn ẩm hoặc xịt rửa trực tiếp với vòi nước áp lực cao, khô ráo ngay tức thì.</li>
                </ul>

                <div class="pdetail-feature-banner">
                    <img src="<?= e($mainImg) ?>" alt="Hình ảnh chi tiết sản phẩm <?= e($product['name']) ?>">
                </div>
            </div>
        </div>

        <!-- Tab 2: Thông số kỹ thuật -->
        <div class="pdetail-tab-pane" id="pane-thongso">
            <table class="pdetail-specs-table">
                <tbody>
                    <tr>
                        <td class="spec-label">Thương hiệu / Phân phối</td>
                        <td class="spec-value"><strong>Phụ Kiện Xe VIP</strong> (Chính hãng)</td>
                    </tr>
                    <tr>
                        <td class="spec-label">Tên sản phẩm</td>
                        <td class="spec-value"><?= e($product['name']) ?></td>
                    </tr>
                    <tr>
                        <td class="spec-label">Danh mục nội thất</td>
                        <td class="spec-value"><?= e($product['category']) ?></td>
                    </tr>
                    <tr>
                        <td class="spec-label">Mã SKU phiên bản</td>
                        <td class="spec-value"><code style="color: #e67e22; font-weight: 700;"><?= e($selectedVariant['sku']) ?></code></td>
                    </tr>
                    <tr>
                        <td class="spec-label">Chất liệu chính</td>
                        <td class="spec-value">TPE nguyên sinh / Da Nappa / Hợp kim Titan cao cấp</td>
                    </tr>
                    <tr>
                        <td class="spec-label">Thời gian bảo hành</td>
                        <td class="spec-value"><strong>24 Tháng chính hãng</strong> (Lỗi 1 đổi 1 tận nơi)</td>
                    </tr>
                    <tr>
                        <td class="spec-label">Tình trạng kho hàng</td>
                        <td class="spec-value"><span style="color: #27ae60; font-weight: 700;">● Còn hàng sẵn sàng giao</span> (Tồn khả dụng: <?= (int)$selectedVariant['available_quantity'] ?> cái)</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tab 3: Chính sách đổi trả Đề tài 19 -->
        <div class="pdetail-tab-pane" id="pane-chinhsach">
            <div class="pdetail-prose">
                <h3>Chính sách Đổi trả linh hoạt 14 ngày (Nghiệp vụ cốt lõi Đề tài 19)</h3>
                <p>Hệ thống tự động áp dụng đầy đủ quy tắc tính toán và hoàn tiền chuẩn xác:</p>
                <ul>
                    <li><strong>Đổi trả từng phần:</strong> Khách hàng có thể yêu cầu trả một phần số lượng sản phẩm trong đơn đã giao thành công mà không bắt buộc phải trả toàn bộ đơn hàng.</li>
                    <li><strong>Đóng băng giá (Price Snapshot):</strong> Số tiền hoàn được tính căn cứ theo đơn giá thực tế lúc chốt đơn hàng, bảo vệ quyền lợi tối đa cho khách hàng.</li>
                    <li><strong>Hoàn kho tự động:</strong> Sản phẩm trả hợp lệ sẽ được hệ thống kiểm định và tự động giải phóng tồn kho khả dụng để tiếp tục phục vụ người dùng khác.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- KHỐI SẢN PHẨM LIÊN QUAN / CÙNG DANH MỤC -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="pdetail-related-section">
            <h2 class="pdetail-related-title">Sản Phẩm Tương Tự Cùng Danh Mục</h2>
            <div class="noithat-grid active" style="display: grid;">
                <?php foreach ($relatedProducts as $idx => $rp): 
                    $rpVar = $rp['variants'][0] ?? null;
                    $rpPrice = $rpVar ? (float)$rpVar['price'] : 250000;
                    $rpOld = $rpPrice >= 1000000 ? round($rpPrice * 1.35, -4) : round($rpPrice * 1.25, -3);
                ?>
                    <article class="noithat-card">
                        <a href="/product.php?id=<?= (int)$rp['id'] ?>" class="noithat-card-link">
                            <div class="noithat-img-box">
                                <img src="<?= e(productImageUrl($rp['image_url'])) ?>?v=3" alt="<?= e($rp['name']) ?>" loading="lazy">
                            </div>
                            <h4 class="noithat-card-title" title="<?= e($rp['name']) ?>"><?= e($rp['name']) ?></h4>
                        </a>
                        <div class="noithat-price-row">
                            <span class="noithat-price-old"><?= formatCurrency($rpOld) ?></span>
                            <span class="noithat-price-sale"><?= formatCurrency($rpPrice) ?></span>
                        </div>
                        <div class="noithat-bottom-row">
                            <span class="noithat-star">★ 4.9</span>
                            <a href="/product.php?id=<?= (int)$rp['id'] ?>" class="noithat-btn-cart" title="Xem chi tiết">
                                ➔
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>

<!-- Script tương tác Gallery & Variant Selection -->
<script>
function switchGalleryImage(imgSrc, btn) {
    var preview = document.getElementById('mainProductPreview');
    if (preview) {
        preview.style.opacity = '0.3';
        preview.src = imgSrc;
        setTimeout(function() {
            preview.style.opacity = '1';
        }, 150);
    }
    document.querySelectorAll('.pdetail-thumb-btn').forEach(function(b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');
}

function selectProductVariant(variantId, price, oldPrice, btn) {
    document.getElementById('selectedVariantInput').value = variantId;
    
    // Cập nhật giá hiển thị mượt mà
    var curElem = document.getElementById('dynPriceCurrent');
    var oldElem = document.getElementById('dynPriceOld');
    if (curElem) {
        curElem.innerText = new Intl.NumberFormat('vi-VN').format(price) + ' ₫';
    }
    if (oldElem) {
        oldElem.innerText = new Intl.NumberFormat('vi-VN').format(oldPrice) + ' ₫';
    }

    // Toggle active pill
    var parent = btn.parentElement;
    parent.querySelectorAll('.pdetail-option-pill').forEach(function(p) {
        p.classList.remove('active');
    });
    btn.classList.add('active');
}

function updateQty(delta) {
    var input = document.getElementById('productQtyInput');
    if (!input) return;
    var val = parseInt(input.value, 10) || 1;
    val += delta;
    if (val < 1) val = 1;
    if (val > 10) val = 10;
    input.value = val;
}

function switchDetailTab(tabKey, btn) {
    document.querySelectorAll('.pdetail-tab-nav').forEach(function(b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    document.querySelectorAll('.pdetail-tab-pane').forEach(function(p) {
        p.classList.remove('active');
    });
    var target = document.getElementById('pane-' + tabKey);
    if (target) {
        target.classList.add('active');
    }
}

function openFullscreenImage() {
    var preview = document.getElementById('mainProductPreview');
    if (preview) {
        window.open(preview.src, '_blank');
    }
}
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
