<?php
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

$user = currentUser();
$cart = getCart();
$cartCount = array_sum($cart);
$flash = getFlash();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Kiểm tra xem khách hàng đã đăng nhập và đã từng mua hàng hay chưa
$userHasOrders = false;
if ($user && $user['role'] === 'customer') {
    try {
        require_once __DIR__ . '/../src/Database.php';
        $db = db();
        $stmt = queryParams($db, "SELECT COUNT(*) as cnt FROM orders WHERE user_id = ?", [(int)$user['id']]);
        $row = fetchOne($stmt);
        $userHasOrders = ((int)($row['cnt'] ?? 0)) > 0;
    } catch (Throwable $e) {
        $userHasOrders = false;
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PhuKienXeVIP • Phụ Kiện Ô Tô & Đồ Chơi Xe Hơi Cao Cấp (Đề tài 19)</title>
<link rel="stylesheet" href="/assets/css/tokens.css?v=11">
<link rel="stylesheet" href="/assets/css/app.css?v=11">
</head>
<body class="vip-theme-body">

<!-- ==============================================================================
     1. BANNER KHUYẾN MÃI SIÊU KHỦNG TRÊN CÙNG
     ============================================================================== -->
<div class="top-promo-strip">
    <div class="top-promo-container">
        <img src="/assets/images/vip/top_promo_banner.png" alt="Khuyến mãi siêu khủng - Miễn phí giao hàng - Giảm 10% - Mua ngay - Giờ vàng giá sốc" class="top-promo-banner-img">
    </div>
</div>

<!-- ==============================================================================
     2. HEADER CHÍNH NÂU BRONZE (PHUKIENXEVIP)
     ============================================================================== -->
<header class="vip-main-header">
    <div class="vip-header-container">
        <!-- Logo PhuKienXeVIP -->
        <a href="/" class="vip-header-logo-link" title="Trang chủ PhuKienXeVIP">
            <img src="/assets/images/vip/logo_phukienxevip_transparent.png" alt="PhuKienXeVIP" class="vip-header-logo-img">
        </a>

        <!-- Thanh tìm kiếm & 4 Quick Links bên dưới -->
        <div class="vip-search-center">
            <div class="vip-search-wrapper" style="position: relative; width: 100%;">
                <form action="/index.php" method="get" class="vip-search-bar-exact" id="headerSearchForm">
                    <input type="text" name="q" id="headerSearchInput" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Bạn cần tìm gì ?" autocomplete="off">
                    <button type="submit" aria-label="Tìm kiếm">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7.5"></circle>
                            <line x1="21" y1="21" x2="16.5" y2="16.5"></line>
                        </svg>
                    </button>
                </form>
                <!-- Popup gợi ý kết quả tìm kiếm trực tiếp -->
                <div id="headerSearchDropdown" class="vip-search-dropdown" style="display: none;"></div>
            </div>
            
            <div class="vip-search-sublinks">
                <a href="#map-section">
                    <img src="/assets/images/vip/icon1_trans.png" alt="Điểm bán" class="sublink-icon-img">
                    <span>Các điểm bán hàng</span>
                </a>
                <a href="#flash-sale">
                    <img src="/assets/images/vip/icon2_trans.png" alt="Khuyến mãi" class="sublink-icon-img">
                    <span>Khuyến mãi</span>
                </a>
                <a href="#products-list">
                    <img src="/assets/images/vip/icon3_trans.png" alt="Đã xem" class="sublink-icon-img">
                    <span>Sản phẩm đã xem</span>
                </a>
                <a href="/orders.php">
                    <img src="/assets/images/vip/icon4_trans.png" alt="Kiểm tra đơn" class="sublink-icon-img">
                    <span>Kiểm tra đơn hàng</span>
                </a>
            </div>
        </div>

        <!-- Bên phải: Giỏ hàng & Khối Tài khoản Khách hàng (Dạng Icon Tròn Sang Trọng) -->
        <div class="vip-header-actions-right" style="display: flex; align-items: center; gap: 10px;">
            <!-- Icon Giỏ Hàng -->
            <a href="/cart.php" class="vip-header-icon-btn" data-tooltip="Giỏ hàng" aria-label="Giỏ hàng">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                </svg>
                <?php if ($cartCount > 0): ?>
                    <span class="badge-count"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>

            <!-- Trạng thái tài khoản người dùng -->
            <?php if ($user): ?>
                <!-- Đơn hàng của tôi -->
                <a href="/orders.php" class="vip-header-icon-btn" data-tooltip="Đơn mua của tôi" aria-label="Đơn mua của tôi">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="currentColor">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                </a>
                <!-- Thông tin user -->
                <div style="display: flex; align-items: center; gap: 8px; background: #1c1510; border: 1.5px solid #3d2f25; padding: 6px 14px; border-radius: 9999px;">
                    <span style="font-size: 0.8125rem; font-weight: 700; color: #fce7ba;">
                        👤 <?= e($user['full_name']) ?>
                    </span>
                    <a href="/logout.php" style="color: #ef4444; font-size: 0.75rem; font-weight: 700; text-decoration: none; padding: 2px 6px; border-radius: 4px; background: rgba(239,68,68,0.15);" title="Đăng xuất">Thoát</a>
                </div>
            <?php else: ?>
                <!-- Icon Đăng Nhập -->
                <a href="/login.php" class="vip-header-icon-btn" data-tooltip="Đăng nhập" aria-label="Đăng nhập">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </a>
                <!-- Icon Đăng Ký -->
                <a href="/register.php" class="vip-header-icon-btn vip-btn-register" data-tooltip="Đăng ký" aria-label="Đăng ký">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ==============================================================================
     3. THANH MENU ĐIỀU HƯỚNG DÀNH CHO KHÁCH HÀNG
     ============================================================================== -->
<nav class="vip-sub-nav">
    <div class="vip-nav-container">
        <!-- Wrapper Danh mục sản phẩm kèm Mega Menu Hover Dropdown -->
        <div class="category-dropdown-wrapper">
            <div class="subnav-category-btn">
                <span class="subnav-hamburger">☰</span>
                <span>DANH MỤC SẢN PHẨM</span>
            </div>

            <div class="vip-mega-menu-dropdown">
                <div class="mega-menu-heading">NỘI THẤT Ô TÔ</div>
                <div class="mega-menu-grid">
                    <a href="/index.php?tab=volang#noithat-sang-trong" class="mega-item" title="Xem Bọc vô lăng">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_volang.png?v=5" alt="Bọc vô lăng">
                        </div>
                        <span class="mega-item-label">BỌC VÔ LĂNG</span>
                    </a>
                    <a href="/index.php?tab=thamsan#noithat-sang-trong" class="mega-item" title="Xem Thảm lót sàn">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_thamsan.png?v=5" alt="Thảm lót sàn">
                        </div>
                        <span class="mega-item-label">THẢM LÓT SÀN</span>
                    </a>
                    <a href="/index.php?tab=goitua#noithat-sang-trong" class="mega-item" title="Xem Gối tựa đầu, lưng">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_goitua.png?v=5" alt="Gối tựa đầu, lưng">
                        </div>
                        <span class="mega-item-label">GỐI TỰA ĐẦU, LƯNG</span>
                    </a>
                    <a href="/index.php?tab=bangso#noithat-sang-trong" class="mega-item" title="Xem Bảng số điện thoại">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_bangso.png?v=5" alt="Bảng số điện thoại">
                        </div>
                        <span class="mega-item-label">BẢNG SỐ ĐIỆN THOẠI</span>
                    </a>
                    <a href="/index.php?tab=remchenang#noithat-sang-trong" class="mega-item" title="Xem Rèm che nắng">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_remche.png?v=5" alt="Rèm che nắng">
                        </div>
                        <span class="mega-item-label">RÈM CHE NẮNG</span>
                    </a>
                    <a href="/index.php?tab=tausac#noithat-sang-trong" class="mega-item" title="Xem Tẩu sạc ô tô">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_tausac.png?v=5" alt="Tẩu sạc ô tô">
                        </div>
                        <span class="mega-item-label">TẨU SẠC Ô TÔ</span>
                    </a>
                    <a href="/index.php?tab=giado#noithat-sang-trong" class="mega-item" title="Xem Giá đỡ điện thoại">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_giado.png?v=5" alt="Giá đỡ điện thoại">
                        </div>
                        <span class="mega-item-label">GIÁ ĐỠ ĐIỆN THOẠI</span>
                    </a>
                    <a href="/index.php#noithat-sang-trong" class="mega-item" title="Xem Ốp nội thất ô tô">
                        <div class="mega-item-img">
                            <img src="/assets/images/vip/menu/menu_opnoithat.png?v=5" alt="Ốp nội thất ô tô">
                        </div>
                        <span class="mega-item-label">ỐP NỘI THẤT Ô TÔ</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Danh sách menu chính dành cho Khách hàng -->
        <div class="subnav-menu-links">
            <a href="/" class="<?= $currentScript === 'index.php' && empty($_GET['cat']) ? 'active' : '' ?>">TRANG CHỦ</a>
            <a href="#products-list">GIỚI THIỆU</a>
            <a href="#flash-sale">🎁 KHUYẾN MÃI</a>
            <a href="#map-section">CỬA HÀNG</a>
            <a href="#products-list">TIN TỨC</a>
            <a href="#footer-section">LIÊN HỆ</a>
            <?php if (!empty($userHasOrders) || ($user && $user['role'] === 'customer')): ?>
                <a href="/orders.php" class="<?= $currentScript === 'orders.php' ? 'active' : '' ?>">📦 ĐƠN HÀNG CỦA TÔI</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main id="main">
<?php if ($flash): ?>
    <div class="alert-box alert--<?= e($flash['type']) ?>" role="alert" style="max-width: 1240px; margin: 16px auto; padding: 14px 20px; border-radius: 8px;">
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>
