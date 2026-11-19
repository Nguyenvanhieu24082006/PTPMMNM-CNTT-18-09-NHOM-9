<?php
require_once __DIR__ . '/../src/Database.php';

$db = db();

echo "=== CẬP NHẬT 20 SẢN PHẨM PHỤ KIỆN Ô TÔ VÀO CSDL MYSQL ===\n";

$products = [
    1 => [
        'name' => 'Camera Hành Trình 70mai A500S Pro Plus 4K HDR',
        'category' => 'Điện tử & Camera',
        'desc' => 'Ghi hình 4K siêu nét trước sau, cảm biến Sony IMX335, hỗ trợ GPS và cảnh báo ADAS thông minh, kết nối WiFi điện thoại xem lại trực tiếp.',
        'image' => 'product_01.jpg',
        'variants' => [
            1 => ['sku' => 'SKU-CAM-01', 'attrs' => ['Phân loại' => 'Thẻ 32GB Chuyên Dụng', 'Gói' => 'Bản Cam Trước'], 'price' => 1650000.00, 'stock' => 35],
            2 => ['sku' => 'SKU-CAM-02', 'attrs' => ['Phân loại' => 'Thẻ 64GB High Speed', 'Gói' => 'Full Combo Trước & Sau RC06'], 'price' => 2190000.00, 'stock' => 28],
            3 => ['sku' => 'SKU-CAM-03', 'attrs' => ['Phân loại' => 'Thẻ 128GB Extreme', 'Gói' => 'Full Combo Kèm Dây Đỗ Xe 24/24'], 'price' => 2450000.00, 'stock' => 15],
            4 => ['sku' => 'SKU-CAM-04', 'attrs' => ['Phân loại' => 'Thẻ 32GB Chuyên Dụng', 'Gói' => 'Bản Cam Đơn'], 'price' => 1650000.00, 'stock' => 1],
        ]
    ],
    2 => [
        'name' => 'Cảm Biến Áp Suất Lốp TPMS Năng Lượng Mặt Trời Steelmate',
        'category' => 'Điện tử & Camera',
        'desc' => 'Hiển thị chính xác áp suất & nhiệt độ 4 lốp xe, cảnh báo bằng âm thanh và màn hình LCD màu sắc nét, sạc kép năng lượng mặt trời và cổng MicroUSB.',
        'image' => 'product_02.jpg',
        'variants' => [
            5 => ['sku' => 'SKU-TPMS-01', 'attrs' => ['Loại van' => 'Van Gắn Trong (Chống trộm, độ bền 5 năm)', 'Màu sắc' => 'Đen Nhám'], 'price' => 1250000.00, 'stock' => 40],
            6 => ['sku' => 'SKU-TPMS-02', 'attrs' => ['Loại van' => 'Van Gắn Ngoài (Tự lắp dễ dàng trong 5 phút)', 'Màu sắc' => 'Đen Nhám'], 'price' => 950000.00, 'stock' => 50],
        ]
    ],
    3 => [
        'name' => 'Màn Hình Android Ô Tô 9 Inch Bravigo Air 2 IPS 4G',
        'category' => 'Điện tử & Camera',
        'desc' => 'Độ phân giải 1280x720 cảm ứng điện dung, tích hợp bản đồ Vietmap S1 bản quyền trọn đời, hỗ trợ SIM 4G phát WiFi và điều khiển giọng nói Kiki.',
        'image' => 'product_03.jpg',
        'variants' => [
            7 => ['sku' => 'SKU-ANDR-01', 'attrs' => ['Cấu hình' => 'RAM 3GB - ROM 32GB', 'Dòng xe' => 'Dưỡng Zin Theo Xe'], 'price' => 5500000.00, 'stock' => 12],
            8 => ['sku' => 'SKU-ANDR-02', 'attrs' => ['Cấu hình' => 'RAM 4GB - ROM 64GB Kèm Cam 360', 'Dòng xe' => 'Dưỡng Zin Theo Xe'], 'price' => 8200000.00, 'stock' => 8],
        ]
    ],
    4 => [
        'name' => 'Bơm Lốp Điện Tử Mini Tự Ngắt Michelin 12266 Kỹ Thuật Số',
        'category' => 'Điện tử & Camera',
        'desc' => 'Công suất mạnh mẽ bơm đầy lốp trong 3 phút, cài đặt trước áp suất tự ngắt chống nổ lốp, đèn LED chiếu sáng cứu hộ ban đêm.',
        'image' => 'product_04.jpg',
        'variants' => [
            9 => ['sku' => 'SKU-PUMP-01', 'attrs' => ['Nguồn điện' => 'Tẩu Sạc 12V Ô Tô Dây Dài 3.5m', 'Phiên bản' => 'Michelin Chính Hãng'], 'price' => 890000.00, 'stock' => 30],
            10 => ['sku' => 'SKU-PUMP-02', 'attrs' => ['Nguồn điện' => 'Pin Sạc Không Dây 6000mAh Đa Năng', 'Phiên bản' => 'Pro Không Dây'], 'price' => 1190000.00, 'stock' => 25],
        ]
    ],
    5 => [
        'name' => 'Bọc Vô Lăng Da Bò Cao Cấp Chống Trượt UltraGrip Sport',
        'category' => 'Nội thất & Tiện nghi',
        'desc' => 'Chất liệu da Nappa kết hợp sợi Carbon cao cấp, đục lỗ thoáng khí thấm hút mồ hôi tay, ôm khít vô lăng chuẩn kích thước 38cm cho mọi dòng xe.',
        'image' => 'product_05.jpg',
        'variants' => [
            11 => ['sku' => 'SKU-STEER-01', 'attrs' => ['Màu sắc' => 'Đen Chỉ Đỏ Sport', 'Kích cỡ' => 'Size M (37-38cm)'], 'price' => 280000.00, 'stock' => 60],
            12 => ['sku' => 'SKU-STEER-02', 'attrs' => ['Màu sắc' => 'Đen Vân Carbon Sang Trọng', 'Kích cỡ' => 'Size M (37-38cm)'], 'price' => 320000.00, 'stock' => 55],
        ]
    ],
    6 => [
        'name' => 'Bộ Gối Tựa Đầu & Tựa Lưng Cao Su Non Hoạt Tính ErgoCar',
        'category' => 'Nội thất & Tiện nghi',
        'desc' => 'Thiết kế công thái học nâng đỡ đốt sống cổ và cột sống thắt lưng, ruột cao su non nguyên khối đàn hồi chậm, vỏ vải dệt kim 3D tháo giặt dễ dàng.',
        'image' => 'product_06.jpg',
        'variants' => [
            13 => ['sku' => 'SKU-PILL-01', 'attrs' => ['Màu sắc' => 'Đen Nhám Chỉ Trắng', 'Bộ' => 'Full Combo (Gối Đầu + Tựa Lưng)'], 'price' => 390000.00, 'stock' => 45],
            14 => ['sku' => 'SKU-PILL-02', 'attrs' => ['Màu sắc' => 'Kem Be Thanh Lịch', 'Bộ' => 'Full Combo (Gối Đầu + Tựa Lưng)'], 'price' => 390000.00, 'stock' => 30],
        ]
    ],
    7 => [
        'name' => 'Thảm Lót Sàn Ô Tô 6D Carbon Tràn Viền Cao Cấp May Chuẩn Phom',
        'category' => 'Nội thất & Tiện nghi',
        'desc' => 'Chống nước, chống mùi ẩm mốc, may chuẩn theo từng form xe (Vios, Accent, CX5, CRV, Ranger, SantaFe), lớp rối chống trượt dễ dàng tháo rời vệ sinh.',
        'image' => 'product_07.jpg',
        'variants' => [
            15 => ['sku' => 'SKU-MAT-01', 'attrs' => ['Dòng xe' => 'Xe 4 - 5 Chỗ (Sedan / Hatchback)', 'Màu sắc' => 'Carbon Đen Chỉ Đỏ'], 'price' => 1350000.00, 'stock' => 20],
            16 => ['sku' => 'SKU-MAT-02', 'attrs' => ['Dòng xe' => 'Xe 7 Chỗ (SUV / CUV / MPV)', 'Màu sắc' => 'Carbon Nâu Cà Phê'], 'price' => 1750000.00, 'stock' => 18],
        ]
    ],
    8 => [
        'name' => 'Đệm Hơi Ô Tô Du Lịch Cao Cấp Phủ Nhung Gấp Gọn Kèm Bơm',
        'category' => 'Nội thất & Tiện nghi',
        'desc' => 'Biến hàng ghế sau thành giường ngủ êm ái khi đi xa hoặc dã ngoại, chất liệu PVC phủ nhung dày dặn chịu tải 300kg, kèm bơm điện 12V bơm căng trong 2 phút.',
        'image' => 'product_08.jpg',
        'variants' => [
            17 => ['sku' => 'SKU-BED-01', 'attrs' => ['Màu sắc' => 'Xám Bạc Phủ Nhung', 'Phụ kiện' => 'Kèm 2 Gối + Bơm Điện 12V'], 'price' => 320000.00, 'stock' => 35],
            18 => ['sku' => 'SKU-BED-02', 'attrs' => ['Màu sắc' => 'Đen Tuyền Cao Cấp', 'Phụ kiện' => 'Kèm 2 Gối + Bơm Điện 12V'], 'price' => 320000.00, 'stock' => 40],
        ]
    ],
    9 => [
        'name' => 'Bạt Phủ Ô Tô Tráng Bạc 3 Lớp Chống Nắng Nóng & Mưa Axit',
        'category' => 'Ngoại thất & Chăm sóc',
        'desc' => 'Bề mặt tráng nhôm cách nhiệt phản xạ 98% tia UV, lớp bông ép bên trong chống xước sơn xe, dải phản quang an toàn ban đêm và khóa số chống trộm bạt.',
        'image' => 'product_09.jpg',
        'variants' => [
            19 => ['sku' => 'SKU-COV-01', 'attrs' => ['Kích cỡ' => 'Size 2M / 3M (Sedan 4-5 Chỗ)', 'Chất liệu' => 'Tráng Nhôm 3 Lớp Ép Bông'], 'price' => 380000.00, 'stock' => 50],
            20 => ['sku' => 'SKU-COV-02', 'attrs' => ['Kích cỡ' => 'Size 2XL / YXL (SUV / CUV 5-7 Chỗ)', 'Chất liệu' => 'Tráng Nhôm 3 Lớp Ép Bông'], 'price' => 430000.00, 'stock' => 45],
        ]
    ],
    10 => [
        'name' => 'Cặp Gạt Mưa Silicon Thân Mềm Cao Cấp Bosch AeroTwin',
        'category' => 'Ngoại thất & Chăm sóc',
        'desc' => 'Lưỡi gạt silicon phủ nano carbon gạt êm không tiếng ồn, quét sạch nước mưa mang lại tầm nhìn trong suốt, thiết kế khí động học bám khít kính chắn gió.',
        'image' => 'product_10.jpg',
        'variants' => [
            21 => ['sku' => 'SKU-WIP-01', 'attrs' => ['Kích thước' => 'Cặp 14 - 24 Inch (Phổ Thông)', 'Chân cài' => 'Móc Chữ U Đa Năng'], 'price' => 290000.00, 'stock' => 60],
            22 => ['sku' => 'SKU-WIP-02', 'attrs' => ['Kích thước' => 'Cặp 16 - 26 Inch (Xe Lớn / SUV)', 'Chân cài' => 'Móc Chữ U Đa Năng'], 'price' => 340000.00, 'stock' => 40],
        ]
    ],
    11 => [
        'name' => 'Máy Hút Bụi Cầm Tay Ô Tô Không Dây Lực Hút 12000Pa Baseus A3',
        'category' => 'Ngoại thất & Chăm sóc',
        'desc' => 'Động cơ không chổi than tốc độ 80.000 vòng/phút hút sạch bụi mịn và sỏi vụn kẽ ghế, màng lọc HEPA rửa nước tái sử dụng, pin sạc Type-C dùng 45 phút.',
        'image' => 'product_11.jpg',
        'variants' => [
            23 => ['sku' => 'SKU-VAC-01', 'attrs' => ['Màu sắc' => 'Xám Titan Kim Loại', 'Phụ kiện' => '3 Đầu Hút + Đèn LED Soi'], 'price' => 690000.00, 'stock' => 25],
            24 => ['sku' => 'SKU-VAC-02', 'attrs' => ['Màu sắc' => 'Đen Nhám Sport', 'Phụ kiện' => '3 Đầu Hút + Đèn LED Soi'], 'price' => 690000.00, 'stock' => 30],
        ]
    ],
    12 => [
        'name' => 'Bộ Khăn Lau Sợi Microfiber Chuyên Dụng Chăm Sóc Xe (Set 4 Chiếc)',
        'category' => 'Ngoại thất & Chăm sóc',
        'desc' => 'Sợi san hô siêu mềm mịn mật độ 800GSM không rụng lông không để lại vệt ố, siêu thấm hút gấp 8 lần trọng lượng, chuyên dùng rửa xe và lau nội thất da.',
        'image' => 'product_12.jpg',
        'variants' => [
            25 => ['sku' => 'SKU-TOW-01', 'attrs' => ['Kích thước' => '30x60cm Dày 600GSM (Set 4 Chiếc)', 'Màu sắc' => 'Vàng Xám 2 Mặt'], 'price' => 95000.00, 'stock' => 100],
            26 => ['sku' => 'SKU-TOW-02', 'attrs' => ['Kích thước' => '40x80cm Cực Đại 800GSM (Set 4 Chiếc)', 'Màu sắc' => 'Vàng Xám 2 Mặt'], 'price' => 140000.00, 'stock' => 80],
        ]
    ],
    13 => [
        'name' => 'Bộ Kích Bình Ắc Quy Ô Tô Kiêm Sạc Dự Phòng 20000mAh Baseus Super Energy',
        'category' => 'Cứu hộ & An toàn',
        'desc' => 'Dòng khởi động cực đại 1000A kích nổ xe tức thì khi hết bình ắc quy giữa đường, tích hợp đèn pin SOS cứu hộ 3 chế độ, sạc nhanh điện thoại 2 cổng USB.',
        'image' => 'product_13.jpg',
        'variants' => [
            27 => ['sku' => 'SKU-JUMP-01', 'attrs' => ['Công suất' => '1000A (Xe Xăng < 4.0L)', 'Màu sắc' => 'Đen Bọc Thép'], 'price' => 990000.00, 'stock' => 20],
            28 => ['sku' => 'SKU-JUMP-02', 'attrs' => ['Công suất' => '2000A Cường Lực (Xe Dầu Diesel & Xe Tải)', 'Màu sắc' => 'Đen Bọc Thép'], 'price' => 1350000.00, 'stock' => 15],
        ]
    ],
    14 => [
        'name' => 'Búa Thoát Hiểm Khẩn Cấp Ô Tô Kèm Dao Cắt Dây An Toàn Baseus',
        'category' => 'Cứu hộ & An toàn',
        'desc' => 'Đầu búa thép Tungsten tôi cứng độ cứng Rockwell 55 đập vỡ kính cường lực dưới nước chỉ trong 1 giây, lưỡi dao thép sắc bén cắt dây đai kẹt khi xảy ra va chạm.',
        'image' => 'product_14.jpg',
        'variants' => [
            29 => ['sku' => 'SKU-HAM-01', 'attrs' => ['Màu sắc' => 'Đỏ Cảnh Báo An Toàn', 'Vật liệu' => 'Hợp Kim Nhôm CNC'], 'price' => 1650000.00, 'stock' => 50],
            30 => ['sku' => 'SKU-HAM-02', 'attrs' => ['Màu sắc' => 'Xám Titan Sang Trọng', 'Vật liệu' => 'Hợp Kim Nhôm CNC'], 'price' => 1650000.00, 'stock' => 45],
        ]
    ],
    15 => [
        'name' => 'Bình Cứu Hỏa Mini Ô Tô Foam Bọt Chữa Cháy 1000ml FireStop Kèm Giá Đỡ',
        'category' => 'Cứu hộ & An toàn',
        'desc' => 'Dung dịch bọt Foam an toàn không gây hại thiết bị điện tử nội thất xe, dập tắt nhanh đám cháy xăng dầu và chập điện, kèm giá đỡ gắn sườn ghế hoặc cốp sau.',
        'image' => 'product_15.jpg',
        'variants' => [
            31 => ['sku' => 'SKU-FIRE-01', 'attrs' => ['Dung tích' => 'Bình Đơn 1000ml Kèm Khung Gắn', 'Chứng nhận' => 'Tiêu Chuẩn PCCC'], 'price' => 75000.00, 'stock' => 80],
            32 => ['sku' => 'SKU-FIRE-02', 'attrs' => ['Dung tích' => 'Combo 2 Bình (Khoang Lái + Cốp Sau)', 'Chứng nhận' => 'Tiêu Chuẩn PCCC'], 'price' => 135000.00, 'stock' => 60],
        ]
    ],
    16 => [
        'name' => 'Dây Câu Bình Ắc Quy Ô Tô Lõi Đồng Nguyên Chất 4 Mét 2000A Chịu Tải',
        'category' => 'Cứu hộ & An toàn',
        'desc' => 'Lõi đồng nhiều sợi dẫn dòng điện khỏe không bị nóng dây, kẹp bọc cao su cách điện dày dặn chịu lực kẹp mạnh mẽ, túi đựng tròn chống rối tiện cất cốp xe.',
        'image' => 'product_16.jpg',
        'variants' => [
            33 => ['sku' => 'SKU-CABLE-01', 'attrs' => ['Chiều dài' => '3 Mét Dòng Tải 1500A (Xe 4-7 Chỗ)', 'Túi đựng' => 'Kèm Túi Tròn Khóa Kéo'], 'price' => 170000.00, 'stock' => 40],
            34 => ['sku' => 'SKU-CABLE-02', 'attrs' => ['Chiều dài' => '4 Mét Dòng Tải 2500A (Xe Tải & Bán Tải)', 'Túi đựng' => 'Kèm Túi Tròn Khóa Kéo'], 'price' => 230000.00, 'stock' => 35],
        ]
    ],
    17 => [
        'name' => 'Tẩu Sạc Nhanh Ô Tô 66W 3 Cổng Type-C & USB Baseus Magic Display',
        'category' => 'Tiện ích & Khử mùi',
        'desc' => 'Hỗ trợ đồng thời chuẩn sạc nhanh PD 3.0 và QC 4.0+, màn hình LED hiển thị điện áp bình ắc quy xe theo thời gian thực giúp chủ xe giám sát nguồn điện.',
        'image' => 'product_17.jpg',
        'variants' => [
            35 => ['sku' => 'SKU-CHARG-01', 'attrs' => ['Màu sắc' => 'Đen Mờ Nhám', 'Công suất' => '66W Dual Fast Charge'], 'price' => 180000.00, 'stock' => 70],
            36 => ['sku' => 'SKU-CHARG-02', 'attrs' => ['Màu sắc' => 'Bạc Trong Suốt Cyber', 'Công suất' => '100W Super Charge'], 'price' => 260000.00, 'stock' => 50],
        ]
    ],
    18 => [
        'name' => 'Giá Đỡ Điện Thoại Ô Tô Sạc Không Dây MagSafe 15W Hút Nam Châm Cực Mạnh',
        'category' => 'Tiện ích & Khử mùi',
        'desc' => 'Lực hút nam châm Neodymium 16 thỏi giữ chắc điện thoại khi qua gờ giảm tốc, xoay 360 độ linh hoạt theo góc nhìn lái xe, tương thích iPhone và các dòng máy sạc không dây.',
        'image' => 'product_18.jpg',
        'variants' => [
            37 => ['sku' => 'SKU-HOLD-01', 'attrs' => ['Chân đế' => 'Kẹp Cửa Gió Điều Hòa Siêu Chắc', 'Màu sắc' => 'Đen Carbon'], 'price' => 290000.00, 'stock' => 45],
            38 => ['sku' => 'SKU-HOLD-02', 'attrs' => ['Chân đế' => 'Đế Hút Chân Không Bảng Tablo & Kính', 'Màu sắc' => 'Đen Carbon'], 'price' => 350000.00, 'stock' => 35],
        ]
    ],
    19 => [
        'name' => 'Nước Hoa Ô Tô Năng Lượng Mặt Trời Xoay 360 Độ Cao Cấp Tinh Dầu Pháp',
        'category' => 'Tiện ích & Khử mùi',
        'desc' => 'Tự động xoay tỏa hương khi có ánh sáng mặt trời chiếu vào, chiết xuất 100% thảo mộc tự nhiên an toàn cho bà bầu và trẻ nhỏ, khử triệt để mùi xe mới và thuốc lá.',
        'image' => 'product_19.jpg',
        'variants' => [
            39 => ['sku' => 'SKU-PERF-01', 'attrs' => ['Mùi hương' => 'Gỗ Đàn Hương Cổ Điển Sang Trọng', 'Màu máy' => 'Xanh Đậm Phi Hành'], 'price' => 195000.00, 'stock' => 50],
            40 => ['sku' => 'SKU-PERF-02', 'attrs' => ['Mùi hương' => 'Đại Dương Tươi Mát Thanh Lọc', 'Màu máy' => 'Đen Kim Loại'], 'price' => 195000.00, 'stock' => 40],
        ]
    ],
    20 => [
        'name' => 'Máy Lọc Không Khí Ô Tô Diệt Khuẩn Ion Âm Philips GoPure Cao Cấp',
        'category' => 'Tiện ích & Khử mùi',
        'desc' => 'Lọc sạch 99% bụi mịn PM2.5, khí độc Formaldehyde và vi khuẩn trong khoang xe chỉ trong 10 phút, cảm biến chất lượng không khí hiển thị đèn LED 3 màu thông minh.',
        'image' => 'product_20.jpg',
        'variants' => [
            41 => ['sku' => 'SKU-AIR-01', 'attrs' => ['Màu sắc' => 'Đen Bóng Piano', 'Công nghệ' => 'Màng Lọc HEPA Plus + Ion Âm'], 'price' => 1890000.00, 'stock' => 20],
            42 => ['sku' => 'SKU-AIR-02', 'attrs' => ['Màu sắc' => 'Xám Titan Bản Quốc Tế', 'Công nghệ' => 'Màng Lọc HEPA Plus + Ion Âm'], 'price' => 2190000.00, 'stock' => 15],
        ]
    ]
];

$db->begin_transaction();
try {
    foreach ($products as $id => $p) {
        // Cập nhật sản phẩm
        queryParams(
            $db,
            "UPDATE products SET name = ?, category = ?, description = ?, image_url = ?, is_active = 1 WHERE id = ?",
            [$p['name'], $p['category'], $p['desc'], $p['image'], $id]
        );

        // Cập nhật tên trong order_items tương ứng nếu có
        queryParams(
            $db,
            "UPDATE order_items oi 
             JOIN variants v ON oi.variant_id = v.id 
             SET oi.product_name = ? 
             WHERE v.product_id = ?",
            [$p['name'], $id]
        );

        // Cập nhật từng biến thể
        foreach ($p['variants'] as $vId => $v) {
            $attrsJson = json_encode($v['attrs'], JSON_UNESCAPED_UNICODE);
            queryParams(
                $db,
                "UPDATE variants SET sku = ?, attributes = ?, price = ?, stock_quantity = ? WHERE id = ?",
                [$v['sku'], $attrsJson, $v['price'], $v['stock'], $vId]
            );
        }
        echo "  [OK] Cập nhật xong sản phẩm $id: {$p['name']}\n";
    }

    $db->commit();
    echo "\n=== CẬP NHẬT CSDL THÀNH CÔNG RỰC RỠ! ===\n";
} catch (Throwable $e) {
    $db->rollback();
    echo "LỖI: " . $e->getMessage() . "\n";
    exit(1);
}

