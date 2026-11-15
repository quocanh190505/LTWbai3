<?php
// users/delete.php – xóa tài khoản. Chỉ nhận POST từ nút Xóa trong users/index.php.
// Tài khoản đã tạo giao dịch hoặc đánh giá thì không xóa được (khóa ngoại RESTRICT): hãy khóa tài khoản thay vì xóa.
require_once __DIR__ . "/../includes/config.php";
$admin = requireLogin();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . ROOT . "users/index.php");
    exit;
}

$id = toInt($_POST["user_id"] ?? "");
if ($id === (int) $admin["user_id"]) {
    header("Location: " . ROOT . "users/index.php?msg=user_delete_self");
    exit;
}
if ($id === 0 || getValue("SELECT user_id FROM users WHERE user_id = :id", ["id" => $id]) === null) {
    header("Location: " . ROOT . "users/index.php?msg=not_found");
    exit;
}

$transactions = (int) getValue("SELECT COUNT(*) FROM supply_transactions WHERE created_by = :id", ["id" => $id]);
$evaluations = (int) getValue("SELECT COUNT(*) FROM supplier_evaluations WHERE evaluated_by = :id", ["id" => $id]);
if ($transactions + $evaluations > 0) {
    header("Location: " . ROOT . "users/index.php?msg=user_in_use&n=$transactions&m=$evaluations");
    exit;
}

try {
    execute("DELETE FROM users WHERE user_id = :id", ["id" => $id]);
} catch (PDOException $e) {
    if (!isForeignKeyError($e)) {   // vừa có dữ liệu mới tham chiếu tới tài khoản này
        throw $e;
    }
    header("Location: " . ROOT . "users/index.php?msg=in_use");
    exit;
}
header("Location: " . ROOT . "users/index.php?msg=user_deleted");
exit;
