# 09. CÂU HỎI THƯỜNG GẶP KHI BẢO VỆ (và cách trả lời)

Mọi câu trả lời dưới đây đều chỉ ra được **file và dòng** trong project để mở lên chứng minh.

---

## A. Về luồng form (câu thầy hay hỏi nhất)

**1. Luồng của một form trong project là gì?**
> Form HTML → bấm nút → trình duyệt gửi **POST** về **chính file PHP đó** → PHP chạy `if ($_SERVER["REQUEST_METHOD"] == "POST")`, lấy dữ liệu, kiểm tra từng ô → **có lỗi**: in lại form, giữ giá trị đã gõ, báo lỗi cạnh ô → **không lỗi**: ghi database rồi `header("Location: ...")` chuyển về danh sách.

Ví dụ rõ nhất: `supplier_form.php` (form dòng 175, nhận POST dòng 32, kiểm tra 43–91, ghi 97–153, chuyển trang 130/137 + `exit` dòng 139, in lại form dòng 167 trở đi). Sơ đồ ở `00_cau_truc_va_mau_form_w3schools.md`.

**2. Sao không tách thành `form.html` và `xuly.php` như ví dụ cơ bản?**
> Ví dụ "PHP Form Validation" của W3Schools cũng để form và phần xử lý chung một file, vì khi có lỗi phải **hiện lại form với giá trị đã gõ**. Tách hai file thì phải truyền lỗi và dữ liệu qua lại bằng session hoặc tham số, phức tạp hơn mà không lợi gì.

**3. `action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"` nghĩa là gì?**
> `PHP_SELF` là đường dẫn của file đang chạy, nên form gửi về chính nó. `htmlspecialchars` bắt buộc phải có: nếu không, người ta gõ địa chỉ kiểu `supplier_form.php/"><script>...` thì mã độc chèn thẳng vào trang (XSS).

**4. Vì sao form sửa phải có `<input type="hidden" name="id">`?**
> `PHP_SELF` **không** mang theo phần `?id=5` trên địa chỉ. Muốn lần gửi POST biết đang sửa NCC nào thì phải gửi `id` lại bằng ô ẩn (`supplier_form.php` dòng 176; đọc ở dòng 15: `$_POST["id"] ?? $_GET["id"]`).

**5. GET và POST khác nhau, dùng khi nào?**
> **GET**: đọc, lọc, tìm kiếm (`suppliers.php?q=...`); tham số hiện trên địa chỉ, F5 an toàn, chia sẻ link được. **POST**: làm thay đổi dữ liệu (thêm, sửa, xóa, đăng nhập, thanh toán); dữ liệu nằm trong thân yêu cầu, không hiện trên địa chỉ và không bị trình duyệt hoặc công cụ quét link tự gọi.

**6. Sao sau khi lưu phải `header("Location: ...")` rồi `exit`?**
> Để tránh **gửi lại form khi bấm F5** (nếu trả luôn trang kết quả thì F5 sẽ POST lần nữa và thêm bản ghi thứ hai). Chuyển sang trang danh sách bằng GET thì F5 chỉ tải lại danh sách. Mẫu này gọi là Post/Redirect/Get. `exit` bắt buộc để PHP dừng, không chạy tiếp phần in HTML bên dưới.

**7. Thông báo "Đã thêm nhà cung cấp" hiện bằng cách nào khi đã chuyển trang?**
> Đi theo địa chỉ: `suppliers.php?msg=supplier_added`. Trang danh sách gọi `showMessage()` (`functions.php` dòng 243), tra mã trong một bảng **cố định**; mã lạ thì không hiện gì nên không ai chèn chữ tùy ý vào thông báo.

**8. Sao không dùng AJAX?**
> Yêu cầu của đề là PHP form chuẩn: mỗi thao tác tải lại trang. Cách này đơn giản, dễ theo dõi từng bước, và vẫn đầy đủ chức năng.

---

## B. Về bảo mật

**9. Chống SQL injection thế nào?**
> Mọi câu SQL có dữ liệu người dùng đều dùng **prepared statement** với tham số có tên (`:id`, `:code`) qua bốn hàm `getAll/getOne/getValue/execute` (`functions.php` dòng 91–114). Dữ liệu không bao giờ được nối vào chuỗi SQL. Chỗ duy nhất ghép chuỗi là tên cột để sắp xếp, và nó lấy từ bảng cố định `SUPPLIER_SORTS` (dòng 661), không lấy từ người dùng.

**10. Chống XSS thế nào?**
> `h()` (= `htmlspecialchars`, `functions.php` dòng 26) bọc **mọi** dữ liệu in ra HTML. Dữ liệu lưu vào database là chữ gốc, thoát ký tự lúc in.

**11. `test_input()` của project khác W3Schools chỗ nào?**
> W3Schools làm `trim` + `stripslashes` + `htmlspecialchars` ngay lúc nhận. Ở đây `test_input()` chỉ `trim` (dòng 17–24) và thoát HTML lúc **in** bằng `h()`; nếu thoát lúc lưu thì database lưu `&amp;` thay vì `&`, và thoát hai lần sẽ hiện sai. `stripslashes` bỏ vì PHP hiện đại không còn tự thêm dấu `\`.

**12. Mật khẩu lưu thế nào?**
> Băm bằng `password_hash()` (bcrypt), kiểm tra bằng `password_verify()` (`functions.php` dòng 228). Không lưu mật khẩu thô. Đăng nhập sai báo chung một câu, và khi tên không tồn tại vẫn chạy kiểm tra với một mã băm giả để thời gian trả lời không lộ tên đăng nhập có thật.

**13. Chống chiếm phiên?**
> `session_regenerate_id(true)` sau khi đăng nhập (dòng 232) và sau khi đổi mật khẩu (`change_password.php` dòng 48); cookie phiên có `httponly` và `samesite=Lax` (`config.php` dòng 27–32).

**14. Sao xóa là nút POST chứ không phải liên kết?**
> Liên kết `?id=5` có thể bị tự gọi (trình duyệt tải trước, ai đó gửi link) và mất dữ liệu. Nút Xóa là form POST kèm hộp xác nhận `confirm()`; gõ thẳng địa chỉ file xóa (GET) chỉ bị chuyển về danh sách (`supplier_delete.php` dòng 7–10).

**15. Chặn người chưa đăng nhập vào trang quản trị thế nào?**
> Dòng đầu của mọi trang quản trị: `requireLogin();` (`functions.php` dòng 211): chưa đăng nhập thì chuyển về `login.php`. Các file phụ (`header.php`, `functions.php`...) có `if (!defined("APP_START")) exit(...)` nên mở thẳng trên trình duyệt cũng không chạy.

**16. Upload tệp an toàn thế nào?**
> Kiểm tra **loại thật** của tệp bằng `finfo` (đọc nội dung, không tin đuôi tên tệp), giới hạn dung lượng (logo 2MB, hóa đơn 5MB), lưu với **tên ngẫu nhiên**, không dùng tên người dùng đặt. Hóa đơn nằm trong thư mục bị chặn bằng `.htaccess` và chỉ xem được qua `transaction_invoice.php` sau khi kiểm tra đăng nhập, có `realpath` chống `../`.

---

## C. Về dữ liệu và nghiệp vụ

**17. Vì sao phải dùng `transaction()` (giao dịch)?**
> Khi một thao tác gồm **nhiều câu SQL phải xong cùng nhau**: đặt người liên hệ chính (bỏ cờ cũ + đặt cờ mới), ghi đánh giá + tính lại điểm trung bình, thanh toán. Có lỗi giữa chừng thì `rollBack` trả mọi thứ về như cũ (`functions.php` dòng 125).

**18. `FOR UPDATE` ở thanh toán để làm gì?**
> Khóa dòng hóa đơn để hai người cùng trả một lúc không làm "đã trả" vượt tổng (`transaction_pay.php` dòng 34). Giải thích đầy đủ ở `07`.

**19. `remaining_amount` và `payment_status` lưu thế nào?**
> Là **cột sinh tự động** của MySQL (tính từ `total_amount`, `paid_amount`). PHP chỉ ghi hai cột gốc, nên không bao giờ lệch. Tiền tính bằng "xu" (số nguyên) trong PHP để tránh sai số số thực.

**20. Làm sao không xóa nhầm dữ liệu đang dùng?**
> Danh mục còn NCC, NCC còn giao dịch, tài khoản đã tạo giao dịch đều **không xóa được** và có thông báo nói rõ lý do (`category_delete.php` dòng 19–23, `supplier_delete.php` dòng 19–23, `user_delete.php` dòng 22–27). Phòng trường hợp phát sinh đúng lúc đó, khóa ngoại của MySQL vẫn chặn và `catch` báo lại bằng lời dễ hiểu.

**21. Khách (chưa đăng nhập) thấy gì khác admin?**
> Trang chủ `index.php` và `detail.php` công khai, nhưng khách chỉ thấy NCC **đang hoạt động** và **người liên hệ chính**; không thấy mã số thuế, hạn mức, công nợ. Cùng một hàm `loadSupplierDetail($id, $isAdmin)` quyết định cái nào hiện.

**22. Thêm một trường mới (ví dụ "Website") cho nhà cung cấp thì sửa những đâu?**
> Bốn chỗ: (1) database: `ALTER TABLE suppliers ADD website VARCHAR(150) NULL`; (2) `supplier_form.php`: thêm biến `$website`, `$websiteErr`, đoạn kiểm tra, cột trong `UPDATE`/`INSERT`, và ô nhập trong form; (3) `supplierSelectSql()` nếu muốn hiện ở danh sách; (4) `supplierDetailHtml()` nếu muốn hiện ở chi tiết.

**23. Chạy trên máy khác cần làm gì?**
> Xem `README.md` ở thư mục gốc: import `database/schema.sql` và `database/seed.sql`, tạo admin bằng `database/make_hash.php`, chỉnh 4 hằng `DB_*` ở đầu `config.php`.
