# 01. ĐĂNG NHẬP, ĐĂNG XUẤT, ĐỔI MẬT KHẨU

**File**: `login.php`, `logout.php`, `change_password.php`  
**Quy tắc**: chỉ tài khoản có `role = admin` và `is_active = 1` mới vào được khu quản trị. Khách không cần tài khoản (xem `02`).

---

## 1. ĐĂNG NHẬP – `login.php`

Đây là ví dụ **sát nhất** với mẫu W3Schools (form ít ô, dễ đọc nhất để giải thích).

```mermaid
sequenceDiagram
    autonumber
    actor U as Người dùng
    participant L as login.php
    participant DB as users
    U->>L: GET login.php
    L-->>U: Form đăng nhập
    U->>L: POST login.php (username, password)
    L->>L: dòng 17-25: test_input, ô rỗng thì gán $usernameErr / $passwordErr
    L->>DB: loginAdmin(): SELECT theo username (functions.php dòng 219)
    alt Sai (không có TK, sai mật khẩu, không phải admin, bị khóa)
        L-->>U: 200: in lại form, MỘT câu chung "Sai tên đăng nhập hoặc mật khẩu"
    else Đúng
        L->>L: session_regenerate_id(true); $_SESSION["user_id"] = ...
        L-->>U: 302 dashboard.php?msg=login_ok
    end
```

| Dòng | Việc |
|---|---|
| 6–9 | Đã đăng nhập rồi mà mở `login.php` → chuyển thẳng `dashboard.php` |
| 12–13 | Khai báo `$username`, `$password`, `$usernameErr`, `$passwordErr`, `$loginErr` đều `""` |
| 16 | `if ($_SERVER["REQUEST_METHOD"] == "POST")` |
| 18 | Mật khẩu **không** `trim` (khoảng trắng có thể là một phần của mật khẩu) |
| 29–33 | `loginAdmin()` đúng → `header("Location: dashboard.php?msg=login_ok")`; sai → `$loginErr` |
| 37–40 | Hiện form; ô tên đăng nhập giữ lại giá trị vừa gõ, ô mật khẩu **không bao giờ** điền lại |

### Hàm `loginAdmin()` (`functions.php` dòng 219–231)
1. Tìm tài khoản theo `username` bằng prepared statement.
2. `password_verify($password, $hash)`. Nếu không có tài khoản vẫn kiểm tra với **một mã băm giả** để thời gian trả lời không tiết lộ "tài khoản này có tồn tại".
3. Từ chối nếu: không có tài khoản, sai mật khẩu, `role` khác `admin`, hoặc `is_active` khác 1 → **cùng một câu thông báo** cho mọi trường hợp.
4. Đúng: `session_regenerate_id(true)` (đổi mã phiên chống chiếm phiên) rồi lưu `$_SESSION["user_id"]`.

### Mỗi request đều kiểm tra lại người dùng
`currentUser()` (dòng 183) **đọc lại bảng users ở mỗi request**: admin bị khóa hoặc bị hạ quyền giữa chừng sẽ bị đá ra ngay, dù cookie phiên vẫn còn.

---

## 2. ĐĂNG XUẤT – `logout.php`

Không có giao diện: xóa `$_SESSION`, xóa cookie phiên, `session_destroy()`, rồi `header("Location: login.php?msg=logged_out")`. Trang đăng nhập hiện "Đã đăng xuất".

---

## 3. ĐỔI MẬT KHẨU – `change_password.php`

Form 3 ô: `current_password`, `new_password`, `confirm_password`. Cũng theo khung 4 bước:

| Ô | Lỗi hiện cạnh ô |
|---|---|
| Mật khẩu hiện tại | "Vui lòng nhập…" / "Mật khẩu hiện tại không đúng" (so bằng `password_verify` với mã băm trong DB) |
| Mật khẩu mới | trống / dưới 8 ký tự / trên 72 ký tự / "phải khác mật khẩu hiện tại" |
| Nhập lại | trống / "chưa khớp với mật khẩu mới" |

Đúng hết → `password_hash($new, PASSWORD_DEFAULT)` lưu vào DB, `session_regenerate_id(true)`, chuyển `dashboard.php?msg=password_changed`.
