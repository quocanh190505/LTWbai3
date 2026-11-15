<?php
// transactions/pay.php – ghi nhận một khoản thanh toán cho hóa đơn (trả từng phần):  transactions/pay.php?id=N
// Form HTML gửi POST về chính file này; nút "Trả hết" gửi thêm pay_all=1 để trả đúng số còn nợ.
// Khóa dòng (FOR UPDATE) rồi mới đọc số còn nợ và cộng thêm, để hai lần bấm cùng lúc không trả vượt số còn nợ.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

// ----- 1. Khai báo biến -----
$id = toInt($_POST["id"] ?? $_GET["id"] ?? "");
$amount = "";
$amountErr = "";

$row = $id > 0 ? getOne(
    "SELECT t.transaction_id, t.supplier_id, s.supplier_code, s.supplier_name, t.invoice_number, t.total_amount, t.paid_amount, t.remaining_amount
     FROM supply_transactions t JOIN suppliers s ON s.supplier_id = t.supplier_id WHERE t.transaction_id = :id",
    ["id" => $id]
) : null;
if ($row === null) {
    header("Location: " . ROOT . "transactions/index.php?msg=not_found");
    exit;
}
$backUrl = ROOT . "transactions/index.php" . qs(["supplier_id" => $row["supplier_id"]]);

// ----- 2. Khi form được gửi (POST): kiểm tra rồi ghi -----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payAll = ($_POST["pay_all"] ?? "") === "1";
    $amount = test_input($_POST["amount"] ?? "");

    if (!$payAll && (!valid_money($amount) || money_cents($amount) === 0)) {
        $amountErr = "Số tiền thanh toán là số lớn hơn 0, tối đa 2 chữ số thập phân";
    } else {
        // Đọc số còn nợ MỚI NHẤT (đã khóa dòng) rồi mới cộng, nên hai người cùng trả một lúc không làm trả vượt
        $amountErr = transaction(function () use ($id, $payAll, $amount) {
            $current = getOne("SELECT total_amount, paid_amount FROM supply_transactions WHERE transaction_id = :id FOR UPDATE", ["id" => $id]);
            if ($current === null) {
                return "Không tìm thấy giao dịch";
            }
            $paid = money_cents(money($current["paid_amount"]));
            $remaining = money_cents(money($current["total_amount"])) - $paid;
            if ($remaining <= 0) {
                return "Hóa đơn này đã thanh toán đủ";
            }
            $cents = $payAll ? $remaining : money_cents($amount);
            if ($cents > $remaining) {
                return "Số tiền vượt quá số còn nợ (" . formatMoney($remaining / 100) . ")";
            }
            execute("UPDATE supply_transactions SET paid_amount = :paid WHERE transaction_id = :id", ["paid" => money(($paid + $cents) / 100), "id" => $id]);
            return "";
        });
        if ($amountErr == "") {
            header("Location: $backUrl" . (str_contains($backUrl, "?") ? "&" : "?") . "msg=transaction_paid");
            exit;
        }
    }
}

// ----- 3. Hiện form (số còn nợ đọc lại từ database để luôn đúng) -----
$row = getOne("SELECT total_amount, paid_amount, remaining_amount, invoice_number, supplier_code, supplier_name
               FROM supply_transactions t JOIN suppliers s ON s.supplier_id = t.supplier_id WHERE t.transaction_id = :id", ["id" => $id]) ?? $row;
$remaining = (float) $row["remaining_amount"];

$pageTitle = "Ghi nhận thanh toán";
include LAYOUT_DIR . "header.php";
pageHeader($pageTitle, $row["supplier_code"] . " – " . $row["supplier_name"]);
?>
<div class="card" style="max-width: 560px">
    <div class="card-body p-4">
        <p class="mb-1">Hóa đơn <strong><?php echo h($row["invoice_number"]); ?></strong></p>
        <p class="mb-1 text-muted">Tổng giá trị: <?php echo h(formatMoney($row["total_amount"])); ?> · Đã trả: <?php echo h(formatMoney($row["paid_amount"])); ?></p>
        <p class="text-muted">Còn nợ: <strong class="debt"><?php echo h(formatMoney($remaining)); ?></strong></p>
        <?php if ($remaining <= 0) { ?>
            <div class="alert alert-success mb-3">Hóa đơn này đã thanh toán đủ.</div>
            <a class="btn btn-light" href="<?php echo h($backUrl); ?>">Quay lại danh sách</a>
        <?php } else { ?>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <label class="form-label" for="pay_amount">Số tiền thanh toán đợt này (₫)</label>
                <input class="form-control<?php echo invalid($amountErr); ?>" type="number" id="pay_amount" name="amount" min="0" step="0.01" autofocus
                       value="<?php echo h($amount); ?>">
                <div class="invalid-feedback"><?php echo h($amountErr); ?></div>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Ghi nhận</button>
                    <button type="submit" class="btn btn-outline-primary" name="pay_all" value="1"
                            onclick="return confirm('Trả hết <?php echo h(addslashes(formatMoney($remaining))); ?> cho hóa đơn này?');">Trả hết</button>
                    <a class="btn btn-light" href="<?php echo h($backUrl); ?>">Hủy</a>
                </div>
            </form>
        <?php } ?>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
