-- ==============================================================================
-- ĐỀ TÀI 19: HỆ THỐNG XỬ LÝ ĐƠN HÀNG VÀ QUẢN LÝ ĐỔI TRẢ TRONG BÁN HÀNG TRỰC TUYẾN
-- DATABASE: MySQL >= 8.0 (InnoDB Engine, utf8mb4_unicode_ci)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_audit_logs;
DROP TABLE IF EXISTS return_items;
DROP TABLE IF EXISTS returns;
DROP TABLE IF EXISTS payment_events;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS variants;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. BẢNG NGƯỜI DÙNG (Khách hàng, Nhân viên xử lý đơn, Người xác nhận trả hàng)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    role ENUM('customer', 'staff', 'manager') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BẢNG SẢN PHẨM
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG BIẾN THỂ SẢN PHẨM (Màu sắc, kích cỡ, giá niêm yết, tồn kho thực tế)
CREATE TABLE variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    sku VARCHAR(60) NOT NULL UNIQUE,
    attributes JSON NOT NULL, -- Ví dụ: {"color": "Xanh dương", "size": "L"}
    price DECIMAL(12, 2) NOT NULL CHECK (price >= 0),
    stock_quantity INT NOT NULL DEFAULT 0 CHECK (stock_quantity >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG ĐƠN HÀNG (Vòng đời đơn hàng)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(40) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    status ENUM('PENDING', 'PAID', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED', 'EXPIRED', 'PARTIALLY_RETURNED', 'RETURNED') NOT NULL DEFAULT 'PENDING',
    total_amount DECIMAL(12, 2) NOT NULL CHECK (total_amount >= 0),
    shipping_address VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    expires_at TIMESTAMP NULL, -- Thời hạn hết hạn giữ hàng (Reservation TTL)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BẢNG CHI TIẾT ĐƠN HÀNG (Đóng băng giá / Price Snapshot)
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    variant_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    variant_info VARCHAR(100) NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(12, 2) NOT NULL CHECK (unit_price >= 0), -- GIÁ ĐÃ CHỐT TẠI THỜI ĐIỂM ĐẶT
    subtotal DECIMAL(12, 2) NOT NULL CHECK (subtotal >= 0),
    delivered_quantity INT NOT NULL DEFAULT 0 CHECK (delivered_quantity >= 0),
    returned_quantity INT NOT NULL DEFAULT 0 CHECK (returned_quantity >= 0),
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_items_variant FOREIGN KEY (variant_id) REFERENCES variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BẢNG GIỮ HÀNG CÓ THỜI HẠN (Reservations)
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    variant_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    status ENUM('HOLDING', 'RELEASED', 'CONSUMED') NOT NULL DEFAULT 'HOLDING',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    CONSTRAINT fk_res_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_res_variant FOREIGN KEY (variant_id) REFERENCES variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG THÔNG BÁO THANH TOÁN MÔ PHỎNG (Payment Events - Chống lặp / Idempotency)
CREATE TABLE payment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    transaction_code VARCHAR(100) NOT NULL UNIQUE, -- KHÓA DUY NHẤT CHỐNG XỬ LÝ TRÙNG
    amount DECIMAL(12, 2) NOT NULL,
    status ENUM('SUCCESS', 'FAILED', 'LATE_IGNORED') NOT NULL,
    gateway_name VARCHAR(50) NOT NULL DEFAULT 'MOCK_PAY',
    raw_payload JSON NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BẢNG YÊU CẦU ĐỔI TRẢ (Returns)
CREATE TABLE returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_code VARCHAR(40) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('REQUESTED', 'APPROVED', 'REJECTED', 'COMPLETED') NOT NULL DEFAULT 'REQUESTED',
    reason TEXT NOT NULL,
    total_refund_amount DECIMAL(12, 2) NOT NULL DEFAULT 0 CHECK (total_refund_amount >= 0),
    inspector_id INT NULL,
    inspector_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_returns_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_returns_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_returns_inspector FOREIGN KEY (inspector_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BẢNG CHI TIẾT HÀNG TRẢ (Return Items)
CREATE TABLE return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    order_item_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(12, 2) NOT NULL CHECK (unit_price >= 0), -- GIÁ GỐC LÚC MUA
    subtotal_refund DECIMAL(12, 2) NOT NULL CHECK (subtotal_refund >= 0),
    item_condition VARCHAR(100) NOT NULL DEFAULT 'GOOD',
    CONSTRAINT fk_retitem_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_retitem_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BẢNG NHẬT KÝ ĐỐI SOÁT & VẾT LỊCH SỬ (Audit Logs)
CREATE TABLE order_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    actor_id INT NULL,
    actor_role VARCHAR(30) NOT NULL,
    action VARCHAR(60) NOT NULL,
    old_state VARCHAR(50) NULL,
    new_state VARCHAR(50) NULL,
    quantity_change INT NOT NULL DEFAULT 0,
    amount_change DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CHỈ MỤC TỐI ƯU TRUY VẤN
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_reservations_status ON reservations(status, expires_at);
CREATE INDEX idx_audit_order ON order_audit_logs(order_id);
