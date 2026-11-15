<?php
// evaluations/delete.php – xóa đánh giá. Chỉ nhận POST từ nút Xóa trong evaluations/index.php,
// rồi tính lại điểm trung bình của nhà cung cấp (cùng một giao dịch).
require_once __DIR__ . "/../includes/config.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . ROOT . "evaluations/index.php");
    exit;
}

$id = toInt($_POST["evaluation_id"] ?? "");
$existing = $id > 0 ? getOne("SELECT supplier_id FROM supplier_evaluations WHERE evaluation_id = :id", ["id" => $id]) : null;
if ($existing === null) {
    header("Location: " . ROOT . "evaluations/index.php?msg=not_found");
    exit;
}

transaction(function () use ($id, $existing) {
    execute("DELETE FROM supplier_evaluations WHERE evaluation_id = :id", ["id" => $id]);
    recalcAvgRating((int) $existing["supplier_id"]);
});
header("Location: " . ROOT . "evaluations/index.php" . qs(["supplier_id" => toInt($_POST["back_supplier_id"] ?? "")]) . (toInt($_POST["back_supplier_id"] ?? "") > 0 ? "&" : "?") . "msg=evaluation_deleted");
exit;
