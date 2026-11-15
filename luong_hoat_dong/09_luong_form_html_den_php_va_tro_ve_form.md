# 09 – Luồng của từng form: form HTML → file PHP → phương thức → trả về form

> **Đây là tài liệu trả lời đúng câu hỏi của thầy:** "Bắt đầu từ form HTML, form gọi tới file PHP nào, file đó thực hiện phương thức gì, rồi trả về form như thế nào?"
> Khi thầy chỉ vào một ô nhập hoặc một nút bất kỳ, bạn chỉ cần lần theo đúng 4 bước ở mục 1, rồi tra bảng ở mục 3, 4, 5 để lấy file và số dòng.

---

## 1. Chuỗi 4 bước (học thuộc phần này trước)

| Bước | Việc xảy ra | Nằm ở đâu trong project |
|---|---|---|
| **1. Form HTML** | Người dùng điền ô nhập rồi bấm nút. Thẻ `<form method="..." action="...">` quyết định **gửi bằng phương thức nào** và **gửi tới địa chỉ nào**. Tên mỗi ô (`name="..."`) sẽ là tên khóa trong `$_POST`. | File giao diện: `modules/<module>/_form.php` (form dùng chung cho thêm và sửa), hoặc ngay trong `list.php`, `login.php`... |
| **2. File PHP được gọi** | Trình duyệt gửi yêu cầu tới `index.php?module=<module>&action=<action>`. `index.php` ghép ra đường dẫn `modules/<module>/<action>.php` rồi nạp file đó. | `index.php` dòng 20–33 (chọn file), file đích là `modules/<module>/<action>.php` |
| **3. Phương thức thực hiện** | File đích chạy: `requireLogin()` → `isPost()` → `filterData()` → kiểm tra dữ liệu (`validate…`) → ghi database (`insert`/`update`/`delete`, có khi bọc `transaction`). | `includes/functions.php` (`isPost` dòng 24, `filterData` dòng 37), `includes/database.php`, `modules/<module>/_validate.php` |
| **4. Trả về form** | Không in trang ngay sau POST mà **chuyển hướng (redirect)**. Thành công → về trang danh sách kèm thông báo xanh. Có lỗi → về lại chính form, kèm chữ báo lỗi dưới từng ô và giá trị vừa nhập. Thông báo/lỗi đi qua **flash** (lưu trong session, đọc một lần rồi xóa). | `setMsg()` dòng 120, `setFormFlash()` dòng 127, `redirect()` dòng 108 (`functions.php`); `getSessionFlash()` dòng 56 (`session.php`) |

**Một câu nói gọn khi vấn đáp:**
"Form có `method="POST"` và `action` trỏ tới `index.php?module=…&action=…`. `index.php` nạp file `modules/…/….php`. File đó kiểm tra `isPost()`, lọc dữ liệu bằng `filterData()`, kiểm tra bằng hàm `validate`, rồi ghi database. Thành công thì `setMsg` + `redirect` về danh sách; sai thì `setFormFlash` + `redirect` về lại form, form đọc lại lỗi và dữ liệu cũ bằng `getSessionFlash`."

### Về chữ "form.php" trong yêu cầu của thầy

Project **không có file tên `form.php`**. Phần đó được tách thành hai vai, và **cả hai đều có mặt trong luồng**:

- **`_form.php`**: chỉ chứa **HTML của form** (các ô nhập, nút Lưu/Hủy). Dấu `_` đầu tên nghĩa là file phụ, **không mở trực tiếp bằng URL được** (`index.php` dòng 20–25 chặn tên bắt đầu bằng `_`).
- **`add.php` / `edit.php`**: là **file được form gọi tới**. Cùng một file làm hai việc: nhận **GET** thì hiện form (nạp `_form.php`), nhận **POST** thì xử lý dữ liệu. Vì vậy `action` của form trỏ về chính action đang mở.

---

## 2. Ví dụ đi sâu từng dòng: form **Thêm nhà cung cấp**

Học kỹ ví dụ này, các form còn lại đều làm giống hệt (chỉ khác tên bảng và quy tắc kiểm tra).

```mermaid
sequenceDiagram
    autonumber
    participant B as Trình duyệt
    participant I as index.php
    participant A as modules/suppliers/add.php
    participant F as _form.php
    participant D as MySQL
    participant S as Session (flash)

    Note over B,F: BƯỚC 1: mở form (GET)
    B->>I: GET ?module=suppliers&action=add
    I->>A: nạp modules/suppliers/add.php
    A->>A: requireLogin(); isPost() = false
    A->>S: getSessionFlash('errors'), ('oldData') (lần đầu: rỗng)
    A->>F: require _form.php (vẽ HTML form)
    F-->>B: trang có form POST, action = ...&action=add

    Note over B,D: BƯỚC 2 và 3: bấm Lưu (POST) và xử lý
    B->>I: POST ?module=suppliers&action=add (body: supplier_code=..., supplier_name=...)
    I->>A: nạp lại chính add.php
    A->>A: isPost() = true; filterData() (cắt khoảng trắng)
    A->>A: validateSupplierForm()
    alt Hợp lệ
        A->>D: insert('suppliers', ...)
        A->>S: setMsg('Đã thêm nhà cung cấp')
        A-->>B: 302 Location: ?module=suppliers&action=list
        Note over B: BƯỚC 4 (thành công)
        B->>I: GET ...action=list
        I-->>B: danh sách + khung xanh "Đã thêm nhà cung cấp" (đọc xong thì xóa)
    else Có lỗi
        A->>S: setFormFlash($errors, $filter)
        A-->>B: 302 Location: ?module=suppliers&action=add
        Note over B: BƯỚC 4 (thất bại)
        B->>I: GET ...action=add
        A->>S: getSessionFlash('errors'), ('oldData')
        I-->>B: form cũ: ô lỗi viền đỏ + chữ báo lỗi + giá trị vừa nhập
    end
```

### Bước 1: Form HTML

**Mở form.** Ở `modules/suppliers/list.php` dòng 39 có liên kết `<a href="…module=suppliers&action=add">`. Bấm vào là một yêu cầu **GET**.

**Thẻ form** (`modules/suppliers/_form.php` dòng 20):

```php
<form method="POST" action="<?= h($formAction) ?>" enctype="multipart/form-data" novalidate>
```

| Thuộc tính | Ý nghĩa |
|---|---|
| `method="POST"` | Dữ liệu nằm trong thân yêu cầu, **không hiện trên thanh địa chỉ**, và là phương thức dùng cho thao tác ghi dữ liệu. |
| `action="<?= h($formAction) ?>"` | Địa chỉ nhận dữ liệu. `$formAction` được gán ở `add.php` dòng 45: `url('suppliers', 'add')` = `…/index.php?module=suppliers&action=add`. `h()` thoát ký tự đặc biệt khi in vào HTML. |
| `enctype="multipart/form-data"` | Bắt buộc vì form có ô chọn **file logo** (`<input type="file" name="logo">`, `_form.php` dòng 90). Thiếu dòng này thì file không được gửi lên. |
| `novalidate` | Tắt hộp thoại kiểm tra mặc định của trình duyệt để **mọi lỗi do máy chủ kiểm tra và hiển thị thống nhất**. |

**Các ô nhập** (tên `name` = khóa trong `$_POST`): `supplier_code`, `supplier_name`, `category_id`, `tax_code`, `email`, `phone`, `address`, `credit_limit`, `status`, `logo`, và (chỉ khi sửa) `remove_logo`.

**Nút gửi:** `<button type="submit">Lưu</button>` ở `_form.php` dòng 103; nút `Hủy` (dòng 104) chỉ là liên kết GET về danh sách, không gửi gì.

### Bước 2: File PHP được gọi

Trình duyệt gửi `POST index.php?module=suppliers&action=add`.

`index.php` làm đúng 3 việc:
1. Dòng 20–25: lấy `module` và `action` từ `$_GET`, chỉ chấp nhận chữ thường và dấu `_` (regex `/^[a-z][a-z_]*$/`), nên không chèn được `../` hay mở `_form.php`.
2. Dòng 27: ghép `modules/suppliers/add.php`.
3. Dòng 28–33: file có tồn tại thì `require_once`, không thì trả 404.

> Lưu ý: tham số `module` và `action` nằm trên **URL** (nên đọc bằng `$_GET`), còn dữ liệu form nằm trong **thân POST** (đọc bằng `$_POST`). Một yêu cầu POST vẫn có query string trên URL.

### Bước 3: Phương thức thực hiện (`modules/suppliers/add.php`)

| Dòng | Việc |
|---|---|
| 6 | `requireLogin()`: chưa đăng nhập thì bị chuyển về trang đăng nhập. |
| 9 | `if (isPost())`: **phân nhánh theo phương thức**. GET thì bỏ qua cả khối, đi xuống phần hiện form. |
| 10 | `$filter = filterData();` lấy toàn bộ `$_POST` và `trim()` từng ô. |
| 11 | `[$data, $errors, $logoFile] = validateSupplierForm($filter, null);` kiểm tra từng ô (mã 3–20 ký tự, mã/MST không trùng, email, số điện thoại, hạn mức, logo...). Luật nằm trong `modules/suppliers/_validate.php`. |
| 14–21 | Không lỗi và có file logo thì lưu file vào `uploads/logos/` (`handle_upload`). |
| 23–25 | Không lỗi thì `insert('suppliers', …)` (câu lệnh có tham số `:name`, chống SQL injection). |
| 28–34 | Bắt lỗi trùng khóa của MySQL (hai người thêm cùng mã trong cùng lúc) rồi biến thành lỗi hiển thị ở ô; xóa file logo vừa lưu để không để lại file mồ côi. |

### Bước 4: Trả về form

**Nhánh thành công** (dòng 26–27):

```php
setMsg('Đã thêm nhà cung cấp');
redirect(url('suppliers', 'list'));
```
`setMsg` cất câu thông báo vào session. `redirect` gửi `Location: …action=list` rồi dừng. Trình duyệt tự gọi GET tới danh sách; `templates/assets/layouts/header.php` dòng 23 gọi `flashHtml()` để in khung xanh rồi **xóa luôn** khỏi session.

**Nhánh thất bại** (dòng 36–37):

```php
setFormFlash($errors, $filter);
redirect(url('suppliers', 'add'));
```
`setFormFlash` cất **lỗi từng ô** (`errors`) và **dữ liệu vừa nhập** (`oldData`) vào session. Redirect về lại chính form. Lần GET này chạy xuống dòng 40–41:

```php
$errors = getSessionFlash('errors') ?: [];
$old    = getSessionFlash('oldData') ?: [];
```
`_form.php` dùng ba hàm trợ giúp để vẽ lại: `invalid($errors,'tên_ô')` thêm class `is-invalid` (viền đỏ), `formError(...)` in chữ lỗi dưới ô, `oldData($old,'tên_ô', mặc định)` điền lại giá trị cũ (`functions.php` dòng 154–169).

**Vì sao không in trang ngay sau POST mà phải redirect (mẫu PRG – Post/Redirect/Get)?** Để bấm **F5 không gửi lại form** (không thêm trùng) và để thanh địa chỉ về dạng GET sạch.

> Lưu ý ô file: trình duyệt **không** tự điền lại ô chọn file vì lý do bảo mật, nên khi form báo lỗi, người dùng phải chọn lại logo.

---

## 3. Bảng tổng hợp: mọi form POST ghi dữ liệu (15 form)

Cách đọc: cột **Gửi tới** là file PHP nhận `action` của form. Cột **Thành công →** và **Lỗi →** là bước 4 (kèm số dòng `redirect` trong file đó). Cùng một `_form.php` phục vụ cả thêm và sửa, chỉ khác `action`.

| # | Form | Form HTML nằm ở | Gửi tới (`action`) | Các ô `name=` | Thành công → | Lỗi → quay lại form |
|---|---|---|---|---|---|---|
| 1 | **Đăng nhập** | `modules/auth/login.php:51` (`action=""`: gửi lại **chính URL đang mở**) | `auth/login.php` | `username`, `password` | `dashboard/index` (dòng 34–35) | `auth/login` (dòng 24–26); sai mật khẩu: dòng 29–32 (báo chung một câu, không cho biết tài khoản có tồn tại) |
| 2 | **Đổi mật khẩu** | `modules/auth/change_password.php:57` (`action=""`) | `auth/change_password.php` | `current_password`, `new_password`, `confirm_password` | `dashboard/index` (dòng 44–47) | `auth/change_password` (dòng 39–41); **không** lưu lại mật khẩu đã gõ |
| 3 | **Thêm nhà cung cấp** | `suppliers/_form.php:20` | `suppliers/add.php` | `supplier_code`, `supplier_name`, `category_id`, `tax_code`, `email`, `phone`, `address`, `credit_limit`, `status`, `logo` | `suppliers/list` (dòng 26–27) | `suppliers/add` (dòng 36–37) |
| 4 | **Sửa nhà cung cấp** | `suppliers/_form.php:20` (cùng form, có thêm `remove_logo`) | `suppliers/edit.php?id=N` | như trên + `remove_logo` | `suppliers/list` (dòng 48–49) | `suppliers/edit&id=N` (dòng 58–59) |
| 5 | **Thêm người liên hệ** | `contacts/_form.php:17` | `contacts/add.php?supplier_id=N` | `full_name`, `position`, `email`, `phone`, `is_primary` | `suppliers/detail&id=N` (dòng 33–34) | `contacts/add&supplier_id=N` (dòng 36–37) |
| 6 | **Sửa người liên hệ** | `contacts/_form.php:17` | `contacts/edit.php?id=N` | như trên | `suppliers/detail&id=…` (dòng 35–36) | `contacts/edit&id=N` (dòng 38–39) |
| 7 | **Thêm danh mục** | `categories/_form.php:15` | `categories/add.php` | `category_name`, `description` | `categories/list` (dòng 28–29) | `categories/add` (dòng 38–39) |
| 8 | **Sửa danh mục** | `categories/_form.php:15` | `categories/edit.php?id=N` | như trên | `categories/list` (dòng 35–36) | `categories/edit&id=N` (dòng 44–45) |
| 9 | **Thêm giao dịch** | `transactions/_form.php:18` (có file hóa đơn nên `multipart`) | `transactions/add.php` | `supplier_id`, `invoice_number`, `transaction_date`, `due_date`, `total_amount`, `paid_amount`, `invoice_file` | `transactions/list&supplier_id=…` (dòng 34–35) | `transactions/add` (dòng 41–42) |
| 10 | **Sửa giao dịch** | `transactions/_form.php:18` (thêm `remove_invoice`) | `transactions/edit.php?id=N` | như trên + `remove_invoice` | `transactions/list&supplier_id=…` (dòng 48–49) | `transactions/edit&id=N` (dòng 55–56) |
| 11 | **Thanh toán công nợ** | `transactions/pay.php:74` | `transactions/pay.php?id=N` | `amount`; nút **Trả hết** gửi thêm `pay_all=1` (dòng 81) | `$backUrl` = danh sách giao dịch của nhà cung cấp (dòng 51–52) | `transactions/pay&id=N` (dòng 54–55) |
| 12 | **Thêm đánh giá** | `evaluations/_form.php:18` | `evaluations/add.php` | `supplier_id`, `evaluation_date`, `quality_score`, `delivery_score`, `service_score`, `comment` | `evaluations/list&supplier_id=…` (dòng 33–34) | `evaluations/add` (dòng 36–37) |
| 13 | **Sửa đánh giá** | `evaluations/_form.php:18` | `evaluations/edit.php?id=N` | như trên | `evaluations/list&supplier_id=…` (dòng 37–38) | `evaluations/edit&id=N` (dòng 40–41) |
| 14 | **Thêm tài khoản** | `users/_form.php:19` | `users/add.php` | `username`, `password`, `full_name`, `role`, `is_active` | `users/list` (dòng 22–23) | `users/add` (dòng 32–33); mật khẩu **không** lưu vào flash (dòng 31) |
| 15 | **Sửa tài khoản** | `users/_form.php:19` | `users/edit.php?id=N` | như trên (mật khẩu để trống = giữ nguyên) | `users/list` (dòng 34–35) | `users/edit&id=N` (dòng 44–45) |

**Phần "phương thức thực hiện" của mỗi form (khác nhau ở bước 3):**

| Form | Kiểm tra bằng | Ghi database / điểm đặc biệt |
|---|---|---|
| 1 Đăng nhập | kiểm tra trực tiếp trong file (dòng 17–27) rồi `loginAdmin()` (`functions.php` dòng 219–231): `password_verify`, chỉ nhận `role = admin` và `is_active = 1`, đổi mã phiên | Không ghi database; ghi **session** đăng nhập |
| 2 Đổi mật khẩu | trực tiếp (dòng 15–37): đúng mật khẩu hiện tại, mới 8–72 ký tự, khác cũ, nhập lại khớp | `update('users', password_hash …)`, rồi `session_regenerate_id(true)` |
| 3, 4 Nhà cung cấp | `validateSupplierForm` (`suppliers/_validate.php`) | `insert` / `update`; lưu hoặc thay hoặc gỡ logo; bắt lỗi trùng mã |
| 5, 6 Người liên hệ | `validateContactForm` | `transaction`: bỏ cờ "chính" của người khác rồi ghi, để mỗi nhà cung cấp chỉ có một người chính; người đầu tiên tự là người chính |
| 7, 8 Danh mục | trực tiếp: tên bắt buộc, ≤ 100 ký tự, không trùng (không phân biệt hoa thường) | `insert` / `update` |
| 9, 10 Giao dịch | `validateTransactionForm`: số tiền ≤ 2 số thập phân, hạn thanh toán không trước ngày giao dịch, đã trả ≤ tổng | `insert` / `update`; cột còn nợ và trạng thái thanh toán **do MySQL tự tính** (cột sinh tự động) |
| 11 Thanh toán | trực tiếp: số tiền > 0 hoặc bấm "Trả hết" | `transaction` + `SELECT … FOR UPDATE` (khóa dòng để hai người thanh toán cùng lúc không làm sai số) rồi `update paid_amount`; tính bằng đơn vị xu (số nguyên) |
| 12, 13 Đánh giá | `validateEvaluationForm`: điểm nguyên 1–5, ngày hợp lệ, nhận xét ≤ 2000 ký tự | `transaction`: ghi đánh giá + `recalcAvgRating()` tính lại điểm trung bình |
| 14, 15 Tài khoản | `validateUserForm`: tên đăng nhập 3–50 ký tự không trùng; mật khẩu 8–72; **không tự khóa / tự hạ quyền chính mình** | `insert` / `update` với `password_hash()` |

---

## 4. Các nút "Xóa" và "Đặt làm chính": mỗi nút là một form POST nhỏ

Nút Xóa **không phải liên kết** mà là một `<form method="POST">` chỉ có **một ô ẩn** (id cần xóa) và nút gửi. Lý do: liên kết là GET, mà GET không được dùng để xóa (một đường link, một ảnh hay công cụ tải trước trang cũng có thể vô tình kích hoạt).

Mẫu chung (ví dụ `suppliers/list.php` dòng 116–119):

```php
<form class="d-inline" method="POST" action="<?= h(url('suppliers', 'delete')) ?>"
      onsubmit="return confirm('Xóa nhà cung cấp …? … Không thể hoàn tác.');">
    <input type="hidden" name="id" value="<?= (int) $s['supplier_id'] ?>">
    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
</form>
```
- `onsubmit="return confirm(...)"`: hộp xác nhận ở trình duyệt; bấm Hủy thì **không gửi**.
- `(int)` ép số nguyên khi in ra ô ẩn.

| Nút | Form HTML nằm ở | Ô ẩn | File xử lý | Kiểm tra / hành động | Xong thì chuyển tới |
|---|---|---|---|---|---|
| Xóa nhà cung cấp | `suppliers/list.php:116` | `id` | `suppliers/delete.php` | không tồn tại → báo lỗi (dòng 14–17); **đã có giao dịch thì không xóa** (dòng 20–24); xóa (dòng 27) kèm file logo | `suppliers/list` (dòng 36) |
| Xóa người liên hệ | `templates/assets/layouts/supplier-detail.php:83` | `contact_id` | `contacts/delete.php` | không tồn tại → báo lỗi; xóa (dòng 20) | `suppliers/detail&id=…` (dòng 22) |
| Đặt làm chính | `templates/assets/layouts/supplier-detail.php:77` | `contact_id` | `contacts/primary.php` | `transaction`: bỏ cờ chính của cả nhà cung cấp rồi đặt cờ cho người này (dòng 20–23) | `suppliers/detail&id=…` (dòng 25) |
| Xóa danh mục | `categories/list.php:45` | `category_id` | `categories/delete.php` | **còn nhà cung cấp thuộc danh mục thì không xóa** (dòng 19–23) | `categories/list` (dòng 34) |
| Xóa giao dịch | `transactions/list.php:143` | `transaction_id` | `transactions/delete.php` | xóa dòng + file hóa đơn (dòng 19–20) | `transactions/list&supplier_id=…` (dòng 22) |
| Xóa đánh giá | `evaluations/list.php:87` | `evaluation_id`, `back_supplier_id` | `evaluations/delete.php` | `transaction`: xóa + `recalcAvgRating()` (dòng 19–22) | `evaluations/list&supplier_id=…` (dòng 24) |
| Xóa tài khoản | `users/list.php:49` | `user_id` | `users/delete.php` | **không tự xóa chính mình** (dòng 13–16); đã tạo giao dịch/đánh giá thì không xóa, gợi ý khóa tài khoản (dòng 23–28) | `users/list` (dòng 39) |

**Điểm chung của mọi file xóa** (đều ở đầu file):

1. `requireLogin()`: chưa đăng nhập thì không vào được.
2. `if (!isPost()) redirect(...)`: **gõ thẳng URL (GET) vào file xóa thì không xóa gì**, bị đẩy về danh sách.
3. Đọc id bằng `postInt('…')` (vì id nằm trong **thân POST**), lấy bản ghi ra kiểm tra tồn tại.
4. Xóa trong `try/catch`: nếu MySQL báo vi phạm khóa ngoại (`isForeignKeyError`) thì báo lỗi dễ hiểu thay vì trang lỗi.
5. Cuối cùng luôn `setMsg(...)` + `redirect(...)`: **không có trang nào hiển thị trực tiếp từ file xóa**.

---

## 5. Các form GET (tìm kiếm, lọc): không ghi dữ liệu nên không cần redirect

| Form | Nằm ở | Các ô | Gửi tới |
|---|---|---|---|
| Tìm và lọc nhà cung cấp (quản trị) | `suppliers/list.php:47` | `q`, `category_id`, `status`, `rating_min` (+ ô ẩn `module=suppliers`, `action=list`, và tham số sắp xếp) | `suppliers/list.php` |
| Lọc giao dịch | `transactions/list.php:71` | `supplier_id`, `payment_status`, `due` (+ ô ẩn `module`, `action`) | `transactions/list.php` |
| Lọc đánh giá | `evaluations/list.php:41` | `supplier_id` (+ ô ẩn `module`, `action`) | `evaluations/list.php` |
| Tìm nhà cung cấp (khách) | `home/index.php:49` | `q` | `home/index.php` |
| Lọc và sắp xếp (khách) | `home/index.php:73` | `rating_min`, `order` | `home/index.php` |

**Luồng:** `<form method="GET" action=".../index.php">` → trình duyệt gửi `index.php?module=suppliers&action=list&q=abc&status=active` → chính file `list.php` đọc `$_GET` (bằng `getParam('q')`, `getInt('page')`), truy vấn có điều kiện, rồi **vẽ lại trang luôn**. Không `redirect`, không flash.

**Vì sao form GET phải có hai ô ẩn `module` và `action`?** Khi gửi form GET, trình duyệt **thay toàn bộ query string trên `action` bằng các ô của form**. Nếu thiếu hai ô ẩn này thì URL mất `module/action` và `index.php` quay về trang mặc định.

**Vì sao dùng GET cho lọc?** Bộ lọc nằm trên URL nên có thể lưu đánh dấu, chia sẻ, và bấm Back vẫn giữ nguyên bộ lọc. Các ô `<select>` có `onchange="this.form.submit()"` để chọn xong là lọc ngay không cần bấm nút.

**Đăng xuất** cũng không phải form: là liên kết GET (`templates/assets/layouts/sidebar.php:57`) → `auth/logout.php` xóa phiên, đặt `setMsg('Đã đăng xuất')` rồi `redirect` về đăng nhập.

---

## 6. Cách `id` đi vào file PHP: có 3 trường hợp

| Trường hợp | Ví dụ | Hàm đọc | Giải thích |
|---|---|---|---|
| id nằm **trên địa chỉ của form** (`action="…&id=N"`) | form Sửa (`edit`), form Thanh toán (`pay`), form Thêm liên hệ (`contacts/add&supplier_id=N`) | `getInt('id')` | Dù yêu cầu là GET (mở form) hay POST (gửi form) thì id vẫn nằm trên URL, nên cả hai lần đều đọc được. Ví dụ `suppliers/edit.php` dòng 10, `transactions/pay.php` dòng 10. |
| id nằm trong **ô ẩn** của form | các nút Xóa, nút Đặt làm chính | `postInt('id')`, `postInt('contact_id')`… | Thân POST. Action của form xóa không chứa id. |
| **Không có id** | form Thêm (`add`) | không cần | Bản ghi mới, id do MySQL tự tăng. |

Cả `getInt` và `postInt` chỉ nhận chuỗi toàn chữ số (`ctype_digit`), còn lại trả `0`, nên đưa chữ hay `-1` vào đều bị coi là "không tìm thấy".

---

## 7. Cơ chế "flash": vì sao thông báo chỉ hiện một lần

```
setMsg('Đã thêm …')            →  $_SESSION['msgFlash']  = '…'      (functions.php dòng 120)
setFormFlash($errors, $filter) →  $_SESSION['errorsFlash'] và $_SESSION['oldDataFlash'] (+ một msg màu đỏ)   (dòng 127)
        │
   redirect()  →  trình duyệt gọi GET trang kế tiếp
        │
getSessionFlash('errors') / flashHtml()   →  ĐỌC rồi XÓA ngay khỏi session  (session.php dòng 56–61)
        │
   Bấm F5 lần nữa  →  session đã trống  →  không còn thông báo
```

- Mọi dữ liệu lưu qua flash đều là **dữ liệu form đã `trim()` nhưng chưa thoát HTML**; chỉ khi in ra trang mới qua `h()` (`htmlspecialchars`), nên không bị chèn mã.
- Dữ liệu cũ **không** lưu mật khẩu (đăng nhập chỉ lưu `username`; đổi mật khẩu lưu mảng rỗng; thêm/sửa tài khoản có `unset($filter['password'])`).
- Khung thông báo in ở `templates/assets/layouts/header.php` dòng 23 (`flashHtml()`), ở `login.php` dòng 49, và `header-public.php` dòng 19 cho trang khách.

---

## 8. CÂU HỎI VẤN ĐÁP

**1. Form này gọi tới file nào? Làm sao biết?**
Nhìn thuộc tính `action` của thẻ `<form>`. Ví dụ form thêm nhà cung cấp có `action = url('suppliers','add')`, tức `index.php?module=suppliers&action=add`, nên `index.php` nạp `modules/suppliers/add.php`. Form đăng nhập có `action=""` nghĩa là gửi lại **chính URL đang mở** (`auth/login`).

**2. Trong project này "form.php" là file nào?**
Không có file tên đó. HTML của form nằm ở `_form.php` (dùng chung cho thêm và sửa), còn file được form gọi tới và xử lý là `add.php` hoặc `edit.php`.

**3. Cùng một file `add.php` mà vừa hiện form vừa xử lý, làm sao phân biệt?**
Bằng `isPost()` (dòng 9). Mở form là **GET** → bỏ qua khối `if`, chạy xuống phần vẽ trang. Gửi form là **POST** → chạy khối `if`: lọc, kiểm tra, ghi, rồi `redirect` (kết thúc luôn, không chạy xuống phần vẽ trang).

**4. Vì sao thêm/sửa/xóa dùng POST, còn tìm kiếm dùng GET?**
POST cho thao tác làm thay đổi dữ liệu: dữ liệu nằm trong thân yêu cầu, không lộ trên URL, không bị kích hoạt bởi liên kết hay trình tải trước trang. GET cho thao tác chỉ đọc: kết quả lọc nằm trên URL nên lưu, chia sẻ, bấm Back được.

**5. Sau khi lưu xong, vì sao phải `redirect` mà không hiện luôn trang danh sách?**
Để tránh gửi lại form khi bấm F5 (làm thêm trùng bản ghi). Đây là mẫu Post/Redirect/Get: POST xong thì chuyển sang một yêu cầu GET mới. Thông báo "Đã thêm" được chuyển qua session bằng flash.

**6. Khi nhập sai, vì sao các ô vẫn giữ nguyên giá trị đã gõ?**
`setFormFlash($errors, $filter)` cất lỗi và dữ liệu vừa nhập vào session trước khi redirect. Khi form được mở lại, `getSessionFlash('oldData')` lấy ra, và `_form.php` điền lại bằng `oldData($old, 'tên_ô', mặc định)`. Lỗi từng ô lấy từ `errors` qua `invalid()` và `formError()`.

**7. Flash là gì? Vì sao F5 thì thông báo mất?**
Flash là giá trị trong session chỉ sống đến lần đọc kế tiếp: `getSessionFlash()` đọc xong là xóa ngay (`session.php` dòng 56–61).

**8. Vì sao form thêm nhà cung cấp có `enctype="multipart/form-data"`?**
Vì có ô chọn file logo (`name="logo"`). Không có `enctype` này thì trình duyệt chỉ gửi tên file chứ không gửi nội dung file, `$_FILES` rỗng. Form không có file (danh mục, tài khoản...) thì không cần.

**9. `novalidate` để làm gì? Có bỏ kiểm tra ở trình duyệt thì có nguy hiểm không?**
`novalidate` tắt kiểm tra mặc định của trình duyệt để mọi lỗi do máy chủ báo bằng cùng một kiểu (viền đỏ + chữ dưới ô). Kiểm tra ở máy chủ mới là bắt buộc vì người dùng có thể bỏ qua trình duyệt, nên project kiểm tra **tất cả ở máy chủ** (`validate…`).

**10. Nếu gõ thẳng địa chỉ `index.php?module=suppliers&action=delete` vào thanh địa chỉ thì sao?**
Đó là yêu cầu GET. File `delete.php` có `if (!isPost()) redirect(...)` (dòng 8–10) nên **không xóa gì**, chỉ bị đưa về danh sách. Thêm nữa, chưa đăng nhập thì `requireLogin()` (dòng 6) đã chặn từ trước.

**11. Vì sao nút Xóa phải là form POST chứ không phải thẻ `<a>`?**
Thẻ `<a>` luôn là GET. Nếu xóa bằng GET thì bất kỳ liên kết, ảnh hay trình tải trước trang đều có thể xóa dữ liệu. Nút Xóa còn có `confirm()` ở `onsubmit` để hỏi lại trước khi gửi.

**12. Khi form Sửa gửi lên, file `edit.php` biết sửa bản ghi nào bằng cách nào?**
Bằng id nằm trên **địa chỉ của form**: `action = url('suppliers','edit',['id'=>$id])` (`edit.php` dòng 67). File đọc bằng `getInt('id')` ở dòng 10 (cả lúc mở form lẫn lúc gửi form), lấy bản ghi hiện tại để làm giá trị mặc định rồi `update(... WHERE supplier_id = :w_id)`.

**13. Ai chặn người chưa đăng nhập gửi POST thẳng vào `add.php`?**
`requireLogin()` ở đầu mọi file thêm/sửa/xóa (ví dụ `suppliers/add.php` dòng 6). Chưa đăng nhập thì bị chuyển về trang đăng nhập, không chạy tới `isPost()`.

**14. Người dùng bấm "Lưu" hai lần thật nhanh thì sao?**
Hai yêu cầu POST đến liên tiếp. Với trường hợp trùng mã/MST, ràng buộc UNIQUE của MySQL từ chối lần thứ hai, `isDuplicateError` bắt và hiển thị lỗi ở ô. Riêng thanh toán, `FOR UPDATE` trong `transaction` khóa dòng nên hai lần ghi không chồng lên nhau.

**15. Mật khẩu có được giữ lại khi form báo lỗi không?**
Không. Đăng nhập chỉ giữ lại `username`, đổi mật khẩu giữ mảng rỗng, thêm/sửa tài khoản có `unset($filter['password'])`. Ô mật khẩu cũng đọc thẳng từ `$_POST` chứ không qua `filterData()` vì mật khẩu **không được cắt khoảng trắng**.

---

## 9. Mẫu trả lời 30 giây khi thầy chỉ vào một nút bất kỳ

> "Nút này nằm trong `<form method="…" action="…">` ở file **…** (dòng …). Khi bấm, trình duyệt gửi yêu cầu **GET/POST** tới `index.php?module=**…**&action=**…**`, `index.php` nạp file `modules/**…**/**…**.php`. File đó chạy `requireLogin()`, kiểm tra `isPost()`, đọc dữ liệu bằng `filterData()`/`postInt()`, kiểm tra hợp lệ rồi **ghi/xóa database**. Xong thì `setMsg()` rồi `redirect` về **…** (thành công), hoặc `setFormFlash()` rồi `redirect` về lại form (thất bại), form đọc lỗi bằng `getSessionFlash()`."

Điền ba chỗ `…` từ bảng ở mục 3, 4 hoặc 5 là xong.
