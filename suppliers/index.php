<?php
// suppliers/index.php – danh sách nhà cung cấp cho admin: tìm kiếm, lọc, sắp xếp, phân trang.
// Tất cả là GET (địa chỉ có đủ tham số nên gửi link cho người khác vẫn thấy đúng kết quả):
//   suppliers/index.php?q=&category_id=&status=&rating_min=&has_debt=&sort=&dir=&page=
// Nút Tìm là một form GET gửi lại chính trang này; sắp xếp và phân trang là các liên kết giữ nguyên bộ lọc.
// Thêm / sửa: suppliers/form.php.  Xóa: form POST gửi tới suppliers/delete.php.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

[$whereSql, $params] = supplierListFilters(true);
$total = (int) getValue("SELECT COUNT(*) FROM suppliers s" . $whereSql, $params);
$lastPage = lastPageOf($total);
$page = min(currentPage(), $lastPage);   // trang vượt quá thì về trang cuối

$suppliers = getAll(
    supplierSelectSql(true) . $whereSql . supplierOrderSql(true)
    . " LIMIT " . PER_PAGE . " OFFSET " . (($page - 1) * PER_PAGE),
    $params
);
$categories = categoryOptions();

$filters = supplierFilterParams(true);
[$sortKey, $sortDir] = supplierSort(true);
$sortParams = $sortKey === "code" ? [] : ["sort" => $sortKey, "dir" => $sortDir];
$hasFilter = array_filter($filters) !== [];

$pageTitle = "Nhà cung cấp";
include LAYOUT_DIR . "header.php";
?>
<div class="page-header">
    <div>
        <h1>Nhà cung cấp</h1>
        <p class="page-sub">Tra cứu nhà cung cấp, người liên hệ và đánh giá.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary d-inline-flex align-items-center gap-2" href="<?php echo ROOT; ?>suppliers/export.php<?php echo h(qs($filters + $sortParams)); ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>
            Xuất Excel
        </a>
        <a class="btn btn-primary d-inline-flex align-items-center gap-2" href="<?php echo ROOT; ?>suppliers/form.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Thêm nhà cung cấp
        </a>
    </div>
</div>

<div class="card">
    <form class="toolbar" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" role="search">
        <?php foreach ($sortParams as $name => $value) { ?>
            <input type="hidden" name="<?php echo h($name); ?>" value="<?php echo h($value); ?>">
        <?php } ?>
        <div class="search-field">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <label class="visually-hidden" for="searchInput">Tìm kiếm</label>
            <input type="search" class="form-control" id="searchInput" name="q" value="<?php echo h(getParam("q")); ?>" autocomplete="off" placeholder="Tìm theo mã hoặc tên">
            <button type="submit" class="btn btn-primary">Tìm</button>
        </div>
        <label class="visually-hidden" for="categoryFilter">Danh mục</label>
        <select class="form-select" id="categoryFilter" name="category_id" onchange="this.form.submit()">
            <option value="">Tất cả danh mục</option>
            <?php foreach ($categories as $c) { ?>
                <option value="<?php echo (int) $c["category_id"]; ?>"<?php echo selected(getInt("category_id"), $c["category_id"]); ?>><?php echo h($c["category_name"]); ?></option>
            <?php } ?>
        </select>
        <label class="visually-hidden" for="statusFilter">Trạng thái</label>
        <select class="form-select" id="statusFilter" name="status" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <?php foreach (STATUS_LABELS as $value => $label) { ?>
                <option value="<?php echo $value; ?>"<?php echo selected(getParam("status"), $value); ?>><?php echo h($label); ?></option>
            <?php } ?>
        </select>
        <label class="visually-hidden" for="ratingFilter">Điểm đánh giá</label>
        <select class="form-select" id="ratingFilter" name="rating_min" onchange="this.form.submit()">
            <option value="">Mọi điểm đánh giá</option>
            <?php foreach ([4, 3, 2] as $n) { ?>
                <option value="<?php echo $n; ?>"<?php echo selected(getInt("rating_min"), $n); ?>>Từ <?php echo $n; ?> sao trở lên</option>
            <?php } ?>
        </select>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="debtFilter" name="has_debt" value="1"<?php echo getParam("has_debt") === "1" ? " checked" : ""; ?> onchange="this.form.submit()">
            <label class="form-check-label" for="debtFilter">Chỉ nhà cung cấp còn nợ</label>
        </div>
        <?php if ($hasFilter) { ?>
            <a class="btn btn-light" href="<?php echo ROOT; ?>suppliers/index.php<?php echo h(qs($sortParams)); ?>">Bỏ lọc</a>
        <?php } ?>
    </form>
    <div class="table-responsive">
        <table class="table table-hover table-compact mb-0">
            <thead>
                <tr>
                    <th aria-sort="<?php echo ariaSort("name", [$sortKey, $sortDir]); ?>"><?php echo sortHeader("Nhà cung cấp", "name", [$sortKey, $sortDir], $filters); ?></th>
                    <th>Danh mục</th>
                    <th>Điện thoại</th>
                    <th aria-sort="<?php echo ariaSort("rating", [$sortKey, $sortDir]); ?>"><?php echo sortHeader("Đánh giá", "rating", [$sortKey, $sortDir], $filters); ?></th>
                    <th class="text-end" aria-sort="<?php echo ariaSort("debt", [$sortKey, $sortDir]); ?>"><?php echo sortHeader("Còn nợ", "debt", [$sortKey, $sortDir], $filters); ?></th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$suppliers) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Không tìm thấy nhà cung cấp phù hợp</td></tr>
            <?php } ?>
            <?php foreach ($suppliers as $s) { ?>
                <tr>
                    <td><?php echo supplierCell($s); ?></td>
                    <td><span class="chip"><?php echo h($s["category_name"]); ?></span></td>
                    <td class="num"><?php echo textOrDash($s["phone"]); ?></td>
                    <td class="text-nowrap"><?php echo ratingHtml($s["avg_rating"], 1); ?></td>
                    <td class="text-end"><?php echo debtCell($s); ?></td>
                    <td><?php echo statusBadge($s["status"]); ?></td>
                    <td class="text-end text-nowrap actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>suppliers/detail.php?id=<?php echo (int) $s["supplier_id"]; ?>">Xem</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>suppliers/form.php?id=<?php echo (int) $s["supplier_id"]; ?>">Sửa</a>
                        <form class="d-inline" method="post" action="<?php echo ROOT; ?>suppliers/delete.php"
                              onsubmit="return confirm('Xóa nhà cung cấp «<?php echo h(addslashes($s["supplier_name"])); ?>»? Người liên hệ và đánh giá của nhà cung cấp này cũng bị xóa. Không thể hoàn tác.');">
                            <input type="hidden" name="id" value="<?php echo (int) $s["supplier_id"]; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-muted"><?php echo h(resultInfo($page, PER_PAGE, $total, count($suppliers), "nhà cung cấp")); ?></span>
        <nav aria-label="Phân trang"><?php echo paginationHtml($page, $lastPage, $filters + $sortParams); ?></nav>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
