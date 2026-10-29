<?php
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/AuditLogService.php';
require_once __DIR__ . '/../src/Session.php';
require_once __DIR__ . '/../src/Input.php';

requireRole(['admin', 'manager']);

$db = db();
$auditService = new AuditLogService($db);
$logs = $auditService->getRecentLogs(100);

require __DIR__ . '/../templates/admin_header.php';
?>

<!-- BẢNG NHẬT KÝ KIỂM TOÁN (CARD ADMINLTE 3) -->
<div class="card-admin">
    <div class="card-admin-header">
        <h3 class="card-admin-title">
            <span>📜</span> Vết kiểm toán & Biến động hệ thống (Audit Trail)
        </h3>
        <div class="card-admin-tools">
            <span class="badge-admin badge-success">100 bản ghi gần nhất</span>
        </div>
    </div>
    <div class="card-admin-body" style="padding: 0;">
        <table class="table-admin table-striped table-hover" style="margin: 0;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Thời gian</th>
                    <th>Mã đơn</th>
                    <th>Tác nhân</th>
                    <th>Hành động</th>
                    <th>Chuyển trạng thái</th>
                    <th>Biến động tiền</th>
                    <th>Ghi chú giải trình</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #6c757d;">
                            Chưa có nhật ký kiểm toán nào được ghi nhận.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td style="font-size: 0.8125rem; color: #6c757d;">#<?= (int)$l['id'] ?></td>
                        <td style="font-size: 0.8125rem; color: #6c757d;"><?= formatDate($l['created_at']) ?></td>
                        <td><strong style="color: #007bff;"><?= e($l['order_code']) ?></strong></td>
                        <td>
                            <strong><?= e($l['actor_role']) ?></strong>
                            <?= !empty($l['actor_name']) ? '<br><small style="color: #6c757d;">' . e($l['actor_name']) . '</small>' : '' ?>
                        </td>
                        <td><span class="badge-admin badge-info"><?= e($l['action']) ?></span></td>
                        <td>
                            <?php if ($l['old_state'] || $l['new_state']): ?>
                                <span style="color: #6c757d;"><?= e($l['old_state'] ?? 'NONE') ?></span> ➔ <strong style="color: #28a745;"><?= e($l['new_state'] ?? '') ?></strong>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((float)$l['amount_change'] != 0): ?>
                                <span style="font-weight: 700; color: <?= (float)$l['amount_change'] > 0 ? '#28a745' : '#dc3545' ?>;">
                                    <?= (float)$l['amount_change'] > 0 ? '+' : '' ?><?= formatMoney((float)$l['amount_change']) ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 0.8125rem; color: #495057;"><?= e($l['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../templates/admin_footer.php'; ?>
