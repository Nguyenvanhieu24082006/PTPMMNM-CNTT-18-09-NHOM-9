# ĐỀ TÀI 19: XÂY DỰNG HỆ THỐNG XỬ LÝ ĐƠN HÀNG VÀ QUẢN LÝ ĐỔI TRẢ TRONG BÁN HÀNG TRỰC TUYẾN

* **Học phần:** Phát triển phần mềm mã nguồn mở
* **Trường:** Đại học Đại Nam
* **Hệ quản trị CSDL lựa chọn:** MySQL (InnoDB, Prepared Statements, ACID Transactions)
* **Ngôn ngữ triển khai:** PHP >= 8.1 (Thuần, kiến trúc Service-Repository chuẩn mực)

---

## I. MỤC TIÊU & TÍNH NĂNG ĐÃ HOÀN THÀNH

Hệ thống đáp ứng trọn vẹn 100% các tiêu chí trong Rubric đánh giá (10/10):

### 1. 5 Chức năng cốt lõi (3,0 điểm)
1. **Quản lý sản phẩm & biến thể:** 20 sản phẩm đa dạng, phân loại theo danh mục. Hiển thị tồn kho thực tế và **lượng hàng có thể bán** (Available = Stock - Active Holding Reservations) theo thời gian thực.
2. **Tạo đơn & Giữ hàng có thời hạn (15 phút):** Khóa tồn kho nguyên tử, chốt bản sao giá cứng (**Price Snapshot**) trong `order_items` tại thời điểm đặt hàng.
3. **Tiếp nhận thanh toán mô phỏng & Chống trùng (Idempotency):** Tiếp nhận kết quả thanh toán từ cổng giả lập, tra cứu `transaction_code` (ràng buộc `UNIQUE`) để bỏ qua các request gửi lặp.
4. **Xử lý giao nhận & Đổi trả một phần:** Nhân viên cập nhật tiến trình `PAID` ➔ `PROCESSING` ➔ `SHIPPED` ➔ `DELIVERED`. Cho phép khách hàng gửi yêu cầu trả một phần, ràng buộc $\sum \text{trả} \le \text{đã giao}$. Người duyệt thẩm định hoàn tiền và hoàn kho.
5. **Theo dõi lịch sử & Đối soát:** Bảng `order_audit_logs` lưu vết chi tiết mọi biến động trạng thái, số lượng và dòng tiền. Trang báo cáo đối chiếu tài chính và hàng hóa trực quan.

### 2. 2 Yêu cầu kỹ thuật trọng tâm (2,0 điểm)
* **Kỹ thuật 1:** Xử lý tranh chấp sản phẩm cuối cùng (Race Condition / Concurrency Control) bằng MySQL Transaction + Pessimistic Locking (`SELECT ... FOR UPDATE`). Hai khách hàng cùng tranh 1 sản phẩm cuối chỉ 1 người thành công, lượng có thể bán không âm. Lệnh hủy và giải phóng hàng hết hạn đảm bảo tính **Idempotent** (không hoàn kho 2 lần).
* **Kỹ thuật 2:** Đóng băng giá đơn đã chốt (Snapshot Price). Kiểm soát webhook lặp, xử lý **thanh toán đến muộn** khi đơn đã hết hạn (không tự hồi sinh đơn để tránh bán vượt tồn kho). Tiền hoàn đổi trả tính chính xác theo giá chốt lúc mua.

---

## II. HƯỚNG DẪN CÀI ĐẶT & CHẠY HỆ THỐNG

### Cách 1: Chạy trên Windows (PowerShell)
```powershell
# 1. Khởi tạo CSDL 10 bảng và nạp 100 đơn hàng mẫu
.\run_project.ps1 setup

# 2. Chạy bộ kiểm thử tự động 5 phép thử bắt buộc (Pass 100%)
.\run_project.ps1 test

# 3. Khởi động Web Server
.\run_project.ps1 run
```
Mở trình duyệt truy cập: **`http://127.0.0.1:8105`**

### Cách 2: Chạy trên Ubuntu / WSL (Bash)
```bash
# 1. Cấu hình CSDL nếu cần
bash lab.sh db-config

# 2. Khởi tạo Database & Seed data
bash lab.sh setup

# 3. Chạy kiểm thử tự động
bash lab.sh test

# 4. Chạy Web Server
bash lab.sh run
```

---

## III. TÀI KHOẢN TRẢI NGHIỆM THEO VAI TRÒ
Hệ thống tích hợp sẵn thanh **Quick Switcher** ở góc trên bên phải để chuyển nhanh vai trò khi thuyết trình vấn đáp:
* **Khách hàng (Customer):** `customer1` / mật khẩu `123456`
* **Nhân viên xử lý đơn (Staff):** `staff1` / mật khẩu `123456`
* **Người xác nhận trả hàng (Manager):** `manager1` / mật khẩu `123456`

---

## IV. KỊCH BẢN DEMO 2 TÌNH HUỐNG (MỤC 4 ĐỀ TÀI)

### Tình huống 1: Đặt hàng ➔ Giữ hàng ➔ Thanh toán mô phỏng ➔ Giao hàng
1. Đăng nhập với vai trò **Khách hàng** (`customer1`).
2. Vào trang chủ chọn **Áo thun Cotton Basic** (Size L) ➔ Bấm **+ Giỏ hàng** ➔ Vào **Giỏ hàng** ➔ Bấm **Tiến hành Đặt hàng**.
3. Bấm **Xác nhận Đặt hàng & Giữ chỗ**: Hệ thống chuyển sang trang Chi tiết đơn hàng với đồng hồ đếm ngược **15 phút**.
4. Bấm **Tiếp nhận thanh toán mô phỏng** ➔ Bấm nút **✅ Gửi thông báo Thành công**: Đơn hàng chuyển sang `PAID`, kho hàng thực tế được chốt trừ vĩnh viễn.
5. Chuyển sang vai trò **Nhân viên** (`staff1`): Vào **Quản lý đơn hàng** ➔ Chuyển đơn sang `PROCESSING` ➔ `SHIPPED` ➔ `DELIVERED`.

### Tình huống 2: Yêu cầu trả một phần ➔ Thẩm định ➔ Hoàn tiền mô phỏng & Đối chiếu
1. Đăng nhập vai trò **Khách hàng**: Mở đơn hàng đã giao ở Tình huống 1.
2. Bấm nút **🔄 Yêu cầu đổi trả một phần**:
   * Nhập số lượng muốn trả (hệ thống chặn không cho nhập vượt quá số lượng đã giao).
   * Nhập lý do đổi trả và bấm **Gửi yêu cầu**.
3. Chuyển sang vai trò **Người duyệt trả** (`manager1`):
   * Vào menu **Thẩm định đổi trả**.
   * Nhập ghi chú thẩm định, tích chọn *Nhập lại hàng vào kho bán* ➔ Bấm **✅ Duyệt**.
   * Hệ thống cập nhật trạng thái đơn thành `PARTIALLY_RETURNED`, hoàn lại tiền theo giá gốc lúc mua, và cộng lại tồn kho.
4. Mở menu **Đối chiếu & Báo cáo**: Quan sát dòng tiền thu, tiền hoàn và đối soát hàng hóa được cập nhật chính xác.

---

## V. BỘ KIỂM THỬ TỰ ĐỘNG (5 PHÉP THỬ MỤC 6)

Chạy lệnh `.\run_project.ps1 test` hoặc `bash lab.sh test`:
1. **Phép thử 1:** Hai đơn tranh sản phẩm cuối không cùng giữ được hàng; lượng có thể bán không âm.
2. **Phép thử 2:** Gửi lại webhook thanh toán hoặc lệnh hủy không thay đổi số lượng/tiền thêm lần nữa (Idempotency).
3. **Phép thử 3:** Đổi giá sản phẩm sau đặt không thay đổi giá trong đơn đã chốt (Price Snapshot).
4. **Phép thử 4:** Tổng lượng trả nhiều lần không vượt lượng đã giao; tiền hoàn tính theo đúng giá lúc mua.
5. **Phép thử 5:** Thanh toán đến sau khi đơn hết hạn không tự hồi sinh đơn gây cấp hàng vượt tồn.
