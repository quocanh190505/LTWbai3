<?php
// evaluations/index.php – danh sách đánh giá nhà cung cấp (chỉ admin).
// Bộ lọc theo nhà cung cấp là form GET gửi lại chính trang này:  evaluations/index.php?supplier_id=N
// Không chọn nhà cung cấp: phân trang 10 dòng. Có chọn: hiện hết đánh giá của nhà cung cấp đó (mỗi nhà cung cấp có ít đánh giá).
// Thêm/sửa: evaluations/form.php.  Xóa: form POST gửi tới evaluations/delete.php.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

$supplierId = getInt("supplier_id");
$select = "SELECT e.evaluation_id, e.supplier_id, s.supplier_code, s.supplier_name, e.evaluation_date,
                  e.quality_score, e.delivery_score, e.service_score, e.overall_score, e.comment, u.full_name AS evaluated_by_name
           FROM supplier_evaluations e
           JOIN suppliers s ON s.supplier_id = e.supplier_id
           JOIN users u ON u.user_id = e.evaluated_by";
$order = " ORDER BY e.evaluation_date DESC, e.evaluation_id DESC";

if ($supplierId > 0) {
    $evaluations = getAll($select . " WHERE e.supplier_id = :sid" . $order, ["sid" => $supplierId]);
    $total = count($evaluations);
    $page = 1;
    $lastPage = 1;
    $info = $total . " đánh giá của nhà cung cấp này";
} else {
    $total = (int) getValue("SELECT COUNT(*) FROM supplier_evaluations");
    $lastPage = lastPageOf($total);
    $page = min(currentPage(), $lastPage);   // trang vượt quá thì về trang cuối
    $evaluations = getAll($select . $order . " LIMIT " . PER_PAGE . " OFFSET " . (($page - 1) * PER_PAGE));
    $info = resultInfo($page, PER_PAGE, $total, count($evaluations), "đánh giá");
}
$suppliers = supplierOptions();

$pageTitle = "Đánh giá";
include LAYOUT_DIR . "header.php";
pageHeader("Đánh giá nhà cung cấp", "Điểm chất lượng, giao hàng và dịch vụ của từng nhà cung cấp.", "Thêm đánh giá", ROOT . "evaluations/form.php" . qs(["supplier_id" => $supplierId]));
?>
<div class="card">
    <form class="toolbar" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <label class="visually-hidden" for="supplierFilter">Nhà cung cấp</label>
        <select class="form-select" id="supplierFilter" name="supplier_id">
            <option value="">Tất cả nhà cung cấp</option>
            <?php foreach ($suppliers as $s) { ?>
                <option value="<?php echo (int) $s["supplier_id"]; ?>"<?php echo selected($supplierId, $s["supplier_id"]); ?>><?php echo h($s["supplier_code"] . " – " . $s["supplier_name"]); ?></option>
            <?php } ?>
        </select>
        <button type="submit" class="btn btn-primary">Lọc</button>
        <?php if ($supplierId > 0) { ?>
            <a class="btn btn-light" href="<?php echo ROOT; ?>evaluations/index.php">Bỏ lọc</a>
        <?php } ?>
    </form>
    <div class="table-responsive">
        <table class="table table-hover table-compact mb-0">
            <thead>
                <tr>
                    <th>Nhà cung cấp</th>
                    <th>Ngày</th>
                    <th>Chất lượng</th>
                    <th>Giao hàng</th>
                    <th>Dịch vụ</th>
                    <th>Tổng</th>
                    <th>Nhận xét</th>
                    <th>Người đánh giá</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$evaluations) { ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Chưa có đánh giá nào</td></tr>
            <?php } ?>
            <?php foreach ($evaluations as $e) { ?>
                <tr>
                    <td><div class="supplier-name"><?php echo h($e["supplier_name"]); ?></div><div class="supplier-sub"><?php echo h($e["supplier_code"]); ?></div></td>
                    <td><?php echo h(formatDate($e["evaluation_date"])); ?></td>
                    <td><?php echo (int) $e["quality_score"]; ?></td>
                    <td><?php echo (int) $e["delivery_score"]; ?></td>
                    <td><?php echo (int) $e["service_score"]; ?></td>
                    <td class="fw-medium"><span class="star-gold">★</span> <?php echo number_format((float) $e["overall_score"], 2, ".", ""); ?></td>
                    <td><?php echo textOrDash($e["comment"]); ?></td>
                    <td><?php echo textOrDash($e["evaluated_by_name"]); ?></td>
                    <td class="text-end text-nowrap actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>evaluations/form.php?id=<?php echo (int) $e["evaluation_id"]; ?>">Sửa</a>
                        <form class="d-inline" method="post" action="<?php echo ROOT; ?>evaluations/delete.php"
                              onsubmit="return confirm('Xóa đánh giá của «<?php echo h(addslashes($e["supplier_name"])); ?>»? Điểm trung bình của nhà cung cấp sẽ được tính lại. Không thể hoàn tác.');">
                            <input type="hidden" name="evaluation_id" value="<?php echo (int) $e["evaluation_id"]; ?>">
                            <input type="hidden" name="back_supplier_id" value="<?php echo $supplierId; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-muted"><?php echo h($info); ?></span>
        <nav aria-label="Phân trang"><?php echo paginationHtml($page, $lastPage, ["supplier_id" => $supplierId]); ?></nav>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
