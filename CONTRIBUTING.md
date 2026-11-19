# HƯỚNG DẪN ĐÓNG GÓP MÃ NGUỒN (CONTRIBUTING GUIDELINES)

Chào mừng bạn đến với dự án **PhuKienXeVIP - Hệ thống Xử lý Đơn hàng và Quản lý Đổi trả trong Bán hàng Trực tuyến** (Đề tài 19 - Học phần Phát triển Phần mềm Mã Nguồn Mở, Đại học Đại Nam).

Tài liệu này quy định các chuẩn mực phát triển, quy trình đóng góp mã nguồn và kiểm thử bắt buộc nhằm đảm bảo chất lượng, tính toàn vẹn dữ liệu và tính minh bạch của dự án.

---

## 1. Nguyên tắc chung

1. **Chuẩn mã nguồn:** Dự án viết bằng PHP >= 8.1 thuần theo kiến trúc Service-Repository. Bắt buộc kích hoạt chế độ nghiêm ngặt:
   ```php
   declare(strict_types=1);
   ```
2. **An toàn cơ sở dữ liệu:** Mọi thao tác truy vấn CSDL phải sử dụng **Prepared Statements** (chống SQL Injection) và nằm trong **ACID Transactions** khi có từ 2 thao tác ghi dữ liệu trở lên.
3. **Bảo mật và Dữ liệu nhạy cảm:**
   - Tuyệt đối **KHÔNG** đưa mật khẩu, khóa bí mật hoặc file cấu hình cục bộ (`.local/`, `.env`) lên Git.
   - Chỉ sử dụng dữ liệu giả lập (mock data) và cấu hình mẫu.

---

## 2. Quy trình làm việc với Git (Git Workflow)

1. **Fork và Clone repository:**
   ```bash
   git clone https://github.com/Nguyenvanhieu24082006/PTPMMNM-CNTT-18-09-NHOM-9.git
   cd PTPMMNM-CNTT-18-09-NHOM-9
   ```

2. **Tạo nhánh làm việc (Branching convention):**
   - Tính năng mới: `feature/<ten-tinh-nang>` (Ví dụ: `feature/order-reservation`)
   - Sửa lỗi: `bugfix/<ten-loi>` (Ví dụ: `bugfix/fix-price-snapshot`)
   - Tài liệu & Kiểm thử: `docs/<noi-dung>` hoặc `test/<noi-dung>`

3. **Quy chuẩn thông điệp Commit (Conventional Commits):**
   Thông điệp commit cần ngắn gọn, rõ ràng bằng tiếng Việt hoặc tiếng Anh:
   - `feat: Thêm tính năng giữ hàng 15 phút`
   - `fix: Sửa lỗi tính tiền hoàn khi đổi trả nhiều lần`
   - `test: Bổ sung ca kiểm thử webhook thanh toán lặp`
   - `docs: Cập nhật hướng dẫn cài đặt trong README`

---

## 3. Quy chuẩn viết mã (Coding Standards)

- Tuân thủ chuẩn định dạng mã nguồn **PSR-12**.
- Tên hàm và biến sử dụng `camelCase` (ví dụ: `$orderService`, `calculateAvailableQuantity()`).
- Tên lớp sử dụng `PascalCase` (ví dụ: `OrderService`, `PaymentService`).
- Tên bảng và cột CSDL sử dụng `snake_case` (ví dụ: `order_items`, `stock_quantity`).
- Xử lý lỗi: Sử dụng ngoại lệ chuẩn (`InvalidArgumentException`, `RuntimeException`), không dùng `die()` hoặc `exit()` trong tầng Service.

---

## 4. Quy trình kiểm thử bắt buộc (Testing Requirements)

Trước khi gửi Pull Request hoặc phát hành bản Release, mọi thành viên **bắt buộc** phải chạy bộ kiểm thử tự động 5 phép thử trọng tâm:

```powershell
# Chạy trên Windows:
.\run_project.ps1 test
```
hoặc:
```bash
# Chạy trên Ubuntu / WSL:
bash lab.sh test
```

> **Điều kiện tiên quyết:** Toàn bộ **18/18 ca kiểm tra** phải đạt trạng thái `[PASS]` (100% đạt chuẩn). Nếu có bất kỳ ca kiểm thử nào thất bại, Pull Request sẽ bị từ chối.

---

## 5. Quy trình gửi Pull Request (PR)

1. Đẩy nhánh làm việc lên GitHub repository cá nhân hoặc nhóm.
2. Mở Pull Request vào nhánh `main`.
3. Điền đầy đủ mô tả PR:
   - Chức năng hoặc lỗi được xử lý.
   - Kết quả chạy bộ kiểm thử `tests/test_suite.php`.
   - Ảnh chụp màn hình giao diện (nếu có thay đổi UI).
4. Ít nhất một thành viên khác trong nhóm thực hiện Code Review và phê duyệt trước khi Merge.

---

## 6. Liên hệ & Hỗ trợ

Mọi thắc mắc hoặc đề xuất cải tiến xin vui lòng tạo Issue trên GitHub hoặc liên hệ qua kênh trao đổi nội bộ của nhóm BTL.
