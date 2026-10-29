<?php
$images = [
    // 20 sản phẩm phụ kiện ô tô
    1 => 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=600&auto=format&fit=crop&q=80', // Camera hành trình
    2 => 'https://images.unsplash.com/photo-1578844251758-2f71da64c96f?w=600&auto=format&fit=crop&q=80', // Cảm biến áp suất lốp
    3 => 'https://images.unsplash.com/photo-1563720223185-11003d516935?w=600&auto=format&fit=crop&q=80', // Màn hình Android ô tô
    4 => 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?w=600&auto=format&fit=crop&q=80', // Bơm lốp điện tử mini
    5 => 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=600&auto=format&fit=crop&q=80', // Bọc vô lăng da
    6 => 'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=600&auto=format&fit=crop&q=80', // Gối tựa đầu cao su non
    7 => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&auto=format&fit=crop&q=80', // Thảm lót sàn ô tô
    8 => 'https://images.unsplash.com/photo-1517524008697-84bbe3c3fd98?w=600&auto=format&fit=crop&q=80', // Đệm hơi ô tô du lịch
    9 => 'https://images.unsplash.com/photo-1550355291-bbee04a92027?w=600&auto=format&fit=crop&q=80', // Bạt phủ ô tô 3 lớp
    10 => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=600&auto=format&fit=crop&q=80', // Gạt mưa silicon
    11 => 'https://images.unsplash.com/photo-1607860108855-64acf2078ed9?w=600&auto=format&fit=crop&q=80', // Máy hút bụi ô tô
    12 => 'https://images.unsplash.com/photo-1601362840469-51e4d8d58785?w=600&auto=format&fit=crop&q=80', // Khăn lau microfiber
    13 => 'https://images.unsplash.com/photo-1486006920555-c77dce18193b?w=600&auto=format&fit=crop&q=80', // Kích bình ắc quy
    14 => 'https://images.unsplash.com/photo-1580273916550-e323be2ae537?w=600&auto=format&fit=crop&q=80', // Búa thoát hiểm
    15 => 'https://images.unsplash.com/photo-1599837565318-67429bde7162?w=600&auto=format&fit=crop&q=80', // Bình cứu hỏa mini
    16 => 'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=600&auto=format&fit=crop&q=80', // Dây câu bình
    17 => 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?w=600&auto=format&fit=crop&q=80', // Tẩu sạc nhanh
    18 => 'https://images.unsplash.com/photo-1580910051074-3eb694886505?w=600&auto=format&fit=crop&q=80', // Giá đỡ điện thoại MagSafe
    19 => 'https://images.unsplash.com/photo-1592945403244-b3fbafd7f539?w=600&auto=format&fit=crop&q=80', // Nước hoa kẹp cửa gió
    20 => 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?w=600&auto=format&fit=crop&q=80', // Máy lọc không khí
];

$banners = [
    'banner_slide_1.jpg' => 'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=1200&auto=format&fit=crop&q=80',
    'banner_slide_2.jpg' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=1200&auto=format&fit=crop&q=80',
    'banner_slide_3.jpg' => 'https://images.unsplash.com/photo-1563720223185-11003d516935?w=1200&auto=format&fit=crop&q=80',
    'banner_right_top.jpg' => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=600&auto=format&fit=crop&q=80',
    'banner_right_bottom.jpg' => 'https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?w=600&auto=format&fit=crop&q=80'
];

$prodDir = __DIR__ . '/../public/assets/images/products';
$bannerDir = __DIR__ . '/../public/assets/images/banners';

if (!is_dir($prodDir)) mkdir($prodDir, 0777, true);
if (!is_dir($bannerDir)) mkdir($bannerDir, 0777, true);

$ctx = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
    ]
]);

echo "=== TẢI 20 ẢNH PHỤ KIỆN Ô TÔ ===\n";
foreach ($images as $id => $url) {
    $target = sprintf("%s/product_%02d.jpg", $prodDir, $id);
    echo "Tải ảnh sản phẩm $id ... ";
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) > 1000) {
        file_put_contents($target, $data);
        echo "OK (" . round(strlen($data)/1024) . " KB)\n";
    } else {
        echo "FAILED\n";
    }
}

echo "\n=== TẢI 5 BANNER Ô TÔ HERO ===\n";
foreach ($banners as $fname => $url) {
    $target = $bannerDir . '/' . $fname;
    echo "Tải banner $fname ... ";
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) > 1000) {
        file_put_contents($target, $data);
        echo "OK (" . round(strlen($data)/1024) . " KB)\n";
    } else {
        echo "FAILED\n";
    }
}

echo "Hoàn thành tải ảnh phụ kiện xe hơi!\n";

