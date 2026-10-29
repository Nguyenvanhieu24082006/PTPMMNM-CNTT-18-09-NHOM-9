<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Phân hệ Admin: FOLDER 'admin/'
if (str_starts_with($uri, '/admin')) {
    $sub = substr($uri, 6);
    if ($sub === '' || $sub === '/') {
        require __DIR__ . '/admin/index.php';
        exit;
    }
    $target = __DIR__ . '/admin' . $sub;
    if (is_file($target)) {
        if (str_ends_with($target, '.php')) {
            require $target;
            exit;
        }
        return false;
    }
    if (is_file($target . '.php')) {
        require $target . '.php';
        exit;
    }
    header('Location: /admin/index.php');
    exit;
}

// 2. Tài nguyên tĩnh (CSS, JS, hình ảnh, fonts...)
// Kiểm tra nếu là file tĩnh (không phải .php) trong thư mục gốc hoặc public
if ($uri !== '/' && !str_ends_with($uri, '.php')) {
    if (is_file(__DIR__ . $uri)) {
        return false; // PHP CLI server phục vụ file tĩnh trực tiếp (nhờ junction /assets)
    }
    if (is_file(__DIR__ . '/public' . $uri)) {
        $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        $mimes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
        ];
        header("Content-Type: " . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile(__DIR__ . '/public' . $uri);
        exit;
    }
}

// 3. Phân hệ Khách hàng: FOLDER 'public/' (Storefront PHP files)
$pubPhp = __DIR__ . '/public' . ($uri === '/' ? '/index.php' : $uri);
if (is_file($pubPhp) && str_ends_with($pubPhp, '.php')) {
    require $pubPhp;
    exit;
}
if (is_file($pubPhp . '.php')) {
    require $pubPhp . '.php';
    exit;
}

// Mặc định vào trang chủ khách hàng
require __DIR__ . '/public/index.php';
