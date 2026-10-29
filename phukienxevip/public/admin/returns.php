<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/ReturnService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);
$user = currentUser();

$db = db();
$returnService = new ReturnService($db);

// Xử lý phê duyệt hoặc từ chối đổi trả
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf($_POST);
    $action = field($_POST, 'action');
    $returnId = fieldInt($_POST, 'return_id');
    $note = field($_POST, 'inspector_note');

    if ($action === 'approve') {
        $restock = isset($_POST['restock_inventory']) && $_POST['restock_inventory'] === '1';
        try {
            $returnService->approveReturn($returnId, (int)$user['id'], $note, $restock);
            setFlash('success', "Đã phê duyệt yêu cầu đổi trả ID $returnId thành công.");
        } catch (Throwable $e) {
            setFlash('danger', 'Lỗi duyệt đổi trả: ' . $e->getMessage());
        }
    } elseif ($action === 'reject') {
        try {
            $returnService->rejectReturn($returnId, (int)$user['id'], $note);
            setFlash('info', "Đã từ chối yêu cầu đổi trả ID $returnId.");
        } catch (Throwable $e) {
            setFlash('danger', 'Lỗi từ chối: ' . $e->getMessage());
        }
    }
    header('Location: /admin/returns.php');
    exit;
}

$statusFilter = field($_GET, 'status');
$returns = $returnService->listReturns($statusFilter !== '' ? ['status' => $statusFilter] : []);

require __DIR__ . '/../templates/admin_header.php';
?>

<!-- BỘ LỌC TRẠNG THÁI ĐỔI TRẢ (CARD ADMINLTE 3) -->
<div class="card-admin" style="margin-bottom: 20px;">
    <div class="card-admin-header">
        <h3 class="card-admin-title">
            <span>🔄</span> Phân loại yêu cầu đổi trả
        </h3>
        <div class="card-admin-tools">
            <span class="badge-admin badge-info">Tổng: <?= count($returns) ?> hồ sơ</span>
        </div>
    </div>
    <div class="card-admin-body" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <span style="font-size: 0.875rem; font-weight: 600; color: #495057;">Trạng thái:</span>
        <a href="/admin/returns.php" class="btn-admin <?= empty($statusFilter) ? 'btn-admin-primary' : 'btn-admin-secondary' ?> btn-admin-sm">
            Tất cả (<?= count($returns) ?>)
        </a>
        <a href="/admin/returns.php?status=REQUESTED" class="btn-admin <?= $statusFilter === 'REQUESTED' ? 'btn-admin-danger' : 'btn-admin-secondary' ?> btn-admin-sm">
            ⏳ Chờ thẩm định (REQUESTED)
        </a>
        <a href="/admin/returns.php?status=COMPLETED" class="btn-admin <?= $statusFilter === 'COMPLETED' ? 'btn-admin-success' : 'btn-admin-secondary' ?> btn-admin-sm">
            ✓ Đã phê duyệt (COMPLETED)
        </a>
        <a href="/admin/returns.php?status=REJECTED" class="btn-admin <?= $statusFilter === 'REJECTED' ? 'btn-admin-secondary' : 'btn-admin-secondary' ?> btn-admin-sm">
            ✗ Đã từ chối (REJECTED)
        </a>
    </div>
</div>

<!-- BẢNG DANH SÁCH YÊU CẦU ĐỔI TRẢ (CARD ADMINLTE 3) -->
<div class="card-admin">
    <div class="card-admin-header">
        <h3 class="card-admin-title">
            <span>📋</span> Danh sách hồ sơ đổi trả hàng
        </h3>
        <div class="card-admin-tools">
            <span style="font-size: 0.8125rem; color: #6c757d;">Thẩm định chất lượng, tính tiền hoàn và cập nhật tồn kho</span>
        </div>
    </div>
    <div class="card-admin-body" style="padding: 0;">
        <table class="table-admin table-striped table-hover" style="margin: 0;">
            <thead>
                <tr>
                    <th>Mã đổi trả</th>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Ngày gửi</th>
                    <th>Lý do của khách</th>
                    <th>Tiền hoàn mô phỏng</th>
                    <th>Trạng thái</th>
                    <th style="text-align: right; min-width: 220px;">Hành động thẩm định</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($returns)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #6c757d;">
                            Không có yêu cầu đổi trả nào theo điều kiện lọc hiện tại.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($returns as $ret): ?>
                    <tr>
                        <td><strong style="color: #007bff;"><?= e($ret['return_code']) ?></strong></td>
                        <td>
                            <strong style="color: #17a2b8;"><?= e($ret['order_code']) ?></strong>
                        </td>
                        <td><?= e($ret['customer_name']) ?></td>
                        <td style="font-size: 0.8125rem; color: #6c757d;"><?= formatDate($ret['created_at']) ?></td>
                        <td><span style="font-style: italic; color: #495057;">"<?= e($ret['reason']) ?>"</span></td>
                        <td><strong style="color: #dc3545; font-size: 0.9375rem;"><?= formatMoney((float)$ret['total_refund_amount']) ?></strong></td>
                        <td><?= getStatusBadge($ret['status']) ?></td>
                        <td style="text-align: right;">
                            <?php if ($ret['status'] === 'REQUESTED'): ?>
                                <div style="display: flex; flex-direction: column; gap: 6px; text-align: left; background: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #dee2e6;">
                                    <!-- Form Duyệt đổi trả -->
                                    <form method="post" style="margin: 0;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="return_id" value="<?= (int)$ret['id'] ?>">
                                        <input name="inspector_note" placeholder="Ghi chú thẩm định..." style="width: 100%; box-sizing: border-box; font-size: 0.8125rem; padding: 4px 8px; border-radius: 4px; border: 1px solid #ced4da; margin-bottom: 6px;" required>
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                            <label style="font-size: 0.75rem; color: #495057; margin: 0; display: flex; align-items: center; gap: 4px;">
                                                <input type="checkbox" name="restock_inventory" value="1" checked> Hoàn kho
                                            </label>
                                            <button type="submit" class="btn-admin btn-admin-success btn-admin-sm">
                                                ✓ Duyệt hoàn tiền
                                            </button>
                                        </div>
                                    </form>

                                    <!-- Form Từ chối -->
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('Bạn có chắc muốn từ chối yêu cầu đổi trả này?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="return_id" value="<?= (int)$ret['id'] ?>">
                                        <input type="hidden" name="inspector_note" value="Hàng không đáp ứng tiêu chuẩn hoàn trả">
                                        <button type="submit" class="btn-admin btn-admin-danger btn-admin-sm" style="width: 100%; padding: 2px 6px;">
                                            ✕ Từ chối
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <small style="color: #6c757d;">
                                    Cán bộ duyệt: <strong><?= e($ret['inspector_name'] ?? 'Hệ thống') ?></strong><br>
                                    Ghi chú: <em><?= e($ret['inspector_note'] ?? '-') ?></em>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
