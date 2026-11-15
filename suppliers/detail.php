<?php
// suppliers/detail.php – chi tiết một nhà cung cấp cho admin:  suppliers/detail.php?id=N
// Gồm tóm tắt công nợ, thông tin, người liên hệ (thêm/sửa/xóa/đặt chính) và các đánh giá.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

$id = getInt("id");
$detail = $id > 0 ? loadSupplierDetail($id, true) : null;
if ($detail === null) {
    header("Location: " . ROOT . "suppliers/index.php?msg=not_found");
    exit;
}

$pageTitle = $detail["supplier"]["supplier_name"];
include LAYOUT_DIR . "header.php";
?>
<div class="page-header">
    <div>
        <h1><?php echo h($detail["supplier"]["supplier_name"]); ?></h1>
        <p class="page-sub">Chi tiết nhà cung cấp.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-light" href="<?php echo ROOT; ?>suppliers/index.php">Quay lại danh sách</a>
        <a class="btn btn-primary" href="<?php echo ROOT; ?>suppliers/form.php?id=<?php echo $id; ?>">Sửa</a>
    </div>
</div>
<?php
supplierDetailHtml($detail, true);
include LAYOUT_DIR . "footer.php";
