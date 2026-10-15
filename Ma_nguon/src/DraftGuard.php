<?php

declare(strict_types=1);

function canComplete(array $session, int $eventId, int $now, int $ttl): bool
{
    // Thời gian sống phải > 0
    if ($ttl <= 0) {
        return false;
    }

    // Phải có bản nháp đầy đủ
    if (!isset($session['draft']['data']) || !isset($session['draft']['created_at'])) {
        return false;
    }

    $draft = $session['draft'];

    // Thời điểm tạo không được ở tương lai
    if ($draft['created_at'] > $now) {
        return false;
    }

    // Kiểm tra đúng ID sự kiện
    if (!isset($draft['data']['event_id']) || (int)$draft['data']['event_id'] !== $eventId) {
        return false;
    }

    // Chưa hết hạn (đúng ngưỡng $ttl hoặc vượt quá sẽ trả về false)
    if (($now - $draft['created_at']) >= $ttl) {
        return false;
    }

    return true;
}