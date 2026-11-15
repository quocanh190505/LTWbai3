# TÀI LIỆU LUỒNG HOẠT ĐỘNG

Đọc **`00`** trước: file này trả lời đúng câu hỏi "form HTML → file PHP → phương thức → trả về form", và mọi file còn lại chỉ là áp dụng lại khuôn đó.

| File | Nội dung | Mã nguồn chính |
|---|---|---|
| `00_cau_truc_va_mau_form_w3schools.md` | Khuôn chung của mọi trang, sơ đồ luồng, bảng thông báo `?msg=` | `functions.php`, `supplier_form.php` |
| `01_dang_nhap_dang_xuat_doi_mat_khau.md` | Đăng nhập, đăng xuất, đổi mật khẩu | `login.php`, `logout.php`, `change_password.php` |
| `02_trang_chu_khach_va_chi_tiet.md` | Trang công khai cho khách | `index.php`, `detail.php` |
| `03_danh_sach_nha_cung_cap.md` | Tìm kiếm, lọc, sắp xếp, phân trang, xuất Excel (GET) | `suppliers.php`, `supplier_export.php` |
| `04_them_sua_nha_cung_cap.md` | **Form mẫu**: thêm/sửa, kiểm tra từng ô, upload logo | `supplier_form.php` |
| `05_xoa_nha_cung_cap.md` | Xóa bằng POST, chặn xóa khi còn giao dịch | `supplier_delete.php` |
| `06_nguoi_lien_he.md` | Người liên hệ, liên hệ chính, giao dịch CSDL | `contact_form.php`, `contact_primary.php`, `contact_delete.php` |
| `07_giao_dich_va_thanh_toan.md` | Hóa đơn, thanh toán từng phần (`FOR UPDATE`), quá hạn | `transactions.php`, `transaction_*.php` |
| `08_danh_gia_danh_muc_tai_khoan_va_tong_quan.md` | Đánh giá, danh mục, tài khoản, tổng quan | `evaluation_*.php`, `categor*.php`, `user*.php`, `dashboard.php` |
| `09_cau_hoi_thuong_gap_khi_bao_ve.md` | Câu hỏi hay gặp khi bảo vệ và cách trả lời | cả project |

**Số dòng** trong tài liệu là số dòng của file hiện tại. Nếu sửa code, số dòng có thể lệch vài dòng; tên biến và tên hàm vẫn tìm được bằng chức năng tìm kiếm của trình soạn thảo.
