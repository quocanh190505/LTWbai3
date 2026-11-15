<?php
// transactions/delete.php – xóa giao dịch. Chỉ nhận POST từ nút Xóa trong transactions/index.php; file hóa đơn đính kèm cũng bị xóa.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . ROOT . "transactions/index.php");
    exit;
}

$id = toInt($_POST["transaction_id"] ?? "");
$row = $id > 0 ? getOne("SELECT supplier_id, invoice_file_path FROM supply_transactions WHERE transaction_id = :id", ["id" => $id]) : null;
if ($row === null) {
    header("Location: " . ROOT . "transactions/index.php?msg=not_found");
    exit;
}

execute("DELETE FROM supply_transactions WHERE transaction_id = :id", ["id" => $id]);
delete_upload($row["invoice_file_path"]);
header("Location: " . ROOT . "transactions/index.php?supplier_id=" . (int) $row["supplier_id"] . "&msg=transaction_deleted");
exit;
