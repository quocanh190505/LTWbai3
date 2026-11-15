# 07. GIAO DỊCH (HÓA ĐƠN MUA HÀNG), THANH TOÁN VÀ CÔNG NỢ

**Các file**: `transactions.php` (danh sách), `transaction_form.php` (thêm/sửa), `transaction_pay.php` (thanh toán), `transaction_delete.php` (xóa), `transaction_invoice.php` (xem file hóa đơn).

**Ý chính**: hai cột `remaining_amount` (còn nợ) và `payment_status` (chưa trả / trả một phần / đã trả) **do MySQL tự tính** từ `total_amount` và `paid_amount` (cột sinh tự động trong `database/schema.sql`). PHP chỉ ghi hai cột gốc, nên không bao giờ có trạng thái sai lệch.

---

## 1. `transactions.php` – danh sách và bộ lọc (GET)

```
transactions.php?supplier_id=3&payment_status=partial&due=overdue&page=2
```

| Dòng | Việc |
|---|---|
| 10–16 | `$where`, `$params` rồi `supplier_id` qua `getInt()` |
| 17–21 | `payment_status` phải nằm trong `PAYMENT_STATUSES` (`unpaid/partial/paid`), lạ thì bỏ |
| 22–27 | `due=overdue` → `overdueSql()`; `due=soon` → `dueSoonSql()` |
| 32–37 | Một câu SQL tính **tổng số, tổng giá trị, tổng còn nợ** của cả kết quả lọc (hai ô tổng phía trên không phụ thuộc trang) |
| 43–44 | `CASE WHEN overdueSql() ...` gắn nhãn "quá hạn" / "sắp đến hạn" cho từng dòng |
| 69 | Form lọc: `method="get"` |

### Quá hạn và sắp đến hạn (`functions.php` dòng 931–944)
* **Quá hạn**: có `due_date`, `due_date < hôm nay` (đúng ngày hạn **chưa** quá hạn) và `remaining_amount > 0`.
* **Sắp đến hạn**: `hôm nay <= due_date <= hôm nay + 7 ngày` và còn nợ. Hai nhóm không giao nhau.
* Ngày hôm nay do PHP tính (`date("Y-m-d")`) rồi ghép vào câu SQL. Đây không phải dữ liệu người dùng nên an toàn, và danh sách, chi tiết, tổng quan dùng **chung** hai hàm này để số liệu luôn khớp.

Các nút mỗi dòng: **Thanh toán** (liên kết GET, dòng 133), **Hóa đơn** (dòng 136), **Sửa**, **Xóa** (form POST gửi `transaction_id` tới `transaction_delete.php`, dòng 139).

---

## 2. `transaction_form.php` – thêm và sửa (có tải tệp)

Cùng bốn bước với `supplier_form.php`. Thẻ form có `enctype="multipart/form-data"` (dòng 160).

| Dòng | Việc |
|---|---|
| 14–15 | `$id` (sửa) và `$supplierId` (chỉ dùng khi thêm) |
| 23–35 | Sửa: đọc giao dịch kèm mã/tên NCC; không có → `not_found` |
| 45–51 | **Chỉ khi thêm** mới kiểm tra nhà cung cấp (phải chọn, phải tồn tại). Sửa thì NCC cố định, ô chọn bị `disabled` (dòng 165–167) |
| 53–57 | Số hóa đơn: bắt buộc, ≤ 50 ký tự |
| 59–62 | Ngày giao dịch hợp lệ (`valid_date`) |
| 64–70 | Hạn thanh toán: không bắt buộc, hợp lệ, **không trước ngày giao dịch** (so sánh chuỗi `YYYY-MM-DD` đúng thứ tự thời gian) |
| 72–81 | Tổng giá trị: bắt buộc, `valid_money`, lớn hơn 0 |
| 83–89 | Đã trả: rỗng = 0, hợp lệ, **không lớn hơn tổng** |
| 92 | `read_optional_upload("invoice_file", 5MB, pdf/jpg/png)`: chỉ kiểm tra, chưa lưu |
| 95–96 | Không lỗi ở ô nào mới ghi |
| 98–109 | Lưu tệp hóa đơn (tên ngẫu nhiên) / gỡ / giữ cũ |
| 117–127 | **Sửa**: `UPDATE` hai cột gốc + xóa tệp cũ nếu bị thay; `Location: transactions.php?supplier_id=N&msg=transaction_updated` |
| 128–134 | **Thêm**: `INSERT` kèm `created_by` = id tài khoản đang đăng nhập; `msg=transaction_added` |
| 136 | `exit;` |
| 137–140 | Lỗi database thì xóa tệp vừa lưu rồi ném lỗi tiếp (không để tệp mồ côi) |
| 143–150 | `elseif ($id > 0)`: điền sẵn khi mở form sửa |

Không có ô nhập `remaining_amount` hay `payment_status`: người dùng không thể làm hai số này sai.

---

## 3. `transaction_pay.php` – thanh toán từng phần

Mở từ nút "Thanh toán": `transaction_pay.php?id=7`. Form hiện **số còn nợ** và ô nhập số tiền đợt này; nút **Trả hết** gửi thêm `pay_all=1`.

| Dòng | Việc |
|---|---|
| 9 | `$id` từ ô ẩn (POST) hoặc `?id=` (GET) |
| 13–21 | Đọc hóa đơn; không có → `not_found` |
| 25–27 | POST: `$payAll`, `$amount` |
| 29–30 | Không trả hết mà số tiền sai hoặc bằng 0 → lỗi hiện dưới ô nhập |
| 33–49 | `transaction(function () {...})` – phần quan trọng nhất |
| 34 | `SELECT ... WHERE transaction_id = :id FOR UPDATE` |
| 38–39 | Tính còn nợ **từ dữ liệu vừa đọc**, tính theo "xu" (số nguyên) để không sai số thập phân |
| 40–42 | Còn nợ ≤ 0 → "Hóa đơn này đã thanh toán đủ" |
| 43–46 | `$cents = $payAll ? còn nợ : số nhập`; vượt quá còn nợ → báo lỗi kèm số còn nợ |
| 47 | `UPDATE ... SET paid_amount = đã trả + số vừa trả` |
| 50–53 | Không lỗi → `Location: transactions.php?...&msg=transaction_paid` |

### `FOR UPDATE` để làm gì?
Nếu hai người cùng mở hóa đơn còn nợ 100 và cùng bấm "Trả hết": nếu không khóa, cả hai cùng đọc "còn 100", cùng cộng 100 → đã trả 200, vượt tổng. `FOR UPDATE` **khóa dòng** trong lúc giao dịch chạy: người thứ hai phải chờ, đến khi vào thì đọc lại thấy còn 0 → báo "đã thanh toán đủ".

---

## 4. `transaction_delete.php` – xóa (POST)

Dòng 6–9 chặn GET; dòng 11–16 đọc dòng (lấy `supplier_id` và đường dẫn tệp); dòng 18 `DELETE`; dòng 19 `delete_upload()` xóa tệp hóa đơn; dòng 20 về danh sách đúng nhà cung cấp kèm `msg=transaction_deleted`.

---

## 5. `transaction_invoice.php?id=N` – xem tệp hóa đơn

Thư mục `uploads/invoices/` có `.htaccess` cấm truy cập trực tiếp (hóa đơn là dữ liệu nội bộ). Tệp chỉ ra được qua trang này, **sau khi kiểm tra đăng nhập**:

| Dòng | Việc |
|---|---|
| 6 | `requireLogin()` |
| 8–17 | Không có giao dịch → `not_found`; giao dịch không có tệp → `invoice_none` |
| 20–25 | `realpath()` rồi kiểm tra tệp **nằm trong** `uploads/invoices/` (chặn đường dẫn kiểu `../`), thiếu tệp → `invoice_missing` |
| 27–29 | Bỏ chữ đã đệm để nội dung tệp không bị lẫn |
| 30–34 | Gửi header `Content-Type` (đọc từ nội dung tệp bằng `finfo`), `nosniff`, `Content-Length`, `inline`; rồi `readfile()` |
