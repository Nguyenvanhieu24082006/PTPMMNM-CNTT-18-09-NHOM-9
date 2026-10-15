<?php

declare(strict_types=1);

final class RegistrationPolicy
{
    /**
     * Trả về: 'confirmed', 'waiting', hoặc 'rejected'
     */
    public function decide(int $capacity, int $registered, bool $isOpen, bool $hasWaitlist): string
    {
        // 1. Kiểm tra tính hợp lệ của dữ liệu đầu vào
        if ($capacity <= 0 || $registered < 0 || $registered > $capacity) {
            return 'rejected';
        }

        // 2. Kiểm tra trạng thái đóng/mở sự kiện
        if (!$isOpen) {
            return 'rejected';
        }

        // 3. Sự kiện mở và còn chỗ
        if ($registered < $capacity) {
            return 'confirmed';
        }

        // 4. Sự kiện mở, hết chỗ (registered == capacity)
        if ($hasWaitlist) {
            return 'waiting';
        }

        return 'rejected';
    }
}