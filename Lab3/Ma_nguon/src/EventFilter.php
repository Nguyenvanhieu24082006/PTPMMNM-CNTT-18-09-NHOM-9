<?php
declare(strict_types=1);

/**
 * Lọc danh sách sự kiện theo từ khóa, loại sự kiện và tình trạng chỗ trống.
 * Các điều kiện được kết hợp bằng AND. Hàm không thay đổi mảng đầu vào.
 *
 * @param array<int, array<string, mixed>> $events Danh sách sự kiện
 * @param string $query Từ khóa tìm trong tiêu đề (không phân biệt hoa/thường)
 * @param string $category Loại sự kiện; chuỗi rỗng nghĩa là tất cả loại
 * @param bool $availableOnly Chỉ lấy sự kiện còn chỗ nếu true
 * @return array<int, array<string, mixed>> Danh sách sự kiện phù hợp
 */
function filterEvents(
    array $events,
    string $query,
    string $category = '',
    bool $availableOnly = false
): array {
    $result = [];
    $query = trim($query);
    $category = trim($category);

    foreach ($events as $event) {
        // Bỏ qua dữ liệu không có cấu trúc tối thiểu cần thiết.
        if (!isset($event['title'], $event['category'], $event['capacity'], $event['registered'])) {
            continue;
        }

        $matchesQuery = $query === ''
            || stripos((string) $event['title'], $query) !== false;
        $matchesCategory = $category === ''
            || (string) $event['category'] === $category;
        $remainingSeats = max(0, (int) $event['capacity'] - (int) $event['registered']);
        $matchesAvailability = !$availableOnly || $remainingSeats > 0;

        if ($matchesQuery && $matchesCategory && $matchesAvailability) {
            $result[] = $event;
        }
    }

    return $result;
}
