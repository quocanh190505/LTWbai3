<?php
// layouts/footer.php – cuối trang của khu quản trị: đóng <main>, .app-content, .app-shell (mở trong layouts/header.php) và nạp JavaScript.
// Chỉ được nạp bằng include từ các trang; ai mở thẳng file này trên trình duyệt thì dừng ngay.
if (!defined("APP_START")) {
    exit("Truy cập không hợp lệ");
}
?>
</main>
<footer class="app-footer">Quản lý nhà cung cấp – Bài thực hành 3</footer>
    </div><!-- .app-content (mở trong layouts/header.php) -->
</div><!-- .app-shell (mở trong layouts/header.php) -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo h(asset("js/sidebar.js")); ?>"></script>
</body>
</html>
