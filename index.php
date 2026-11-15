<?php
// index.php – trang chủ công khai cho khách (không cần đăng nhập): tra cứu nhà cung cấp, chỉ xem.
//   index.php?q=&category_id=&rating_min=&order=&page=
// Khách chỉ thấy nhà cung cấp đang hoạt động và không nhận dữ liệu nội bộ (mã số thuế, hạn mức, công nợ).
// Các ô tìm/lọc là form GET gửi về chính trang này; phân trang là liên kết giữ nguyên bộ lọc.
require_once __DIR__ . "/includes/config.php";

if (isLogin()) {                       // admin đã đăng nhập thì chuyển sang trang tổng quan
    header("Location: " . ROOT . "dashboard/index.php");
    exit;
}

// Ô "Sắp xếp" của khách gửi một giá trị duy nhất (order); đổi sang cặp sort + dir mà supplierSort() đọc
$guestOrders = [
    ""            => ["Sắp xếp theo mã", "code", "asc"],
    "name_asc"    => ["Tên A → Z", "name", "asc"],
    "name_desc"   => ["Tên Z → A", "name", "desc"],
    "rating_desc" => ["Đánh giá cao nhất", "rating", "desc"],
    "rating_asc"  => ["Đánh giá thấp nhất", "rating", "asc"],
];
$order = array_key_exists(getParam("order"), $guestOrders) ? getParam("order") : "";
$_GET["sort"] = $guestOrders[$order][1];
$_GET["dir"] = $guestOrders[$order][2];

$perPage = 12;
[$whereSql, $params] = supplierListFilters(false);
$total = (int) getValue("SELECT COUNT(*) FROM suppliers s" . $whereSql, $params);
$lastPage = lastPageOf($total, $perPage);
$page = min(currentPage(), $lastPage);
$suppliers = getAll(
    supplierSelectSql(false) . $whereSql . supplierOrderSql(false) . " LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
    $params
);
$categories = categoryOptions();

$filters = supplierFilterParams(false) + ["order" => $order !== "" ? $order : null];
$categoryId = getInt("category_id");

$pageTitle = "Danh sách nhà cung cấp";
include LAYOUT_DIR . "header_public.php";
?>
<section class="hero">
    <svg class="hero-art" viewBox="0 0 400 400" fill="none" stroke="#fff" stroke-width="2" stroke-linejoin="round" aria-hidden="true">
        <path d="M200 40 L340 120 V280 L200 360 L60 280 V120 Z M60 120 L200 200 L340 120 M200 200 V360"/>
        <path d="M200 100 L288 150 V250 L200 300 L112 250 V150 Z" opacity=".6"/>
    </svg>
    <div class="container hero-inner">
        <h1>Danh sách nhà&nbsp;cung&nbsp;cấp đang hợp tác</h1>
        <p class="hero-sub">Tra cứu thông tin liên hệ và điểm đánh giá của các nhà cung cấp. Tìm theo tên, mã hoặc danh mục.</p>
        <form class="hero-search" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" role="search">
            <?php if ($categoryId > 0) { ?><input type="hidden" name="category_id" value="<?php echo $categoryId; ?>"><?php } ?>
            <?php if (getInt("rating_min") > 0) { ?><input type="hidden" name="rating_min" value="<?php echo getInt("rating_min"); ?>"><?php } ?>
            <?php if ($order !== "") { ?><input type="hidden" name="order" value="<?php echo h($order); ?>"><?php } ?>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <label class="visually-hidden" for="searchInput">Tìm kiếm</label>
            <input type="search" class="form-control" id="searchInput" name="q" value="<?php echo h(getParam("q")); ?>" autocomplete="off" placeholder="Tìm theo tên hoặc mã nhà cung cấp">
            <button type="submit" class="btn btn-primary">Tìm</button>
        </form>
        <div class="hero-chips" id="danhmuc" role="group" aria-label="Lọc theo danh mục">
            <?php // Phải có "index.php" đứng trước: nếu chỉ chọn danh mục thì qs() trả về "", href thành "#danhmuc" và nút không bỏ được bộ lọc ?>
            <a class="hero-chip<?php echo $categoryId === 0 ? " is-active" : ""; ?>" href="<?php echo ROOT; ?>index.php<?php echo h(qs(["category_id" => null] + $filters)); ?>#danhmuc">Tất cả</a>
            <?php foreach ($categories as $c) { ?>
                <a class="hero-chip<?php echo $categoryId === (int) $c["category_id"] ? " is-active" : ""; ?>"
                   href="<?php echo h(qs(["category_id" => (int) $c["category_id"]] + $filters)); ?>#danhmuc"><?php echo h($c["category_name"]); ?></a>
            <?php } ?>
        </div>
    </div>
</section>
<section class="container pub-section" id="nhacungcap">
    <div class="section-head">
        <h2>Nhà cung cấp</h2>
        <span id="resultInfo"><?php echo h(resultInfo($page, $perPage, $total, count($suppliers), "nhà cung cấp")); ?></span>
        <form class="pub-controls" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <?php if (getParam("q") !== "") { ?><input type="hidden" name="q" value="<?php echo h(getParam("q")); ?>"><?php } ?>
            <?php if ($categoryId > 0) { ?><input type="hidden" name="category_id" value="<?php echo $categoryId; ?>"><?php } ?>
            <label class="visually-hidden" for="ratingFilter">Điểm đánh giá</label>
            <select class="form-select form-select-sm" id="ratingFilter" name="rating_min" onchange="this.form.submit()">
                <option value="">Mọi điểm đánh giá</option>
                <?php foreach ([4, 3, 2] as $n) { ?>
                    <option value="<?php echo $n; ?>"<?php echo selected(getInt("rating_min"), $n); ?>>Từ <?php echo $n; ?> sao trở lên</option>
                <?php } ?>
            </select>
            <label class="visually-hidden" for="sortSelect">Sắp xếp</label>
            <select class="form-select form-select-sm" id="sortSelect" name="order" onchange="this.form.submit()">
                <?php foreach ($guestOrders as $value => $info) { ?>
                    <option value="<?php echo $value; ?>"<?php echo selected($order, $value); ?>><?php echo h($info[0]); ?></option>
                <?php } ?>
            </select>
            <noscript><button type="submit" class="btn btn-sm btn-primary">Áp dụng</button></noscript>
        </form>
    </div>
    <div class="supplier-grid" aria-live="polite">
        <?php if (!$suppliers) { ?>
            <div class="grid-message">Không tìm thấy nhà cung cấp phù hợp</div>
        <?php } ?>
        <?php foreach ($suppliers as $s) { ?>
            <?php echo supplierCard($s); ?>
        <?php } ?>
    </div>
    <div class="pub-pager">
        <nav aria-label="Phân trang"><?php echo paginationHtml($page, $lastPage, $filters); ?></nav>
    </div>
</section>
<?php include LAYOUT_DIR . "footer_public.php"; ?>
