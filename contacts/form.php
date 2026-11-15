<?php
// contacts/form.php – form THÊM và SỬA người liên hệ của một nhà cung cấp (một file cho cả hai việc).
//   contacts/form.php?supplier_id=N   -> form trống, thêm người liên hệ cho nhà cung cấp N
//   contacts/form.php?id=N            -> form điền sẵn, sửa người liên hệ N
//
// Luồng: form HTML -> gửi POST về chính file này -> PHP kiểm tra từng ô -> lỗi thì hiện lại form kèm câu báo lỗi,
// không lỗi thì ghi database rồi chuyển về trang chi tiết nhà cung cấp kèm thông báo.
// Quy tắc: mỗi nhà cung cấp có tối đa MỘT người liên hệ chính; người đầu tiên được thêm tự là người chính.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

// ----- 1. Khai báo biến -----
$id = toInt($_POST["id"] ?? $_GET["id"] ?? "");                      // có id = đang sửa
$supplierId = toInt($_POST["supplier_id"] ?? $_GET["supplier_id"] ?? "");   // chỉ dùng khi thêm
$fullName = $position = $email = $phone = "";
$isPrimary = "0";
$fullNameErr = $positionErr = $emailErr = $phoneErr = "";
$contact = null;                                                     // dòng liên hệ đang sửa (thêm mới thì không có)

if ($id > 0) {
    // Sửa: nhà cung cấp lấy từ chính dòng liên hệ (không đổi được)
    $contact = getOne("SELECT * FROM supplier_contacts WHERE contact_id = :id", ["id" => $id]);
    if ($contact === null) {
        header("Location: " . ROOT . "suppliers/index.php?msg=not_found");
        exit;
    }
    $supplierId = (int) $contact["supplier_id"];
}
$supplier = $supplierId > 0 ? getOne("SELECT supplier_id, supplier_code, supplier_name FROM suppliers WHERE supplier_id = :id", ["id" => $supplierId]) : null;
if ($supplier === null) {
    header("Location: " . ROOT . "suppliers/index.php?msg=not_found");
    exit;
}
$backUrl = ROOT . "suppliers/detail.php?id=" . $supplierId;

// ----- 2. Khi form được gửi (POST): lấy dữ liệu và kiểm tra -----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName  = test_input($_POST["full_name"] ?? "");
    $position  = test_input($_POST["position"] ?? "");
    $email     = test_input($_POST["email"] ?? "");
    $phone     = test_input($_POST["phone"] ?? "");
    $isPrimary = ($_POST["is_primary"] ?? "") === "1" ? "1" : "0";

    if ($fullName == "") {
        $fullNameErr = "Vui lòng nhập họ tên";
    } elseif (mb_strlen($fullName) > 100) {
        $fullNameErr = "Họ tên tối đa 100 ký tự";
    }

    if (mb_strlen($position) > 100) {
        $positionErr = "Chức vụ tối đa 100 ký tự";
    }

    if ($email != "" && (mb_strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        $emailErr = "Email không hợp lệ";
    }

    if ($phone != "" && !valid_phone($phone)) {
        $phoneErr = "Số điện thoại không hợp lệ (ví dụ 0912345678 hoặc +84912345678)";
    }

    // ----- 3. Không có lỗi: ghi database -----
    if ($fullNameErr == "" && $positionErr == "" && $emailErr == "" && $phoneErr == "") {
        $values = [
            "full_name" => $fullName, "position" => nullIfEmpty($position),
            "email" => nullIfEmpty($email), "phone" => nullIfEmpty($phone),
        ];
        // Bỏ cờ "chính" của người khác rồi ghi dòng này trong CÙNG MỘT giao dịch, để không bao giờ có hai người chính
        transaction(function () use ($id, $supplierId, $values, $isPrimary, $contact) {
            if ($id > 0) {
                $primary = $isPrimary === "1" || (int) $contact["is_primary"] === 1;   // người đang là chính thì luôn giữ cờ chính
                if ($primary) {
                    execute("UPDATE supplier_contacts SET is_primary = 0 WHERE supplier_id = :sid AND contact_id <> :cid", ["sid" => $supplierId, "cid" => $id]);
                }
                execute(
                    "UPDATE supplier_contacts SET full_name = :full_name, position = :position, email = :email, phone = :phone, is_primary = :is_primary
                     WHERE contact_id = :id",
                    $values + ["is_primary" => $primary ? 1 : 0, "id" => $id]
                );
            } else {
                $hasAny = (int) getValue("SELECT COUNT(*) FROM supplier_contacts WHERE supplier_id = :id", ["id" => $supplierId]) > 0;
                $primary = $isPrimary === "1" || !$hasAny;   // người đầu tiên tự là người chính
                if ($primary) {
                    execute("UPDATE supplier_contacts SET is_primary = 0 WHERE supplier_id = :id", ["id" => $supplierId]);
                }
                execute(
                    "INSERT INTO supplier_contacts (supplier_id, full_name, position, email, phone, is_primary)
                     VALUES (:supplier_id, :full_name, :position, :email, :phone, :is_primary)",
                    $values + ["supplier_id" => $supplierId, "is_primary" => $primary ? 1 : 0]
                );
            }
        });
        header("Location: $backUrl&msg=" . ($id > 0 ? "contact_updated" : "contact_added"));
        exit;
    }
} elseif ($id > 0) {
    // Mở form sửa lần đầu (GET): điền sẵn dữ liệu đang có
    $fullName  = $contact["full_name"];
    $position  = (string) $contact["position"];
    $email     = (string) $contact["email"];
    $phone     = (string) $contact["phone"];
    $isPrimary = (string) (int) $contact["is_primary"];
}

// ----- 4. Hiện form -----
$pageTitle = $id > 0 ? "Sửa người liên hệ" : "Thêm người liên hệ";
include LAYOUT_DIR . "header.php";
pageHeader($pageTitle, $supplier["supplier_code"] . " – " . $supplier["supplier_name"]);
?>
<div class="card" style="max-width: 760px">
    <div class="card-body p-4">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="supplier_id" value="<?php echo $supplierId; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="c_full_name">Họ tên <span class="text-danger">*</span></label>
                    <input class="form-control<?php echo invalid($fullNameErr); ?>" id="c_full_name" name="full_name" maxlength="100" autocomplete="off"
                           value="<?php echo h($fullName); ?>">
                    <div class="invalid-feedback"><?php echo h($fullNameErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="c_position">Chức vụ</label>
                    <input class="form-control<?php echo invalid($positionErr); ?>" id="c_position" name="position" maxlength="100" autocomplete="off"
                           value="<?php echo h($position); ?>">
                    <div class="invalid-feedback"><?php echo h($positionErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="c_email">Email</label>
                    <input class="form-control<?php echo invalid($emailErr); ?>" id="c_email" name="email" maxlength="100" autocomplete="off"
                           value="<?php echo h($email); ?>">
                    <div class="invalid-feedback"><?php echo h($emailErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="c_phone">Số điện thoại</label>
                    <input class="form-control<?php echo invalid($phoneErr); ?>" id="c_phone" name="phone" maxlength="20" autocomplete="off" placeholder="Ví dụ: 0912345678"
                           value="<?php echo h($phone); ?>">
                    <div class="invalid-feedback"><?php echo h($phoneErr); ?></div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_primary" value="0">
                        <input class="form-check-input" type="checkbox" id="c_is_primary" name="is_primary" value="1"<?php echo $isPrimary === "1" ? " checked" : ""; ?>>
                        <label class="form-check-label" for="c_is_primary">Đặt làm người liên hệ chính</label>
                    </div>
                    <div class="form-text">Mỗi nhà cung cấp chỉ có một người liên hệ chính. Người đầu tiên được thêm tự là người chính.</div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Lưu</button>
                <a class="btn btn-light" href="<?php echo h($backUrl); ?>">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
