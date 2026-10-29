<?php
require_once __DIR__ . '/../src/Database.php';
try {
    // Keep the connection alive until the prepared result has been read.
    $connection = db();
    $result = queryParams(
        $connection,
        'SELECT e.id,e.title,e.category,e.capacity,COUNT(r.id) AS registered
         FROM events e LEFT JOIN registrations r ON r.event_id=e.id
         GROUP BY e.id,e.title,e.category,e.capacity ORDER BY e.id',
        []
    );
    $rows = $result->get_result()->fetch_all(MYSQLI_ASSOC);
    $result->close();
    return array_map(static function (array $row): array {
        $row['id'] = (int)$row['id'];
        $row['capacity'] = (int)$row['capacity'];
        $row['registered'] = (int)$row['registered'];
        return $row;
    }, $rows);
} catch (Throwable $e) {
    http_response_code(503);
    exit('DATABASE_UNAVAILABLE: check the local lab configuration.');
}
