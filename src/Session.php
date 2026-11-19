<?php

declare(strict_types=1);

// Quản lý phiên làm việc, CSRF và phân quyền người dùng
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function csrfToken(): string
{
    return $_SESSION['csrf'] ?? '';
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function checkCsrf(array $input): void
{
    $token = $input['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('CSRF_INVALID: Yêu cầu bị từ chối do mã xác thực không hợp lệ.');
    }
}

function isAdminContext(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $file = $_SERVER['SCRIPT_FILENAME'] ?? '';
    
    return str_starts_with($uri, '/admin') 
        || str_contains($script, '/admin/') 
        || str_contains(str_replace('\\', '/', $file), '/admin/');
}

function userSessionKey(): string
{
    return isAdminContext() ? 'admin_user' : 'customer_user';
}

function currentUser(): ?array
{
    $key = userSessionKey();
    if (isset($_SESSION[$key]) && is_array($_SESSION[$key])) {
        return $_SESSION[$key];
    }
    
    // Tự động di chuyển dữ liệu phiên cũ (nếu có) vào đúng phân hệ
    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $legacyRole = $_SESSION['user']['role'] ?? '';
        if (in_array($legacyRole, ['staff', 'manager', 'admin'], true)) {
            $_SESSION['admin_user'] = $_SESSION['user'];
        } else {
            $_SESSION['customer_user'] = $_SESSION['user'];
        }
        unset($_SESSION['user']);
        
        return $_SESSION[$key] ?? null;
    }
    
    return null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function currentRole(): string
{
    $u = currentUser();
    return $u['role'] ?? 'guest';
}

function loginUser(array $user): void
{
    $userData = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'address' => $user['address'] ?? '',
        'role' => $user['role']
    ];

    if (in_array($user['role'], ['staff', 'manager', 'admin'], true)) {
        $_SESSION['admin_user'] = $userData;
    } else {
        $_SESSION['customer_user'] = $userData;
    }
    unset($_SESSION['user']);
}

function logoutUser(): void
{
    if (isAdminContext()) {
        unset($_SESSION['admin_user']);
    } else {
        unset($_SESSION['customer_user']);
    }
    unset($_SESSION['user']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireAdminLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
    if (!in_array(currentRole(), ['staff', 'manager', 'admin'], true)) {
        header('Location: /admin/login.php?error=unauthorized');
        exit;
    }
}

function requireRole(array|string $roles): void
{
    $isAdmin = str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin') || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin');
    if (!isLoggedIn()) {
        header('Location: ' . ($isAdmin ? '/admin/login.php' : '/login.php'));
        exit;
    }
    // Admin có toàn quyền truy cập tất cả các phân hệ quản trị
    if ($isAdmin && in_array(currentRole(), ['admin', 'manager'], true)) {
        return;
    }
    $roles = (array)$roles;
    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        if ($isAdmin) {
            header('Location: /admin/login.php?error=unauthorized');
        } else {
            setFlash('danger', 'Truy cập bị từ chối: Khu vực này chỉ dành cho Nhân viên (Staff) và Quản lý (Admin). Khách hàng không có quyền truy cập.');
            header('Location: /index.php');
        }
        exit;
    }
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Quản lý giỏ hàng trong Session
function getCart(): array
{
    return $_SESSION['cart'] ?? [];
}

function addToCart(int $variantId, int $quantity): void
{
    if ($quantity <= 0) return;
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$variantId] = ($_SESSION['cart'][$variantId] ?? 0) + $quantity;
}

function updateCart(int $variantId, int $quantity): void
{
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$variantId]);
    } else {
        $_SESSION['cart'][$variantId] = $quantity;
    }
}

function clearCart(): void
{
    unset($_SESSION['cart']);
}
