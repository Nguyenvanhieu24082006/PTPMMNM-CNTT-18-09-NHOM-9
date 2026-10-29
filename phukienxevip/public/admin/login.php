<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

// Nếu đã đăng nhập với vai trò admin hoặc manager, chuyển hướng ngay vào Dashboard Quản trị
if (isLoggedIn() && in_array(currentRole(), ['admin', 'manager'], true)) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';
if (isset($_GET['error']) && $_GET['error'] === 'unauthorized') {
    $error = 'Bạn cần đăng nhập bằng tài khoản Quản trị viên (Admin) để vào Cổng Quản Trị.';
}

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $username = field($_POST, 'username');
    $password = field($_POST, 'password');

    $stmt = queryParams($db, "SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
    $user = fetchOne($stmt);

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!in_array($user['role'], ['admin', 'manager'], true)) {
            $error = 'Quyền truy cập bị từ chối: Tài khoản của bạn không có quyền Quản trị viên (Admin)!';
        } else {
            loginUser($user);
            setFlash('success', 'Đăng nhập Cổng Quản trị thành công! Chào mừng ' . $user['full_name']);
            header('Location: /admin/index.php');
            exit;
        }
    } else {
        $error = 'Tên đăng nhập hoặc mật khẩu quản trị không chính xác.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng Nhập Cổng Quản Trị • PhuKienXeVIP (Đề tài 19)</title>
<link rel="stylesheet" href="/assets/css/tokens.css?v=11">
<link rel="stylesheet" href="/assets/css/app.css?v=11">
<style>
body {
    background: radial-gradient(circle at 50% 25%, #2b2017 0%, #120e0b 80%);
    color: #f1f5f9;
    font-family: var(--font-family);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 20px;
    margin: 0;
}
.admin-login-card {
    width: 100%;
    max-width: 440px;
    background: #1c1612;
    border: 1px solid #4a382c;
    border-radius: 16px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.65), 0 0 0 1px rgba(217,119,6,0.15);
    padding: 36px 32px;
}
.admin-brand {
    text-align: center;
    margin-bottom: 24px;
}
.admin-brand img {
    height: 44px;
    object-fit: contain;
    margin-bottom: 12px;
}
.security-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #fce7ba;
    background: #2f2217;
    border: 1px solid #6b4c33;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
}
.form-input-dark {
    width: 100%;
    box-sizing: border-box;
    height: 48px;
    padding: 0 16px;
    border-radius: 8px;
    background: #261f19;
    border: 1.5px solid #4f3b2d;
    color: #fce7ba;
    font-size: 0.9375rem;
    outline: none;
    transition: all 0.2s ease;
}
.form-input-dark:focus {
    border-color: #d97706;
    background: #2e241c;
    box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.2);
}
.form-input-dark::placeholder {
    color: #64748b;
}
.btn-admin-submit {
    width: 100%;
    height: 48px;
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    color: #ffffff;
    font-weight: 800;
    border: 1px solid #f59e0b;
    border-radius: 8px;
    font-size: 0.9375rem;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(217, 119, 6, 0.35);
    transition: all 0.2s;
    letter-spacing: 0.5px;
    margin-top: 10px;
}
.btn-admin-submit:hover {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(217, 119, 6, 0.45);
}
</style>
</head>
<body>

<div class="admin-login-card">
    <div class="admin-brand">
        <a href="/" title="Về trang chủ bán hàng">
            <img src="/assets/images/vip/logo_phukienxevip_transparent.png" alt="PhuKienXeVIP">
        </a>
        <div>
            <span class="security-badge">🔒 KHU VỰC QUẢN TRỊ NỘI BỘ</span>
        </div>
        <h1 style="margin: 0; font-size: 1.35rem; color: #fce7ba; font-weight: 800;">
            Đăng nhập Quản Trị Viên (Admin)
        </h1>
        <p style="margin: 6px 0 0; font-size: 0.8125rem; color: #94a3b8;">
            Hệ thống quản lý trạng thái đơn hàng, đối soát và phê duyệt đổi trả.
        </p>
    </div>

    <?php if ($error): ?>
        <div style="background: #450a0a; border: 1px solid #991b1b; color: #fecaca; border-radius: 8px; padding: 12px 14px; font-size: 0.875rem; margin-bottom: 20px;">
            ⚠️ <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form id="adminLoginForm" method="post" action="/admin/login.php">
        <?= csrfField() ?>
        
        <div style="margin-bottom: 18px;">
            <label for="username" style="display: block; margin-bottom: 6px; font-size: 0.8125rem; font-weight: 700; color: #cbd5e1;">Tài khoản Admin / Email</label>
            <input 
                id="username" 
                name="username" 
                type="text" 
                class="form-input-dark" 
                placeholder="Nhập tên đăng nhập admin..." 
                value="<?= e($_POST['username'] ?? '') ?>" 
                required 
                autocomplete="username"
            >
        </div>
        
        <div style="margin-bottom: 22px;">
            <label for="password" style="display: block; margin-bottom: 6px; font-size: 0.8125rem; font-weight: 700; color: #cbd5e1;">Mật khẩu bảo mật</label>
            <input 
                id="password" 
                name="password" 
                type="password" 
                class="form-input-dark" 
                placeholder="Nhập mật khẩu quản trị..." 
                value="" 
                required 
                autocomplete="current-password"
            >
        </div>
        
        <button type="submit" class="btn-admin-submit">
            🔐 ĐĂNG NHẬP QUẢN TRỊ VIÊN
        </button>
    </form>

    <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #33261c; text-align: center; font-size: 0.8125rem; color: #94a3b8;">
        <a href="/" style="color: #f59e0b; text-decoration: none; font-weight: 600;">⬅ Quay lại Trang chủ Mua sắm</a>
    </div>
</div>

</body>
</html>
