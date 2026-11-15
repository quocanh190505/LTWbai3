<?php
// contacts/delete.php – xóa người liên hệ. Chỉ nhận POST từ nút Xóa ở trang chi tiết nhà cung cấp.
// Xóa người chính thì nhà cung cấp tạm không có người chính; muốn có lại thì bấm "Đặt làm chính" ở người khác.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . ROOT . "suppliers/index.php");
    exit;
}

$id = toInt($_POST["contact_id"] ?? "");
$row = $id > 0 ? getOne("SELECT supplier_id FROM supplier_contacts WHERE contact_id = :id", ["id" => $id]) : null;
if ($row === null) {
    header("Location: " . ROOT . "suppliers/index.php?msg=not_found");
    exit;
}

execute("DELETE FROM supplier_contacts WHERE contact_id = :id", ["id" => $id]);
header("Location: " . ROOT . "suppliers/detail.php?id=" . (int) $row["supplier_id"] . "&msg=contact_deleted");
exit;
