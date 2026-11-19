<?php
require __DIR__ . '/../src/Database.php';
try {
    $c = db();
    $v = queryParams($c, 'SELECT DATABASE() AS db, CURRENT_USER() AS u, VERSION() AS version', [])->get_result()->fetch_assoc();
    if (stripos($v['version'], 'mariadb') !== false || version_compare($v['version'], '8.0.16', '<')) {
        throw new RuntimeException('CAN_MYSQL_8_0_16_TRO_LEN');
    }
    echo 'CSDL=' . $v['db'] . ' | USER=' . $v['u'] . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'CHUA_SAN_SANG_CSDL: ' . $e->getMessage() . "\nChay bash lab.sh db-config trong Ma_nguon_MySQL.\n");
    exit(2);
}
