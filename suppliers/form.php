<?php
// suppliers/form.php – form THÊM và SỬA nhà cung cấp (một file cho cả hai việc).
//   suppliers/form.php        -> form trống, thêm mới
//   suppliers/form.php?id=N   -> form điền sẵn dữ liệu của nhà cung cấp N, sửa
//
// Luồng (giống ví dụ "PHP Form Validation" của W3Schools):
//   form HTML  ->  bấm Lưu: gửi POST về CHÍNH file này (action = PHP_SELF)
//   ->  PHP kiểm tra từng ô  ->  có lỗi: hiện lại form, giữ giá trị đã gõ, báo lỗi cạnh từng ô
//   ->  không lỗi: ghi vào database rồi chuyển về suppliers/index.php kèm thông báo.
// Vì PHP_SELF không mang theo ?id=N nên id được gửi lại qua ô ẩn <input type="hidden" name="id">.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

// ----- 1. Khai báo biến: giá trị các ô nhập và câu báo lỗi (ban đầu đều rỗng) -----
$id = toInt($_POST["id"] ?? $_GET["id"] ?? "");      // có id = đang sửa, không có = thêm mới
$code = $name = $categoryId = $tax = $email = $phone = $address = "";
$credit = "0";
$status = "active";
$codeErr = $nameErr = $categoryErr = $taxErr = $emailErr = $phoneErr = $addressErr = $creditErr = $statusErr = $logoErr = "";
$currentLogo = null;                                  // logo đang lưu (chỉ có khi sửa)

if ($id > 0) {
    $existing = getOne("SELECT * FROM suppliers WHERE supplier_id = :id", ["id" => $id]);
    if ($existing === null) {
        header("Location: " . ROOT . "suppliers/index.php?msg=not_found");
        exit;
    }
    $currentLogo = $existing["logo_url"];
}

// ----- 2. Khi form được gửi (POST): lấy dữ liệu và kiểm tra từng ô -----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code       = test_input($_POST["supplier_code"] ?? "");
    $name       = test_input($_POST["supplier_name"] ?? "");
    $categoryId = test_input($_POST["category_id"] ?? "");
    $tax        = test_input($_POST["tax_code"] ?? "");
    $email      = test_input($_POST["email"] ?? "");
    $phone      = test_input($_POST["phone"] ?? "");
    $address    = test_input($_POST["address"] ?? "");
    $credit     = test_input($_POST["credit_limit"] ?? "");
    $status     = test_input($_POST["status"] ?? "");

    if ($code == "") {
        $codeErr = "Vui lòng nhập mã nhà cung cấp";
    } elseif (!valid_supplier_code($code)) {
        $codeErr = "Mã gồm 3–20 ký tự: chữ hoa, số, dấu - hoặc _";
    } elseif (getValue("SELECT supplier_id FROM suppliers WHERE supplier_code = :code AND supplier_id <> :id", ["code" => $code, "id" => $id]) !== null) {
        $codeErr = "Mã nhà cung cấp đã tồn tại";   // không tính chính dòng đang sửa
    }

    if ($name == "") {
        $nameErr = "Vui lòng nhập tên nhà cung cấp";
    } elseif (mb_strlen($name) > 150) {
        $nameErr = "Tên tối đa 150 ký tự";
    }

    if (toInt($categoryId) < 1) {
        $categoryErr = "Vui lòng chọn danh mục";
    } elseif (getValue("SELECT category_id FROM supplier_categories WHERE category_id = :id", ["id" => toInt($categoryId)]) === null) {
        $categoryErr = "Danh mục không tồn tại";
    }

    if ($tax != "") {                                   // mã số thuế không bắt buộc
        if (!valid_tax_code($tax)) {
            $taxErr = "Mã số thuế gồm 10 chữ số (hoặc 10 chữ số kèm -XXX)";
        } elseif (getValue("SELECT supplier_id FROM suppliers WHERE tax_code = :tax AND supplier_id <> :id", ["tax" => $tax, "id" => $id]) !== null) {
            $taxErr = "Mã số thuế đã tồn tại";
        }
    }

    if ($email != "" && (mb_strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
        $emailErr = "Email không hợp lệ";
    }

    if ($phone != "" && !valid_phone($phone)) {
        $phoneErr = "Số điện thoại không hợp lệ (ví dụ 0912345678 hoặc +84912345678)";
    }

    if (mb_strlen($address) > 255) {
        $addressErr = "Địa chỉ tối đa 255 ký tự";
    }

    if ($credit == "") {
        $credit = "0";
    } elseif (!valid_money($credit)) {
        $creditErr = "Hạn mức là số từ 0 trở lên, tối đa 2 chữ số thập phân";
    }

    if (!in_array($status, SUPPLIER_STATUSES, true)) {
        $statusErr = "Trạng thái không hợp lệ";
    }

    // Logo: chỉ kiểm tra ở bước này, chưa lưu file (để lỗi ở ô khác không để lại file thừa)
    [$logoFile, $logoErr] = read_optional_upload("logo", LOGO_MAX_BYTES, LOGO_TYPES);

    // ----- 3. Không có lỗi ở ô nào: lưu vào database -----
    if ($codeErr == "" && $nameErr == "" && $categoryErr == "" && $taxErr == "" && $emailErr == "" && $phoneErr == ""
        && $addressErr == "" && $creditErr == "" && $statusErr == "" && $logoErr == "") {

        $logoPath = $currentLogo;                         // mặc định giữ logo cũ
        $newLogo = null;
        if ($logoFile !== null) {                         // có chọn file mới: lưu file rồi dùng thay logo cũ
            $newLogo = save_upload($logoFile, "logos", LOGO_TYPES);
            if ($newLogo === null) {
                $logoErr = "Không lưu được tệp logo";
            } else {
                $logoPath = $newLogo;
            }
        } elseif ($id > 0 && ($_POST["remove_logo"] ?? "") === "1") {
            $logoPath = null;                             // tick "Xóa logo hiện tại"
        }

        if ($logoErr == "") {
            $values = [
                "code" => $code, "category_id" => toInt($categoryId), "name" => $name, "tax" => nullIfEmpty($tax),
                "email" => nullIfEmpty($email), "phone" => nullIfEmpty($phone), "address" => nullIfEmpty($address),
                "credit" => $credit, "status" => $status, "logo" => $logoPath,
            ];
            try {
                if ($id > 0) {                            // SỬA: câu UPDATE (không đụng tới avg_rating, cột này chỉ do phần đánh giá cập nhật)
                    execute(
                        "UPDATE suppliers SET supplier_code = :code, category_id = :category_id, supplier_name = :name, tax_code = :tax,
                                email = :email, phone = :phone, address = :address, credit_limit = :credit, status = :status, logo_url = :logo
                         WHERE supplier_id = :id",
                        $values + ["id" => $id]
                    );
                    if ($currentLogo !== null && $currentLogo !== $logoPath) {
                        delete_upload($currentLogo);      // logo cũ đã được thay hoặc gỡ
                    }
                    header("Location: " . ROOT . "suppliers/index.php?msg=supplier_updated");
                } else {                                  // THÊM: câu INSERT
                    execute(
                        "INSERT INTO suppliers (supplier_code, category_id, supplier_name, tax_code, email, phone, address, credit_limit, status, logo_url)
                         VALUES (:code, :category_id, :name, :tax, :email, :phone, :address, :credit, :status, :logo)",
                        $values
                    );
                    header("Location: " . ROOT . "suppliers/index.php?msg=supplier_added");
                }
                exit;                                     // chuyển về danh sách (tránh gửi lại form khi bấm F5)
            } catch (PDOException $e) {
                delete_upload($newLogo);                  // không để lại file mồ côi
                if (!isDuplicateError($e)) {
                    throw $e;
                }
                // Hai người cùng lưu một mã trong cùng lúc: cột UNIQUE của database chặn, ta báo đúng ô
                if (str_contains($e->getMessage(), "tax_code")) {
                    $taxErr = "Mã số thuế đã tồn tại";
                } else {
                    $codeErr = "Mã nhà cung cấp đã tồn tại";
                }
            }
        }
    }
} elseif ($id > 0) {
    // Mở form sửa lần đầu (GET): điền sẵn dữ liệu đang có trong database
    $code       = $existing["supplier_code"];
    $name       = $existing["supplier_name"];
    $categoryId = (string) $existing["category_id"];
    $tax        = (string) $existing["tax_code"];
    $email      = (string) $existing["email"];
    $phone      = (string) $existing["phone"];
    $address    = (string) $existing["address"];
    $credit     = (string) $existing["credit_limit"];
    $status     = $existing["status"];
}

// ----- 4. Hiện form (lần đầu mở trang, hoặc sau khi có lỗi) -----
$categories = categoryOptions();
$pageTitle = $id > 0 ? "Sửa nhà cung cấp" : "Thêm nhà cung cấp";
include LAYOUT_DIR . "header.php";
pageHeader($pageTitle, $id > 0 ? "Sửa thông tin nhà cung cấp." : "Nhập thông tin nhà cung cấp mới.");
?>
<div class="card">
    <div class="card-body p-4">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="f_supplier_code">Mã nhà cung cấp <span class="text-danger">*</span></label>
                    <input class="form-control<?php echo invalid($codeErr); ?>" id="f_supplier_code" name="supplier_code" maxlength="20" autocomplete="off" placeholder="Ví dụ: NCC011"
                           value="<?php echo h($code); ?>">
                    <div class="invalid-feedback"><?php echo h($codeErr); ?></div>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="f_supplier_name">Tên nhà cung cấp <span class="text-danger">*</span></label>
                    <input class="form-control<?php echo invalid($nameErr); ?>" id="f_supplier_name" name="supplier_name" maxlength="150" autocomplete="off"
                           value="<?php echo h($name); ?>">
                    <div class="invalid-feedback"><?php echo h($nameErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_category_id">Danh mục <span class="text-danger">*</span></label>
                    <select class="form-select<?php echo invalid($categoryErr); ?>" id="f_category_id" name="category_id">
                        <option value="">Chọn danh mục</option>
                        <?php foreach ($categories as $c) { ?>
                            <option value="<?php echo (int) $c["category_id"]; ?>"<?php echo selected($categoryId, $c["category_id"]); ?>><?php echo h($c["category_name"]); ?></option>
                        <?php } ?>
                    </select>
                    <div class="invalid-feedback"><?php echo h($categoryErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_tax_code">Mã số thuế</label>
                    <input class="form-control<?php echo invalid($taxErr); ?>" id="f_tax_code" name="tax_code" maxlength="14" autocomplete="off" placeholder="10 chữ số, có thể để trống"
                           value="<?php echo h($tax); ?>">
                    <div class="invalid-feedback"><?php echo h($taxErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_email">Email</label>
                    <input class="form-control<?php echo invalid($emailErr); ?>" id="f_email" name="email" maxlength="100" autocomplete="off"
                           value="<?php echo h($email); ?>">
                    <div class="invalid-feedback"><?php echo h($emailErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_phone">Số điện thoại</label>
                    <input class="form-control<?php echo invalid($phoneErr); ?>" id="f_phone" name="phone" maxlength="20" autocomplete="off" placeholder="Ví dụ: 0912345678"
                           value="<?php echo h($phone); ?>">
                    <div class="invalid-feedback"><?php echo h($phoneErr); ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="f_address">Địa chỉ</label>
                    <input class="form-control<?php echo invalid($addressErr); ?>" id="f_address" name="address" maxlength="255" autocomplete="off"
                           value="<?php echo h($address); ?>">
                    <div class="invalid-feedback"><?php echo h($addressErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_credit_limit">Hạn mức tín dụng (₫)</label>
                    <input class="form-control<?php echo invalid($creditErr); ?>" type="number" id="f_credit_limit" name="credit_limit" min="0" step="0.01"
                           value="<?php echo h($credit); ?>">
                    <div class="invalid-feedback"><?php echo h($creditErr); ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="f_status">Trạng thái</label>
                    <select class="form-select<?php echo invalid($statusErr); ?>" id="f_status" name="status">
                        <?php foreach (STATUS_LABELS as $value => $label) { ?>
                            <option value="<?php echo $value; ?>"<?php echo selected($status, $value); ?>><?php echo h($label); ?></option>
                        <?php } ?>
                    </select>
                    <div class="invalid-feedback"><?php echo h($statusErr); ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="f_logo">Logo (JPG, PNG hoặc WEBP, tối đa 2 MB)</label>
                    <div class="d-flex align-items-start gap-3">
                        <?php if ($currentLogo) { ?>
                            <img class="logo-preview" src="<?php echo h(ROOT . $currentLogo); ?>" alt="Logo hiện tại">
                        <?php } ?>
                        <div class="flex-grow-1">
                            <input class="form-control<?php echo invalid($logoErr); ?>" type="file" id="f_logo" name="logo" accept="image/jpeg,image/png,image/webp">
                            <div class="invalid-feedback"><?php echo h($logoErr); ?></div>
                            <?php if ($currentLogo) { ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="f_remove_logo" name="remove_logo" value="1">
                                    <label class="form-check-label" for="f_remove_logo">Xóa logo hiện tại</label>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Lưu</button>
                <a class="btn btn-light" href="<?php echo ROOT; ?>suppliers/index.php">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
