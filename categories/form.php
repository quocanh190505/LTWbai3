<?php
// categories/form.php – form THÊM và SỬA danh mục; một file cho cả hai việc.
//   categories/form.php       -> form trống, thêm mới
//   categories/form.php?id=N  -> form điền sẵn, sửa danh mục N
//
// Luồng (giống ví dụ "PHP Form Validation" của W3Schools):
//   form HTML -> bấm Lưu: POST về chính file này -> PHP kiểm tra từng ô
//   -> lỗi: hiện lại form, giữ giá trị đã gõ, báo lỗi cạnh ô  |  không lỗi: ghi database rồi chuyển về categories/index.php.
require_once __DIR__ . "/../includes/config.php";
requireLogin();

// ----- 1. Khai báo biến -----
$id = toInt($_POST["id"] ?? $_GET["id"] ?? "");   // có id = đang sửa
$name = $description = "";
$nameErr = $descriptionErr = "";

if ($id > 0) {
    $existing = getOne("SELECT category_id, category_name, description FROM supplier_categories WHERE category_id = :id", ["id" => $id]);
    if ($existing === null) {
        header("Location: " . ROOT . "categories/index.php?msg=not_found");
        exit;
    }
}

// ----- 2. Khi form được gửi (POST): lấy dữ liệu và kiểm tra -----
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = test_input($_POST["category_name"] ?? "");
    $description = test_input($_POST["description"] ?? "");

    if ($name == "") {
        $nameErr = "Vui lòng nhập tên danh mục";
    } elseif (mb_strlen($name) > 100) {
        $nameErr = "Tên danh mục tối đa 100 ký tự";
    } elseif (getValue("SELECT category_id FROM supplier_categories WHERE LOWER(category_name) = LOWER(:name) AND category_id <> :id", ["name" => $name, "id" => $id]) !== null) {
        $nameErr = "Tên danh mục đã tồn tại";   // không tính chính dòng đang sửa
    }

    if (mb_strlen($description) > 255) {
        $descriptionErr = "Mô tả tối đa 255 ký tự";
    }

    // ----- 3. Không có lỗi: ghi database -----
    if ($nameErr == "" && $descriptionErr == "") {
        try {
            if ($id > 0) {
                execute(
                    "UPDATE supplier_categories SET category_name = :name, description = :description WHERE category_id = :id",
                    ["name" => $name, "description" => nullIfEmpty($description), "id" => $id]
                );
                header("Location: " . ROOT . "categories/index.php?msg=category_updated");
            } else {
                execute(
                    "INSERT INTO supplier_categories (category_name, description) VALUES (:name, :description)",
                    ["name" => $name, "description" => nullIfEmpty($description)]
                );
                header("Location: " . ROOT . "categories/index.php?msg=category_added");
            }
            exit;
        } catch (PDOException $e) {
            if (!isDuplicateError($e)) {
                throw $e;
            }
            // MySQL coi "Nguyên vật liệu" và "nguyen vat lieu" là trùng (không phân biệt hoa thường, dấu); phép kiểm tra phía trên có thể chưa bắt được
            $nameErr = "Tên danh mục đã tồn tại";
        }
    }
} elseif ($id > 0) {
    // Mở form sửa lần đầu (GET): điền sẵn dữ liệu đang có
    $name = $existing["category_name"];
    $description = (string) $existing["description"];
}

// ----- 4. Hiện form -----
$pageTitle = $id > 0 ? "Sửa danh mục" : "Thêm danh mục";
include LAYOUT_DIR . "header.php";
pageHeader($pageTitle, $id > 0 ? $existing["category_name"] : "Nhóm nhà cung cấp theo ngành hàng.");
?>
<div class="card" style="max-width: 640px">
    <div class="card-body p-4">
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <div class="mb-3">
                <label class="form-label" for="f_category_name">Tên danh mục <span class="text-danger">*</span></label>
                <input class="form-control<?php echo invalid($nameErr); ?>" id="f_category_name" name="category_name" maxlength="100" autocomplete="off"
                       value="<?php echo h($name); ?>">
                <div class="invalid-feedback"><?php echo h($nameErr); ?></div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="f_description">Mô tả</label>
                <input class="form-control<?php echo invalid($descriptionErr); ?>" id="f_description" name="description" maxlength="255" autocomplete="off"
                       value="<?php echo h($description); ?>">
                <div class="invalid-feedback"><?php echo h($descriptionErr); ?></div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Lưu</button>
                <a class="btn btn-light" href="<?php echo ROOT; ?>categories/index.php">Hủy</a>
            </div>
        </form>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
