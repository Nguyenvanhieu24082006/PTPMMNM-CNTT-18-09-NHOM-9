<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ProductRepository.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);
$user = currentUser();

$db = db();
$productRepo = new ProductRepository($db);

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

$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
if (!in_array($perPage, [10, 15, 25, 50, 100], true)) {
    $perPage = 15;
}

$totalProducts = $productRepo->countAllForAdmin($search, $category);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
} elseif ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$products = $productRepo->listAllForAdmin($search, $category, $perPage, $offset);
$categories = $productRepo->getCategories();

$editingProduct = null;
if ($editId > 0) {
    $editingProduct = $productRepo->findById($editId);
}

require __DIR__ . '/../templates/admin_header.php';
?>

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

<div class="card-admin">
    <div class="card-admin-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 12px 16px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h3 class="card-admin-title" style="margin: 0; font-size: 0.95rem;">
                <span>📦</span> Danh sách Sản phẩm & Kho hàng
            </h3>
            <span class="badge-admin badge-info" style="font-size: 0.72rem;">
                Tổng: <?= number_format($totalProducts) ?> sản phẩm
            </span>
            <?php if ($totalPages > 1): ?>
                <span class="badge-admin badge-secondary" style="font-size: 0.72rem;">
                    Trang <?= $page ?> / <?= $totalPages ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-admin-tools">
            <button type="button" onclick="const f=document.getElementById('addProductCard'); f.style.display=(f.style.display==='none'?'block':'none'); window.scrollTo({top:0, behavior:'smooth'});" class="btn-admin btn-admin-success" style="height: 36px; padding: 0 14px; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 5px; border-radius: 4px; font-weight: 600;">
                ➕ Thêm sản phẩm mới
            </button>
        </div>
    </div>

    <div style="padding: 10px 14px; background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--admin-border-color);">
        <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0;">
            <div style="flex: 1 1 240px;">
                <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="🔍 Nhập tên sản phẩm, mã SKU, danh mục..." style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.85rem; border: 1px solid var(--admin-border-color, #ced4da); border-radius: 4px; background: var(--admin-card-bg, #fff); color: inherit; box-sizing: border-box;">
            </div>

            <div style="flex: 1 1 240px;">
                <select id="category" name="category" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.85rem; border: 1px solid var(--admin-border-color, #ced4da); border-radius: 4px; background: var(--admin-card-bg, #fff); color: inherit; box-sizing: border-box;">
                    <option value="">-- Tất cả danh mục --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($perPage !== 15): ?>
                <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
            <?php endif; ?>

            <div style="display: flex; gap: 6px; align-items: center;">
                <button type="submit" class="btn-admin btn-admin-primary" style="height: 36px; padding: 0 14px; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px;">
                    🔍 Lọc sản phẩm
                </button>
                <?php if ($search !== '' || $category !== ''): ?>
                    <a href="/admin/products.php<?= $perPage !== 15 ? '?per_page=' . (int)$perPage : '' ?>" class="btn-admin btn-admin-secondary" style="height: 36px; padding: 0 14px; font-size: 0.8125rem; display: inline-flex; align-items: center; gap: 4px; border-radius: 4px; text-decoration: none;">
                        ✕ Xóa lọc
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

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
                        <td style="text-align: center;">
                            <img src="<?= e(productImageUrl($p['image_url'])) ?>" alt="<?= e($p['name']) ?>" onerror="this.onerror=null;this.src='/assets/images/vip/cat_keyfob_oto.png';" style="width: 38px; height: 38px; object-fit: cover; border-radius: 4px; border: 1px solid #dee2e6; background: #fff; vertical-align: middle;">
                        </td>

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

                        <td>
                            <span class="badge-admin badge-info" style="font-size: 0.7rem; font-weight: 500;">
                                <?= e($p['category']) ?>
                            </span>
                        </td>

                        <td style="text-align: right;">
                            <strong style="color: #28a745; font-size: 0.8125rem;">
                                <?= formatMoney((float)($firstV['price'] ?? 0)) ?>
                            </strong>
                        </td>

                        <td style="text-align: center; font-size: 0.8125rem;">
                            <strong><?= $availStock ?></strong> / <small style="color: #6c757d;"><?= $totalStock ?></small>
                            <?php if ($totalStock <= 5): ?>
                                <span class="badge-admin badge-warning" style="font-size: 0.625rem; padding: 1px 4px; margin-left: 2px;">Ít</span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align: center;">
                            <?php if ((int)$p['is_active'] === 1): ?>
                                <span class="badge-admin badge-success" title="Đang mở bán" style="font-size: 0.8rem; padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">✓</span>
                            <?php else: ?>
                                <span class="badge-admin badge-secondary" title="Ngừng kinh doanh" style="font-size: 0.8rem; padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">✕</span>
                            <?php endif; ?>
                        </td>

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

    <div class="card-admin-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: rgba(0,0,0,0.02); border-top: 1px solid var(--admin-border-color); padding: 10px 16px;">
        <div style="font-size: 0.8125rem; color: #6c757d; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <span>
                Hiển thị từ <strong><?= $totalProducts > 0 ? ($offset + 1) : 0 ?></strong> đến <strong><?= min($offset + $perPage, $totalProducts) ?></strong> trong tổng số <strong><?= $totalProducts ?></strong> sản phẩm
            </span>
            <div style="display: inline-flex; align-items: center; gap: 6px;">
                <label for="perPageSelect" style="margin: 0; font-size: 0.75rem; color: #6c757d;">Số dòng:</label>
                <select id="perPageSelect" onchange="location.href=this.value;" style="height: 28px; padding: 2px 6px; font-size: 0.75rem; border-radius: 4px; border: 1px solid var(--admin-border-color); background: var(--admin-card-bg); color: inherit;">
                    <?php foreach ([10, 15, 25, 50, 100] as $opt): ?>
                        <?php 
                            $queryArgs = ['search' => $search, 'category' => $category, 'per_page' => $opt, 'page' => 1];
                            $optUrl = '/admin/products.php?' . http_build_query(array_filter($queryArgs, fn($v) => $v !== '' && $v !== null));
                        ?>
                        <option value="<?= e($optUrl) ?>" <?= $perPage === $opt ? 'selected' : '' ?>>
                            <?= $opt ?> / trang
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <?php
                $pageUrl = function(int $p) use ($search, $category, $perPage): string {
                    $args = ['search' => $search, 'category' => $category, 'per_page' => $perPage !== 15 ? $perPage : null, 'page' => $p];
                    return '/admin/products.php?' . http_build_query(array_filter($args, fn($v) => $v !== '' && $v !== null));
                };

                $range = 2;
                $startPage = max(1, $page - $range);
                $endPage = min($totalPages, $page + $range);
            ?>
            <ul class="pagination-admin">
                <?php if ($page > 1): ?>
                    <li><a href="<?= e($pageUrl(1)) ?>" title="Trang đầu">«</a></li>
                    <li><a href="<?= e($pageUrl($page - 1)) ?>" title="Trang trước">‹</a></li>
                <?php else: ?>
                    <li class="disabled"><span>«</span></li>
                    <li class="disabled"><span>‹</span></li>
                <?php endif; ?>

                <?php if ($startPage > 1): ?>
                    <li><a href="<?= e($pageUrl(1)) ?>">1</a></li>
                    <?php if ($startPage > 2): ?>
                        <li class="disabled"><span>…</span></li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i === $page): ?>
                        <li class="active"><span><?= $i ?></span></li>
                    <?php else: ?>
                        <li><a href="<?= e($pageUrl($i)) ?>"><?= $i ?></a></li>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="disabled"><span>…</span></li>
                    <?php endif; ?>
                    <li><a href="<?= e($pageUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <li><a href="<?= e($pageUrl($page + 1)) ?>" title="Trang sau">›</a></li>
                    <li><a href="<?= e($pageUrl($totalPages)) ?>" title="Trang cuối">»</a></li>
                <?php else: ?>
                    <li class="disabled"><span>›</span></li>
                    <li class="disabled"><span>»</span></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
