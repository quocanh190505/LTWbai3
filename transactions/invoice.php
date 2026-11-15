<?php
// transactions/invoice.php – xem file hóa đơn đính kèm:  transactions/invoice.php?id=N
// Thư mục uploads/invoices bị chặn truy cập trực tiếp (uploads/invoices/.htaccess), nên file chỉ ra được qua trang này
// sau khi đã kiểm tra đăng nhập.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

$id = getInt("id");
$row = $id > 0 ? getOne("SELECT invoice_file_path FROM supply_transactions WHERE transaction_id = :id", ["id" => $id]) : null;
if ($row === null) {
    header("Location: " . ROOT . "transactions/index.php?msg=not_found");
    exit;
}
if ($row["invoice_file_path"] === null) {
    header("Location: " . ROOT . "transactions/index.php?msg=invoice_none");
    exit;
}

// Chỉ phát file nằm trong uploads/invoices (chặn đường dẫn kiểu ../)
$base = realpath(APP_DIR . "/uploads/invoices");
$full = realpath(APP_DIR . "/" . $row["invoice_file_path"]);
if ($base === false || $full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR) || !is_file($full)) {
    header("Location: " . ROOT . "transactions/index.php?msg=invoice_missing");
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();   // bỏ mọi chữ đã đệm để file không bị lẫn
}
header("Content-Type: " . (new finfo(FILEINFO_MIME_TYPE))->file($full));
header("X-Content-Type-Options: nosniff");   // trình duyệt không đoán lại loại file
header("Content-Length: " . filesize($full));
header('Content-Disposition: inline; filename="hoa-don-' . $id . "." . pathinfo($full, PATHINFO_EXTENSION) . '"');
readfile($full);
exit;
