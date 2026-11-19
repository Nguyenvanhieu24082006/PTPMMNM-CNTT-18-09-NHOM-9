<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';

try {
    $db = db();
    $repo = new ProductRepository($db);
    $all = $repo->getAll();
    echo "Total products in DB: " . count($all) . "\n";
    $cats = $repo->getCategories();
    echo "Categories: " . implode(', ', $cats) . "\n";
    $boc = $repo->getAll('', 'Bọc vô lăng');
    echo "Bọc vô lăng count: " . count($boc) . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

