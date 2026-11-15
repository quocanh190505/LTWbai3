<?php
// categories/delete.php – xóa danh mục. Chỉ nhận POST từ nút Xóa trong categories/index.php.
// Danh mục còn nhà cung cấp thì không xóa được.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . ROOT . "categories/index.php");
    exit;
}

$id = toInt($_POST["category_id"] ?? "");
if ($id === 0 || getValue("SELECT category_id FROM supplier_categories WHERE category_id = :id", ["id" => $id]) === null) {
    header("Location: " . ROOT . "categories/index.php?msg=not_found");
    exit;
}

// Khóa ngoại RESTRICT: danh mục còn nhà cung cấp thì MySQL không cho xóa; báo trước bằng lời dễ hiểu
$count = (int) getValue("SELECT COUNT(*) FROM suppliers WHERE category_id = :id", ["id" => $id]);
if ($count > 0) {
    header("Location: " . ROOT . "categories/index.php?msg=category_has_suppliers&n=$count");
    exit;
}

try {
    execute("DELETE FROM supplier_categories WHERE category_id = :id", ["id" => $id]);
} catch (PDOException $e) {
    if (!isForeignKeyError($e)) {   // vừa có nhà cung cấp được thêm vào đúng lúc này
        throw $e;
    }
    header("Location: " . ROOT . "categories/index.php?msg=in_use");
    exit;
}
header("Location: " . ROOT . "categories/index.php?msg=category_deleted");
exit;
