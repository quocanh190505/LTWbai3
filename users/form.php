<?php
// users/form.php – form THÊM và SỬA tài khoản quản trị; một file cho cả hai việc.
//   users/form.php       -> form trống, thêm mới (bắt buộc nhập mật khẩu)
//   users/form.php?id=N  -> form điền sẵn, sửa tài khoản N (để trống mật khẩu = giữ mật khẩu cũ)
//
// Luồng: form HTML -> POST về chính file này -> kiểm tra từng ô -> lỗi thì hiện lại form + câu báo lỗi,
// không lỗi thì ghi database rồi chuyển về users/index.php kèm thông báo.
// Mật khẩu KHÔNG BAO GIỜ được điền lại vào form (kể cả khi có lỗi); trong database chỉ lưu mã băm password_hash().
require_once __DIR__ . "/../includes/config.php";
$admin = requireLogin();

// ----- 1. Khai báo biến -----
$id = toInt($_POST["id"] ?? $_GET["id"] ?? "");   // có id = đang sửa
$username = $fullName = "";
$role = "admin";
$isActive = "1";
$usernameErr = $passwordErr = $fullNameErr = $roleErr = $isActiveErr = "";

if ($id > 0) {
    $existing = getOne("SELECT user_id, username, full_name, role, is_active FROM users WHERE user_id = :id", ["id" => $id]);
    if ($existing === null) {
        header("Location: " . ROOT . "users/index.php?msg=not_found");
        exit;
    }
}
$isSelf = $id > 0 && $id === (int) $admin["user_id"];   // đang sửa chính tài khoản đang đăng nhập

// ----- 2. Khi form được gửi (POST): lấy dữ liệu và kiểm tra -----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = test_input($_POST["username"] ?? "");
    $fullName = test_input($_POST["full_name"] ?? "");
    $role     = test_input($_POST["role"] ?? "");
    $isActive = test_input($_POST["is_active"] ?? "");
    // Mật khẩu không được cắt khoảng trắng (khoảng trắng có thể là một phần của mật khẩu). bcrypt chỉ dùng 72 byte đầu.
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if ($username == "") {
        $usernameErr = "Vui lòng nhập tên đăng nhập";
    } elseif (!valid_username($username)) {
        $usernameErr = "Tên đăng nhập gồm 3–50 ký tự: chữ, số, dấu chấm, gạch ngang hoặc gạch dưới";
    } elseif (getValue("SELECT user_id FROM users WHERE LOWER(username) = LOWER(:username) AND user_id <> :id", ["username" => $username, "id" => $id]) !== null) {
        $usernameErr = "Tên đăng nhập đã tồn tại";
    }

    if ($fullName == "") {
        $fullNameErr = "Vui lòng nhập họ tên";
    } elseif (mb_strlen($fullName) > 100) {
        $fullNameErr = "Họ tên tối đa 100 ký tự";
    }

    if (!in_array($role, ["admin", "staff"], true)) {
        $roleErr = "Vai trò không hợp lệ";
    }

    if ($isActive !== "0" && $isActive !== "1") {
        $isActiveErr = "Trạng thái không hợp lệ";
    }

    if ($id == 0 || $password !== "") {   // thêm thì bắt buộc; sửa mà để trống thì giữ mật khẩu cũ
        if ($password === "") {
            $passwordErr = "Vui lòng nhập mật khẩu";
        } elseif (strlen($password) < 8) {
            $passwordErr = "Mật khẩu tối thiểu 8 ký tự";
        } elseif (strlen($password) > 72) {
            $passwordErr = "Mật khẩu tối đa 72 ký tự";
        }
    }

    if ($isSelf) {   // không tự khóa hoặc tự hạ quyền chính mình
        if ($isActiveErr == "" && $isActive !== "1") {
            $isActiveErr = "Bạn không thể tự khóa tài khoản của chính mình";
        }
        if ($roleErr == "" && $role !== "admin") {
            $roleErr = "Bạn không thể tự hạ quyền của chính mình";
        }
    }

    // ----- 3. Không có lỗi: ghi database -----
    if ($usernameErr == "" && $passwordErr == "" && $fullNameErr == "" && $roleErr == "" && $isActiveErr == "") {
        try {
            if ($id > 0) {
                $sql = "UPDATE users SET username = :username, full_name = :full_name, role = :role, is_active = :is_active";
                $values = ["username" => $username, "full_name" => $fullName, "role" => $role, "is_active" => (int) $isActive, "id" => $id];
                if ($password !== "") {   // chỉ đổi mật khẩu khi có nhập
                    $sql .= ", password_hash = :password_hash";
                    $values["password_hash"] = password_hash($password, PASSWORD_DEFAULT);
                }
                execute($sql . " WHERE user_id = :id", $values);
                header("Location: " . ROOT . "users/index.php?msg=user_updated");
            } else {
                execute(
                    "INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (:username, :password_hash, :full_name, :role, :is_active)",
                    [
                        "username" => $username, "password_hash" => password_hash($password, PASSWORD_DEFAULT),
                        "full_name" => $fullName, "role" => $role, "is_active" => (int) $isActive,
                    ]
                );
                header("Location: " . ROOT . "users/index.php?msg=user_added");
            }
            exit;
        } catch (PDOException $e) {
            if (!isDuplicateError($e)) {
                throw $e;
            }
            $usernameErr = "Tên đăng nhập đã tồn tại";   // hai người cùng tạo một tên đăng nhập trong cùng lúc
        }
    }
} elseif ($id > 0) {
    // Mở form sửa lần đầu (GET): điền sẵn dữ liệu đang có
    $username = $existing["username"];
    $fullName = $existing["full_name"];
    $role     = $existing["role"];
    $isActive = (string) (int) $existing["is_active"];
}

// ----- 4. Hiện form -----
$pageTitle = $id > 0 ? "Sửa tài khoản" : "Thêm tài khoản";
include LAYOUT_DIR . "header.php";
pageHeader($pageTitle, $id > 0 ? $existing["username"] : "Tạo tài khoản cho người được phép đăng nhập quản trị.");
?>
<div class="card" style="max-width: 640px">
    <div class="card-body p-4">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate autocomplete="off">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="mb-3">
                <label class="form-label" for="u_username">Tên đăng nhập <span class="text-danger">*</span></label>
                <input class="form-control<?php echo invalid($usernameErr); ?>" id="u_username" name="username" maxlength="50" autocomplete="off"
                       value="<?php echo h($username); ?>">
                <div class="invalid-feedback"><?php echo h($usernameErr); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="u_password">Mật khẩu <?php echo $id > 0 ? "" : '<span class="text-danger">*</span>'; ?></label>
                <input class="form-control<?php echo invalid($passwordErr); ?>" type="password" id="u_password" name="password" maxlength="72" autocomplete="new-password">
                <div class="form-text"><?php echo $id > 0 ? "Để trống nếu không đổi mật khẩu." : "Tối thiểu 8 ký tự."; ?></div>
                <div class="invalid-feedback"><?php echo h($passwordErr); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="u_full_name">Họ tên <span class="text-danger">*</span></label>
                <input class="form-control<?php echo invalid($fullNameErr); ?>" id="u_full_name" name="full_name" maxlength="100" autocomplete="off"
                       value="<?php echo h($fullName); ?>">
                <div class="invalid-feedback"><?php echo h($fullNameErr); ?></div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="u_role">Vai trò</label>
                    <select class="form-select<?php echo invalid($roleErr); ?>" id="u_role" name="role">
                        <option value="admin"<?php echo selected($role, "admin"); ?>>Quản trị (đăng nhập được)</option>
                        <option value="staff"<?php echo selected($role, "staff"); ?>>Nhân viên (không đăng nhập được)</option>
                    </select>
                    <div class="invalid-feedback"><?php echo h($roleErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="u_is_active">Trạng thái</label>
                    <select class="form-select<?php echo invalid($isActiveErr); ?>" id="u_is_active" name="is_active">
                        <option value="1"<?php echo selected($isActive, "1"); ?>>Hoạt động</option>
                        <option value="0"<?php echo selected($isActive, "0"); ?>>Đã khóa</option>
                    </select>
                    <div class="invalid-feedback"><?php echo h($isActiveErr); ?></div>
                </div>
            </div>
            <?php if ($isSelf) { ?>
                <p class="text-muted mt-3 mb-0">Đây là tài khoản của bạn: không thể tự khóa hoặc hạ quyền.</p>
            <?php } ?>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Lưu</button>
                <a class="btn btn-light" href="<?php echo ROOT; ?>users/index.php">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
