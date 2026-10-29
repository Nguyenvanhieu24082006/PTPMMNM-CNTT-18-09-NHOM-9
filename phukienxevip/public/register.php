<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

// Nếu khách hàng đã đăng nhập, chuyển hướng về trang chủ
if (isLoggedIn() && currentRole() === 'customer') {
    header('Location: /index.php');
    exit;
}

$errors = [];
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);

    $username = trim(field($_POST, 'username'));
    $fullName = trim(field($_POST, 'full_name'));
    $email = trim(field($_POST, 'email'));
    $phone = trim(field($_POST, 'phone'));
    $address = trim(field($_POST, 'address'));
    $password = field($_POST, 'password');
    $confirmPassword = field($_POST, 'confirm_password');

    // 1. Kiểm tra định dạng dữ liệu đầu vào
    if (strlen($fullName) < 2) {
        $errors[] = 'Vui lòng nhập họ và tên khách hàng đầy đủ.';
    }
    if (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Tên đăng nhập phải chứa từ 3 đến 50 ký tự.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái, chữ số và ký tự gạch (_ - .).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Địa chỉ Email không đúng định dạng.';
    }
    if (strlen($phone) < 8 || !preg_match('/^[0-9+() -]+$/', $phone)) {
        $errors[] = 'Số điện thoại không hợp lệ (ít nhất 8 chữ số).';
    }
    if (strlen($address) < 5) {
        $errors[] = 'Vui lòng nhập địa chỉ nhận hàng chi tiết.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Mật khẩu bảo mật phải có ít nhất 6 ký tự.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Mật khẩu xác nhận không trùng khớp với mật khẩu đã nhập.';
    }

    // 2. Kiểm tra tài khoản đã tồn tại trong CSDL MySQL chưa
    if (empty($errors)) {
        $stmtCheck = queryParams(
            $db,
            "SELECT id, username, email FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        $existing = fetchOne($stmtCheck);
        if ($existing) {
            if (strcasecmp($existing['username'], $username) === 0) {
                $errors[] = "⚠️ Tên đăng nhập '{$username}' đã được đăng ký. Vui lòng chọn tên đăng nhập khác!";
            } else {
                $errors[] = "⚠️ Địa chỉ Email '{$email}' đã tồn tại trong hệ thống. Vui lòng chọn email khác hoặc Đăng nhập!";
            }
        }
    }

    // 3. Tất cả tài khoản mới đều được lưu an toàn vào CSDL MySQL (bảng users)
    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        queryParams(
            $db,
            "INSERT INTO users (username, password_hash, full_name, email, phone, address, role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'customer', NOW())",
            [$username, $passwordHash, $fullName, $email, $phone, $address]
        );

        // Sau khi đăng ký thành công -> Chuyển đến trang đăng nhập kèm thông báo
        setFlash('success', "🎉 Chúc mừng bạn đã đăng ký tài khoản '{$username}' thành công! Vui lòng đăng nhập để bắt đầu mua sắm.");
        header('Location: /login.php');
        exit;
    }
}

require __DIR__ . '/../templates/header.php';
?>

<style>
.vip-reg-wrapper {
    min-height: calc(85vh - 120px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 16px;
    background: radial-gradient(circle at 50% 15%, #261d16 0%, #0d0a08 85%);
}

.vip-reg-card {
    width: 100%;
    max-width: 580px;
    background: #18130f;
    border: 1px solid #4a382b;
    border-radius: 16px;
    padding: 36px 32px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.65), 0 0 0 1px rgba(217, 119, 6, 0.2);
    position: relative;
    overflow: hidden;
}

.vip-reg-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #b45309, #f59e0b, #d97706);
}

.vip-badge-reg {
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

.vip-reg-title {
    margin: 0 0 8px;
    font-size: 1.6rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.3px;
}

.vip-reg-title span {
    color: #fce7ba;
    background: linear-gradient(90deg, #fce7ba, #e5b974);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.vip-reg-subtitle {
    margin: 0 0 24px;
    font-size: 0.875rem;
    color: #94a3b8;
    line-height: 1.5;
}

.vip-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 560px) {
    .vip-form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}

.vip-form-group {
    margin-bottom: 18px;
}

.vip-form-label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #fce7ba;
    letter-spacing: 0.3px;
}

.vip-form-label span.req {
    color: #ef4444;
    margin-left: 2px;
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
    font-size: 0.875rem;
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

.vip-reg-footer-links {
    margin-top: 22px;
    text-align: center;
    font-size: 0.875rem;
    color: #94a3b8;
}

.vip-reg-footer-links a {
    color: #f59e0b;
    font-weight: 700;
    text-decoration: none;
    transition: color 0.2s;
}

.vip-reg-footer-links a:hover {
    color: #fce7ba;
    text-decoration: underline;
}

.vip-trust-badges {
    display: flex;
    justify-content: space-around;
    margin-top: 22px;
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

<div class="vip-reg-wrapper">
    <div class="vip-reg-card">
        <!-- Huy hiệu Khách hàng mới -->
        <div style="text-align: center;">
            <div class="vip-badge-reg">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fce7ba" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
                Tạo Tài Khoản Khách Hàng Mới
            </div>
            
            <h1 class="vip-reg-title">
                Đăng ký <span>Thành viên VIP</span>
            </h1>
            
            <p class="vip-reg-subtitle">
                Đăng ký tài khoản để theo dõi tiến trình đơn hàng, nhận ưu đãi độc quyền và gửi yêu cầu đổi trả trực tuyến.
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div style="background: #451010; border: 1px solid #881c1c; color: #fecaca; border-radius: 10px; padding: 14px 18px; font-size: 0.875rem; margin-bottom: 22px;">
                <div style="font-weight: 700; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <span>⚠️ Không thể hoàn tất đăng ký:</span>
                </div>
                <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/register.php">
            <?= csrfField() ?>
            
            <!-- Họ và tên khách hàng -->
            <div class="vip-form-group">
                <label for="full_name" class="vip-form-label">
                    Họ và tên khách hàng <span class="req">*</span>
                </label>
                <div class="vip-input-container">
                    <div class="vip-input-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <input 
                        id="full_name" 
                        name="full_name" 
                        type="text" 
                        class="vip-form-input" 
                        placeholder="Ví dụ: Nguyễn Văn An"
                        value="<?= e($_POST['full_name'] ?? '') ?>" 
                        required 
                        autocomplete="name"
                    >
                </div>
            </div>

            <!-- Tên đăng nhập & Email -->
            <div class="vip-form-row">
                <div class="vip-form-group">
                    <label for="username" class="vip-form-label">
                        Tên đăng nhập <span class="req">*</span>
                    </label>
                    <div class="vip-input-container">
                        <div class="vip-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="4"></circle>
                                <path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path>
                            </svg>
                        </div>
                        <input 
                            id="username" 
                            name="username" 
                            type="text" 
                            class="vip-form-input" 
                            placeholder="Ví dụ: nguyenvanan"
                            value="<?= e($_POST['username'] ?? '') ?>" 
                            required 
                            autocomplete="username"
                        >
                    </div>
                </div>

                <div class="vip-form-group">
                    <label for="email" class="vip-form-label">
                        Địa chỉ Email <span class="req">*</span>
                    </label>
                    <div class="vip-input-container">
                        <div class="vip-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </div>
                        <input 
                            id="email" 
                            name="email" 
                            type="email" 
                            class="vip-form-input" 
                            placeholder="an.nguyen@gmail.com"
                            value="<?= e($_POST['email'] ?? '') ?>" 
                            required 
                            autocomplete="email"
                        >
                    </div>
                </div>
            </div>

            <!-- Số điện thoại & Địa chỉ nhận hàng -->
            <div class="vip-form-row">
                <div class="vip-form-group">
                    <label for="phone" class="vip-form-label">
                        Số điện thoại <span class="req">*</span>
                    </label>
                    <div class="vip-input-container">
                        <div class="vip-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </div>
                        <input 
                            id="phone" 
                            name="phone" 
                            type="tel" 
                            class="vip-form-input" 
                            placeholder="Ví dụ: 0912345678"
                            value="<?= e($_POST['phone'] ?? '') ?>" 
                            required 
                            autocomplete="tel"
                        >
                    </div>
                </div>

                <div class="vip-form-group">
                    <label for="address" class="vip-form-label">
                        Địa chỉ nhận hàng <span class="req">*</span>
                    </label>
                    <div class="vip-input-container">
                        <div class="vip-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </div>
                        <input 
                            id="address" 
                            name="address" 
                            type="text" 
                            class="vip-form-input" 
                            placeholder="Số nhà, đường, quận/huyện..."
                            value="<?= e($_POST['address'] ?? '') ?>" 
                            required 
                            autocomplete="street-address"
                        >
                    </div>
                </div>
            </div>

            <!-- Mật khẩu & Nhập lại mật khẩu -->
            <div class="vip-form-row">
                <div class="vip-form-group">
                    <label for="password" class="vip-form-label">
                        Mật khẩu bảo mật <span class="req">*</span>
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
                            placeholder="Ít nhất 6 ký tự"
                            required 
                            autocomplete="new-password"
                        >
                    </div>
                </div>

                <div class="vip-form-group">
                    <label for="confirm_password" class="vip-form-label">
                        Xác nhận mật khẩu <span class="req">*</span>
                    </label>
                    <div class="vip-input-container">
                        <div class="vip-input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <input 
                            id="confirm_password" 
                            name="confirm_password" 
                            type="password" 
                            class="vip-form-input" 
                            placeholder="Nhập lại mật khẩu"
                            required 
                            autocomplete="new-password"
                        >
                    </div>
                </div>
            </div>

            <!-- Nút Tạo tài khoản -->
            <button type="submit" class="vip-btn-submit">
                <span>TẠO TÀI KHOẢN NGAY</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </form>

        <!-- Đã có tài khoản? Đăng nhập -->
        <div class="vip-reg-footer-links">
            Đã có tài khoản? 
            <a href="/login.php">Đăng nhập ngay ➔</a>
        </div>

        <!-- Cam kết an toàn & Đề tài 19 -->
        <div class="vip-trust-badges">
            <div class="vip-trust-item">
                <span style="font-size: 1.1rem;">🔒</span>
                <span>Lưu CSDL MySQL</span>
            </div>
            <div class="vip-trust-item">
                <span style="font-size: 1.1rem;">🛡️</span>
                <span>Mã hóa Bcrypt</span>
            </div>
            <div class="vip-trust-item">
                <span style="font-size: 1.1rem;">⚡</span>
                <span>Kích hoạt ngay</span>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
