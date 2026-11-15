# 08. ĐÁNH GIÁ, DANH MỤC, TÀI KHOẢN VÀ TỔNG QUAN

Bốn nhóm này dùng **đúng khuôn** của `supplier_form.php` (xem `04`), nên ở đây chỉ nêu phần khác biệt.

---

## 1. Đánh giá nhà cung cấp

**File**: `evaluations.php` (danh sách), `evaluation_form.php` (thêm/sửa), `evaluation_delete.php` (xóa).

### `evaluations.php`
Bộ lọc theo nhà cung cấp là form GET (`evaluations.php?supplier_id=N`). Có chọn NCC: hiện hết đánh giá của NCC đó (dòng 17–22). Không chọn: phân trang 10 dòng (dòng 23–28).

### `evaluation_form.php`
| Dòng | Việc |
|---|---|
| 41–47 | Chỉ khi thêm mới chọn nhà cung cấp |
| 49–51 | Ngày đánh giá hợp lệ |
| 53–62 | Ba điểm chất lượng / giao hàng / dịch vụ: `preg_match('/^[1-5]\z/')` – số nguyên 1 đến 5 (`\z` chặn cả ký tự xuống dòng thừa) |
| 64–66 | Nhận xét ≤ 2000 ký tự |
| 76–91 | `transaction(...)`: **ghi đánh giá và gọi `recalcAvgRating($sid)` trong cùng một giao dịch** |
| 92–93 | `Location: evaluations.php?supplier_id=N&msg=evaluation_added|evaluation_updated` |

`overall_score` (điểm tổng của từng đánh giá) là cột MySQL tự tính từ ba điểm. `recalcAvgRating()` (`functions.php` dòng 167) đặt `suppliers.avg_rating` = trung bình `overall_score` của mọi đánh giá, làm tròn 2 chữ số, và bằng 0 nếu chưa có đánh giá nào. Vì nằm chung giao dịch, đánh giá và điểm trung bình **không thể lệch nhau**.

### `evaluation_delete.php`
POST-only. Dòng 19–22: xóa đánh giá rồi `recalcAvgRating()` trong một giao dịch; dòng 23: quay về đúng bộ lọc cũ nhờ ô ẩn `back_supplier_id`.

---

## 2. Danh mục nhà cung cấp

**File**: `categories.php`, `category_form.php`, `category_delete.php`.

### `category_form.php`
| Dòng | Việc |
|---|---|
| 30–36 | Tên: bắt buộc, ≤ 100 ký tự, **không trùng không phân biệt hoa thường** (`LOWER(...) = LOWER(...)`), trừ chính dòng đang sửa |
| 38–40 | Mô tả ≤ 255 ký tự |
| 44–66 | Ghi + bắt lỗi trùng khóa 1062 (MySQL coi "Nguyên vật liệu" và "nguyen vat lieu" là trùng, kiểm tra ở dòng 34 có thể chưa bắt được) |

### `category_delete.php`
Danh mục còn nhà cung cấp thì **không xóa** (khóa ngoại `RESTRICT`): dòng 19–23 đếm trước và báo bằng lời dễ hiểu (`msg=category_has_suppliers&n=SỐ`); dòng 25–33 vẫn bắt lỗi khóa ngoại 1451 phòng trường hợp NCC vừa được thêm đúng lúc đó.

---

## 3. Tài khoản quản trị

**File**: `users.php`, `user_form.php`, `user_delete.php`.

### `user_form.php`
| Dòng | Việc |
|---|---|
| 26 | `$isSelf`: đang sửa chính tài khoản đăng nhập |
| 34–35 | Mật khẩu **không** `test_input()` (khoảng trắng có thể là một phần mật khẩu) |
| 37–43 | Tên đăng nhập: bắt buộc, 3–50 ký tự hợp lệ, không trùng (không phân biệt hoa thường) |
| 59–67 | Thêm: bắt buộc nhập mật khẩu, 8 đến 72 ký tự (bcrypt chỉ dùng 72 byte đầu). Sửa: để trống = giữ mật khẩu cũ |
| 69–76 | **Không tự khóa hoặc tự hạ quyền chính mình** (tránh mất hết tài khoản quản trị) |
| 82–88 | Sửa: `UPDATE`; chỉ thêm `password_hash` khi có nhập mật khẩu mới |
| 91–97 | Thêm: `password_hash($password, PASSWORD_DEFAULT)`, **không lưu mật khẩu thô** |

### `user_delete.php`
Dòng 13–16: không tự xóa mình. Dòng 22–27: tài khoản đã tạo giao dịch hoặc đánh giá thì không xóa (báo số lượng), hãy **khóa** thay vì xóa. Dòng 29–37: bắt lỗi khóa ngoại.

---

## 4. Tổng quan (`dashboard.php`)

Chỉ đọc và in số liệu, **không có form nên không có bước POST**.

| Dòng | Số liệu |
|---|---|
| 9–16 | Số nhà cung cấp theo trạng thái (`GROUP BY status`) |
| 18–24 | Tổng còn nợ, số hóa đơn quá hạn, số sắp đến hạn (dùng chung `overdueSql()` / `dueSoonSql()`) |
| 25–32 | 5 nhà cung cấp còn nợ nhiều nhất (`HAVING SUM(...) > 0`) |
| 35–45 | Hạng đối tác: mọi NCC **đang hoạt động** (`LEFT JOIN`, chưa có hóa đơn thì tổng 0) xếp theo tổng giá trị mua giảm dần |
| 46–54 | Gọi `partner_tier()` (dòng 49) cho từng NCC; hàm ở `functions.php` dòng 957, ngưỡng ở dòng 949–952: VIP 1 từ 200 triệu, VIP 2 từ 100 triệu, VIP 3 từ 30 triệu, còn lại là Thường |

Biểu đồ trong trang là các thanh CSS (không dùng thư viện), nên không có chú giải hiện khi rê chuột.
