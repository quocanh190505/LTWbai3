<?php
// auth/logout.php – đăng xuất: xóa session và cookie phiên rồi quay về trang đăng nhập.
require_once __DIR__ . "/../includes/config.php";

$_SESSION = [];                                   // xóa mọi dữ liệu trong phiên
$p = session_get_cookie_params();
setcookie(SESSION_NAME, "", [                     // xóa cookie phiên trên trình duyệt
    "expires"  => time() - 3600,
    "path"     => $p["path"],
    "domain"   => $p["domain"],
    "secure"   => $p["secure"],
    "httponly" => true,
    "samesite" => "Lax",
]);
session_destroy();

header("Location: " . ROOT . "auth/login.php?msg=logged_out");
exit;
