<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../src/Database.php';
if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "THIEU_MYSQLI\n");
    exit(2);
}
function ask(string $label, string $default): string
{
    echo "$label [$default]: ";
    $line = fgets(STDIN);
    if ($line === false) {
        throw new RuntimeException('Khong doc duoc du lieu');
    }
    $value = trim($line);
    return $value === '' ? $default : $value;
}
try {
    $c = [
        'host' => ask('Máy chủ', '127.0.0.1'),
        'port' => ask('Cổng', '3306'),
        'database' => ask('CSDL lab riêng', 'communitypass_mysql_v5'),
        'user' => ask('Tài khoản lab', 'communitypass_mysql_v5')
    ];
    if (!ctype_digit($c['port']) || (int)$c['port'] < 1 || (int)$c['port'] > 65535) {
        throw new RuntimeException('Cong khong hop le');
    }
    if (!stream_isatty(STDIN)) {
        throw new RuntimeException('Mo terminal tuong tac de nhap mat khau');
    }
    exec('stty -echo', $out, $rc);
    if ($rc !== 0) {
        throw new RuntimeException('Khong an duoc mat khau');
    }
    try {
        echo 'Mật khẩu CSDL (không hiển thị): ';
        $password = fgets(STDIN);
    } finally {
        exec('stty echo');
        echo "\n";
    }
    if ($password === false || rtrim($password, "\r\n") === '') {
        throw new RuntimeException('Mat khau rong');
    }
    $c['password'] = rtrim($password, "\r\n");
    connectMysql($c)->close();
    $dir = __DIR__ . '/../.local';
    $old = umask(0077);
    try {
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            throw new RuntimeException('Khong tao duoc thu muc');
        }
        $json = json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($dir . '/db.json.tmp', $json) === false) {
            throw new RuntimeException('Khong ghi duoc cau hinh');
        }
        chmod($dir . '/db.json.tmp', 0600);
        if (!rename($dir . '/db.json.tmp', $dir . '/db.json')) {
            throw new RuntimeException('Khong luu duoc cau hinh');
        }
    } finally {
        umask($old);
    }
    echo "CAU_HINH=DA_LUU | Chỉ lưu trên máy này. Không gửi thư mục .local.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
}
