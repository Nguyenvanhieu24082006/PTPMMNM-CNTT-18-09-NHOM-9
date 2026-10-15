# BÁO CÁO THỰC HÀNH LAB 03

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Chủ đề:** Hiển thị sự kiện, tìm kiếm và xử lý Form bằng PHP  
**Môi trường:** Ubuntu 26.04.1 LTS trên WSL 2, PHP CLI 8.4.24, Git  
**Dự án:** CommunityPass — Lab 03

> Điền họ tên, mã sinh viên, lớp và ngày thực hiện trước khi nộp.

## 1. Mục tiêu

- Đọc và xử lý mảng kết hợp trong PHP.
- Tách phần xử lý dữ liệu khỏi phần giao diện.
- Tìm sự kiện theo từ khóa không phân biệt chữ hoa/chữ thường.
- Kết hợp bộ lọc theo từ khóa, loại sự kiện và tình trạng còn chỗ.
- Nhận dữ liệu từ Form bằng GET và POST.
- Kiểm tra dữ liệu ở máy chủ và mã hóa đầu ra HTML để giảm nguy cơ XSS.
- Chạy kiểm thử phần mẫu và phần mở rộng, ghi nhận kết quả.

## 2. Chuẩn bị môi trường

Cài đặt công cụ trên Ubuntu/WSL:

```bash
sudo apt update
sudo apt install -y php-cli git unzip
php -v
git --version
```

Giải nén ZIP, chuyển vào thư mục mã nguồn:

```bash
cd ~/Open-Source-Software-Development-lab3/Ma_nguon
```

Nếu giải nén ở vị trí khác, thay đường dẫn `cd` theo vị trí thực tế.

## 3. Cấu trúc và vai trò tệp

| Tệp | Vai trò |
|---|---|
| `data/events.php` | Cung cấp dữ liệu sự kiện mẫu dưới dạng mảng PHP. |
| `src/EventFilter.php` | Lọc theo từ khóa, loại và tình trạng chỗ trống. |
| `src/Input.php` | Đọc trường đầu vào và mã hóa dữ liệu trước khi hiển thị HTML; kiểm tra dữ liệu đăng ký. |
| `public/index.php` | Nhận bộ lọc GET, hiển thị Form và danh sách sự kiện. |
| `public/register.php` | Nhận Form POST và hiển thị lỗi hoặc kết quả xem trước. |
| `templates/header.php` / `templates/footer.php` | Thành phần giao diện dùng chung. |
| `tests/run.php` | Kiểm thử phần mẫu. |
| `tests/extension.php` | Kiểm thử bộ lọc mở rộng. |
| `lab.sh` | Lệnh kiểm tra môi trường, chạy web và chạy kiểm thử. |

## 4. Nội dung đã hoàn thiện

### 4.1. Bộ lọc sự kiện

Hàm `filterEvents(array $events, string $query, string $category = '', bool $availableOnly = false): array` thực hiện ba điều kiện:

1. **Từ khóa:** tìm trong tiêu đề bằng `stripos`, không phân biệt hoa/thường. Từ khóa rỗng không giới hạn kết quả.
2. **Loại sự kiện:** khi người dùng chọn loại, chỉ giữ sự kiện có `category` bằng giá trị đã chọn. Chuỗi rỗng nghĩa là mọi loại.
3. **Còn chỗ:** số chỗ còn lại được tính bằng `max(0, capacity - registered)`. Nếu bật bộ lọc này, chỉ giữ sự kiện có số chỗ còn lại lớn hơn 0.

Ba điều kiện được kết hợp bằng phép AND. Chỉ sự kiện thỏa mãn tất cả điều kiện đã chọn mới được thêm vào mảng kết quả. Hàm tạo mảng kết quả mới, không sửa mảng ban đầu.

### 4.2. Form GET và giữ lựa chọn

Trang danh sách có ô từ khóa, danh sách chọn loại sự kiện, checkbox “Chỉ hiển thị sự kiện còn chỗ”, nút lọc và liên kết xóa bộ lọc. Dữ liệu được gửi qua GET nên các điều kiện xuất hiện trên URL và có thể chia sẻ để tái hiện truy vấn. Sau khi gửi, các lựa chọn được giữ lại trên giao diện.

Giá trị loại được kiểm tra với danh sách cho phép `tech` và `design`; giá trị không hợp lệ được đưa về lựa chọn tất cả loại.

### 4.3. Form POST và an toàn đầu vào

Trang đăng ký sử dụng POST để gửi dữ liệu. Máy chủ kiểm tra họ tên, email và mã sự kiện; dữ liệu hợp lệ chỉ tạo bản xem trước. Dữ liệu nhập do người dùng kiểm soát phải được mã hóa HTML trước khi hiển thị. Bài lab hiện không lưu đăng ký vào cơ sở dữ liệu, không tạo vé thật và không xử lý thanh toán.

## 5. Cách chạy

Trong thư mục `Ma_nguon`:

```bash
bash lab.sh check
bash lab.sh run
```

Mở trình duyệt tại `http://127.0.0.1:8103/`. Trang đăng ký ở `http://127.0.0.1:8103/register.php`. Dừng máy chủ bằng `Ctrl+C` tại terminal đang chạy máy chủ.

## 6. Kiểm thử

Mở terminal thứ hai trong cùng thư mục `Ma_nguon` (hoặc dừng máy chủ trước), chạy:

```bash
bash lab.sh test
bash lab.sh extend
php tests/ui-demo.php
```

Kết quả kiểm thử trên gói đã hoàn thiện:

```text
TESTS=7 FAILED=0
TESTS=8 FAILED=0
DEMO_DATA_ASSERTIONS=PASS | EVENTS=6 | SEATS=96 | AVAILABLE=68 | VIP=16 | STANDARD=52
```

Kiểm tra cú pháp PHP:

```bash
php -l src/EventFilter.php
php -l public/index.php
```

Kết quả: không phát hiện lỗi cú pháp ở hai tệp đã chỉnh sửa.

### Các tình huống cần kiểm tra trên trình duyệt

| Thao tác | Kết quả mong đợi |
|---|---|
| Mở `/` | Hiển thị 3 sự kiện gốc. |
| Nhập `php` | Còn 1 sự kiện, PHP Open Day. |
| Nhập `xyz` | Không có kết quả. |
| Chọn loại `tech` | Có 2 sự kiện. |
| Chọn “Chỉ hiển thị sự kiện còn chỗ” | Có 2 sự kiện còn chỗ. |
| Nhập `Git`, chọn `tech`, bật còn chỗ | Có 1 sự kiện, Git Clinic. |
| Chọn `design` và bật còn chỗ | Không có kết quả vì sự kiện design mẫu đã đủ chỗ. |
| Gửi Form đăng ký thiếu họ tên hoặc email sai | Hiển thị lỗi kiểm tra; không báo thành công. |
| Nhập chuỗi có ký tự HTML trong trường được hỗ trợ | Nội dung phải được hiển thị như văn bản, không thực thi thành HTML. |

Các kết quả HTTP đã được kiểm tra trên máy cục bộ: `q=php` trả 1 kết quả; `category=tech` trả 2; `available=1` trả 2; `q=Git&category=tech&available=1` trả 1; `category=design&available=1` trả 0.

## 7. Kết luận

Lab 03 đã hoàn thiện phần mẫu và phần mở rộng: danh sách sự kiện, tìm kiếm, lọc kết hợp theo ba điều kiện, giữ trạng thái Form GET, kiểm tra dữ liệu đăng ký qua POST và các kiểm thử tự động. Hai bộ kiểm thử chính đều đạt yêu cầu `FAILED=0`.

**Giới hạn:** đây là bài thực hành chạy cục bộ với dữ liệu mẫu. Đăng ký chỉ hiển thị bản xem trước, chưa lưu vào CSDL và không đại diện cho hệ thống đặt vé/đăng ký triển khai thực tế.

## 8. Câu hỏi ôn tập

1. Vì sao hàm `filterEvents` trả về mảng thay vì in HTML trực tiếp?
2. Khác nhau giữa GET và POST trong bài lab là gì?
3. Vì sao dùng `stripos(...) !== false` thay vì kiểm tra điều kiện theo kiểu đúng/sai đơn giản?
4. Vì sao cần mã hóa dữ liệu bằng hàm `e()` trước khi đưa vào HTML?
5. Phép AND giữa ba bộ lọc có ý nghĩa gì? Hãy nêu ví dụ `Git + tech + còn chỗ`.
6. Vì sao kết quả `PREVIEW_OK` không có nghĩa là đăng ký đã được lưu?
7. Vai trò của `bash lab.sh test` và `bash lab.sh extend` khác nhau như thế nào?
