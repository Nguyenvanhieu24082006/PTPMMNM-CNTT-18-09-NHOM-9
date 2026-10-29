<?php

declare(strict_types=1);

/**
 * Xử lý dữ liệu đầu vào, phòng chống XSS và định dạng hiển thị
 */
function field(array $input, string $key, string $default = ''): string
{
    $value = $input[$key] ?? $default;
    return is_string($value) ? trim($value) : (string)$value;
}

function fieldInt(array $input, string $key, int $default = 0): int
{
    $val = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
    return $val !== false ? $val : $default;
}

function fieldFloat(array $input, string $key, float $default = 0.0): float
{
    $val = filter_var($input[$key] ?? null, FILTER_VALIDATE_FLOAT);
    return $val !== false ? (float)$val : $default;
}

function e(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatMoney(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

function formatCurrency(float $amount): string
{
    return formatMoney($amount);
}

function productImageUrl(?string $url): string
{
    if (empty($url)) {
        return '/assets/images/fallback.svg';
    }
    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) {
        return $url;
    }
    $vipPath = __DIR__ . '/../public/assets/images/vip/' . $url;
    if (is_file($vipPath)) {
        return '/assets/images/vip/' . $url;
    }
    $prodPath = __DIR__ . '/../public/assets/images/products/' . $url;
    if (is_file($prodPath)) {
        return '/assets/images/products/' . $url;
    }
    return '/assets/images/vip/' . $url;
}

function formatDate(string $timestamp): string
{
    if (!$timestamp) return '';
    $time = strtotime($timestamp);
    return date('d/m/Y H:i:s', $time);
}

function getStatusBadge(string $status): string
{
    $map = [
        'PENDING' => ['badge--warning', 'Chờ thanh toán / Giữ hàng'],
        'PAID' => ['badge--info', 'Đã thanh toán'],
        'PROCESSING' => ['badge--info', 'Đang xử lý'],
        'SHIPPED' => ['badge--primary', 'Đang giao hàng'],
        'DELIVERED' => ['badge--success', 'Đã giao thành công'],
        'CANCELLED' => ['badge--danger', 'Đã hủy'],
        'EXPIRED' => ['badge--danger', 'Hết hạn giữ hàng'],
        'PARTIALLY_RETURNED' => ['badge--warning', 'Đổi trả một phần'],
        'RETURNED' => ['badge--secondary', 'Đã trả toàn bộ'],
        'REQUESTED' => ['badge--warning', 'Chờ thẩm định'],
        'APPROVED' => ['badge--info', 'Đã duyệt'],
        'COMPLETED' => ['badge--success', 'Hoàn tất'],
        'REJECTED' => ['badge--danger', 'Đã từ chối']
    ];
    $info = $map[$status] ?? ['badge--secondary', $status];
    return sprintf('<span class="status-badge %s">%s</span>', $info[0], e($info[1]));
}
