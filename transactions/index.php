<?php
// transactions/index.php – danh sách giao dịch và công nợ (chỉ admin).
// Bộ lọc là form GET gửi lại chính trang này:  transactions/index.php?supplier_id=&payment_status=&due=overdue|soon&page=
// Hai ô tổng ở trên tính trên toàn bộ kết quả của bộ lọc (không chỉ trang đang xem).
// Thêm/sửa: transactions/form.php.  Thanh toán: transactions/pay.php.  Xóa: form POST gửi tới transactions/delete.php.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

// ----- 1. Đọc bộ lọc từ địa chỉ và ghép điều kiện WHERE -----
$where = [];
$params = [];
$supplierId = getInt("supplier_id");
if ($supplierId > 0) {
    $where[] = "t.supplier_id = :supplier_id";
    $params["supplier_id"] = $supplierId;
}
$paymentStatus = in_array(getParam("payment_status"), PAYMENT_STATUSES, true) ? getParam("payment_status") : "";   // giá trị lạ thì bỏ qua
if ($paymentStatus !== "") {
    $where[] = "t.payment_status = :payment_status";
    $params["payment_status"] = $paymentStatus;
}
$due = array_key_exists(getParam("due"), DUE_FILTERS) ? getParam("due") : "";
if ($due === "overdue") {
    $where[] = overdueSql();
} elseif ($due === "soon") {
    $where[] = dueSoonSql();
}
$whereSql = $where ? " WHERE " . implode(" AND ", $where) : "";
$from = " FROM supply_transactions t JOIN suppliers s ON s.supplier_id = t.supplier_id JOIN users u ON u.user_id = t.created_by";

// ----- 2. Truy vấn: tổng, rồi đúng trang đang xem -----
$sum = getOne(
    "SELECT COUNT(*) AS n, COALESCE(SUM(t.total_amount), 0) AS total, COALESCE(SUM(t.remaining_amount), 0) AS remaining" . $from . $whereSql,
    $params
);
$total = (int) $sum["n"];
$lastPage = lastPageOf($total);
$page = min(currentPage(), $lastPage);   // trang vượt quá thì về trang cuối

$transactions = getAll(
    "SELECT t.transaction_id, t.supplier_id, s.supplier_code, s.supplier_name, t.invoice_number, t.transaction_date, t.due_date,
            t.total_amount, t.paid_amount, t.remaining_amount, t.payment_status,
            CASE WHEN " . overdueSql() . " THEN 1 ELSE 0 END AS is_overdue,
            CASE WHEN " . dueSoonSql() . " THEN 1 ELSE 0 END AS is_due_soon,
            CASE WHEN t.invoice_file_path IS NOT NULL THEN 1 ELSE 0 END AS has_invoice" . $from . $whereSql . "
     ORDER BY t.transaction_date DESC, t.transaction_id DESC LIMIT " . PER_PAGE . " OFFSET " . (($page - 1) * PER_PAGE),
    $params
);
$suppliers = supplierOptions();
$filters = ["supplier_id" => $supplierId, "payment_status" => $paymentStatus, "due" => $due];
$hasFilter = array_filter($filters) !== [];
$paymentLabels = ["unpaid" => "Chưa trả", "partial" => "Trả một phần", "paid" => "Đã trả đủ"];

// ----- 3. Hiện trang -----
$pageTitle = "Giao dịch";
include LAYOUT_DIR . "header.php";
pageHeader("Giao dịch và công nợ", "Hóa đơn mua hàng, số đã trả và số còn nợ.", "Thêm giao dịch", ROOT . "transactions/form.php" . qs(["supplier_id" => $supplierId]));
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="summary-card"><div class="text-muted">Tổng giá trị (theo bộ lọc)</div><div class="summary-value"><?php echo h(formatMoney($sum["total"])); ?></div></div>
    </div>
    <div class="col-md-4">
        <div class="summary-card"><div class="text-muted">Còn nợ (theo bộ lọc)</div><div class="summary-value<?php echo (float) $sum["remaining"] > 0 ? " debt" : ""; ?>"><?php echo h(formatMoney($sum["remaining"])); ?></div></div>
    </div>
</div>

<div class="card">
    <form class="toolbar" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <label class="visually-hidden" for="supplierFilter">Nhà cung cấp</label>
        <select class="form-select" id="supplierFilter" name="supplier_id" onchange="this.form.submit()">
            <option value="">Tất cả nhà cung cấp</option>
            <?php foreach ($suppliers as $s) { ?>
                <option value="<?php echo (int) $s["supplier_id"]; ?>"<?php echo selected($supplierId, $s["supplier_id"]); ?>><?php echo h($s["supplier_code"] . " – " . $s["supplier_name"]); ?></option>
            <?php } ?>
        </select>
        <label class="visually-hidden" for="statusFilter">Tình trạng thanh toán</label>
        <select class="form-select" id="statusFilter" name="payment_status" onchange="this.form.submit()">
            <option value="">Mọi tình trạng</option>
            <?php foreach ($paymentLabels as $value => $label) { ?>
                <option value="<?php echo $value; ?>"<?php echo selected($paymentStatus, $value); ?>><?php echo h($label); ?></option>
            <?php } ?>
        </select>
        <label class="visually-hidden" for="dueFilter">Hạn thanh toán</label>
        <select class="form-select" id="dueFilter" name="due" onchange="this.form.submit()">
            <option value="">Mọi hạn thanh toán</option>
            <?php foreach (DUE_FILTERS as $value => $label) { ?>
                <option value="<?php echo $value; ?>"<?php echo selected($due, $value); ?>><?php echo h($label); ?></option>
            <?php } ?>
        </select>
        <noscript><button type="submit" class="btn btn-primary">Lọc</button></noscript>
        <?php if ($hasFilter) { ?>
            <a class="btn btn-light" href="<?php echo ROOT; ?>transactions/index.php">Bỏ lọc</a>
        <?php } ?>
    </form>
    <div class="table-responsive">
        <table class="table table-hover table-compact mb-0">
            <thead>
                <tr>
                    <th>Nhà cung cấp</th>
                    <th>Hóa đơn</th>
                    <th>Hạn thanh toán</th>
                    <th class="text-end">Tổng</th>
                    <th class="text-end">Đã trả</th>
                    <th class="text-end">Còn nợ</th>
                    <th>Tình trạng</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$transactions) { ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Không có giao dịch phù hợp</td></tr>
            <?php } ?>
            <?php foreach ($transactions as $t) { ?>
                <?php $owes = (float) $t["remaining_amount"] > 0; ?>
                <tr>
                    <td><div class="supplier-name"><?php echo h($t["supplier_name"]); ?></div><div class="supplier-sub"><?php echo h($t["supplier_code"]); ?></div></td>
                    <td class="text-nowrap"><div class="fw-medium"><?php echo h($t["invoice_number"]); ?></div><div class="supplier-sub"><?php echo h(formatDate($t["transaction_date"])); ?></div></td>
                    <td>
                        <?php echo $t["due_date"] ? h(formatDate($t["due_date"])) : '<span class="text-muted">—</span>'; ?>
                        <?php if ((int) $t["is_overdue"] === 1) { ?>
                            <span class="badge badge-soft badge-soft-danger">Quá hạn</span>
                        <?php } elseif ((int) $t["is_due_soon"] === 1) { ?>
                            <span class="badge badge-soft badge-soft-warning">Sắp đến hạn</span>
                        <?php } ?>
                    </td>
                    <td class="text-end"><?php echo h(formatMoney($t["total_amount"])); ?></td>
                    <td class="text-end"><?php echo h(formatMoney($t["paid_amount"])); ?></td>
                    <td class="text-end<?php echo $owes ? " debt" : ""; ?>"><?php echo h(formatMoney($t["remaining_amount"])); ?></td>
                    <td><?php echo paymentBadge($t["payment_status"]); ?></td>
                    <td class="text-end text-nowrap actions">
                        <?php if ($t["payment_status"] !== "paid") { ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>transactions/pay.php?id=<?php echo (int) $t["transaction_id"]; ?>">Thanh toán</a>
                        <?php } ?>
                        <?php if ((int) $t["has_invoice"] === 1) { ?>
                            <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="<?php echo ROOT; ?>transactions/invoice.php?id=<?php echo (int) $t["transaction_id"]; ?>">Hóa đơn</a>
                        <?php } ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>transactions/form.php?id=<?php echo (int) $t["transaction_id"]; ?>">Sửa</a>
                        <form class="d-inline" method="post" action="<?php echo ROOT; ?>transactions/delete.php"
                              onsubmit="return confirm('Xóa hóa đơn «<?php echo h(addslashes($t["invoice_number"])); ?>»? File hóa đơn đính kèm (nếu có) cũng bị xóa. Không thể hoàn tác.');">
                            <input type="hidden" name="transaction_id" value="<?php echo (int) $t["transaction_id"]; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-muted"><?php echo h(resultInfo($page, PER_PAGE, $total, count($transactions), "giao dịch")); ?></span>
        <nav aria-label="Phân trang"><?php echo paginationHtml($page, $lastPage, $filters); ?></nav>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
