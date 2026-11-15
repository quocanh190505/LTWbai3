<?php
// suppliers/export.php – xuất Excel (.xlsx): cùng bộ lọc và cách sắp xếp với suppliers/index.php nhưng lấy toàn bộ kết quả
// (tối đa 5000 dòng). Chỉ admin: file có dữ liệu nội bộ (mã số thuế, hạn mức, công nợ).
// Là GET: trình duyệt tải file về, trang đang xem không đổi. Cách tạo file .xlsx xem includes/xlsx.php.
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/xlsx.php";
requireLogin();

[$whereSql, $params] = supplierListFilters(true);
$rows = getAll(supplierSelectSql(true) . $whereSql . supplierOrderSql(true) . " LIMIT 5000", $params);

$columns = [
    ["title" => "Mã",               "width" => 14, "type" => "text"],
    ["title" => "Tên nhà cung cấp", "width" => 36, "type" => "text"],
    ["title" => "Danh mục",         "width" => 20, "type" => "text"],
    ["title" => "Mã số thuế",       "width" => 16, "type" => "text"],
    ["title" => "Email",            "width" => 30, "type" => "text"],
    ["title" => "Điện thoại",       "width" => 16, "type" => "text"],
    ["title" => "Địa chỉ",          "width" => 40, "type" => "text"],
    ["title" => "Hạn mức",          "width" => 18, "type" => "money"],
    ["title" => "Còn nợ",           "width" => 18, "type" => "money"],
    ["title" => "Điểm đánh giá",    "width" => 14, "type" => "rating"],
    ["title" => "Trạng thái",       "width" => 18, "type" => "text"],
];
$table = [];
foreach ($rows as $r) {
    $table[] = [
        $r["supplier_code"], $r["supplier_name"], $r["category_name"], $r["tax_code"], $r["email"], $r["phone"], $r["address"],
        money($r["credit_limit"]), money($r["remaining_amount"]),
        (float) $r["avg_rating"] > 0 ? number_format((float) $r["avg_rating"], 2, ".", "") : null,   // 0 nghĩa là chưa có đánh giá: để ô trống
        STATUS_LABELS[$r["status"]] ?? $r["status"],
    ];
}
$file = xlsx_build("Nhà cung cấp", $columns, $table);

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header('Content-Disposition: attachment; filename="nha-cung-cap-' . date("Ymd-His") . '.xlsx"');
header("Content-Length: " . strlen($file));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");
echo $file;
exit;
