<?php
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireAdminLogin();
$user = currentUser();
$flash = getFlash();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Xác định tiêu đề trang và Breadcrumb
$pageTitle = 'Dashboard v3';
$breadcrumbCurrent = 'Dashboard v3';
if ($currentScript === 'products.php') {
    $pageTitle = 'Quản lý Sản phẩm & Tồn kho';
    $breadcrumbCurrent = 'Sản phẩm';
} elseif ($currentScript === 'orders.php') {
    $pageTitle = 'Quản lý Đơn hàng';
    $breadcrumbCurrent = 'Đơn hàng';
} elseif ($currentScript === 'returns.php') {
    $pageTitle = 'Xử lý Đổi trả';
    $breadcrumbCurrent = 'Đổi trả';
} elseif ($currentScript === 'reports.php') {
    $pageTitle = 'Báo cáo & Đối soát Tài chính';
    $breadcrumbCurrent = 'Báo cáo';
} elseif ($currentScript === 'audit-logs.php') {
    $pageTitle = 'Nhật ký Kiểm toán (Audit Trail)';
    $breadcrumbCurrent = 'Kiểm toán';
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> • PhuKienXeVIP Pro Portal</title>
<link rel="stylesheet" href="/assets/css/tokens.css?v=12">
<link rel="stylesheet" href="/assets/css/adminlte-theme.css?v=1.2">
</head>
<body class="adminlte-layout hold-transition sidebar-mini layout-fixed">

<div class="wrapper">

    <!-- ==============================================================================
         1. TOP NAVBAR (THANH ĐIỀU HƯỚNG TRÊN CÙNG - TINH GỌN)
         ============================================================================== -->
    <nav class="main-header navbar">
        <!-- Khối bên trái: Nút Hamburger & Menu nhanh -->
        <ul class="navbar-nav">
            <li>
                <button type="button" class="nav-link-btn" id="sidebarToggleBtn" title="Thu gọn / Mở rộng Sidebar">
                    ☰
                </button>
            </li>
            <li>
                <a href="/admin/index.php" class="nav-link-btn" style="font-size: 0.8125rem; font-weight: 500;">
                    Trang chủ Admin
                </a>
            </li>
            <li>
                <a href="/" target="_blank" class="nav-link-btn" style="font-size: 0.8125rem; font-weight: 500;">
                    Xem Cửa Hàng ↗
                </a>
            </li>
        </ul>

        <!-- Khối bên phải: Cán bộ quản trị & Đăng xuất -->
        <ul class="navbar-nav" style="gap: 10px;">
            <li>
                <button type="button" class="nav-link-btn" id="fullscreenToggleBtn" title="Toàn màn hình">
                    ⛶
                </button>
            </li>
            <li style="display: flex; align-items: center; gap: 8px; border-left: 1px solid #dee2e6; padding-left: 10px;">
                <span class="badge-admin badge-danger" style="background: #dc3545; color: #fff; font-size: 0.7rem;">
                    Admin
                </span>
                <span style="font-size: 0.8125rem; font-weight: 600; color: #343a40;">
                    <?= e($user['full_name'] ?? '') ?>
                </span>
                <a href="/admin/logout.php" class="btn-admin btn-admin-danger btn-admin-sm" style="margin-left: 4px; padding: 2px 8px;">
                    Thoát
                </a>
            </li>
        </ul>
    </nav>

    <!-- ==============================================================================
         2. MAIN SIDEBAR (SIDEBAR TỐI MÀU)
         ============================================================================== -->
    <aside class="main-sidebar">
        <!-- User Panel Area -->
        <div class="user-panel">
            <div class="user-panel-img">
                👤
            </div>
            <div class="user-panel-info">
                <a href="/admin/index.php" class="user-panel-name">
                    <?= e($user['full_name'] ?? 'Quản trị viên') ?>
                </a>
                <span class="user-panel-status">● Đang hoạt động</span>
            </div>
        </div>

        <!-- Sidebar Navigation Menu -->
        <div class="sidebar-menu">
            <ul class="nav-sidebar-list">
                <!-- Dashboard -->
                <li class="nav-sidebar-item">
                    <a href="/admin/index.php" class="nav-sidebar-link <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                        <span class="nav-sidebar-icon">📊</span>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-header">QUẢN TRỊ NGHIỆP VỤ</li>

                <!-- Quản lý Sản phẩm & Tồn kho -->
                <li class="nav-sidebar-item">
                    <a href="/admin/products.php" class="nav-sidebar-link <?= $currentScript === 'products.php' ? 'active' : '' ?>">
                        <span class="nav-sidebar-icon">🏷️</span>
                        <span>Quản lý Sản phẩm</span>
                    </a>
                </li>

                <!-- Quản lý Đơn hàng -->
                <li class="nav-sidebar-item">
                    <a href="/admin/orders.php" class="nav-sidebar-link <?= $currentScript === 'orders.php' ? 'active' : '' ?>">
                        <span class="nav-sidebar-icon">📦</span>
                        <span>Quản lý Đơn hàng</span>
                    </a>
                </li>

                <!-- Duyệt Đổi trả -->
                <li class="nav-sidebar-item">
                    <a href="/admin/returns.php" class="nav-sidebar-link <?= $currentScript === 'returns.php' ? 'active' : '' ?>">
                        <span class="nav-sidebar-icon">🔄</span>
                        <span>Duyệt Đổi trả</span>
                    </a>
                </li>

                <!-- Báo cáo Đối soát -->
                <li class="nav-sidebar-item">
                    <a href="/admin/reports.php" class="nav-sidebar-link <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
                        <span class="nav-sidebar-icon">📈</span>
                        <span>Báo cáo Đối soát</span>
                    </a>
                </li>

                <!-- Nhật ký Kiểm toán -->
                <li class="nav-sidebar-item">
                    <a href="/admin/audit-logs.php" class="nav-sidebar-link <?= $currentScript === 'audit-logs.php' ? 'active' : '' ?>">
                        <span class="nav-sidebar-icon">📜</span>
                        <span>Nhật ký Kiểm toán</span>
                    </a>
                </li>

                <li class="nav-header">HỆ THỐNG</li>

                <!-- Phép thử Đề tài 19 -->
                <li class="nav-sidebar-item">
                    <a href="/admin/index.php#test-suite-box" class="nav-sidebar-link">
                        <span class="nav-sidebar-icon">🧪</span>
                        <span>5 Phép thử Bắt buộc</span>
                        <span class="badge-admin badge-success" style="margin-left: auto; font-size: 0.65rem;">18/18 PASS</span>
                    </a>
                </li>

                <!-- Xem Shop -->
                <li class="nav-sidebar-item">
                    <a href="/" target="_blank" class="nav-sidebar-link">
                        <span class="nav-sidebar-icon">🛒</span>
                        <span>Xem Cửa Hàng (Shop)</span>
                    </a>
                </li>

                <!-- Đăng xuất -->
                <li class="nav-sidebar-item" style="margin-top: 15px;">
                    <a href="/admin/logout.php" class="nav-sidebar-link" style="color: #fca5a5;">
                        <span class="nav-sidebar-icon">🚪</span>
                        <span>Đăng xuất</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ==============================================================================
         3. CONTENT WRAPPER (VÙNG NỘI DUNG CHÍNH)
         ============================================================================== -->
    <div class="content-wrapper">
        <!-- Content Header (Page header & Breadcrumb) -->
        <div class="content-header">
            <h1><?= e($pageTitle) ?></h1>
            <ol class="breadcrumb-list">
                <li><a href="/admin/index.php">Home</a></li>
                <li>/</li>
                <li style="color: #6c757d; font-weight: 500;"><?= e($breadcrumbCurrent) ?></li>
            </ol>
        </div>

        <!-- Main Content Area -->
        <div class="content-body">
            <?php if ($flash): ?>
                <div style="background-color: <?= $flash['type'] === 'success' ? '#d4edda' : ($flash['type'] === 'danger' ? '#f8d7da' : '#fff3cd') ?>; color: <?= $flash['type'] === 'success' ? '#155724' : ($flash['type'] === 'danger' ? '#721c24' : '#856404') ?>; border: 1px solid <?= $flash['type'] === 'success' ? '#c3e6cb' : ($flash['type'] === 'danger' ? '#f5c6cb' : '#ffeeba') ?>; border-radius: 4px; padding: 12px 18px; margin-bottom: 20px; font-size: 0.9375rem; font-weight: 500; display: flex; align-items: center; justify-content: space-between;">
                    <div><?= e($flash['message']) ?></div>
                    <button type="button" onclick="this.parentElement.style.display='none'" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit;">×</button>
                </div>
            <?php endif; ?>
