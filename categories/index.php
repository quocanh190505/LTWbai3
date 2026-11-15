<?php
// categories/index.php – danh sách danh mục nhà cung cấp (chỉ admin).
// Mỗi dòng có nút Sửa (categories/form.php?id=N) và Xóa (form POST gửi tới categories/delete.php).
require_once __DIR__ . "/../includes/config.php";
requireLogin();

// Số nhà cung cấp trong từng danh mục (LEFT JOIN để danh mục chưa có nhà cung cấp vẫn hiện, với số 0)
$categories = getAll(
    "SELECT c.category_id, c.category_name, c.description, COUNT(s.supplier_id) AS supplier_count
     FROM supplier_categories c LEFT JOIN suppliers s ON s.category_id = c.category_id
     GROUP BY c.category_id, c.category_name, c.description ORDER BY c.category_name"
);

$pageTitle = "Danh mục";
include LAYOUT_DIR . "header.php";
pageHeader("Danh mục nhà cung cấp", "Nhóm nhà cung cấp theo ngành hàng.", "Thêm danh mục", ROOT . "categories/form.php");
?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Tên danh mục</th>
                    <th>Mô tả</th>
                    <th class="text-end">Số nhà cung cấp</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$categories) { ?>
                <tr><td colspan="4" class="text-center text-muted py-4">Chưa có danh mục nào</td></tr>
            <?php } ?>
            <?php foreach ($categories as $c) { ?>
                <tr>
                    <td class="fw-medium"><?php echo h($c["category_name"]); ?></td>
                    <td><?php echo textOrDash($c["description"]); ?></td>
                    <td class="text-end"><?php echo (int) $c["supplier_count"]; ?></td>
                    <td class="text-end text-nowrap actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>categories/form.php?id=<?php echo (int) $c["category_id"]; ?>">Sửa</a>
                        <form class="d-inline" method="post" action="<?php echo ROOT; ?>categories/delete.php"
                              onsubmit="return confirm('Xóa danh mục «<?php echo h(addslashes($c["category_name"])); ?>»?');">
                            <input type="hidden" name="category_id" value="<?php echo (int) $c["category_id"]; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include LAYOUT_DIR . "footer.php"; ?>
