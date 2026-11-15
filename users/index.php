<?php
// users/index.php – danh sách tài khoản quản trị (chỉ admin). Mã băm mật khẩu không bao giờ được đọc ra trang.
// Thêm/sửa: users/form.php.  Xóa: form POST gửi tới users/delete.php.
require_once __DIR__ . "/../includes/config.php";
$admin = requireLogin();

$users = getAll("SELECT user_id, username, full_name, role, is_active FROM users ORDER BY username");

$pageTitle = "Tài khoản";
include LAYOUT_DIR . "header.php";
pageHeader("Tài khoản", "Những người được phép đăng nhập quản trị.", "Thêm tài khoản", ROOT . "users/form.php");
?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Tên đăng nhập</th>
                    <th>Họ tên</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u) { ?>
                <?php $isSelf = (int) $u["user_id"] === (int) $admin["user_id"]; ?>
                <tr>
                    <td class="fw-medium"><?php echo h($u["username"]); ?><?php echo $isSelf ? ' <span class="badge badge-soft badge-soft-secondary">Bạn</span>' : ""; ?></td>
                    <td><?php echo h($u["full_name"]); ?></td>
                    <td>
                        <?php echo $u["role"] === "admin"
                            ? '<span class="badge badge-soft badge-soft-success">Quản trị</span>'
                            : '<span class="badge badge-soft badge-soft-secondary">Nhân viên</span>'; ?>
                    </td>
                    <td>
                        <?php echo (int) $u["is_active"] === 1
                            ? '<span class="badge badge-soft badge-soft-success">Hoạt động</span>'
                            : '<span class="badge badge-soft badge-soft-danger">Đã khóa</span>'; ?>
                    </td>
                    <td class="text-end text-nowrap actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo ROOT; ?>users/form.php?id=<?php echo (int) $u["user_id"]; ?>">Sửa</a>
                        <?php if (!$isSelf) { ?>
                            <form class="d-inline" method="post" action="<?php echo ROOT; ?>users/delete.php"
                                  onsubmit="return confirm('Xóa tài khoản «<?php echo h(addslashes($u["username"])); ?>»? Không thể hoàn tác.');">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u["user_id"]; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted mt-2 mb-0">Chỉ tài khoản vai trò Quản trị và đang hoạt động mới đăng nhập được. Tài khoản đã tạo giao dịch hoặc đánh giá không xóa được, hãy khóa thay vì xóa.</p>
<?php include LAYOUT_DIR . "footer.php"; ?>
