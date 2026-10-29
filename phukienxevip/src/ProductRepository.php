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

    /**
     * Lấy toàn bộ danh sách sản phẩm quản trị (kể cả sản phẩm ngừng hoạt động nếu có)
     */
    public function listAllForAdmin(string $search = '', string $category = ''): array
    {
        $sql = "SELECT p.*, 
                       COUNT(v.id) AS variant_count,
                       COALESCE(MIN(v.price), 0) AS min_price,
                       COALESCE(MAX(v.price), 0) AS max_price,
                       COALESCE(SUM(v.stock_quantity), 0) AS total_stock
                FROM products p
                LEFT JOIN variants v ON p.id = v.product_id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (p.name LIKE ? OR p.category LIKE ? OR v.sku LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($category !== '') {
            $sql .= " AND p.category = ?";
            $params[] = $category;
        }

        $sql .= " GROUP BY p.id ORDER BY p.id DESC";
        $stmt = queryParams($this->db, $sql, $params);
        $products = fetchAll($stmt);

        foreach ($products as &$prod) {
            $prod['variants'] = $this->getVariantsForProduct((int)$prod['id']);
        }

        return $products;
    }

    /**
     * Thêm sản phẩm mới kèm biến thể mặc định
     */
    public function createProduct(array $p, array $v): int
    {
        $name = trim($p['name'] ?? '');
        $category = trim($p['category'] ?? 'Phụ kiện');
        $description = trim($p['description'] ?? '');
        $imageUrl = trim($p['image_url'] ?? '/assets/images/vip/p1.webp');
        $isActive = isset($p['is_active']) ? (int)$p['is_active'] : 1;

        if ($name === '') {
            throw new InvalidArgumentException("Tên sản phẩm không được để trống");
        }

        queryParams(
            $this->db,
            "INSERT INTO products (name, category, description, image_url, is_active) VALUES (?, ?, ?, ?, ?)",
            [$name, $category, $description, $imageUrl, $isActive]
        );
        $productId = (int)$this->db->insert_id;

        $sku = trim($v['sku'] ?? ('SKU-' . strtoupper(substr(md5(uniqid()), 0, 8))));
        $price = max(0, (float)($v['price'] ?? 0));
        $stock = max(0, (int)($v['stock_quantity'] ?? 0));
        $attributes = $v['attributes'] ?? ['type' => 'Tiêu chuẩn'];
        if (!is_string($attributes)) {
            $attributes = json_encode($attributes, JSON_UNESCAPED_UNICODE);
        }

        queryParams(
            $this->db,
            "INSERT INTO variants (product_id, sku, attributes, price, stock_quantity) VALUES (?, ?, ?, ?, ?)",
            [$productId, $sku, $attributes, $price, $stock]
        );

        return $productId;
    }

    /**
     * Cập nhật thông tin sản phẩm và biến thể
     */
    public function updateProduct(int $productId, array $p, array $v): void
    {
        $name = trim($p['name'] ?? '');
        $category = trim($p['category'] ?? '');
        $description = trim($p['description'] ?? '');
        $imageUrl = trim($p['image_url'] ?? '');
        $isActive = isset($p['is_active']) ? (int)$p['is_active'] : 1;

        if ($name === '') {
            throw new InvalidArgumentException("Tên sản phẩm không được để trống");
        }

        queryParams(
            $this->db,
            "UPDATE products SET name = ?, category = ?, description = ?, image_url = ?, is_active = ? WHERE id = ?",
            [$name, $category, $description, $imageUrl, $isActive, $productId]
        );

        // Cập nhật biến thể chính
        $variants = $this->getVariantsForProduct($productId);
        $price = max(0, (float)($v['price'] ?? 0));
        $stock = max(0, (int)($v['stock_quantity'] ?? 0));

        if (!empty($variants)) {
            $firstVariant = $variants[0];
            $variantId = (int)$firstVariant['id'];
            $sku = !empty($v['sku']) ? trim($v['sku']) : $firstVariant['sku'];

            queryParams(
                $this->db,
                "UPDATE variants SET sku = ?, price = ?, stock_quantity = ? WHERE id = ?",
                [$sku, $price, $stock, $variantId]
            );
        } else {
            $sku = !empty($v['sku']) ? trim($v['sku']) : ('SKU-' . strtoupper(substr(md5(uniqid()), 0, 8)));
            $attributes = json_encode(['type' => 'Tiêu chuẩn'], JSON_UNESCAPED_UNICODE);
            queryParams(
                $this->db,
                "INSERT INTO variants (product_id, sku, attributes, price, stock_quantity) VALUES (?, ?, ?, ?, ?)",
                [$productId, $sku, $attributes, $price, $stock]
            );
        }
    }

    /**
     * Xóa sản phẩm: Nếu đã có đơn hàng thì chuyển is_active = 0 (Soft delete) để bảo toàn dữ liệu,
     * nếu chưa có đơn hàng thì xóa cứng khỏi CSDL.
     * @return bool Trả về true nếu xóa cứng, false nếu chuyển trạng thái ngừng bán
     */
    public function deleteProduct(int $productId): bool
    {
        // Kiểm tra xem có đơn hàng nào từng mua sản phẩm này chưa
        $stmt = queryParams(
            $this->db,
            "SELECT COUNT(*) AS order_cnt 
             FROM order_items oi 
             JOIN variants v ON oi.variant_id = v.id 
             WHERE v.product_id = ?",
            [$productId]
        );
        $row = fetchOne($stmt);
        $hasOrders = (int)($row['order_cnt'] ?? 0) > 0;

        if ($hasOrders) {
            queryParams($this->db, "UPDATE products SET is_active = 0 WHERE id = ?", [$productId]);
            return false;
        }

        queryParams($this->db, "DELETE FROM products WHERE id = ?", [$productId]);
        return true;
    }
}

