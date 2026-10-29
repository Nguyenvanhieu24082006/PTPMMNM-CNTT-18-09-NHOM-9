<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);
$user = currentUser();

$db = db();
$productRepo = new ProductRepository($db);

// Xử lý các thao tác Thêm, Sửa, Xóa sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = field($_POST, 'action');

    if ($action === 'add') {
        try {
            $name = field($_POST, 'name');
            $category = field($_POST, 'category');
            $description = field($_POST, 'description');
            $imageUrl = field($_POST, 'image_url');
            $sku = field($_POST, 'sku');
            $price = (float)field($_POST, 'price');
            $stock = (int)field($_POST, 'stock_quantity');
            $color = field($_POST, 'color');

            $productData = [
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'image_url' => $imageUrl !== '' ? $imageUrl : '/assets/images/vip/p1.webp',
                'is_active' => 1
            ];
            $variantData = [
                'sku' => $sku !== '' ? $sku : ('SKU-' . strtoupper(substr(md5(uniqid()), 0, 8))),
                'price' => $price,
                'stock_quantity' => $stock,
                'attributes' => ['color' => $color !== '' ? $color : 'Tiêu chuẩn']
            ];

            $newId = $productRepo->createProduct($productData, $variantData);
            setFlash('success', "Đã thêm sản phẩm ID #$newId thành công.");
        } catch (Throwable $e) {
            setFlash('danger', 'Lỗi thêm: ' . $e->getMessage());
        }
        header('Location: /admin/products.php');
        exit;
    } elseif ($action === 'edit') {
        try {
            $productId = fieldInt($_POST, 'product_id');
            $name = field($_POST, 'name');
            $category = field($_POST, 'category');
            $description = field($_POST, 'description');
            $imageUrl = field($_POST, 'image_url');
            $sku = field($_POST, 'sku');
            $price = (float)field($_POST, 'price');
            $stock = (int)field($_POST, 'stock_quantity');
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            $productData = [
                'name' => $name,
                'category' => $category,
                'description' => $description,
                'image_url' => $imageUrl,
                'is_active' => $isActive
            ];
            $variantData = [
                'sku' => $sku,
                'price' => $price,
                'stock_quantity' => $stock
            ];

            $productRepo->updateProduct($productId, $productData, $variantData);
            setFlash('success', "Đã cập nhật sản phẩm ID #$productId.");
        } catch (Throwable $e) {
            setFlash('danger', 'Lỗi cập nhật: ' . $e->getMessage());
        }
        header('Location: /admin/products.php');
        exit;
    } elseif ($action === 'delete') {
        try {
            $productId = fieldInt($_POST, 'product_id');
            $isHardDeleted = $productRepo->deleteProduct($productId);
            if ($isHardDeleted) {
                setFlash('success', "Đã xóa sản phẩm ID #$productId.");
            } else {
                setFlash('warning', "Sản phẩm ID #$productId đã có đơn hàng trong quá khứ nên được chuyển sang trạng thái 'Ngừng kinh doanh' để bảo toàn dữ liệu.");
            }
        } catch (Throwable $e) {
            setFlash('danger', 'Lỗi xóa: ' . $e->getMessage());
        }
        header('Location: /admin/products.php');
        exit;
    }
}

$search = field($_GET, 'search');
$category = field($_GET, 'category');
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

$products = $productRepo->listAllForAdmin($search, $category);
$categories = $productRepo->getCategories();

$editingProduct = null;
if ($editId > 0) {
    $editingProduct = $productRepo->findById($editId);
}

require __DIR__ . '/../templates/admin_header.php';
?>

<!-- FORM SỬA SẢN PHẨM (CHỈ HIỂN THỊ KHI BẤM SỬA) -->
<?php if ($editingProduct): ?>
    <?php $v0 = !empty($editingProduct['variants']) ? $editingProduct['variants'][0] : []; ?>
    <div class="card-admin" style="margin-bottom: 16px; border: 1px solid #007bff;">
        <div class="card-admin-header" style="background: rgba(0, 123, 255, 0.08);">
            <h3 class="card-admin-title" style="color: #007bff; font-size: 0.875rem;">
                ✏️ Chỉnh sửa sản phẩm #<?= (int)$editingProduct['id'] ?>: <?= e($editingProduct['name']) ?>
            </h3>
            <div class="card-admin-tools">
                <a href="/admin/products.php" class="btn-admin btn-admin-secondary btn-admin-sm">✕ Hủy</a>
            </div>
        </div>
        <div class="card-admin-body" style="padding: 12px 14px;">
            <form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="product_id" value="<?= (int)$editingProduct['id'] ?>">

                <div style="grid-column: 1 / -1;">
                    <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Tên sản phẩm *</label>
                    <input name="name" value="<?= e($editingProduct['name']) ?>" required style="width: 100%;">
                </div>

                <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Danh mục</label>
                    <input name="category" value="<?= e($editingProduct['category']) ?>" required style="width: 100%;">
                </div>

                <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Mã SKU</label>
                    <input name="sku" value="<?= e($v0['sku'] ?? '') ?>" required style="width: 100%;">
                </div>

                <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Giá bán (VNĐ) *</label>
                    <input type="number" step="1000" name="price" value="<?= (float)($v0['price'] ?? 0) ?>" required style="width: 100%;">
                </div>

                <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Tồn kho *</label>
                    <input type="number" name="stock_quantity" value="<?= (int)($v0['stock_quantity'] ?? 0) ?>" required style="width: 100%;">
                </div>

                <div>
                    <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Trạng thái</label>
                    <select name="is_active" style="width: 100%;">
                        <option value="1" <?= (int)$editingProduct['is_active'] === 1 ? 'selected' : '' ?>>Đang mở bán</option>
                        <option value="0" <?= (int)$editingProduct['is_active'] === 0 ? 'selected' : '' ?>>Tạm ngừng bán</option>
                    </select>
                </div>

                <div style="grid-column: 1 / -1;">
                    <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Đường dẫn ảnh (URL)</label>
                    <input name="image_url" value="<?= e($editingProduct['image_url'] ?? '') ?>" style="width: 100%;">
                </div>

                <div style="grid-column: 1 / -1; display: flex; gap: 8px; margin-top: 4px;">
                    <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm">
                        💾 Lưu thay đổi
                    </button>
                    <a href="/admin/products.php" class="btn-admin btn-admin-secondary btn-admin-sm">
                        Hủy
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- FORM THÊM SẢN PHẨM MỚI (MẶC ĐỊNH ẨN, BẤM NÚT SẼ MỞ) -->
<div class="card-admin" id="addProductCard" style="margin-bottom: 16px; border: 1px solid #28a745; <?= isset($_GET['action']) && $_GET['action'] === 'new' ? '' : 'display: none;' ?>">
    <div class="card-admin-header" style="background: rgba(40, 167, 69, 0.08);">
        <h3 class="card-admin-title" style="color: #28a745; font-size: 0.875rem;">
            ➕ Thêm sản phẩm mới
        </h3>
        <div class="card-admin-tools">
            <button type="button" onclick="document.getElementById('addProductCard').style.display='none'" class="btn-admin btn-admin-secondary btn-admin-sm">✕ Đóng</button>
        </div>
    </div>
    <div class="card-admin-body" style="padding: 12px 14px;">
        <form method="post" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">

            <div style="grid-column: 1 / -1;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Tên sản phẩm *</label>
                <input name="name" placeholder="Ví dụ: Bơm lốp mini 12V..." required style="width: 100%;">
            </div>

            <div>
                <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Danh mục</label>
                <input name="category" placeholder="Ví dụ: Phụ kiện điện tử..." list="catList" required style="width: 100%;">
                <datalist id="catList">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div>
                <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Mã SKU</label>
                <input name="sku" placeholder="Để trống sinh tự động" style="width: 100%;">
            </div>

            <div>
                <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Giá bán (VNĐ) *</label>
                <input type="number" step="1000" name="price" placeholder="350000" required style="width: 100%;">
            </div>

            <div>
                <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Tồn kho ban đầu *</label>
                <input type="number" name="stock_quantity" value="50" min="0" required style="width: 100%;">
            </div>

            <div>
                <label style="font-size: 0.75rem; font-weight: 600; color: #495057;">Đường dẫn ảnh</label>
                <input name="image_url" value="/assets/images/vip/p1.webp" style="width: 100%;">
            </div>

            <div style="grid-column: 1 / -1; margin-top: 4px;">
                <button type="submit" class="btn-admin btn-admin-success btn-admin-sm">
                    ✓ Hoàn tất thêm sản phẩm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==============================================================================
     CARD DUY NHẤT: BẢNG DANH SÁCH SẢN PHẨM KÈM THANH TÌM KIẾM TINH GỌN
     ============================================================================== -->
<div class="card-admin">
    <!-- Header của Card: Tiêu đề + Nút thêm mới -->
    <div class="card-admin-header">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h3 class="card-admin-title">
                📦 Danh sách Sản phẩm
            </h3>
            <span class="badge-admin badge-info" style="font-size: 0.7rem;">
                <?= count($products) ?> sản phẩm
            </span>
        </div>
        <div class="card-admin-tools">
            <button type="button" onclick="const f=document.getElementById('addProductCard'); f.style.display=(f.style.display==='none'?'block':'none'); window.scrollTo({top:0, behavior:'smooth'});" class="btn-admin btn-admin-success btn-admin-sm">
                ➕ Thêm sản phẩm mới
            </button>
        </div>
    </div>

    <!-- Thanh tìm kiếm & lọc 1 dòng tinh gọn ngay trong Card -->
    <div style="padding: 8px 12px; background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--admin-border-color);">
        <form method="get" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 2 1 200px;">
                <input id="search" name="search" value="<?= e($search) ?>" placeholder="🔍 Nhập tên sản phẩm, mã SKU, danh mục..." style="width: 100%;">
            </div>
            <div style="flex: 1 1 150px;">
                <select id="category" name="category" style="width: 100%;">
                    <option value="">-- Tất cả danh mục --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-admin btn-admin-primary btn-admin-sm">
                Lọc
            </button>
            <?php if ($search !== '' || $category !== ''): ?>
                <a href="/admin/products.php" class="btn-admin btn-admin-secondary btn-admin-sm" title="Đặt lại bộ lọc">
                    ↺
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bảng danh sách sản phẩm tinh gọn -->
    <div class="card-admin-body" style="padding: 0; overflow-x: auto;">
        <table class="table-admin table-striped table-hover" style="margin: 0; width: 100%;">
            <thead>
                <tr>
                    <th style="width: 48px; text-align: center;">Ảnh</th>
                    <th style="min-width: 280px;">Sản phẩm</th>
                    <th style="width: 140px;">Danh mục</th>
                    <th style="width: 110px; text-align: right;">Giá niêm yết</th>
                    <th style="width: 100px; text-align: center;">Tồn kho</th>
                    <th style="width: 65px; text-align: center;">Trạng thái</th>
                    <th style="width: 75px; text-align: right;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #6c757d;">
                            Không tìm thấy sản phẩm nào.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <?php 
                        $firstV = !empty($p['variants']) ? $p['variants'][0] : null;
                        $totalStock = (int)($p['total_stock'] ?? 0);
                        $availStock = $firstV ? (int)($firstV['available_quantity'] ?? $firstV['stock_quantity']) : 0;
                    ?>
                    <tr>
                        <!-- Cột 1: Ảnh -->
                        <td style="text-align: center;">
                            <img src="<?= e(productImageUrl($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" onerror="this.onerror=null;this.src='/assets/images/vip/cat_keyfob_oto.png';" style="width: 38px; height: 38px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6; background: #fff; vertical-align: middle;">
                        </td>

                        <!-- Cột 2: Tên sản phẩm & Mã SKU + ID gộp lại -->
                        <td>
                            <div style="font-weight: 600; color: inherit; line-height: 1.35; max-width: 380px;">
                                <a href="/admin/products.php?edit_id=<?= (int)$p['id'] ?>" style="color: inherit; text-decoration: none;" title="Bấm để sửa">
                                    <?= e($p['name']) ?>
                                </a>
                            </div>
                            <div style="font-size: 0.72rem; color: #6c757d; margin-top: 2px;">
                                SKU: <code><?= e($firstV['sku'] ?? 'N/A') ?></code> • ID: #<?= (int)$p['id'] ?>
                            </div>
                        </td>

                        <!-- Cột 3: Danh mục -->
                        <td>
                            <span class="badge-admin badge-info" style="font-size: 0.7rem; font-weight: 500;">
                                <?= e($p['category']) ?>
                            </span>
                        </td>

                        <!-- Cột 4: Giá bán -->
                        <td style="text-align: right;">
                            <strong style="color: #28a745; font-size: 0.8125rem;">
                                <?= formatMoney((float)($firstV['price'] ?? 0)) ?>
                            </strong>
                        </td>

                        <!-- Cột 5: Tồn kho -->
                        <td style="text-align: center; font-size: 0.8125rem;">
                            <strong><?= $availStock ?></strong> / <small style="color: #6c757d;"><?= $totalStock ?></small>
                            <?php if ($totalStock <= 5): ?>
                                <span class="badge-admin badge-warning" style="font-size: 0.625rem; padding: 1px 4px; margin-left: 2px;">Ít</span>
                            <?php endif; ?>
                        </td>

                        <!-- Cột 6: Trạng thái (Hiển thị dạng icon tinh gọn) -->
                        <td style="text-align: center;">
                            <?php if ((int)$p['is_active'] === 1): ?>
                                <span class="badge-admin badge-success" title="Đang mở bán" style="font-size: 0.8rem; padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">✓</span>
                            <?php else: ?>
                                <span class="badge-admin badge-secondary" title="Ngừng kinh doanh" style="font-size: 0.8rem; padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">✕</span>
                            <?php endif; ?>
                        </td>

                        <!-- Cột 7: Thao tác (Hiển thị icon nút bấm đồng đều 28x28px) -->
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 5px; justify-content: flex-end; align-items: center;">
                                <a href="/admin/products.php?edit_id=<?= (int)$p['id'] ?>" class="btn-admin btn-admin-primary btn-admin-sm" title="Chỉnh sửa sản phẩm" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; padding: 0; font-size: 0.85rem; border-radius: 4px; text-decoration: none;">
                                    ✏️
                                </a>
                                <form method="post" style="margin: 0; display: inline-flex;" onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" class="btn-admin btn-admin-danger btn-admin-sm" title="Xóa sản phẩm" style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; padding: 0; font-size: 0.85rem; border-radius: 4px; cursor: pointer; border: none;">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
