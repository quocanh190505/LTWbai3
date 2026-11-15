# 06. NGƯỜI LIÊN HỆ CỦA NHÀ CUNG CẤP

**Ba file**: `contact_form.php` (thêm và sửa), `contact_primary.php` (đặt làm chính), `contact_delete.php` (xóa).  
Các nút nằm trong trang `supplier_detail.php?id=N` (phần hiển thị nằm trong `supplierDetailHtml()`, `functions.php` dòng 845–884).

**Quy tắc nghiệp vụ**: mỗi nhà cung cấp có **tối đa một** người liên hệ chính (`is_primary = 1`). Người đầu tiên được thêm tự động là người chính.

---

## 1. `contact_form.php` – thêm và sửa

| Địa chỉ | Chế độ | Biến |
|---|---|---|
| `contact_form.php?supplier_id=5` | Thêm người liên hệ cho NCC số 5 | `$id = 0`, `$supplierId = 5` |
| `contact_form.php?id=12` | Sửa người liên hệ số 12 | `$id = 12`, `$supplierId` lấy từ chính dòng liên hệ |

Cùng bốn bước với `supplier_form.php` (xem `04`). Điểm khác:

| Dòng | Việc |
|---|---|
| 13–14 | Hai id: `$id` (liên hệ đang sửa) và `$supplierId` (thuộc nhà cung cấp nào). Cả hai đều được gửi lại bằng hai ô ẩn (dòng 113–114) vì `PHP_SELF` không mang `?id=` |
| 18 | `$contact = null;` – khai báo trước vì đoạn ghi dùng biến này, kể cả khi thêm mới |
| 20–28 | Sửa: đọc dòng liên hệ; không có → `suppliers.php?msg=not_found`; nhà cung cấp **không đổi được** nên lấy từ dòng này |
| 29–33 | Thêm: phải có nhà cung cấp hợp lệ, nếu không → `not_found` |
| 37 | `if ($_SERVER["REQUEST_METHOD"] == "POST")` |
| 44–48 | Họ tên: bắt buộc, ≤ 100 ký tự |
| 50–52 | Chức vụ: ≤ 100 ký tự |
| 54–56 | Email: không bắt buộc, đúng định dạng |
| 58–60 | Điện thoại: không bắt buộc, `valid_phone()` |
| 63 | Không ô nào lỗi mới ghi |
| 69–92 | `transaction(function () {...})`: gỡ cờ "chính" của người khác **và** ghi dòng này trong cùng một giao dịch |
| 93–94 | `header("Location: supplier_detail.php?id=N&msg=contact_added|contact_updated"); exit;` |
| 96–103 | `elseif ($id > 0)`: mở form sửa lần đầu thì điền sẵn từ database |

### Vì sao dùng `transaction()` (dòng 69–92)?
Muốn đặt người B làm chính thì phải làm **hai việc**: bỏ cờ chính của người A, rồi ghi người B. Nếu việc thứ nhất xong mà việc thứ hai lỗi, nhà cung cấp sẽ mất người chính. `transaction()` (`functions.php` dòng 125) bao hai việc vào một khối: cả hai xong mới `commit`, có lỗi thì `rollBack` trả về như cũ.

Ba tình huống trong khối này:
* **Sửa, tick "liên hệ chính"** (dòng 72–74): bỏ cờ của mọi người khác cùng nhà cung cấp.
* **Sửa người đang là chính** (dòng 71): luôn giữ cờ chính, không có cách bỏ chính mà không chỉ định người khác.
* **Thêm mới** (dòng 82–85): nếu nhà cung cấp chưa có ai thì người này tự là chính.

---

## 2. `contact_primary.php` – đặt làm chính

Nút "Đặt làm chính" là form POST gửi `contact_id` (file `functions.php` dòng 868). Xử lý (dòng 12–25): đọc dòng, tìm `supplier_id`, rồi trong một `transaction`:

```php
UPDATE supplier_contacts SET is_primary = 0 WHERE supplier_id = :sid          // bỏ cờ của tất cả
UPDATE supplier_contacts SET is_primary = 1 WHERE contact_id  = :id            // đặt cho người được chọn
```
Xong → `supplier_detail.php?id=N&msg=contact_primary`.

## 3. `contact_delete.php` – xóa

POST-only (dòng 7–10 đẩy GET về danh sách). Đọc dòng (dòng 12–17), `DELETE` (dòng 19), chuyển về chi tiết kèm `msg=contact_deleted` (dòng 20). Xóa người chính thì nhà cung cấp tạm không có người chính; bấm "Đặt làm chính" ở người khác để có lại.
