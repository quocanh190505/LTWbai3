<?php
// layouts/head.php – phần <head> dùng chung cho mọi khung trang.
// Biến do trang đặt trước khi include: $pageTitle (tên trang), $bodyClass (class của <body>), $collapsible (true: nhớ trạng thái thu gọn menu).
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}
$pageTitle = $pageTitle ?? "Nhà cung cấp";
$bodyClass = $bodyClass ?? "";
$collapsible = $collapsible ?? false;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($pageTitle); ?> – Quản lý nhà cung cấp</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo h(asset("image/favicon.svg")); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo h(asset("css/style.css")); ?>" rel="stylesheet">
<?php if ($collapsible) { ?>
    <!-- Áp lại lựa chọn thu gọn menu ngay từ đầu để trang không bị giật khi tải -->
    <script>try { if (localStorage.getItem('sidebar') === 'collapsed') { document.documentElement.classList.add('sidebar-collapsed'); } } catch (e) {}</script>
<?php } ?>
</head>
<body<?php echo $bodyClass !== "" ? ' class="' . h($bodyClass) . '"' : ""; ?>>
