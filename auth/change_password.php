<?php
// auth/change_password.php – admin tự đổi mật khẩu của chính mình. Phải nhập đúng mật khẩu hiện tại
// (người ngồi nhầm máy đang đăng nhập không đổi được). Mật khẩu mới: 8 đến 72 ký tự (bcrypt chỉ đọc 72 byte đầu).
// Mật khẩu không bị cắt khoảng trắng và không bao giờ được in lại vào form.
require_once __DIR__ . "/../includes/config.php";
$admin = requireLogin();

// 1. Khai báo biến
$currentErr = $newErr = $confirmErr = "";

// 2. Khi form được gửi: kiểm tra từng ô
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current = is_string($_POST["current_password"] ?? null) ? $_POST["current_password"] : "";
    $new     = is_string($_POST["new_password"] ?? null) ? $_POST["new_password"] : "";
    $confirm = is_string($_POST["confirm_password"] ?? null) ? $_POST["confirm_password"] : "";

    if ($current == "") {
        $currentErr = "Vui lòng nhập mật khẩu hiện tại";
    } else {
        $hash = getValue("SELECT password_hash FROM users WHERE user_id = :id", ["id" => $admin["user_id"]]);
        if (!is_string($hash) || !password_verify($current, $hash)) {
            $currentErr = "Mật khẩu hiện tại không đúng";
        }
    }

    if ($new == "") {
        $newErr = "Vui lòng nhập mật khẩu mới";
    } elseif (strlen($new) < 8) {
        $newErr = "Mật khẩu mới tối thiểu 8 ký tự";
    } elseif (strlen($new) > 72) {
        $newErr = "Mật khẩu mới tối đa 72 ký tự";
    } elseif ($new === $current) {
        $newErr = "Mật khẩu mới phải khác mật khẩu hiện tại";
    }

    if ($confirm == "") {
        $confirmErr = "Vui lòng nhập lại mật khẩu mới";
    } elseif ($new != "" && $confirm !== $new) {
        $confirmErr = "Nhập lại chưa khớp với mật khẩu mới";
    }

    // 3. Không có lỗi: lưu mã băm của mật khẩu mới rồi chuyển trang
    if ($currentErr == "" && $newErr == "" && $confirmErr == "") {
        execute(
            "UPDATE users SET password_hash = :hash WHERE user_id = :id",
            ["hash" => password_hash($new, PASSWORD_DEFAULT), "id" => $admin["user_id"]]
        );
        session_regenerate_id(true);   // đổi mã phiên sau một thay đổi nhạy cảm; phiên hiện tại vẫn đăng nhập
        header("Location: " . ROOT . "dashboard/index.php?msg=password_changed");
        exit;
    }
}

// 4. Hiện form
$pageTitle = "Đổi mật khẩu";
include LAYOUT_DIR . "header.php";
pageHeader("Đổi mật khẩu", "Đổi mật khẩu đăng nhập của chính bạn.");
?>
<div class="card" style="max-width: 560px">
    <div class="card-body p-4">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate autocomplete="off">
            <div class="mb-3">
                <label class="form-label" for="pw_current">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                <input class="form-control<?php echo invalid($currentErr); ?>" type="password" id="pw_current" name="current_password" maxlength="72" autocomplete="current-password">
                <div class="invalid-feedback"><?php echo h($currentErr); ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="pw_new">Mật khẩu mới <span class="text-danger">*</span></label>
                <input class="form-control<?php echo invalid($newErr); ?>" type="password" id="pw_new" name="new_password" maxlength="72" autocomplete="new-password">
                <div class="form-text">Từ 8 đến 72 ký tự, phải khác mật khẩu hiện tại.</div>
                <div class="invalid-feedback"><?php echo h($newErr); ?></div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="pw_confirm">Nhập lại mật khẩu mới <span class="text-danger">*</span></label>
                <input class="form-control<?php echo invalid($confirmErr); ?>" type="password" id="pw_confirm" name="confirm_password" maxlength="72" autocomplete="new-password">
                <div class="invalid-feedback"><?php echo h($confirmErr); ?></div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                <a class="btn btn-light" href="<?php echo ROOT; ?>dashboard/index.php">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
