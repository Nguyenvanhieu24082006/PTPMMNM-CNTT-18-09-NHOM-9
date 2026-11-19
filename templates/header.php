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

<!-- HỆ THỐNG THÔNG BÁO NỘI BỘ TRANG WEB (IN-APP TOAST NOTIFICATION POPUP) -->
<style>
.vip-toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
    max-width: 380px;
    width: calc(100% - 36px);
}
.vip-toast {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
    pointer-events: auto;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    animation: vipToastSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.vip-toast.fade-out {
    animation: vipToastSlideOut 0.3s cubic-bezier(0.7, 0, 0.84, 0) forwards;
}
@keyframes vipToastSlideIn {
    from {
        opacity: 0;
        transform: translateX(110%) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}
@keyframes vipToastSlideOut {
    from {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
    to {
        opacity: 0;
        transform: translateX(110%) scale(0.9);
    }
}
.vip-toast-content {
    flex: 1;
    min-width: 0;
}
.vip-toast-title {
    font-size: 0.875rem; /* 14px */
    font-weight: 700;
    color: #111827;
    margin: 0 0 3px;
    line-height: 1.35;
    display: flex;
    align-items: center;
    gap: 6px;
}
.vip-toast-subtitle {
    font-size: 0.78rem; /* 12.5px */
    color: #854d0e; /* Màu olive / đồng như ảnh minh họa */
    font-weight: 600;
    margin: 0;
    line-height: 1.4;
    word-break: break-word;
}
.vip-toast-close {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #111827;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    flex-shrink: 0;
    padding: 0;
    margin-top: 1px;
    transition: background 0.2s, transform 0.15s;
    line-height: 1;
}
.vip-toast-close:hover {
    background: #374151;
    transform: scale(1.1);
}
.vip-toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: #dfa056;
    width: 100%;
    animation: vipToastProgress 3.5s linear forwards;
}
.vip-toast.toast-success .vip-toast-progress { background: #22c55e; }
.vip-toast.toast-danger .vip-toast-progress { background: #ef4444; }
.vip-toast.toast-warning .vip-toast-progress { background: #f59e0b; }
.vip-toast.toast-info .vip-toast-progress { background: #dfa056; }
@keyframes vipToastProgress {
    from { width: 100%; }
    to { width: 0%; }
}

/* MODAL HỘP THOẠI XÁC NHẬN NỘI BỘ (IN-APP CONFIRM MODAL) */
.vip-confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.78);
    backdrop-filter: blur(5px);
    z-index: 99999999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.vip-confirm-overlay.show {
    opacity: 1;
    pointer-events: auto;
}
.vip-confirm-box {
    background: #181818;
    border: 1px solid #333333;
    border-radius: 14px;
    max-width: 440px;
    width: 100%;
    padding: 26px 28px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.05);
    text-align: center;
    transform: scale(0.92) translateY(10px);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.vip-confirm-overlay.show .vip-confirm-box {
    transform: scale(1) translateY(0);
}
.vip-confirm-icon-wrap {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #ef4444;
    font-size: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}
.vip-confirm-icon-wrap.info-mode {
    background: rgba(223, 160, 86, 0.12);
    border-color: rgba(223, 160, 86, 0.35);
    color: #dfa056;
}
.vip-confirm-title {
    margin: 0 0 8px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #f3f4f6;
    letter-spacing: 0.2px;
}
.vip-confirm-message {
    margin: 0 0 24px;
    font-size: 0.875rem;
    color: #9ca3af;
    line-height: 1.55;
}
.vip-confirm-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.vip-cbtn {
    flex: 1;
    padding: 11px 18px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.vip-cbtn-cancel {
    background: #262626;
    border: 1px solid #3a3a3a;
    color: #d1d5db;
}
.vip-cbtn-cancel:hover {
    background: #323232;
    color: #ffffff;
    border-color: #555;
}
.vip-cbtn-confirm {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
}
.vip-cbtn-confirm:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(239, 68, 68, 0.5);
}
.vip-cbtn-confirm.primary-mode {
    background: linear-gradient(135deg, #dfa056 0%, #c98a44 100%);
    color: #120e0b;
    box-shadow: 0 4px 14px rgba(223, 160, 86, 0.35);
}
</style>

<script>
window.showToast = function(title, subtitle, type, duration) {
    var container = document.getElementById('vipToastContainer');
    if (!container) return;
    
    type = type || 'info';
    subtitle = subtitle || '';
    duration = (duration !== undefined) ? duration : 4000;
    
    var toast = document.createElement('div');
    toast.className = 'vip-toast toast-' + type;
    
    var icon = '✓';
    if (type === 'danger' || type === 'error') icon = '✕';
    else if (type === 'warning') icon = '⚠️';
    else if (type === 'info') icon = 'ℹ️';
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }
    
    var progressHtml = duration > 0 
        ? '<div class="vip-toast-progress" style="animation-duration: ' + (duration / 1000) + 's;"></div>' 
        : '';
        
    toast.innerHTML = 
        '<div class="vip-toast-content">' +
            '<div class="vip-toast-title">' + escapeHtml(title) + '</div>' +
            (subtitle ? '<div class="vip-toast-subtitle">' + escapeHtml(subtitle) + '</div>' : '') +
        '</div>' +
        '<button type="button" class="vip-toast-close" title="Đóng">✕</button>' +
        progressHtml;
    
    var closeBtn = toast.querySelector('.vip-toast-close');
    var timer = null;
    
    function dismiss() {
        if (timer) clearTimeout(timer);
        toast.classList.add('fade-out');
        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }
    
    closeBtn.addEventListener('click', dismiss);
    if (duration > 0) {
        timer = setTimeout(dismiss, duration);
    }
    
    container.appendChild(toast);
};

window.showConfirm = function(options, onConfirm) {
    var overlay = document.getElementById('vipConfirmModal');
    if (!overlay) {
        if (confirm(options.message || 'Xác nhận?')) {
            if (onConfirm) onConfirm();
        }
        return;
    }
    
    var titleEl = document.getElementById('vipConfirmTitle');
    var msgEl = document.getElementById('vipConfirmMessage');
    var iconEl = document.getElementById('vipConfirmIcon');
    var btnCancel = document.getElementById('vipConfirmBtnCancel');
    var btnOk = document.getElementById('vipConfirmBtnOk');
    
    titleEl.innerText = options.title || 'Xác nhận thao tác';
    msgEl.innerText = options.message || 'Bạn có chắc chắn muốn thực hiện thao tác này?';
    btnCancel.innerText = options.cancelText || 'Hủy bỏ';
    btnOk.innerText = options.confirmText || 'Xác nhận';
    
    if (options.danger === false) {
        iconEl.innerText = 'ℹ️';
        iconEl.className = 'vip-confirm-icon-wrap info-mode';
        btnOk.className = 'vip-cbtn vip-cbtn-confirm primary-mode';
    } else {
        iconEl.innerText = '⚠️';
        iconEl.className = 'vip-confirm-icon-wrap';
        btnOk.className = 'vip-cbtn vip-cbtn-confirm';
    }
    
    function close() {
        overlay.classList.remove('show');
        btnCancel.removeEventListener('click', onCancel);
        btnOk.removeEventListener('click', onOk);
        overlay.removeEventListener('click', onBackdrop);
    }
    
    function onCancel() {
        close();
    }
    
    function onOk() {
        close();
        if (onConfirm) onConfirm();
    }
    
    function onBackdrop(e) {
        if (e.target === overlay) {
            close();
        }
    }
    
    btnCancel.addEventListener('click', onCancel);
    btnOk.addEventListener('click', onOk);
    overlay.addEventListener('click', onBackdrop);
    
    overlay.classList.add('show');
};
</script>
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

<!-- Container hiển thị Toast Notification nổi góc -->
<div id="vipToastContainer" class="vip-toast-container"></div>

<!-- Modal Hộp thoại xác nhận nội bộ (In-App Confirm Modal) -->
<div id="vipConfirmModal" class="vip-confirm-overlay">
    <div class="vip-confirm-box">
        <div class="vip-confirm-icon-wrap" id="vipConfirmIcon">⚠️</div>
        <h3 class="vip-confirm-title" id="vipConfirmTitle">Xác nhận thao tác</h3>
        <p class="vip-confirm-message" id="vipConfirmMessage">Bạn có chắc chắn muốn thực hiện thao tác này?</p>
        <div class="vip-confirm-actions">
            <button type="button" class="vip-cbtn vip-cbtn-cancel" id="vipConfirmBtnCancel">Hủy bỏ</button>
            <button type="button" class="vip-cbtn vip-cbtn-confirm" id="vipConfirmBtnOk">Xác nhận</button>
        </div>
    </div>
</div>

<main id="main">
<?php if ($flash): ?>
    <noscript>
        <div class="alert-box alert--<?= e($flash['type']) ?>" role="alert" style="max-width: 1240px; margin: 16px auto; padding: 14px 20px; border-radius: 8px;">
            <?= e($flash['message']) ?>
        </div>
    </noscript>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var rawMsg = <?= json_encode($flash['message']) ?>;
        var fType = <?= json_encode($flash['type'] ?? 'info') ?>;
        var fTitle = 'Thông báo hệ thống';
        var fSub = rawMsg;
        if (fType === 'success') {
            fTitle = 'Thao tác thành công!';
        } else if (fType === 'danger' || fType === 'error') {
            fTitle = 'Có lỗi xảy ra!';
        } else if (fType === 'warning') {
            fTitle = 'Cảnh báo';
        }
        
        // Nhận diện thông báo thêm vào giỏ hàng: "Đã thêm ... sản phẩm '...' vào giỏ hàng thành công!"
        if (rawMsg.indexOf('vào giỏ hàng thành công') !== -1) {
            fTitle = 'Đã thêm vào giỏ hàng thành công!';
            var match = rawMsg.match(/'([^']+)'/);
            if (match && match[1]) {
                fSub = match[1];
            } else {
                fSub = rawMsg.replace(/Đã thêm.*sản phẩm/i, '').replace(/vào giỏ hàng thành công!/i, '').trim();
            }
        } else if (rawMsg.indexOf('!') > 0 && rawMsg.indexOf('!') < rawMsg.length - 2) {
            var parts = rawMsg.split('!');
            fTitle = parts[0].trim() + '!';
            fSub = parts.slice(1).join('!').trim();
        }
        
        if (window.showToast) {
            window.showToast(fTitle, fSub, fType);
        }
    });
    </script>
<?php endif; ?>
