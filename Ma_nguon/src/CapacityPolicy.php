<?php

declare(strict_types=1);

final class CapacityPolicy
{
    public function canRegister(int $capacity, int $registered): bool
    {
        // Sức chứa phải > 0 và số đã đăng ký không được âm
        if ($capacity <= 0 || $registered < 0) {
            return false;
        }

        // Còn chỗ khi số đã đăng ký < sức chứa
        return $registered < $capacity;
    }
}