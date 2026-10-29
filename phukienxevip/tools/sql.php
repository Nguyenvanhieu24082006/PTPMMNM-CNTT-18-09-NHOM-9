<?php
require __DIR__ . '/../src/Database.php';
try {
    db()->close();
    $c = mysqlConfig();
} catch (Throwable $e) {
    fwrite(STDERR, "Chua ket noi duoc CSDL.\n");
    exit(2);
}
// The client prompts for the password; never put it in argv or the environment.
$proc = proc_open(['mysql', '--protocol=TCP', '--host=' . $c['host'],
    '--port=' . $c['port'], '--user=' . $c['user'], '--password',
    '--default-character-set=utf8mb4', '--database=' . $c['database']],
    [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
if (!is_resource($proc)) { exit(2); }
exit(proc_close($proc));
