<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class ProductRepository
{
    public function __construct(private mysqli $db) {}

    /**
     * Lấy danh sách sản phẩm kèm các biến thể và tính lượng hàng có thể bán
     */
    public function getAll(string $search = '', string $category = ''): array
    {
        $sql = "SELECT p.* FROM products p WHERE p.is_active = 1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($category !== '') {
            $sql .= " AND p.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY p.id ASC";
        $stmt = queryParams($this->db, $sql, $params);
        $products = fetchAll($stmt);

        foreach ($products as &$prod) {
            $prod['variants'] = $this->getVariantsForProduct((int)$prod['id']);
        }

        return $products;
    }

    public function getCategories(): array
    {
        $stmt = queryParams($this->db, "SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category ASC");
        $rows = fetchAll($stmt);
        return array_column($rows, 'category');
    }

    public function findById(int $productId): ?array
    {
        $stmt = queryParams($this->db, "SELECT * FROM products WHERE id = ? AND is_active = 1", [$productId]);
        $prod = fetchOne($stmt);
        if ($prod) {
            $prod['variants'] = $this->getVariantsForProduct($productId);
        }
        return $prod;
    }

    public function findVariantById(int $variantId): ?array
    {
        $sql = "SELECT v.*, p.name AS product_name, p.category, p.image_url 
                FROM variants v 
                JOIN products p ON v.product_id = p.id 
                WHERE v.id = ?";
        $stmt = queryParams($this->db, $sql, [$variantId]);
        $variant = fetchOne($stmt);
        if (!$variant) {
            return null;
        }

        $variant['attributes'] = json_decode($variant['attributes'] ?: '{}', true);
        $variant['available_quantity'] = $this->calculateAvailableQuantity($variantId, (int)$variant['stock_quantity']);
        return $variant;
    }

    public function getVariantsForProduct(int $productId): array
    {
        $sql = "SELECT v.* FROM variants v WHERE v.product_id = ? ORDER BY v.id ASC";
        $stmt = queryParams($this->db, $sql, [$productId]);
        $variants = fetchAll($stmt);

        foreach ($variants as &$v) {
            $v['attributes'] = json_decode($v['attributes'] ?: '{}', true);
            $v['available_quantity'] = $this->calculateAvailableQuantity((int)$v['id'], (int)$v['stock_quantity']);
        }

        return $variants;
    }

    /**
     * Tính lượng hàng thực tế có thể bán:
     * Available = Tồn kho thực tế (stock_quantity) - Lượng hàng đang giữ trong các đơn chưa hết hạn (HOLDING)
     */
    public function calculateAvailableQuantity(int $variantId, int $stockQuantity): int
    {
        $sql = "SELECT COALESCE(SUM(quantity), 0) AS holding_qty 
                FROM reservations 
                WHERE variant_id = ? AND status = 'HOLDING' AND expires_at > NOW()";
        $stmt = queryParams($this->db, $sql, [$variantId]);
        $row = fetchOne($stmt);
        $holding = (int)($row['holding_qty'] ?? 0);

        return max(0, $stockQuantity - $holding);
    }

    /**
     * Cập nhật giá sản phẩm biến thể (Dùng để kiểm thử Phép thử 3: Snapshot Giá)
     */
    public function updatePrice(int $variantId, float $newPrice): void
    {
        if ($newPrice < 0) {
            throw new InvalidArgumentException("Giá không được âm");
        }
        queryParams($this->db, "UPDATE variants SET price = ? WHERE id = ?", [$newPrice, $variantId]);
    }
}

