<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/Input.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim(field($_GET, 'q'));
if (strlen($q) < 1) {
    echo json_encode(['results' => [], 'total' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = db();
    $repo = new ProductRepository($db);
    $products = $repo->getAll($q);
    $results = [];

    foreach (array_slice($products, 0, 6) as $p) {
        $firstVar = $p['variants'][0] ?? null;
        $results[] = [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'category' => $p['category'],
            'price' => $firstVar ? formatMoney((float)$firstVar['price']) : '',
            'image' => productImageUrl($p['image_url']),
            'available' => $firstVar ? (int)$firstVar['available_quantity'] : 0,
            'url' => '/product.php?id=' . (int)$p['id']
        ];
    }

    echo json_encode([
        'results' => $results,
        'total' => count($products),
        'query' => $q
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['results' => [], 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

