<?php
// layouts/header_auth.php – đầu trang đăng nhập / báo lỗi: không có thanh menu. layouts/footer_auth.php đóng lại.
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}
$pageTitle = $pageTitle ?? "Đăng nhập";
$bodyClass = "";
$collapsible = false;
include LAYOUT_DIR . "head.php";
?>
<main class="auth-main">
