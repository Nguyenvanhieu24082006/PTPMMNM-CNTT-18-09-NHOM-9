<?php
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/RegistrationRepository.php';
try { $connection = db(); }
catch (Throwable $e) { fwrite(STDERR, "NOT_RUN: MySQL unavailable or unconfigured\n"); exit(2); }
function rejects(callable $action, int|array $code, string $label, string $type = RuntimeException::class): void
{
    try {
        $action();
        same(false, true, $label);
    } catch (Throwable $e) {
        same($e instanceof $type && in_array($e->getCode(), (array)$code, true), true, $label);
    }
}
if (!$connection->begin_transaction()) { exit(2); }
try {
    $id = 500000000 + random_int(1, 100000000);
    queryParams($connection, 'INSERT INTO events(id,title,category,capacity) VALUES (?,?,?,?)',
        [$id, 'Constraint fixture', 'test', 5])->close();
    $repo = new RegistrationRepository($connection);
    rejects(fn() => $repo->save(-1, 'Name', 'a@example.test'), 0, 'invalid-input', InvalidArgumentException::class);
    // A restricted account can receive 1216 instead of the detailed FK error 1452.
    rejects(fn() => $repo->save(0x7fffffff, 'Name', 'a@example.test'), [1216, 1452], 'foreign-key-not-duplicate');
    rejects(fn() => queryParams($connection,
        'INSERT INTO registrations(event_id,name,email) VALUES (?,?,?)',
        [$id, 'Name', 'UPPER@example.test']), 3819, 'database-normalized-email');
    rejects(fn() => queryParams($connection,
        'INSERT INTO registrations(event_id,name,email) VALUES (?,?,?)',
        [$id, '   ', 'empty@example.test']), 3819, 'database-blank-name');
    $name = "Nguyễn O'Neil'); DROP TABLE events; --";
    same($repo->save($id, $name, 'a@example.test'), 'CREATED', 'parameterized-unicode-name');
    same($repo->listForEvent($id)[0]['name'], $name, 'literal-sql-text-preserved');
    same($repo->save($id, 'Again', ' A@example.test '), 'DUPLICATE', 'duplicate-after-errors');
    same(count($repo->listForEvent($id)), 1, 'errors-did-not-write');
    same((int)queryParams($connection, 'SELECT COUNT(*) AS n FROM events WHERE id=?', [$id])
        ->get_result()->fetch_assoc()['n'], 1, 'events-table-intact');
} catch (Throwable $e) {
    $failed++; echo "FAIL constraints: " . $e->getMessage() . "\n";
} finally { $connection->rollback(); }
finish();
