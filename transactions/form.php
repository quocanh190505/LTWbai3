<?php
// transactions/form.php – form THÊM và SỬA giao dịch (hóa đơn mua hàng); một file cho cả hai việc.
//   transactions/form.php[?supplier_id=N]  -> thêm (chọn sẵn nhà cung cấp N nếu có)
//   transactions/form.php?id=N             -> sửa giao dịch N (giữ nguyên nhà cung cấp và người tạo)
//
// Luồng: form HTML -> POST về chính file này -> kiểm tra từng ô -> lỗi thì hiện lại form + câu báo lỗi;
// không lỗi thì lưu hóa đơn đính kèm, ghi database rồi chuyển về transactions/index.php kèm thông báo.
// Cột remaining_amount và payment_status do MySQL tự tính từ total_amount và paid_amount, không ghi tay.
// Form có tải tệp nên phải có enctype="multipart/form-data".
require_once __DIR__ . "/../includes/config.php";
$admin = requireLogin();

// ----- 1. Khai báo biến -----
$id = toInt($_POST["id"] ?? $_GET["id"] ?? "");                              // có id = đang sửa
$supplierId = $_POST["supplier_id"] ?? $_GET["supplier_id"] ?? "";           // chỉ dùng khi thêm
$invoiceNumber = $transactionDate = $dueDate = $totalAmount = "";
$paidAmount = "0";
$supplierErr = $invoiceNumberErr = $transactionDateErr = $dueDateErr = $totalAmountErr = $paidAmountErr = $invoiceFileErr = "";
$transactionDate = date("Y-m-d");
$currentFile = null;                                                         // hóa đơn đang lưu (chỉ có khi sửa)
$existing = null;

if ($id > 0) {
    $existing = getOne(
        "SELECT t.*, s.supplier_code, s.supplier_name
         FROM supply_transactions t JOIN suppliers s ON s.supplier_id = t.supplier_id WHERE t.transaction_id = :id",
        ["id" => $id]
    );
    if ($existing === null) {
        header("Location: " . ROOT . "transactions/index.php?msg=not_found");
        exit;
    }
    $supplierId = (string) $existing["supplier_id"];
    $currentFile = $existing["invoice_file_path"];
}

// ----- 2. Khi form được gửi (POST): lấy dữ liệu và kiểm tra -----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $invoiceNumber   = test_input($_POST["invoice_number"] ?? "");
    $transactionDate = test_input($_POST["transaction_date"] ?? "");
    $dueDate         = test_input($_POST["due_date"] ?? "");
    $totalAmount     = test_input($_POST["total_amount"] ?? "");
    $paidAmount      = test_input($_POST["paid_amount"] ?? "");

    if ($id == 0) {   // chỉ khi thêm mới mới chọn nhà cung cấp
        if (toInt($supplierId) < 1) {
            $supplierErr = "Vui lòng chọn nhà cung cấp";
        } elseif (getValue("SELECT supplier_id FROM suppliers WHERE supplier_id = :id", ["id" => toInt($supplierId)]) === null) {
            $supplierErr = "Nhà cung cấp không tồn tại";
        }
    }

    if ($invoiceNumber == "") {
        $invoiceNumberErr = "Vui lòng nhập số hóa đơn";
    } elseif (mb_strlen($invoiceNumber) > 50) {
        $invoiceNumberErr = "Số hóa đơn tối đa 50 ký tự";
    }

    $dateOk = valid_date($transactionDate);
    if (!$dateOk) {
        $transactionDateErr = "Vui lòng chọn ngày giao dịch hợp lệ";
    }

    if ($dueDate != "") {                              // hạn thanh toán không bắt buộc
        if (!valid_date($dueDate)) {
            $dueDateErr = "Hạn thanh toán không hợp lệ";
        } elseif ($dateOk && $dueDate < $transactionDate) {   // ngày dạng YYYY-MM-DD so sánh theo chuỗi là đúng thứ tự thời gian
            $dueDateErr = "Hạn thanh toán không được trước ngày giao dịch";
        }
    }

    $totalOk = false;
    if ($totalAmount == "") {
        $totalAmountErr = "Vui lòng nhập tổng giá trị";
    } elseif (!valid_money($totalAmount)) {
        $totalAmountErr = "Tổng giá trị là số tiền, tối đa 2 chữ số thập phân";
    } elseif (money_cents($totalAmount) === 0) {
        $totalAmountErr = "Tổng giá trị phải lớn hơn 0";
    } else {
        $totalOk = true;
    }

    if ($paidAmount == "") {
        $paidAmount = "0";
    } elseif (!valid_money($paidAmount)) {
        $paidAmountErr = "Số đã trả là số tiền từ 0 trở lên, tối đa 2 chữ số thập phân";
    } elseif ($totalOk && money_cents($paidAmount) > money_cents($totalAmount)) {
        $paidAmountErr = "Số đã trả không được lớn hơn tổng giá trị";
    }

    // Hóa đơn đính kèm: chỉ kiểm tra ở bước này, chưa lưu file (để lỗi ở ô khác không để lại file thừa)
    [$invoiceFile, $invoiceFileErr] = read_optional_upload("invoice_file", INVOICE_MAX_BYTES, INVOICE_TYPES);

    // ----- 3. Không có lỗi: lưu file rồi ghi database -----
    if ($supplierErr == "" && $invoiceNumberErr == "" && $transactionDateErr == "" && $dueDateErr == "" && $totalAmountErr == ""
        && $paidAmountErr == "" && $invoiceFileErr == "") {

        $filePath = $currentFile;                        // mặc định giữ hóa đơn cũ
        $newFile = null;
        if ($invoiceFile !== null) {                     // có chọn tệp mới: lưu rồi dùng thay hóa đơn cũ
            $newFile = save_upload($invoiceFile, "invoices", INVOICE_TYPES);
            if ($newFile === null) {
                $invoiceFileErr = "Không lưu được tệp hóa đơn";
            } else {
                $filePath = $newFile;
            }
        } elseif ($id > 0 && ($_POST["remove_invoice"] ?? "") === "1") {
            $filePath = null;                            // tick "Xóa hóa đơn đang đính kèm"
        }

        if ($invoiceFileErr == "") {
            $values = [
                "invoice_number" => $invoiceNumber, "transaction_date" => $transactionDate, "due_date" => nullIfEmpty($dueDate),
                "total_amount" => $totalAmount, "paid_amount" => $paidAmount, "invoice_file_path" => $filePath,
            ];
            try {
                if ($id > 0) {                           // SỬA
                    execute(
                        "UPDATE supply_transactions SET invoice_number = :invoice_number, transaction_date = :transaction_date, due_date = :due_date,
                                total_amount = :total_amount, paid_amount = :paid_amount, invoice_file_path = :invoice_file_path
                         WHERE transaction_id = :id",
                        $values + ["id" => $id]
                    );
                    if ($currentFile !== null && $currentFile !== $filePath) {
                        delete_upload($currentFile);     // hóa đơn cũ đã được thay hoặc gỡ
                    }
                    header("Location: " . ROOT . "transactions/index.php?supplier_id=" . (int) $existing["supplier_id"] . "&msg=transaction_updated");
                } else {                                 // THÊM
                    execute(
                        "INSERT INTO supply_transactions (supplier_id, invoice_number, transaction_date, due_date, total_amount, paid_amount, invoice_file_path, created_by)
                         VALUES (:supplier_id, :invoice_number, :transaction_date, :due_date, :total_amount, :paid_amount, :invoice_file_path, :created_by)",
                        $values + ["supplier_id" => toInt($supplierId), "created_by" => (int) $admin["user_id"]]
                    );
                    header("Location: " . ROOT . "transactions/index.php?supplier_id=" . toInt($supplierId) . "&msg=transaction_added");
                }
                exit;
            } catch (PDOException $e) {
                delete_upload($newFile);                 // không để lại file mồ côi
                throw $e;
            }
        }
    }
} elseif ($id > 0) {
    // Mở form sửa lần đầu (GET): điền sẵn dữ liệu đang có
    $invoiceNumber   = $existing["invoice_number"];
    $transactionDate = $existing["transaction_date"];
    $dueDate         = (string) $existing["due_date"];
    $totalAmount     = (string) $existing["total_amount"];
    $paidAmount      = (string) $existing["paid_amount"];
}

// ----- 4. Hiện form -----
$suppliers = $id > 0 ? [] : supplierOptions();
$pageTitle = $id > 0 ? "Sửa giao dịch" : "Thêm giao dịch";
include LAYOUT_DIR . "header.php";
pageHeader($pageTitle, $id > 0 ? $existing["supplier_code"] . " – " . $existing["supplier_name"] : "Ghi nhận một hóa đơn mua hàng từ nhà cung cấp.");
?>
<div class="card" style="max-width: 820px">
    <div class="card-body p-4">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="t_supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                    <?php if ($id > 0) { ?>
                        <!-- Giao dịch đã tạo thì không đổi sang nhà cung cấp khác -->
                        <input class="form-control" id="t_supplier_id" value="<?php echo h($existing["supplier_code"] . " – " . $existing["supplier_name"]); ?>" disabled>
                    <?php } else { ?>
                        <select class="form-select<?php echo invalid($supplierErr); ?>" id="t_supplier_id" name="supplier_id">
                            <option value="">Chọn nhà cung cấp</option>
                            <?php foreach ($suppliers as $s) { ?>
                                <option value="<?php echo (int) $s["supplier_id"]; ?>"<?php echo selected($supplierId, $s["supplier_id"]); ?>><?php echo h($s["supplier_code"] . " – " . $s["supplier_name"]); ?></option>
                            <?php } ?>
                        </select>
                        <div class="invalid-feedback"><?php echo h($supplierErr); ?></div>
                    <?php } ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="t_invoice_number">Số hóa đơn <span class="text-danger">*</span></label>
                    <input class="form-control<?php echo invalid($invoiceNumberErr); ?>" id="t_invoice_number" name="invoice_number" maxlength="50" autocomplete="off"
                           value="<?php echo h($invoiceNumber); ?>">
                    <div class="invalid-feedback"><?php echo h($invoiceNumberErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="t_transaction_date">Ngày giao dịch <span class="text-danger">*</span></label>
                    <input class="form-control<?php echo invalid($transactionDateErr); ?>" type="date" id="t_transaction_date" name="transaction_date"
                           value="<?php echo h($transactionDate); ?>">
                    <div class="invalid-feedback"><?php echo h($transactionDateErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="t_due_date">Hạn thanh toán</label>
                    <input class="form-control<?php echo invalid($dueDateErr); ?>" type="date" id="t_due_date" name="due_date"
                           value="<?php echo h($dueDate); ?>">
                    <div class="invalid-feedback"><?php echo h($dueDateErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="t_total_amount">Tổng giá trị (₫) <span class="text-danger">*</span></label>
                    <input class="form-control<?php echo invalid($totalAmountErr); ?>" type="number" id="t_total_amount" name="total_amount" min="0" step="0.01"
                           value="<?php echo h($totalAmount); ?>">
                    <div class="invalid-feedback"><?php echo h($totalAmountErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="t_paid_amount">Đã trả (₫)</label>
                    <input class="form-control<?php echo invalid($paidAmountErr); ?>" type="number" id="t_paid_amount" name="paid_amount" min="0" step="0.01"
                           value="<?php echo h($paidAmount); ?>">
                    <div class="invalid-feedback"><?php echo h($paidAmountErr); ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="t_invoice_file">Hóa đơn đính kèm (PDF, JPG hoặc PNG, tối đa 5 MB)</label>
                    <input class="form-control<?php echo invalid($invoiceFileErr); ?>" type="file" id="t_invoice_file" name="invoice_file" accept="application/pdf,image/jpeg,image/png">
                    <div class="invalid-feedback"><?php echo h($invoiceFileErr); ?></div>
                    <?php if ($currentFile) { ?>
                        <div class="form-text">
                            Đang có hóa đơn đính kèm:
                            <a target="_blank" rel="noopener" href="<?php echo ROOT; ?>transactions/invoice.php?id=<?php echo $id; ?>">xem hóa đơn</a>.
                            Chọn tệp mới để thay thế.
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="t_remove_invoice" name="remove_invoice" value="1">
                            <label class="form-check-label" for="t_remove_invoice">Xóa hóa đơn đang đính kèm</label>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Lưu</button>
                <a class="btn btn-light" href="<?php echo ROOT; ?>transactions/index.php<?php echo h(qs(["supplier_id" => $id > 0 ? $existing["supplier_id"] : toInt($supplierId)])); ?>">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
