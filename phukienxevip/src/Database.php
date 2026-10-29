<?php

declare(strict_types=1);

/**
 * Quản lý kết nối cơ sở dữ liệu MySQL và thực thi Transaction an toàn
 */
function mysqlConfig(): array
{
    $path = __DIR__ . '/../.local/db.json';
    if (is_file($path)) {
        $config = json_decode((string)file_get_contents($path), true);
        if (is_array($config) && isset($config['host'], $config['database'], $config['user'], $config['password'])) {
            $config['port'] = (string)($config['port'] ?? '3306');
            return $config;
        }
    }

    // Cấu hình mặc định nếu chưa chạy db-config
    return [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'communitypass_mysql_v5',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''
    ];
}

function connectMysql(array $config): mysqli
{
    if (!extension_loaded('mysqli')) {
        throw new RuntimeException('MYSQLI_EXTENSION_MISSING');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
    $passwordsToTry = [$config['password']];
    if ($config['password'] !== 'Hieu123123') {
        $passwordsToTry[] = 'Hieu123123';
    }
    if ($config['password'] !== '') {
        $passwordsToTry[] = '';
    }

    $lastException = null;
    foreach ($passwordsToTry as $pwd) {
        try {
            $connection = mysqli_init();
            $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
            $connection->real_connect(
                $config['host'],
                $config['user'],
                $pwd,
                $config['database'],
                (int)$config['port']
            );
            $connection->set_charset('utf8mb4');
            $connection->query("SET time_zone = '+07:00'");
            $connection->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY'");
            return $connection;
        } catch (mysqli_sql_exception $e) {
            $lastException = $e;
        }
    }
    throw new RuntimeException('DATABASE_UNAVAILABLE: ' . ($lastException ? $lastException->getMessage() : 'Unknown error'));
}

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        $conn = connectMysql(mysqlConfig());
    }
    return $conn;
}

/**
 * Thực thi câu lệnh SQL với tham số an toàn (Prepared Statement chống SQL Injection)
 */
function queryParams(mysqli $connection, string $sql, array $params = []): mysqli_stmt
{
    try {
        $statement = $connection->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('PREPARE_FAILED: ' . $connection->error);
        }
        if (!empty($params)) {
            $statement->execute($params);
        } else {
            $statement->execute();
        }
        return $statement;
    } catch (mysqli_sql_exception $e) {
        throw new RuntimeException('DATABASE_OPERATION_FAILED: ' . $e->getMessage(), (int)$e->getCode());
    }
}

/**
 * Lấy tất cả bản ghi trả về dưới dạng mảng kết hợp
 */
function fetchAll(mysqli_stmt $stmt): array
{
    $result = $stmt->get_result();
    if ($result === false) {
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Lấy một bản ghi đầu tiên
 */
function fetchOne(mysqli_stmt $stmt): ?array
{
    $result = $stmt->get_result();
    if ($result === false) {
        return null;
    }
    $row = $result->fetch_assoc();
    return $row ?: null;
}
