<?php
// suppliers/delete.php – xóa nhà cung cấp. Chỉ nhận POST từ nút Xóa trong suppliers/index.php (không có giao diện, xử lý xong chuyển trang).
// Đã có giao dịch thì không xóa để giữ sổ sách (gợi ý chuyển sang "ngừng hoạt động").
require_once __DIR__ . "/../includes/config.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: " . ROOT . "suppliers/index.php");
    exit;
}

$id = toInt($_POST["id"] ?? "");
$row = $id > 0 ? getOne("SELECT supplier_id, logo_url FROM suppliers WHERE supplier_id = :id", ["id" => $id]) : null;
if ($row === null) {
    header("Location: " . ROOT . "suppliers/index.php?msg=not_found");
    exit;
}

$transactions = (int) getValue("SELECT COUNT(*) FROM supply_transactions WHERE supplier_id = :id", ["id" => $id]);
if ($transactions > 0) {
    header("Location: " . ROOT . "suppliers/index.php?msg=supplier_has_transactions&n=$transactions");
    exit;
}

try {
    execute("DELETE FROM suppliers WHERE supplier_id = :id", ["id" => $id]);   // người liên hệ và đánh giá xóa theo (CASCADE)
    delete_upload($row["logo_url"]);
} catch (PDOException $e) {
    if (!isForeignKeyError($e)) {
        throw $e;
    }
    header("Location: " . ROOT . "suppliers/index.php?msg=in_use");   // vừa có giao dịch phát sinh ngay lúc này
    exit;
}
header("Location: " . ROOT . "suppliers/index.php?msg=supplier_deleted");
exit;
