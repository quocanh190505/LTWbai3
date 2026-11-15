<?php
// layouts/header_public.php – đầu trang công khai cho khách (không cần đăng nhập): đầu trang kiểu website + liên kết "Đăng nhập quản trị".
// Dùng:   $pageTitle = "Tên trang"; include LAYOUT_DIR . "header_public.php";    layouts/footer_public.php đóng lại.
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}
require_once __DIR__ . "/../includes/config.php";
$pageTitle = $pageTitle ?? "Nhà cung cấp";
$bodyClass = "is-public";
$collapsible = false;
include LAYOUT_DIR . "head.php";
?>
<header class="pub-header">
    <div class="container pub-bar">
        <?php echo brandHtml(ROOT . "index.php"); ?>
        <nav class="pub-nav" aria-label="Điều hướng">
            <a href="<?php echo ROOT; ?>index.php#danhmuc">Danh mục</a>
            <a href="<?php echo ROOT; ?>index.php#nhacungcap">Nhà cung cấp</a>
        </nav>
        <a class="btn btn-sm btn-outline-primary pub-login" href="<?php echo ROOT; ?>auth/login.php">Đăng nhập quản trị</a>
    </div>
</header>
<main class="pub-main">
<?php $notice = showMessage(); if ($notice !== "") { ?>
<div class="container pt-3"><?php echo $notice; ?></div>
<?php } ?>
