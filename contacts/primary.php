<?php
// contacts/primary.php – đặt một người làm liên hệ chính. Chỉ nhận POST từ nút "Đặt làm chính".
// Người chính cũ tự bị bỏ cờ trong cùng một giao dịch.
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
$supplierId = (int) $row["supplier_id"];

transaction(function () use ($id, $supplierId) {
    execute("UPDATE supplier_contacts SET is_primary = 0 WHERE supplier_id = :sid", ["sid" => $supplierId]);
    execute("UPDATE supplier_contacts SET is_primary = 1 WHERE contact_id = :id", ["id" => $id]);
});
header("Location: " . ROOT . "suppliers/detail.php?id=$supplierId&msg=contact_primary");
exit;
