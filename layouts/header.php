<?php
// layouts/header.php – đầu trang của khu quản trị: <head>, thanh menu bên trái, thanh trên cùng cho điện thoại, khung thông báo.
// Trang nào cũng mở đầu phần HTML bằng:   $pageTitle = "Tên trang"; include LAYOUT_DIR . "header.php";
// Chưa đăng nhập thì chuyển về auth/login.php. layouts/footer.php đóng các thẻ được mở ở đây.
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}
require_once __DIR__ . "/../includes/config.php";
$pageTitle = $pageTitle ?? "Quản trị";
$admin = requireLogin();
$bodyClass = "has-sidebar";
$collapsible = true;
include LAYOUT_DIR . "head.php";

// Mục menu: [địa chỉ trang, chữ hiển thị, biểu tượng, các thư mục thuộc mục này (để tô sáng mục đang xem)]
$current = basename(dirname($_SERVER["PHP_SELF"]));   // tên thư mục chứa trang đang xem, ví dụ "suppliers"
$menu = [
    [ROOT . "dashboard/index.php",    "Tổng quan",     "dashboard",    ["dashboard"]],
    [ROOT . "suppliers/index.php",    "Nhà cung cấp",  "suppliers",    ["suppliers", "contacts"]],
    [ROOT . "categories/index.php",   "Danh mục",      "categories",   ["categories"]],
    [ROOT . "transactions/index.php", "Giao dịch",     "transactions", ["transactions"]],
    [ROOT . "evaluations/index.php",  "Đánh giá",      "evaluations",  ["evaluations"]],
    [ROOT . "users/index.php",        "Tài khoản",     "users",        ["users"]],
];
// Biểu tượng nét mảnh (SVG, lưới 24x24)
$icons = [
    "dashboard"    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    "suppliers"    => '<path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/>',
    "categories"   => '<path d="M4 6h16M4 12h16M4 18h10"/>',
    "transactions" => '<path d="M7 8h13l-3-3M17 16H4l3 3"/>',
    "evaluations"  => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
    "users"        => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    "password"     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
    "logout"       => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>',
];
$icon = fn($name) => '<svg class="side-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . "</svg>";
?>
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar" aria-label="Menu quản trị">
        <?php echo brandHtml(ROOT . "dashboard/index.php", "sidebar-brand"); ?>
        <nav class="sidebar-nav" aria-label="Chức năng quản trị">
            <ul class="sidebar-list">
                <?php foreach ($menu as [$file, $label, $iconName, $prefixes]) { ?>
                    <?php
                    $active = false;
                    foreach ($prefixes as $prefix) {
                        if (str_starts_with($current, $prefix)) {
                            $active = true;
                        }
                    }
                    ?>
                    <li>
                        <a class="side-link<?php echo $active ? " active" : ""; ?>"<?php echo $active ? ' aria-current="page"' : ""; ?>
                           href="<?php echo $file; ?>"><?php echo $icon($iconName); ?><span><?php echo $label; ?></span></a>
                    </li>
                <?php } ?>
            </ul>
        </nav>
        <div class="sidebar-user">
            <div class="sidebar-person">
                <span class="avatar" aria-hidden="true"><?php echo h(nameInitials($admin["full_name"])); ?></span>
                <span class="user-meta">
                    <span class="user-name"><?php echo h($admin["full_name"]); ?></span>
                    <span class="user-role">Quản trị viên · <?php echo h($admin["username"]); ?></span>
                </span>
            </div>
            <a class="side-link<?php echo $current === "auth" ? " active" : ""; ?>" href="<?php echo ROOT; ?>auth/change_password.php"><?php echo $icon("password"); ?><span>Đổi mật khẩu</span></a>
            <a class="side-link" href="<?php echo ROOT; ?>auth/logout.php"><?php echo $icon("logout"); ?><span>Đăng xuất</span></a>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>

    <div class="app-content">
        <div class="app-topbar">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-controls="appSidebar" aria-expanded="true" aria-label="Mở hoặc đóng menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <?php echo brandHtml(ROOT . "dashboard/index.php", "topbar-brand"); ?>
        </div>
<main class="app-main">
<?php echo showMessage(); ?>
