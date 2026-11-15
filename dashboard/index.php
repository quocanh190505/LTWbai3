<?php
// dashboard/index.php – trang tổng quan (chỉ admin): tổng còn nợ, hóa đơn quá hạn / sắp đến hạn, hạng đối tác theo tổng giá trị mua
// (VIP 1, VIP 2, VIP 3, Thường), số nhà cung cấp theo trạng thái và 5 nhà cung cấp còn nợ nhiều nhất.
// Trang chỉ đọc database rồi in số liệu ra; không có form nên không có bước xử lý POST.
// "Đối tác" là nhà cung cấp đang hoạt động; giá trị mua là tổng total_amount của mọi hóa đơn (đã trả hay chưa đều tính).
require_once __DIR__ . "/../includes/config.php";
requireLogin();

// ----- Số nhà cung cấp theo trạng thái -----
$counts = array_column(getAll("SELECT status, COUNT(*) AS n FROM suppliers GROUP BY status"), "n", "status");
$byStatus = [
    "active"      => (int) ($counts["active"] ?? 0),
    "inactive"    => (int) ($counts["inactive"] ?? 0),
    "blacklisted" => (int) ($counts["blacklisted"] ?? 0),
];
$supplierTotal = array_sum($byStatus);

// ----- Công nợ: tổng còn nợ, số hóa đơn quá hạn, sắp đến hạn (dùng chung quy tắc với trang giao dịch) -----
$debt = getOne(
    "SELECT COALESCE(SUM(t.remaining_amount), 0) AS remaining,
            COALESCE(SUM(CASE WHEN " . overdueSql() . " THEN 1 ELSE 0 END), 0) AS overdue,
            COALESCE(SUM(CASE WHEN " . dueSoonSql() . " THEN 1 ELSE 0 END), 0) AS due_soon
     FROM supply_transactions t"
);
$topDebt = getAll(
    "SELECT s.supplier_id, s.supplier_code, s.supplier_name, SUM(t.remaining_amount) AS remaining
     FROM supply_transactions t JOIN suppliers s ON s.supplier_id = t.supplier_id
     GROUP BY s.supplier_id, s.supplier_code, s.supplier_name
     HAVING SUM(t.remaining_amount) > 0
     ORDER BY remaining DESC, s.supplier_id
     LIMIT 5"
);
$maxDebt = $topDebt ? (float) $topDebt[0]["remaining"] : 0.0;   // đã sắp xếp nợ giảm dần

// ----- Hạng đối tác: mọi nhà cung cấp đang hoạt động (kể cả chưa có hóa đơn: tổng 0, hạng Thường), xếp theo tổng giá trị mua giảm dần -----
$partnerRows = getAll(
    "SELECT s.supplier_id, s.supplier_code, s.supplier_name, s.logo_url, s.avg_rating,
            COALESCE(SUM(t.total_amount), 0) AS purchase_total,
            COUNT(t.supplier_id) AS transaction_count,
            MAX(t.transaction_date) AS last_transaction_date
     FROM suppliers s LEFT JOIN supply_transactions t ON t.supplier_id = s.supplier_id
     WHERE s.status = 'active'
     GROUP BY s.supplier_id, s.supplier_code, s.supplier_name, s.logo_url, s.avg_rating
     ORDER BY purchase_total DESC, s.supplier_id"
);
$tierCounts = array_fill_keys(array_column(PARTNER_TIERS, "key"), 0);
$partners = [];                       // 10 đối tác đầu bảng xếp hạng
foreach ($partnerRows as $r) {
    $tier = partner_tier($r["purchase_total"]);
    $tierCounts[$tier]++;             // số đếm theo hạng tính trên tất cả đối tác
    if (count($partners) < 10) {
        $partners[] = $r + ["tier" => $tier];
    }
}
$tierLabels = array_column(PARTNER_TIERS, "label", "key");
$tierTotal = array_sum($tierCounts);
$maxPurchase = $partners ? (float) $partners[0]["purchase_total"] : 0.0;

$tierSegments = [];
$tierAria = [];
foreach (PARTNER_TIERS as $t) {
    $tierSegments[] = ["tier-fill tier-" . $t["key"], $tierCounts[$t["key"]]];
    $tierAria[] = $t["label"] . " " . $tierCounts[$t["key"]];
}

$pageTitle = "Tổng quan";
include LAYOUT_DIR . "header.php";
pageHeader("Tổng quan", "Công nợ, hạng đối tác và số nhà cung cấp theo trạng thái.");
?>
<div class="kpi-strip mb-4">
    <div class="kpi">
        <div class="kpi-label">Tổng còn nợ</div>
        <div class="kpi-value<?php echo (float) $debt["remaining"] > 0 ? " debt" : ""; ?>"><?php echo h(formatMoney($debt["remaining"])); ?></div>
        <a class="kpi-link" href="<?php echo ROOT; ?>transactions/index.php">Xem giao dịch</a>
    </div>
    <div class="kpi">
        <div class="kpi-label">Hóa đơn quá hạn</div>
        <div class="kpi-value<?php echo (int) $debt["overdue"] > 0 ? " debt" : ""; ?>"><?php echo (int) $debt["overdue"]; ?></div>
        <a class="kpi-link" href="<?php echo ROOT; ?>transactions/index.php?due=overdue">Xem danh sách</a>
    </div>
    <div class="kpi">
        <div class="kpi-label">Sắp đến hạn (<?php echo DUE_SOON_DAYS; ?> ngày tới)</div>
        <div class="kpi-value<?php echo (int) $debt["due_soon"] > 0 ? " warn" : ""; ?>"><?php echo (int) $debt["due_soon"]; ?></div>
        <a class="kpi-link" href="<?php echo ROOT; ?>transactions/index.php?due=soon">Xem danh sách</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-1">
            <h2 class="h6 fw-semibold mb-0">Đối tác theo giá trị mua</h2>
            <span class="text-muted"><?php echo $tierTotal; ?> đối tác đang hoạt động</span>
        </div>
        <p class="chart-sub">Hạng đối tác được tính theo tổng giá trị hóa đơn của nhà cung cấp đang hoạt động.</p>
        <?php echo ratioBar($tierSegments, "Số đối tác theo hạng: " . implode(", ", $tierAria)); ?>
        <ul class="tier-legend">
            <?php foreach (PARTNER_TIERS as $i => $t) { ?>
                <?php $rule = $t["min"] > 0 ? "từ " . compactMoney($t["min"]) : ($i > 0 ? "dưới " . compactMoney(PARTNER_TIERS[$i - 1]["min"]) : "mọi mức"); ?>
                <li>
                    <div class="tl-name"><?php echo tierTag($t["key"], $t["label"]); ?></div>
                    <div class="tl-note"><strong><?php echo $tierCounts[$t["key"]]; ?></strong> đối tác, <?php echo h($rule); ?></div>
                </li>
            <?php } ?>
        </ul>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Bảng xếp hạng đối tác (10 đầu bảng)</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="text-end">#</th><th>Nhà cung cấp</th><th>Hạng</th><th class="text-end">Tổng giá trị mua</th>
                    <th class="text-end">Giao dịch</th><th>Gần nhất</th><th>Đánh giá</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$partners) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Chưa có nhà cung cấp nào đang hoạt động</td></tr>
            <?php } ?>
            <?php foreach ($partners as $i => $p) { ?>
                <?php $pct = $maxPurchase > 0 ? (int) round((float) $p["purchase_total"] / $maxPurchase * 100) : 0; ?>
                <tr>
                    <td class="text-end text-muted"><?php echo $i + 1; ?></td>
                    <td><?php echo supplierCell($p, ROOT . "transactions/index.php?supplier_id=" . (int) $p["supplier_id"]); ?></td>
                    <td><?php echo tierTag($p["tier"], $tierLabels[$p["tier"]] ?? $p["tier"]); ?></td>
                    <td class="text-end">
                        <div class="debt-cell"><span class="money fw-semibold"><?php echo h(formatMoney($p["purchase_total"])); ?></span>
                        <span class="meter" aria-hidden="true"><span class="tier-<?php echo h($p["tier"]); ?>" style="width:<?php echo $pct; ?>%;background:var(--tier)"></span></span></div>
                    </td>
                    <td class="text-end"><?php echo (int) $p["transaction_count"]; ?></td>
                    <td class="text-nowrap"><?php echo $p["last_transaction_date"] ? h(formatDate($p["last_transaction_date"])) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="text-nowrap"><?php echo ratingHtml($p["avg_rating"], 1); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-baseline mb-3">
            <h2 class="h6 fw-semibold mb-0">Nhà cung cấp theo trạng thái</h2>
            <span class="text-muted"><?php echo $supplierTotal; ?> nhà cung cấp</span>
        </div>
        <?php echo ratioBar([["seg-active", $byStatus["active"]], ["seg-inactive", $byStatus["inactive"]], ["seg-blacklisted", $byStatus["blacklisted"]]], "Tỷ lệ nhà cung cấp theo trạng thái"); ?>
        <ul class="status-legend">
            <li><span class="legend-dot seg-active"></span>Đang hoạt động <strong><?php echo $byStatus["active"]; ?></strong></li>
            <li><span class="legend-dot seg-inactive"></span>Ngừng hoạt động <strong><?php echo $byStatus["inactive"]; ?></strong></li>
            <li><span class="legend-dot seg-blacklisted"></span>Trong danh sách đen <strong><?php echo $byStatus["blacklisted"]; ?></strong></li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-header">5 nhà cung cấp còn nợ nhiều nhất</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Nhà cung cấp</th><th class="text-end">Còn nợ</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$topDebt) { ?>
                <tr><td colspan="3" class="text-center text-muted py-4">Chưa có nhà cung cấp nào còn nợ</td></tr>
            <?php } ?>
            <?php foreach ($topDebt as $s) { ?>
                <?php $pct = $maxDebt > 0 ? (int) round((float) $s["remaining"] / $maxDebt * 100) : 0; ?>
                <tr>
                    <td><div class="supplier-name"><?php echo h($s["supplier_name"]); ?></div><div class="supplier-sub"><?php echo h($s["supplier_code"]); ?></div></td>
                    <td class="text-end">
                        <div class="debt-cell"><span class="money debt"><?php echo h(formatMoney($s["remaining"])); ?></span>
                        <span class="meter" aria-hidden="true"><span style="width:<?php echo $pct; ?>%"></span></span></div>
                    </td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>transactions/index.php?supplier_id=<?php echo (int) $s["supplier_id"]; ?>">Xem giao dịch</a></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
