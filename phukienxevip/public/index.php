<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

$db = db();
$orderService = new OrderService($db);
$orderService->checkAndExpireReservations();

$repo = new ProductRepository($db);
$query = field($_GET, 'q');
$category = field($_GET, 'cat');

$products = $repo->getAll($query, $category);
$categories = $repo->getCategories();

// 7 Danh mục Nội Thất Ô Tô Sang Trọng (10 sản phẩm mỗi danh mục = 70 sản phẩm từ MySQL)
$noithatCategories = [
    'Bọc vô lăng' => 'volang',
    'Thảm lót sàn' => 'thamsan',
    'Rèm che nắng' => 'remchenang',
    'Gối tựa đầu, lưng' => 'goitua',
    'Bảng số điện thoại' => 'bangso',
    'Giá đỡ điện thoại' => 'giado',
    'Tẩu sạc ô tô' => 'tausac',
];

$noithatProductsByCat = [];
foreach (array_keys($noithatCategories) as $catName) {
    $noithatProductsByCat[$catName] = $repo->getAll('', $catName);
}

// 3 Danh mục Ngoại Thất Ô Tô Cao Cấp (từ MySQL)
$ngoaithatCategories = [
    'Vè che mưa ô tô' => 'vechemua',
    'Gạt mưa ô tô' => 'gatmua',
    'Lưới chống chuột' => 'luoichuot',
];

$ngoaithatProductsByCat = [];
foreach (array_keys($ngoaithatCategories) as $catName) {
    $ngoaithatProductsByCat[$catName] = $repo->getAll('', $catName);
}

// Xử lý thêm vào giỏ nhanh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    checkCsrf($_POST);
    $variantId = fieldInt($_POST, 'variant_id');
    $quantity = fieldInt($_POST, 'quantity', 1);

    $v = $repo->findVariantById($variantId);
    if (!$v) {
        setFlash('danger', 'Biến thể sản phẩm không tồn tại.');
    } elseif ($v['available_quantity'] < $quantity) {
        setFlash('danger', 'Lượng hàng có thể bán không đủ cho số lượng bạn chọn.');
    } else {
        addToCart($variantId, $quantity);
        setFlash('success', "Đã thêm {$quantity} sản phẩm '{$v['product_name']}' vào giỏ hàng thành công!");
    }
    $redirectUrl = '/index.php' . ($category ? '?cat=' . urlencode($category) : '');
    header("Location: $redirectUrl");
    exit;
}

require __DIR__ . '/../templates/header.php';
?>

<div class="vip-container">

    <?php if ($query !== ''): ?>
    <!-- ==============================================================================
         KẾT QUẢ TÌM KIẾM SẢN PHẨM THEO TỪ KHÓA
         ============================================================================== -->
    <section class="vip-search-results-section" id="search-results" style="margin: 20px 0 35px;">
        <div style="background: #18130f; border: 1.5px solid #4a382b; border-radius: 16px; padding: 24px 28px; box-shadow: 0 8px 30px rgba(0,0,0,0.5);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #33271d; padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0 0 6px; font-size: 1.4rem; color: #fce7ba; display: flex; align-items: center; gap: 10px;">
                        <span>🔍</span> Kết quả tìm kiếm: "<span style="color: #f59e0b;"><?= e($query) ?></span>"
                    </h2>
                    <p style="margin: 0; color: #94a3b8; font-size: 0.875rem;">
                        Tìm thấy <strong style="color: #4ade80;"><?= count($products) ?></strong> sản phẩm phù hợp trong hệ thống
                    </p>
                </div>
                <a href="/index.php" style="color: #cbd5e1; font-size: 0.8125rem; font-weight: 600; text-decoration: none; padding: 8px 16px; background: #261c15; border: 1px solid #4a382b; border-radius: 8px; transition: all 0.2s;">
                    ✕ Xóa tìm kiếm
                </a>
            </div>

            <?php if (empty($products)): ?>
                <div style="text-align: center; padding: 48px 20px; color: #94a3b8;">
                    <span style="font-size: 3.5rem;">🔍</span>
                    <p style="font-size: 1.15rem; color: #fce7ba; margin: 16px 0 8px; font-weight: 700;">Không tìm thấy sản phẩm nào khớp với "<?= e($query) ?>"</p>
                    <p style="font-size: 0.875rem; color: #94a3b8; margin: 0 0 20px;">Gợi ý: Thử tìm theo từ khóa như <em>"vô lăng"</em>, <em>"thảm lót sàn"</em>, <em>"camera"</em>, <em>"chìa khóa"</em>, <em>"gạt mưa"</em>...</p>
                    <a href="/index.php" class="filled-button" style="background: #d97706; padding: 10px 24px; text-decoration: none; border-radius: 8px; display: inline-block;">Xem toàn bộ cửa hàng</a>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 20px;">
                    <?php foreach ($products as $p): ?>
                        <?php 
                            $firstVar = $p['variants'][0] ?? null;
                            $minPrice = $firstVar ? (float)$firstVar['price'] : 0.0;
                            $availStock = $firstVar ? (int)$firstVar['available_quantity'] : 0;
                            $attrs = $firstVar && !empty($firstVar['attributes']) ? implode(' - ', array_values($firstVar['attributes'])) : '';
                        ?>
                        <article style="background: #201812; border: 1px solid #3d2f25; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;">
                            <a href="/product.php?id=<?= (int)$p['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                                <div style="height: 180px; background: #120e0a; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <img src="<?= e(productImageUrl($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div style="padding: 14px 14px 8px;">
                                    <span style="font-size: 0.6875rem; color: #d97706; font-weight: 700; text-transform: uppercase;"><?= e($p['category']) ?></span>
                                    <h3 style="margin: 4px 0 8px; font-size: 0.9375rem; color: #fce7ba; font-weight: 700; line-height: 1.4; height: 2.8em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                        <?= e($p['name']) ?>
                                    </h3>
                                    <?php if ($attrs): ?>
                                        <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            Phân loại: <?= e($attrs) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                                        <div style="font-size: 1.125rem; font-weight: 800; color: #f59e0b;">
                                            <?= formatMoney($minPrice) ?>
                                        </div>
                                        <div style="font-size: 0.6875rem; font-weight: 600; color: <?= $availStock > 0 ? '#4ade80' : '#ef4444' ?>;">
                                            <?= $availStock > 0 ? "Còn {$availStock} cái" : "Hết hàng" ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            <div style="padding: 0 14px 14px; margin-top: auto;">
                                <a href="/product.php?id=<?= (int)$p['id'] ?>" class="filled-button" style="width: 100%; text-align: center; padding: 8px 12px; font-size: 0.8125rem; background: #d97706; text-decoration: none; border-radius: 6px; display: block; font-weight: 700;">
                                    Xem chi tiết
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ==============================================================================
         1. HERO SECTION (ẢNH 1: HERO PHỤ KIỆN XE VIP + 2 BANNER XẾP CHỒNG BÊN PHẢI)
         ============================================================================== -->
    <section class="vip-hero-section" aria-label="Banner chính">
        <!-- Cột trái: Big Hero Banner PhuKienXeVIP Video ô tô chạy full màn hình theo video tùy chỉnh của bạn -->
        <a href="#flash-sale" class="vip-hero-banner-main video-only-banner" title="Xem phụ kiện xe VIP">
            <div class="hero-video-wrapper">
                <video autoplay muted loop playsinline class="hero-bg-video" poster="/assets/videos/car_driving_luxury_thumb.jpg?v=2">
                    <source src="/assets/videos/car_hero_custom.mp4?v=2" type="video/mp4">
                    <source src="/assets/videos/car_driving_luxury.mp4?v=2" type="video/mp4">
                </video>
            </div>
        </a>

        <!-- Cột phải: 2 Banner Xếp Chồng (1 trên: Bọc vô lăng, 1 dưới: Ốp chìa khóa) chuẩn phukienxevip.com -->
        <div class="vip-hero-stack">
            <!-- Banner Trên: Bọc vô lăng ô tô cao cấp - Giá chỉ từ 325.000đ -->
            <a href="/index.php?tab=volang#noithat-sang-trong" class="stack-banner-card full-img-card" title="Bọc vô lăng ô tô cao cấp - Giá chỉ từ 325.000đ">
                <img src="/assets/images/vip/banner_stack_top.jpg?v=7" alt="Bọc vô lăng ô tô cao cấp - Giá chỉ từ 325.000đ - MUA NGAY" class="stack-full-cover-img">
            </a>

            <!-- Banner Dưới: Ốp chìa khóa ô tô sang trọng - Giá chỉ từ 250.000đ -->
            <a href="#flash-sale" class="stack-banner-card full-img-card" title="Ốp chìa khóa ô tô sang trọng - Giá chỉ từ 250.000đ">
                <img src="/assets/images/vip/banner_stack_bottom.jpg?v=7" alt="Ốp chìa khóa ô tô sang trọng - Giá chỉ từ 250.000đ - MUA NGAY" class="stack-full-cover-img">
            </a>
        </div>
    </section>

    <!-- ==============================================================================
         2. CATEGORY FEATURE GRID (LƯỚI 8 DANH MỤC SẢN PHẨM NỔI BẬT CHUẨN PHUKIENXEVIP)
         ============================================================================== -->
    <section class="vip-categories-grid" aria-label="Danh mục nổi bật">
        <!-- Ô 1: Ốp chìa khóa ô tô (Thẻ Lớn bên trái kèm Badge HOT lửa) -->
        <a href="#flash-sale" class="cat-card cat-card-large cat-keyfob" title="Ốp chìa khóa ô tô Hot">
            <span class="hot-flame-badge">HOT 🔥</span>
            <div class="cat-title">Ốp chìa khóa ô tô</div>
            <div class="cat-img-wrap">
                <img src="/assets/images/vip/cat_keyfob_oto_custom.png?v=6" alt="Ốp chìa khóa ô tô cao cấp">
            </div>
        </a>

        <!-- Cột giữa 1: 2 Thẻ xếp chồng (Ốp chìa khóa xe máy & Bọc vô lăng) -->
        <div class="cat-col-stack">
            <a href="#flash-sale" class="cat-card cat-card-mini" title="Ốp chìa khóa xe máy">
                <span class="badge-one-to-one">1 ĐỔI 1</span>
                <div class="cat-title">Ốp chìa khóa xe máy</div>
                <div class="cat-mini-img">
                    <img src="/assets/images/vip/cat_keyfob_xemay_custom.png?v=6" alt="Ốp chìa khóa xe máy">
                </div>
            </a>
            <a href="/index.php?tab=volang#noithat-sang-trong" class="cat-card cat-card-mini" title="Bọc vô lăng">
                <span class="badge-one-to-one">1 ĐỔI 1</span>
                <div class="cat-title">Bọc vô lăng</div>
                <div class="cat-mini-img">
                    <img src="/assets/images/vip/cat_volang_custom.png?v=6" alt="Bọc vô lăng ô tô cao cấp">
                </div>
            </a>
        </div>

        <!-- Ô 4: Thảm lót sàn (Thẻ Rộng ở giữa) -->
        <a href="/index.php?tab=thamsan#noithat-sang-trong" class="cat-card cat-card-wide cat-thamsan" title="Thảm lót sàn">
            <div class="cat-title">Thảm lót sàn</div>
            <div class="cat-wide-img">
                <img src="/assets/images/vip/cat_thamsan_custom.png?v=6" alt="Thảm lót sàn ô tô cao cấp">
            </div>
        </a>

        <!-- Cột phải 1: 2 Thẻ nhỏ (Bao da & Rèm che nắng) -->
        <div class="cat-col-stack">
            <a href="#flash-sale" class="cat-card cat-card-mini" title="Bao da chìa khóa">
                <span class="badge-one-to-one">1 ĐỔI 1</span>
                <div class="cat-title">Bao da</div>
                <div class="cat-mini-img">
                    <img src="/assets/images/vip/cat_baoda_custom.png?v=6" alt="Bao da chìa khóa cao cấp">
                </div>
            </a>
            <a href="/index.php?tab=remchenang#noithat-sang-trong" class="cat-card cat-card-mini" title="Rèm che nắng">
                <div class="cat-title">Rèm che nắng</div>
                <div class="cat-mini-img">
                    <img src="/assets/images/vip/cat_remchenang_custom.png?v=6" alt="Rèm che nắng ô tô">
                </div>
            </a>
        </div>

        <!-- Cột phải 2: 2 Thẻ nhỏ (Gạt mưa & Lưới chống chuột) -->
        <div class="cat-col-stack">
            <a href="#products-list" class="cat-card cat-card-mini" title="Gạt mưa ô tô">
                <div class="cat-title">Gạt mưa ô tô</div>
                <div class="cat-mini-img">
                    <img src="/assets/images/vip/cat_gatmua_custom.png?v=6" alt="Gạt mưa ô tô">
                </div>
            </a>
            <a href="#products-list" class="cat-card cat-card-mini" title="Lưới chống chuột">
                <div class="cat-title">Lưới chống chuột</div>
                <div class="cat-mini-img">
                    <img src="/assets/images/vip/cat_luoichongchuot_custom.png?v=6" alt="Lưới chống chuột ô tô">
                </div>
            </a>
        </div>
    </section>

    <!-- ==============================================================================
         BANNER FLASH SALE PHỤ KIỆN Ô TÔ (BÊN DƯỚI CỤM 8 DANH MỤC SẢN PHẨM)
         ============================================================================== -->
    <div class="vip-flash-banner-wrapper">
        <a href="#flash-sale" class="vip-flash-banner-link" title="Flash Sale Phụ Kiện Ô Tô">
            <img src="/assets/images/vip/banner_flashsale_monkey.png" alt="Flash Sale Phụ Kiện Ô Tô - Giảm đến 500K" class="vip-flash-banner-img">
        </a>
    </div>

    <!-- ==============================================================================
         3. FLASH SALE BOX (KHUYẾN MÃI GIÁ SỐC - CHUẨN ẢNH THIẾT KẾ)
         ============================================================================== -->
    <section class="vip-flash-sale-box" id="flash-sale" aria-label="Khuyến mãi giá sốc">
        <!-- Header Flash Sale (Đã bỏ đếm ngược kết thúc sau) -->
        <div class="flash-sale-header">
            <div class="flash-title">
                <span class="flame-icon">🔥</span>
                <h2>KHUYẾN MÃI GIÁ SỐC</h2>
            </div>
        </div>

        <!-- 5 Sản phẩm Flash Sale Ốp chìa khóa chuẩn mẫu thiết kế -->
        <div class="flash-sale-slider">
            <!-- Item 1: Chevrolet -->
            <a href="/product.php?id=91" class="flash-card-link" title="Xem chi tiết Ốp chìa khóa Chevrolet">
                <article class="flash-product-card">
                    <div class="flash-img-box">
                        <img src="/assets/images/vip/keyfob_chevrolet.png?v=5" alt="Ốp chìa khóa Chevrolet">
                    </div>
                    <h4 class="flash-prod-title">Ốp Kim Loại Bọc Chìa Khoá Ô Tô Chevrolet Cao Cấp</h4>
                    <div class="color-swatches">
                        <span class="swatch" style="background: #783c44;" title="Đỏ Bordeaux"></span>
                        <span class="swatch" style="background: #211411;" title="Đen"></span>
                        <span class="swatch" style="background: #6a7990;" title="Bạc Titan"></span>
                        <span class="swatch" style="background: #4e92be;" title="Xanh Băng"></span>
                    </div>
                    <div class="flash-tags">
                        <span class="f-tag">Hợp kim</span>
                        <span class="f-tag">Mạ Logo</span>
                    </div>
                    <div class="flash-price-row">
                        <span class="old-price">330.000 đ</span>
                        <span class="sale-price">280.000 đ</span>
                    </div>
                    <div class="flash-rating-row">
                        <span class="star-rating">★ 4.6</span>
                    </div>
                </article>
            </a>

            <!-- Item 2: Honda -->
            <a href="/product.php?id=92" class="flash-card-link" title="Xem chi tiết Ốp chìa khóa Honda">
                <article class="flash-product-card">
                    <div class="flash-img-box">
                        <img src="/assets/images/vip/keyfob_honda_flash.png?v=5" alt="Ốp chìa khóa Honda">
                    </div>
                    <h4 class="flash-prod-title">Ốp Kim Loại Bọc Chìa Khoá Ô Tô Honda Cao Cấp</h4>
                    <div class="color-swatches">
                        <span class="swatch" style="background: #783c44;" title="Đỏ Bordeaux"></span>
                        <span class="swatch" style="background: #211411;" title="Đen"></span>
                        <span class="swatch" style="background: #6a7990;" title="Bạc Titan"></span>
                        <span class="swatch" style="background: #4e92be;" title="Xanh Băng"></span>
                    </div>
                    <div class="flash-tags">
                        <span class="f-tag">Hợp kim</span>
                        <span class="f-tag">Mạ Logo</span>
                    </div>
                    <div class="flash-price-row">
                        <span class="old-price">330.000 đ</span>
                        <span class="sale-price">280.000 đ</span>
                    </div>
                    <div class="flash-rating-row">
                        <span class="star-rating">★ 4.7</span>
                    </div>
                </article>
            </a>

            <!-- Item 3: Toyota -->
            <a href="/product.php?id=93" class="flash-card-link" title="Xem chi tiết Ốp chìa khóa Toyota">
                <article class="flash-product-card">
                    <div class="flash-img-box">
                        <img src="/assets/images/vip/keyfob_toyota.png?v=5" alt="Ốp chìa khóa Toyota">
                    </div>
                    <h4 class="flash-prod-title">Ốp Kim Loại Bọc Chìa Khoá Ô Tô Toyota Cao Cấp</h4>
                    <div class="color-swatches">
                        <span class="swatch" style="background: #783c44;" title="Đỏ Bordeaux"></span>
                        <span class="swatch" style="background: #211411;" title="Đen"></span>
                        <span class="swatch" style="background: #6a7990;" title="Bạc Titan"></span>
                        <span class="swatch" style="background: #4e92be;" title="Xanh Băng"></span>
                    </div>
                    <div class="flash-tags">
                        <span class="f-tag">Hợp kim</span>
                        <span class="f-tag">Mạ Logo</span>
                    </div>
                    <div class="flash-price-row">
                        <span class="old-price">330.000 đ</span>
                        <span class="sale-price">280.000 đ</span>
                    </div>
                    <div class="flash-rating-row">
                        <span class="star-rating">★ 4.6</span>
                    </div>
                </article>
            </a>

            <!-- Item 4: Peugeot -->
            <a href="/product.php?id=94" class="flash-card-link" title="Xem chi tiết Ốp chìa khóa Peugeot">
                <article class="flash-product-card">
                    <div class="flash-img-box">
                        <img src="/assets/images/vip/keyfob_peugeot.png?v=5" alt="Ốp chìa khóa Peugeot">
                    </div>
                    <h4 class="flash-prod-title">Ốp Kim Loại Bọc Chìa Khoá Ô Tô Peugeot Cao Cấp</h4>
                    <div class="color-swatches">
                        <span class="swatch" style="background: #783c44;" title="Đỏ Bordeaux"></span>
                        <span class="swatch" style="background: #211411;" title="Đen"></span>
                        <span class="swatch" style="background: #6a7990;" title="Bạc Titan"></span>
                        <span class="swatch" style="background: #4e92be;" title="Xanh Băng"></span>
                    </div>
                    <div class="flash-tags">
                        <span class="f-tag">Hợp kim</span>
                        <span class="f-tag">Mạ Logo</span>
                    </div>
                    <div class="flash-price-row">
                        <span class="old-price">330.000 đ</span>
                        <span class="sale-price">280.000 đ</span>
                    </div>
                    <div class="flash-rating-row">
                        <span class="star-rating">★ 4.7</span>
                    </div>
                </article>
            </a>

            <!-- Item 5: Audi -->
            <a href="/product.php?id=95" class="flash-card-link" title="Xem chi tiết Ốp chìa khóa Audi">
                <article class="flash-product-card">
                    <div class="flash-img-box">
                        <img src="/assets/images/vip/keyfob_audi.png?v=5" alt="Ốp chìa khóa Audi">
                    </div>
                    <h4 class="flash-prod-title">Ốp Kim Loại Bọc Chìa Khoá Ô Tô Audi Cao Cấp</h4>
                    <div class="color-swatches">
                        <span class="swatch" style="background: #6a7990;" title="Bạc Titan"></span>
                        <span class="swatch" style="background: #4e92be;" title="Xanh Băng"></span>
                    </div>
                    <div class="flash-tags">
                        <span class="f-tag">Hợp kim</span>
                        <span class="f-tag">Mạ Logo</span>
                    </div>
                    <div class="flash-price-row">
                        <span class="old-price">330.000 đ</span>
                        <span class="sale-price">280.000 đ</span>
                    </div>
                    <div class="flash-rating-row">
                        <span class="star-rating">★ 4.7</span>
                    </div>
                </article>
            </a>
        </div>

        <!-- DẢI CAM KẾT CHÍNH SÁCH SÀN (HÌNH ẢNH BANNER CHUẨN THIẾT KẾ) -->
        <div class="vip-reassurance-strip">
            <img src="/assets/images/vip/reassurance_strip.png?v=2" alt="Cam kết chính sách sàn: 100% Chính hãng - Giao hàng toàn quốc - Đổi trả 14 ngày - Hỗ trợ 24/7" class="vip-reassurance-banner-img">
        </div>
    </section>

    <!-- ==============================================================================
         4. BANNER PHỤ KIỆN NỘI THẤT Ô TÔ (BÊN DƯỚI KHUYẾN MÃI GIÁ SỐC)
         ============================================================================== -->
    <div class="vip-luxury-banner-wrapper">
        <a href="#noithat-sang-trong" class="vip-luxury-banner-link" title="Phụ Kiện Nội Thất Ô Tô Sang Trọng">
            <img src="/assets/images/vip/banner_mid_noithat_luxury.png?v=1" alt="Phụ kiện nội thất ô tô - Sản phẩm thiết kế riêng cho từng dòng xe" class="vip-luxury-banner-img">
        </a>
    </div>

    <!-- ==============================================================================
         5. NỘI THẤT Ô TÔ SANG TRỌNG (7 TABS x 10 SẢN PHẨM = 70 SẢN PHẨM TỪ MYSQL)
         ============================================================================== -->
    <?php 
        $requestedTab = field($_GET, 'tab');
        $validTabs = array_values($noithatCategories);
        $activeTab = in_array($requestedTab, $validTabs, true) ? $requestedTab : 'thamsan';
    ?>
    <section class="vip-noithat-section" id="noithat-sang-trong" aria-label="Nội thất ô tô sang trọng">
        <!-- Header & Category Filter Pills -->
        <div class="noithat-header">
            <h2 class="noithat-title">Nội Thất Ô Tô Sang Trọng</h2>
            <div class="noithat-pills-row">
                <?php foreach ($noithatCategories as $catName => $catKey): ?>
                    <button type="button" 
                            class="noithat-pill <?= $catKey === $activeTab ? 'active' : '' ?>" 
                            data-tab="<?= $catKey ?>"
                            onclick="switchNoiThatTab('<?= $catKey ?>', this)">
                        <?= e($catName) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Các Grid Sản Phẩm Cho Từng Danh Mục (Mỗi tab 10 sản phẩm từ MySQL) -->
        <?php foreach ($noithatCategories as $catName => $catKey): 
            $catProducts = $noithatProductsByCat[$catName] ?? [];
            $isActive = ($catKey === $activeTab);
        ?>
            <div class="noithat-grid <?= $isActive ? 'active' : '' ?>" id="tab-<?= $catKey ?>">
                <?php foreach ($catProducts as $idx => $p): 
                    $firstVar = $p['variants'][0] ?? null;
                    $price = $firstVar ? (float)$firstVar['price'] : 0;
                    $oldPrice = $price >= 1000000 ? round($price * 1.35, -4) : round($price * 1.25, -3);
                    $attrs = $firstVar['attributes'] ?? [];
                    $rating = match($idx % 5) {
                        0 => '4.9',
                        1 => '4.8',
                        2 => '4.9',
                        3 => '4.7',
                        default => '4.9'
                    };
                ?>
                    <article class="noithat-card">
                        <a href="/product.php?id=<?= (int)$p['id'] ?>" class="noithat-card-link">
                            <div class="noithat-img-box">
                                <img src="<?= e(productImageUrl($p['image_url'])) ?>?v=3" alt="<?= e($p['name']) ?>" loading="lazy">
                            </div>
                            <h4 class="noithat-card-title" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></h4>
                        </a>
                        
                        <?php if (!empty($attrs)): ?>
                            <div class="noithat-tags-row">
                                <?php 
                                    $tagIdx = 0;
                                    foreach ($attrs as $attrVal): 
                                        $tagClass = ($tagIdx % 2 === 0) ? 'noithat-tag-blue' : 'noithat-tag-purple';
                                        $tagIdx++;
                                ?>
                                    <span class="<?= $tagClass ?>"><?= e((string)$attrVal) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="noithat-price-row">
                            <span class="noithat-price-old"><?= formatCurrency($oldPrice) ?></span>
                            <span class="noithat-price-sale"><?= formatCurrency($price) ?></span>
                        </div>
                        <div class="noithat-bottom-row">
                            <span class="noithat-star">★ <?= $rating ?></span>
                            <form method="post" action="/index.php" class="noithat-quick-buy">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="variant_id" value="<?= $firstVar['id'] ?? 0 ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="noithat-btn-cart" title="Thêm vào giỏ hàng">
                                    🛒
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- ==============================================================================
         6. BANNER GẠT MƯA Ô TÔ (CHUẨN ẢNH MẪU 1 PHUKIENXEVIP)
         ============================================================================== -->
    <div class="vip-gatmua-banner-wrapper">
        <a href="#ngoaithat-cao-cap" class="vip-gatmua-banner-link" title="Gạt Mưa Ô Tô - Tầm Nhìn Rõ Ràng Hành Trình An Toàn">
            <img src="/assets/images/vip/banner_gatmua_wide.jpg?v=2" alt="Gạt Mưa Ô Tô - Tầm Nhìn Rõ Ràng – Hành Trình An Toàn" class="vip-gatmua-banner-img">
        </a>
    </div>

    <!-- ==============================================================================
         7. NGOẠI THẤT Ô TÔ CAO CẤP (CHUẨN ẢNH MẪU 2 VỚI DỮ LIỆU TỪ MYSQL)
         ============================================================================== -->
    <section class="vip-noithat-section vip-ngoaithat-section" id="ngoaithat-cao-cap" aria-label="Ngoại thất ô tô cao cấp">
        <!-- Header & Category Filter Pills -->
        <div class="noithat-header">
            <h2 class="noithat-title ngoaithat-title">Ngoại Thất Ô Tô Cao Cấp</h2>
            <div class="noithat-pills-row">
                <?php foreach ($ngoaithatCategories as $catName => $catKey): ?>
                    <button type="button" 
                            class="noithat-pill <?= $catKey === 'vechemua' ? 'active' : '' ?>" 
                            data-tab="<?= $catKey ?>"
                            onclick="switchNgoaiThatTab('<?= $catKey ?>', this)">
                        <?= e($catName) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Các Grid Sản Phẩm Cho Từng Danh Mục Ngoại Thất từ MySQL -->
        <?php foreach ($ngoaithatCategories as $catName => $catKey): 
            $catProducts = $ngoaithatProductsByCat[$catName] ?? [];
            $isActive = ($catKey === 'vechemua');
        ?>
            <div class="noithat-grid <?= $isActive ? 'active' : '' ?>" id="ngoaithat-tab-<?= $catKey ?>">
                <?php foreach ($catProducts as $idx => $p): 
                    $firstVar = $p['variants'][0] ?? null;
                    $price = $firstVar ? (float)$firstVar['price'] : 450000;
                    $oldPrice = $price >= 400000 ? 650000 : 550000;
                    if ($p['category'] === 'Lưới chống chuột') {
                        $oldPrice = round($price * 1.45, -4);
                    }
                    $attrs = $firstVar['attributes'] ?? [];
                    $rating = match($idx % 4) {
                        0 => '4.9',
                        1 => '4.9',
                        2 => '5.0',
                        default => '4.9'
                    };
                ?>
                    <article class="noithat-card">
                        <a href="/product.php?id=<?= (int)$p['id'] ?>" class="noithat-card-link">
                            <div class="noithat-img-box">
                                <img src="<?= e(productImageUrl($p['image_url'])) ?>?v=5" alt="<?= e($p['name']) ?>" loading="lazy">
                            </div>
                            <h4 class="noithat-card-title" title="<?= e($p['name']) ?>"><?= e($p['name']) ?></h4>
                        </a>
                        
                        <?php if (!empty($attrs)): ?>
                            <div class="noithat-tags-row">
                                <?php 
                                    $tagIdx = 0;
                                    foreach ($attrs as $attrVal): 
                                        $tagClass = ($tagIdx % 2 === 0) ? 'noithat-tag-blue' : 'noithat-tag-purple';
                                        $tagIdx++;
                                ?>
                                    <span class="<?= $tagClass ?>"><?= e((string)$attrVal) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="noithat-price-row">
                            <span class="noithat-price-old"><?= formatCurrency($oldPrice) ?></span>
                            <span class="noithat-price-sale"><?= formatCurrency($price) ?></span>
                        </div>
                        <div class="noithat-bottom-row">
                            <span class="noithat-star">★ <?= $rating ?></span>
                            <form method="post" action="/index.php" class="noithat-quick-buy">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="variant_id" value="<?= $firstVar['id'] ?? 0 ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="noithat-btn-cart" title="Thêm vào giỏ hàng">
                                    🛒
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>

</div>

<script>
function switchNoiThatTab(catKey, btn) {
    document.querySelectorAll('#noithat-sang-trong .noithat-pill').forEach(function(b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    document.querySelectorAll('#noithat-sang-trong .noithat-grid').forEach(function(g) {
        g.classList.remove('active');
    });

    var target = document.getElementById('tab-' + catKey);
    if (target) {
        target.classList.add('active');
    }
}

function switchNgoaiThatTab(catKey, btn) {
    document.querySelectorAll('#ngoaithat-cao-cap .noithat-pill').forEach(function(b) {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    document.querySelectorAll('#ngoaithat-cao-cap .noithat-grid').forEach(function(g) {
        g.classList.remove('active');
    });

    var target = document.getElementById('ngoaithat-tab-' + catKey);
    if (target) {
        target.classList.add('active');
    }
}
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
