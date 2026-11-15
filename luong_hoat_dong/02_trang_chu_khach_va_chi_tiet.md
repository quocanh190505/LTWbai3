# 02. TRANG CHỦ CÔNG KHAI VÀ CHI TIẾT CHO KHÁCH

**File**: `index.php`, `detail.php`  
**Ai vào**: khách (không cần đăng nhập). Admin đã đăng nhập mà mở hai trang này sẽ được chuyển sang bản quản trị tương ứng.

---

## 1. `index.php` – trang chủ

Chỉ dùng **GET** (tìm kiếm là form GET nên địa chỉ có đủ tham số, gửi link cho người khác vẫn thấy đúng kết quả):

```
index.php?q=thép&category_id=1&rating_min=4&sort=rating&dir=desc&page=2
```

| Dòng | Việc |
|---|---|
| 8–10 | `isLogin()` → admin thì chuyển `dashboard.php` |
| 25 | `$perPage = 12` (mỗi trang 12 thẻ) |
| 26 | `supplierListFilters(false)` – dựng `WHERE` + tham số từ `?q=&category_id=&rating_min=`; tham số `false` = khách |
| 28 | `lastPageOf($total, $perPage)` – tổng số trang |
| 50, 72 | Hai form tìm kiếm / lọc `method="get"`, `action` = `PHP_SELF` |

### Điều gì khiến khách "chỉ thấy phần được phép"
Trong `supplierListFilters()` (`functions.php` dòng 686): nếu `!$isAdmin` thì **luôn** thêm `s.status = 'active'`. Nên NCC ngừng hoạt động / danh sách đen không bao giờ hiện cho khách dù gõ địa chỉ nào. Câu `SELECT` của khách (`supplierSelectSql(false)`) cũng **không lấy** mã số thuế, hạn mức, công nợ.

### Tìm kiếm an toàn
Từ khóa được thoát `! % _` rồi dùng `LIKE :q1 ESCAPE '!'` (dòng 698–705): gõ `%` chỉ tìm đúng ký tự `%`, không khớp tất cả.

### Sắp xếp
`sort` nằm trong danh sách trắng `SUPPLIER_SORTS` (mã, tên, điểm...). Giá trị lạ bị bỏ qua, không bao giờ nối vào SQL.

---

## 2. `detail.php?id=N` – chi tiết cho khách

| Dòng | Việc |
|---|---|
| 7–10 | Admin → `supplier_detail.php?id=N` |
| 12 | `loadSupplierDetail($id, false)` – chỉ trả NCC đang hoạt động |
| 14 | Không có / ngừng hoạt động → `index.php?msg=not_found` |

Khách chỉ thấy: thông tin cơ bản, **người liên hệ chính**, điểm đánh giá. Không thấy mã số thuế, hạn mức, công nợ, các liên hệ khác, và không có nút Sửa/Xóa (hàm `supplierDetailHtml($detail, false)` bỏ hẳn các phần đó).
