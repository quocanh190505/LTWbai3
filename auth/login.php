<?php
// auth/login.php – trang đăng nhập cho admin (khách không cần đăng nhập, họ xem trang chủ index.php).
// Luồng giống ví dụ "PHP Form Validation" của W3Schools:  form HTML  ->  gửi (POST) về CHÍNH file này  ->  kiểm tra  ->  đúng thì chuyển trang, sai thì hiện lại form kèm lỗi.
require_once __DIR__ . "/../includes/config.php";

if (isLogin()) {                     // đã đăng nhập rồi thì vào thẳng trang tổng quan
    header("Location: " . ROOT . "dashboard/index.php");
    exit;
}

// 1. Khai báo biến: giá trị các ô nhập và câu báo lỗi (ban đầu đều rỗng)
$username = $password = "";
$usernameErr = $passwordErr = $loginErr = "";

// 2. Khi form được gửi (POST): lấy dữ liệu và kiểm tra từng ô
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = test_input($_POST["username"] ?? "");
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";   // mật khẩu không được cắt khoảng trắng

    if ($username == "") {
        $usernameErr = "Vui lòng nhập tên đăng nhập";
    }
    if ($password == "") {
        $passwordErr = "Vui lòng nhập mật khẩu";
    }

    // 3. Không có lỗi ở ô nào thì kiểm tra tài khoản trong database
    if ($usernameErr == "" && $passwordErr == "") {
        if (loginAdmin($username, $password)) {
            header("Location: " . ROOT . "dashboard/index.php?msg=login_ok");   // đúng: chuyển sang trang tổng quan
            exit;
        }
        $loginErr = "Sai tên đăng nhập hoặc mật khẩu";   // một câu chung: không cho biết tài khoản có tồn tại hay không
    }
}

// 4. Hiện form (lần đầu mở trang, hoặc sau khi có lỗi). Ô tên đăng nhập giữ lại giá trị vừa gõ.
$pageTitle = "Đăng nhập";
include LAYOUT_DIR . "header_auth.php";
?>
<div class="auth-shell">
    <?php echo brandHtml(ROOT . "index.php"); ?>

    <div class="auth-card">
        <h1>Đăng nhập</h1>
        <?php echo showMessage(); ?>
        <?php if ($loginErr != "") { ?>
            <div class="alert alert-danger" role="alert"><?php echo h($loginErr); ?></div>
        <?php } ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
            <div class="mb-3">
                <label class="form-label" for="username">Tên đăng nhập</label>
                <input class="form-control<?php echo invalid($usernameErr); ?>" id="username" name="username" value="<?php echo h($username); ?>" autocomplete="username" autofocus>
                <div class="invalid-feedback"><?php echo h($usernameErr); ?></div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Mật khẩu</label>
                <input class="form-control<?php echo invalid($passwordErr); ?>" type="password" id="password" name="password" autocomplete="current-password">
                <div class="invalid-feedback"><?php echo h($passwordErr); ?></div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Đăng nhập</button>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer_auth.php"; ?>
