<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

// Nếu khách hàng đã đăng nhập, chuyển hướng ngay về trang chủ
if (isLoggedIn() && currentRole() === 'customer') {
    header('Location: /index.php');
    exit;
}

$error = '';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $username = field($_POST, 'username');
    $password = field($_POST, 'password');

    $stmt = queryParams($db, "SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
    $user = fetchOne($stmt);

    if ($user && password_verify($password, $user['password_hash']) && $user['role'] === 'customer') {
        loginUser($user);
        setFlash('success', 'Đăng nhập thành công! Chào mừng quý khách ' . $user['full_name']);
        header('Location: /index.php');
        exit;
    } else {
        $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
    }
}

require __DIR__ . '/../templates/header.php';
?>

<style>
.vip-login-wrapper {
    min-height: calc(85vh - 120px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
    background: radial-gradient(circle at 50% 20%, #261d16 0%, #0d0a08 85%);
}

.vip-login-card {
    width: 100%;
    max-width: 460px;
    background: #18130f;
    border: 1px solid #4a382b;
    border-radius: 16px;
    padding: 36px 32px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(217, 119, 6, 0.2);
    position: relative;
    overflow: hidden;
}

.vip-login-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #b45309, #f59e0b, #d97706);
}

.vip-badge-customer {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    background: #2b1f14;
    color: #fce7ba;
    border: 1px solid #543f2e;
    margin-bottom: 14px;
}

.vip-login-title {
    margin: 0 0 8px;
    font-size: 1.6rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.3px;
}

.vip-login-title span {
    color: #fce7ba;
    background: linear-gradient(90deg, #fce7ba, #e5b974);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.vip-login-subtitle {
    margin: 0 0 24px;
    font-size: 0.875rem;
    color: #94a3b8;
    line-height: 1.5;
}

.vip-form-group {
    margin-bottom: 20px;
}

.vip-form-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #fce7ba;
    letter-spacing: 0.3px;
}

.vip-input-container {
    position: relative;
    display: flex;
    align-items: center;
}

.vip-input-icon {
    position: absolute;
    left: 14px;
    width: 20px;
    height: 20px;
    color: #94a3b8;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vip-form-input {
    width: 100%;
    height: 48px;
    box-sizing: border-box;
    background: #0f0c09;
    border: 1.5px solid #3d2f23;
    border-radius: 10px;
    color: #ffffff;
    font-size: 0.9375rem;
    padding: 0 16px 0 44px;
    transition: all 0.25s ease;
    outline: none;
    font-family: inherit;
}

.vip-form-input:focus {
    border-color: #d97706;
    background: #15100c;
    box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.18);
}

.vip-form-input::placeholder {
    color: #64748b;
}

.vip-toggle-password {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 6px;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: color 0.2s;
}

.vip-toggle-password:hover {
    color: #fce7ba;
}

.vip-btn-submit {
    width: 100%;
    height: 50px;
    margin-top: 10px;
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    color: #ffffff;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    border: 1px solid #f59e0b;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(217, 119, 6, 0.35);
    transition: all 0.25s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.vip-btn-submit:hover {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 26px rgba(217, 119, 6, 0.45);
}

.vip-btn-submit:active {
    transform: translateY(0);
}

.vip-login-footer-links {
    margin-top: 22px;
    text-align: center;
    font-size: 0.875rem;
    color: #94a3b8;
}

.vip-login-footer-links a {
    color: #f59e0b;
    font-weight: 700;
    text-decoration: none;
    transition: color 0.2s;
}

.vip-login-footer-links a:hover {
    color: #fce7ba;
    text-decoration: underline;
}

.vip-quick-login-box {
    margin-top: 26px;
    padding: 16px;
    background: #1f1812;
    border: 1px dashed #543f2e;
    border-radius: 10px;
}

.vip-quick-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: #2a2018;
    border: 1px solid #4d3829;
    border-radius: 8px;
    color: #cbd5e1;
    font-size: 0.8125rem;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.vip-quick-btn:hover {
    background: #382a1f;
    border-color: #d97706;
    color: #fce7ba;
    transform: translateY(-1px);
}

.vip-trust-badges {
    display: flex;
    justify-content: space-around;
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #2b2017;
    font-size: 0.75rem;
    color: #94a3b8;
    text-align: center;
}

.vip-trust-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
</style>

<div class="vip-login-wrapper">
    <div class="vip-login-card">
        <!-- Huy hiệu Khách hàng -->
        <div style="text-align: center;">
            <div class="vip-badge-customer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fce7ba" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Dành cho Khách Hàng Mua Sắm
            </div>
            
            <h1 class="vip-login-title">
                Đăng nhập <span>Tài khoản</span>
            </h1>
            
            <p class="vip-login-subtitle">
                Quản lý giỏ hàng, tra cứu tiến trình vận chuyển và gửi yêu cầu đổi trả hàng trực tuyến.
            </p>
        </div>

        <?php if ($error): ?>
            <div style="background: #451010; border: 1px solid #881c1c; color: #fecaca; border-radius: 8px; padding: 12px 14px; font-size: 0.875rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.2rem;">⚠️</span>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form id="customerLoginForm" method="post" action="/login.php" autocomplete="off">
            <?= csrfField() ?>
            
            <!-- Tên đăng nhập / Email -->
            <div class="vip-form-group">
                <label for="username" class="vip-form-label">
                    <span>Tên đăng nhập hoặc Email</span>
                </label>
                <div class="vip-input-container">
                    <div class="vip-input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <input 
                        id="username" 
                        name="username" 
                        type="text" 
                        class="vip-form-input" 
                        placeholder="Nhập tên đăng nhập hoặc email..."
                        value="" 
                        required 
                        autocomplete="off"
                    >
                </div>
            </div>

            <!-- Mật khẩu -->
            <div class="vip-form-group">
                <label for="password" class="vip-form-label">
                    <span>Mật khẩu bảo mật</span>
                </label>
                <div class="vip-input-container">
                    <div class="vip-input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        class="vip-form-input" 
                        placeholder="Nhập mật khẩu..."
                        value="" 
                        required 
                        autocomplete="new-password"
                    >
                    <button type="button" class="vip-toggle-password" onclick="togglePasswordVisibility()" aria-label="Hiện/Ẩn mật khẩu">
                        <span id="pwdToggleText">Hiện</span>
                    </button>
                </div>
            </div>

            <!-- Nút Đăng nhập -->
            <button type="submit" class="vip-btn-submit">
                <span>ĐĂNG NHẬP MUA HÀNG</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </form>

        <!-- Đăng ký tài khoản mới -->
        <div class="vip-login-footer-links">
            Chưa có tài khoản? 
            <a href="/register.php">Đăng ký tài khoản mới ➔</a>
        </div>

        <!-- Cam kết an toàn & Đề tài 19 -->
        <div class="vip-trust-badges">
            <div class="vip-trust-item">
                <span style="font-size: 1.1rem;">🔒</span>
                <span>Bảo mật 100%</span>
            </div>
            <div class="vip-trust-item">
                <span style="font-size: 1.1rem;">❄️</span>
                <span>Chốt giá bất biến</span>
            </div>
            <div class="vip-trust-item">
                <span style="font-size: 1.1rem;">🔄</span>
                <span>Đổi trả 7 ngày</span>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const pwd = document.getElementById('password');
    const txt = document.getElementById('pwdToggleText');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        txt.textContent = 'Ẩn';
    } else {
        pwd.type = 'password';
        txt.textContent = 'Hiện';
    }
}
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
