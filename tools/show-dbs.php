<?php
require_once __DIR__ . '/../src/Database.php';
$c = db();
$res = queryParams($c, "SHOW DATABASES", [])->get_result();
while ($row = $res->fetch_array()) {
    echo "- " . $row[0] . "\n";
}

