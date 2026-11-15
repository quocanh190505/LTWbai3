<?php
// includes/config.php – cấu hình chung. Đầu mỗi trang chỉ cần một dòng:  require_once __DIR__ . "/../includes/config.php";  (trang ở gốc project thì viết __DIR__ . "/includes/config.php")
// File này làm 3 việc: (1) khai báo cấu hình, (2) nối database + mở session, (3) nạp các hàm dùng chung.
const APP_START = true;   // các file phụ (connect, functions, header...) kiểm tra hằng này để không bị mở thẳng trên trình duyệt

// ----- 1. Cấu hình -----
// Kết nối MySQL (Laragon mặc định: tài khoản root, không có mật khẩu)
const DB_HOST = "127.0.0.1";
const DB_NAME = "supplier_management";
const DB_USER = "root";
const DB_PASS = "";

const SESSION_NAME = "LTW_BAI3";   // tên riêng cho cookie phiên, để không đụng các bài khác chạy trên cùng localhost
const PER_PAGE = 10;               // số dòng mỗi trang ở các trang danh sách

// Đường dẫn. APP_DIR: thư mục gốc của project trên ổ đĩa; INC_DIR, LAYOUT_DIR: hai thư mục chứa file dùng chung.
// ROOT: tiền tố đưa địa chỉ về gốc project. Trang ở gốc (index.php, detail.php) có ROOT = "", trang trong thư mục con có ROOT = "../".
// Mọi liên kết, action của form và header("Location") đều viết  ROOT . "thư_mục/trang.php"  nên trang nào gọi cũng đúng.
define("APP_DIR", dirname(__DIR__));
const INC_DIR = __DIR__ . "/";
const LAYOUT_DIR = __DIR__ . "/../layouts/";
define("ROOT", is_dir(dirname($_SERVER["SCRIPT_FILENAME"] ?? "") . "/includes") ? "" : "../");   // thư mục của trang có sẵn "includes" => đang ở gốc

// true: hiện chi tiết lỗi khi đang làm bài. Khi nộp bài hoặc chạy thật thì đổi thành false.
const DEBUG = true;
ini_set("display_errors", DEBUG ? "1" : "0");
error_reporting(E_ALL);

// ----- 2. Kết nối database ($conn) và mở session -----
require_once __DIR__ . "/connect.php";

session_name(SESSION_NAME);
ini_set("session.use_strict_mode", "1");       // không nhận mã phiên do người khác tự đặt
ini_set("session.gc_maxlifetime", "7200");     // phiên sống tối đa 2 giờ không hoạt động
session_set_cookie_params([
    "lifetime" => 0,
    "path"     => "/",
    "secure"   => !empty($_SERVER["HTTPS"]),
    "httponly" => true,      // JavaScript không đọc được cookie
    "samesite" => "Lax",     // trang web khác không gửi kèm cookie này được
]);
session_start();

// ----- 3. Các hàm dùng chung -----
require_once __DIR__ . "/functions.php";
