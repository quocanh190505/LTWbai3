<?php
// evaluations/form.php – form THÊM và SỬA đánh giá nhà cung cấp; một file cho cả hai việc.
//   evaluations/form.php[?supplier_id=N]  -> thêm (chọn sẵn nhà cung cấp N nếu có)
//   evaluations/form.php?id=N             -> sửa đánh giá N (giữ nguyên nhà cung cấp và người đánh giá ban đầu)
//
// Luồng: form HTML -> POST về chính file này -> kiểm tra từng ô -> lỗi thì hiện lại form + câu báo lỗi;
// không lỗi thì ghi đánh giá VÀ tính lại điểm trung bình của nhà cung cấp trong cùng một giao dịch,
// rồi chuyển về evaluations/index.php kèm thông báo.
require_once __DIR__ . "/../includes/config.php";
$admin = requireLogin();

// ----- 1. Khai báo biến -----
$id = toInt($_POST["id"] ?? $_GET["id"] ?? "");                              // có id = đang sửa
$supplierId = $_POST["supplier_id"] ?? $_GET["supplier_id"] ?? "";           // chỉ dùng khi thêm
$evaluationDate = date("Y-m-d");
$quality = $delivery = $service = $comment = "";
$supplierErr = $dateErr = $qualityErr = $deliveryErr = $serviceErr = $commentErr = "";
$existing = null;

if ($id > 0) {
    $existing = getOne(
        "SELECT e.*, s.supplier_code, s.supplier_name
         FROM supplier_evaluations e JOIN suppliers s ON s.supplier_id = e.supplier_id WHERE e.evaluation_id = :id",
        ["id" => $id]
    );
    if ($existing === null) {
        header("Location: " . ROOT . "evaluations/index.php?msg=not_found");
        exit;
    }
    $supplierId = (string) $existing["supplier_id"];
}

// ----- 2. Khi form được gửi (POST): lấy dữ liệu và kiểm tra -----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $evaluationDate = test_input($_POST["evaluation_date"] ?? "");
    $quality  = test_input($_POST["quality_score"] ?? "");
    $delivery = test_input($_POST["delivery_score"] ?? "");
    $service  = test_input($_POST["service_score"] ?? "");
    $comment  = test_input($_POST["comment"] ?? "");

    if ($id == 0) {   // chỉ khi thêm mới mới chọn nhà cung cấp
        if (toInt($supplierId) < 1) {
            $supplierErr = "Vui lòng chọn nhà cung cấp";
        } elseif (getValue("SELECT supplier_id FROM suppliers WHERE supplier_id = :id", ["id" => toInt($supplierId)]) === null) {
            $supplierErr = "Nhà cung cấp không tồn tại";
        }
    }

    if (!valid_date($evaluationDate)) {
        $dateErr = "Vui lòng chọn ngày đánh giá hợp lệ";
    }

    // Ba điểm đều là số nguyên 1–5
    if (preg_match('/^[1-5]\z/', $quality) !== 1) {
        $qualityErr = "Điểm chất lượng là số nguyên từ 1 đến 5";
    }
    if (preg_match('/^[1-5]\z/', $delivery) !== 1) {
        $deliveryErr = "Điểm giao hàng là số nguyên từ 1 đến 5";
    }
    if (preg_match('/^[1-5]\z/', $service) !== 1) {
        $serviceErr = "Điểm dịch vụ là số nguyên từ 1 đến 5";
    }

    if (mb_strlen($comment) > 2000) {
        $commentErr = "Nhận xét tối đa 2000 ký tự";
    }

    // ----- 3. Không có lỗi: ghi database -----
    if ($supplierErr == "" && $dateErr == "" && $qualityErr == "" && $deliveryErr == "" && $serviceErr == "" && $commentErr == "") {
        $sid = $id > 0 ? (int) $existing["supplier_id"] : toInt($supplierId);
        $values = [
            "evaluation_date" => $evaluationDate, "quality_score" => (int) $quality, "delivery_score" => (int) $delivery,
            "service_score" => (int) $service, "comment" => nullIfEmpty($comment),
        ];
        // Ghi đánh giá và tính lại điểm trung bình trong cùng một giao dịch: lỗi giữa chừng thì không bên nào được ghi
        transaction(function () use ($id, $sid, $values, $admin) {
            if ($id > 0) {
                execute(
                    "UPDATE supplier_evaluations SET evaluation_date = :evaluation_date, quality_score = :quality_score, delivery_score = :delivery_score,
                            service_score = :service_score, comment = :comment WHERE evaluation_id = :id",
                    $values + ["id" => $id]
                );
            } else {
                execute(
                    "INSERT INTO supplier_evaluations (supplier_id, evaluation_date, quality_score, delivery_score, service_score, comment, evaluated_by)
                     VALUES (:supplier_id, :evaluation_date, :quality_score, :delivery_score, :service_score, :comment, :evaluated_by)",
                    $values + ["supplier_id" => $sid, "evaluated_by" => (int) $admin["user_id"]]
                );
            }
            recalcAvgRating($sid);
        });
        header("Location: " . ROOT . "evaluations/index.php?supplier_id=$sid&msg=" . ($id > 0 ? "evaluation_updated" : "evaluation_added"));
        exit;
    }
} elseif ($id > 0) {
    // Mở form sửa lần đầu (GET): điền sẵn dữ liệu đang có
    $evaluationDate = $existing["evaluation_date"];
    $quality  = (string) $existing["quality_score"];
    $delivery = (string) $existing["delivery_score"];
    $service  = (string) $existing["service_score"];
    $comment  = (string) $existing["comment"];
}

// ----- 4. Hiện form -----
$suppliers = $id > 0 ? [] : supplierOptions();
$cancelUrl = ROOT . "evaluations/index.php" . qs(["supplier_id" => $id > 0 ? $existing["supplier_id"] : toInt($supplierId)]);
$scoreFields = [
    ["quality_score", "Chất lượng", $quality, $qualityErr],
    ["delivery_score", "Giao hàng", $delivery, $deliveryErr],
    ["service_score", "Dịch vụ", $service, $serviceErr],
];
$pageTitle = $id > 0 ? "Sửa đánh giá" : "Thêm đánh giá";
include LAYOUT_DIR . "header.php";
pageHeader($pageTitle, $id > 0 ? $existing["supplier_name"] : "Chấm điểm chất lượng, giao hàng và dịch vụ của nhà cung cấp.");
?>
<div class="card" style="max-width: 760px">
    <div class="card-body p-4">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="e_supplier_id">Nhà cung cấp <span class="text-danger">*</span></label>
                    <?php if ($id > 0) { ?>
                        <!-- Đánh giá đã tạo thì không đổi sang nhà cung cấp khác -->
                        <input class="form-control" id="e_supplier_id" value="<?php echo h($existing["supplier_code"] . " – " . $existing["supplier_name"]); ?>" disabled>
                    <?php } else { ?>
                        <select class="form-select<?php echo invalid($supplierErr); ?>" id="e_supplier_id" name="supplier_id">
                            <option value="">Chọn nhà cung cấp</option>
                            <?php foreach ($suppliers as $s) { ?>
                                <option value="<?php echo (int) $s["supplier_id"]; ?>"<?php echo selected($supplierId, $s["supplier_id"]); ?>><?php echo h($s["supplier_code"] . " – " . $s["supplier_name"]); ?></option>
                            <?php } ?>
                        </select>
                        <div class="invalid-feedback"><?php echo h($supplierErr); ?></div>
                    <?php } ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="e_evaluation_date">Ngày đánh giá <span class="text-danger">*</span></label>
                    <input class="form-control<?php echo invalid($dateErr); ?>" type="date" id="e_evaluation_date" name="evaluation_date" value="<?php echo h($evaluationDate); ?>">
                    <div class="invalid-feedback"><?php echo h($dateErr); ?></div>
                </div>
                <?php foreach ($scoreFields as [$field, $label, $value, $error]) { ?>
                    <div class="col-md-4">
                        <label class="form-label" for="e_<?php echo $field; ?>"><?php echo $label; ?> (1–5) <span class="text-danger">*</span></label>
                        <select class="form-select<?php echo invalid($error); ?>" id="e_<?php echo $field; ?>" name="<?php echo $field; ?>">
                            <option value="">Chọn điểm</option>
                            <?php for ($i = 1; $i <= 5; $i++) { ?>
                                <option value="<?php echo $i; ?>"<?php echo selected($value, $i); ?>><?php echo $i; ?></option>
                            <?php } ?>
                        </select>
                        <div class="invalid-feedback"><?php echo h($error); ?></div>
                    </div>
                <?php } ?>
                <div class="col-12">
                    <label class="form-label" for="e_comment">Nhận xét</label>
                    <textarea class="form-control<?php echo invalid($commentErr); ?>" id="e_comment" name="comment" rows="3" maxlength="2000"><?php echo h($comment); ?></textarea>
                    <div class="invalid-feedback"><?php echo h($commentErr); ?></div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Lưu</button>
                <a class="btn btn-light" href="<?php echo h($cancelUrl); ?>">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
