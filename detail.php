<?php
// detail.php – chi tiết một nhà cung cấp cho khách:  detail.php?id=N
// Chỉ xem được nhà cung cấp đang hoạt động; chỉ có người liên hệ chính và các đánh giá, không có dữ liệu nội bộ.
require_once __DIR__ . "/includes/config.php";

$id = getInt("id");
if (isLogin()) {                       // admin đã đăng nhập thì xem bản đầy đủ
    header("Location: " . ($id > 0 ? ROOT . "suppliers/detail.php?id=$id" : ROOT . "dashboard/index.php"));
    exit;
}

$detail = $id > 0 ? loadSupplierDetail($id, false) : null;
if ($detail === null) {
    header("Location: " . ROOT . "index.php?msg=not_found");
    exit;
}

$pageTitle = $detail["supplier"]["supplier_name"];
include LAYOUT_DIR . "header_public.php";
?>
<section class="container pub-section">
    <div class="page-header">
        <div>
            <h1><?php echo h($detail["supplier"]["supplier_name"]); ?></h1>
            <p class="page-sub">Chi tiết nhà cung cấp.</p>
        </div>
        <a class="btn btn-light" href="index.php#nhacungcap">Quay lại danh sách</a>
    </div>
    <?php supplierDetailHtml($detail, false); ?>
</section>
<?php include LAYOUT_DIR . "footer_public.php"; ?>
