<?php
// connect.php – kết nối MySQL bằng PDO. Biến $conn dùng chung cho cả project (các hàm trong functions.php dùng lại nó).
// Cách viết giống ví dụ "PHP Connect to MySQL" (PDO) trên W3Schools.
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lỗi SQL ném ngoại lệ thay vì im lặng
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // mỗi dòng là mảng khóa theo tên cột
            PDO::ATTR_EMULATE_PREPARES   => false,                    // dùng prepared statement thật của MySQL
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo "<!DOCTYPE html><meta charset='utf-8'><title>Lỗi kết nối</title>";
    echo "<h1>Không kết nối được cơ sở dữ liệu</h1>";
    echo "<p>Kiểm tra Laragon đã bật MySQL chưa và thông tin trong <code>config.php</code> (DB_HOST, DB_NAME, DB_USER, DB_PASS).</p>";
    if (DEBUG) {
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
    exit;
}
